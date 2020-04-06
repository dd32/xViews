<?php
namespace dd32\xViews;

add_action( 'init', function() {
xViews::instance()
	// The root level is just a regular archive.
	->register( '/' )
	// These pages load specific posts.
	->register( '/testing', [ 'p' => 21 ] )
	->register( '/test', [ 'p' => 20 ] )
	// Lets allow access to the Sample Page page, using a regex match.
	->register( '/(?P<pagename>[^/]+-page)/' )

	// We don't want WordPress query parsing to kick in for this site at all.
	->setting( 'passthrough', false )
	// But wp-json is enabled (by default)
	->setting( 'wp-json', true )
	;
});