<?php

namespace Courier\Internal;

function check_resolution() {
	$manager = Manager::get_instance();
	$response = $manager->resolve();

	if ( is_wp_error( $response ) ) {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$first = null;
			foreach ( $response->get_error_messages() as $message ) {
				if ( $first === null ) {
					$first = $message;
					continue;
				}
				\WP_CLI::line( $message );
			}
			\WP_CLI::error( $first );
		}
		wp_die( $response, 'Courier Resolution Error' );
	}
}