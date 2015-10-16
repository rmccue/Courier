<?php
/**
 * Public API for Courier
 *
 * This file contains all the functions available to other plugins and libraries
 * from Courier.
 */

namespace Courier;

function plugin_requires() {
	$manager = Internal\Manager::get_instance();

	$args = func_get_args();
	return call_user_func_array( array( $manager, 'add' ), $args );
}
