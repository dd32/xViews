<?php
namespace dd32\xViews;
/**
 * Plugin Name: xViews
 * Author: Dion Hulse
 */

xViews::instance();
class xViews {
	protected $routes = [];
	public function register( $route, $args = [] ) {
		$defaults = [];
		$args = wp_parse_args( $args, $defaults );

		$route = new Custom_Route( $route, $args );
		$this->routes[] = $route;

		// Sort, Longer to shorter.
		usort( $this->routes, function( $a, $b ) {
			return strcmp( $b->get_regex(), $a->get_regex() );
		} );

		return $this;
	}

	protected $settings = [
		'passthrough' => true, // Whether it should hit WordPress Query parsing if we fail to match.
		'wp-json'     => true, // Allow wp-json.. or not?
	];
	public function setting( $name, $value = null ) {
		if ( is_null( $value ) ) {
			return $this->settings[ $name ] ?? false;
		}

		$this->settings[ $name ] = $value;
		return $this;
	}

	static $instance = null;

	public static function instance() {
		$class = __CLASS__;
		return self::$instance ?? ( self::$instance = new $class );
	}

	protected function __construct() {
		add_filter( 'do_parse_request', [ $this, 'parse_request' ], 1, 3 );
	}

	public function parse_request( $handle, $wp, $extra_query_vars ) {
		$requested_url = $this->determine_url();

		// If we're not allowing passthrough, but wp-json, allow it to be requested.
		if ( ! $this->setting('passthrough') && $this->setting('wp-json') ) {
			$this->routes[] = new Custom_Route( '/wp-json(?P<rest_route>.+)/' );
			$this->routes[] = new Custom_Route( '/wp-json/', [ 'rest_route' => '/' ] );
		}

		foreach ( $this->routes as $route ) {
			$args = $route->match( $requested_url );

			if ( false !== $args ) {
				$wp->query_vars    = $args;
				$wp->matched_query = http_build_query( $args );
				$wp->request       = $requested_url;
				$wp->did_permalink = true;
				$wp->matched_rule  = 'xViews::' . $route->get_regex();

				do_action_ref_array( 'parse_request', array( &$wp ) );
				return false;
			}
		}

		// If we're passing through to WordPress, allow it.
		if ( $this->setting('passthrough') ) {
			return $handle;
		}

		// Otherwise, 404 the request.
		$wp->query_vars = [ 'error' => 404 ];
		return false;
	}

	protected function determine_url() {
			$pathinfo         = isset( $_SERVER['PATH_INFO'] ) ? $_SERVER['PATH_INFO'] : '';
			list( $pathinfo ) = explode( '?', $pathinfo );
			$pathinfo         = str_replace( '%', '%25', $pathinfo );

			list( $req_uri ) = explode( '?', $_SERVER['REQUEST_URI'] );
			$self            = $_SERVER['PHP_SELF'];
			$home_path       = trim( parse_url( home_url(), PHP_URL_PATH ), '/' );
			$home_path_regex = sprintf( '|^%s|i', preg_quote( $home_path, '|' ) );

			/*
				* Trim path info from the end and the leading home path from the front.
				* For path info requests, this leaves us with the requesting filename, if any.
				* For 404 requests, this leaves us with the requested permalink.
				*/
			$req_uri  = str_replace( $pathinfo, '', $req_uri );
			$req_uri  = trim( $req_uri, '/' );
			$req_uri  = preg_replace( $home_path_regex, '', $req_uri );
			$req_uri  = trim( $req_uri, '/' );
			$pathinfo = trim( $pathinfo, '/' );
			$pathinfo = preg_replace( $home_path_regex, '', $pathinfo );
			$pathinfo = trim( $pathinfo, '/' );
			$self     = trim( $self, '/' );
			$self     = preg_replace( $home_path_regex, '', $self );
			$self     = trim( $self, '/' );

			// The requested permalink is in $pathinfo for path info requests and
			// $req_uri for other requests.
			if ( ! empty( $pathinfo ) && ! preg_match( '|^.*' . $wp_rewrite->index . '$|', $pathinfo ) ) {
				$requested_path = $pathinfo;
			} else {
				$requested_path = $req_uri;
			}
			$requested_file = $req_uri;

			return $requested_path;
	}
}

class Custom_Route {
	protected $route = null;
	protected $args  = [];
	protected $regex = null;

	public function __construct( $route, $args = [] ) {
		$this->route = $route;
		$this->args  = $args;
	}

	public function match( $url ) {
		if ( preg_match( $this->get_regex(), $url, $matches ) ) {
			foreach ( $matches as $i => $k ) {
				if ( is_int( $i ) ) {
					unset( $matches[ $i ] );
				}
			}
			return array_merge( $matches, $this->args );
		}

		return false;
	}

	public function get_regex() {
		if ( $this->regex ) {
			return $this->regex;
		}

		$regex = preg_replace( '!^/!', '^', $this->route );
		$regex = preg_replace( '!/$!', '/?', $regex );

		return $this->regex = "#{$regex}\$#i";
	}

	public function get_query_vars() {
		return $this->args;
	}
}

include __DIR__ . '/test.php';