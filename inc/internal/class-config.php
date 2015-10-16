<?php

namespace Courier\Internal;

use Composer\Config as BaseConfig;

class Config extends BaseConfig {
	/**
	 * @return array
	 */
	public function getRepositories() {
		// Because the Composer devs don't appear to understand what subclassing
		// is, we need to override this here rather than using
		// $this->repositories directly.
		$repos = parent::getRepositories();

		// Add wpackagist by default
		$wp_repos = array(
			'wpackagist' => array(
				'type' => 'composer',
				'url' => 'http://wpackagist.org',
			),
		);

		return array_merge( $wp_repos, $repos );
	}
}
