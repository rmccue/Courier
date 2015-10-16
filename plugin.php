<?php
/**
 * Plugin Name: Courier
 * Description: Plugin/library package management and delivery for WordPress.
 *
 * Note: this file must be parsable by PHP 5.2.
 */

// Sanity check
if ( version_compare( PHP_VERSION, '5.3.2', '<' ) ) {
	status_header( 500 );
	wp_die( 'Courier requires PHP 5.3.2 or newer.', 'Courier Load Error' );
}

if ( did_action( 'muplugins_loaded' ) ) {
	add_action( 'all_admin_notices', function () {
		$message = 'Courier must be loaded as an mu-plugin';
		echo '<div class="error"><p>' . $message . '</p></div>';
	} );
	return;
}

define( 'COURIER_BASE', __DIR__ );
define( 'COURIER_RESOLUTION_PRIORITY', -1000 );

// Bootstrap
require __DIR__ . '/inc/autoloader/class-loader.php';
require __DIR__ . '/inc/autoloader/class-wordpress.php';
require __DIR__ . '/api.php';
require __DIR__ . '/internal.php';

// Register autoloader
$loader_class = 'Courier\\Autoloader\\WordPress';
$loader = new $loader_class( 'Courier', __DIR__ . '/inc' );
spl_autoload_register( array( $loader, 'load' ) );

$loader_class = 'Courier\\Autoloader\\PSR_0';
$loader = new $loader_class( 'Composer', __DIR__ . '/composer/src' );
spl_autoload_register( array( $loader, 'load' ) );

// Clean up global variable space
unset( $loader_class, $loader );

// Register default hooks
add_action( 'plugins_loaded', 'Courier\\Internal\\check_resolution', COURIER_RESOLUTION_PRIORITY );
