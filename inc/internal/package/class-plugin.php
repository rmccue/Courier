<?php

namespace Courier\Internal\Package;

use Composer\Package\Link;
use Composer\Package\LinkConstraint\VersionConstraint;
use Composer\Package\RootPackage;
use Composer\Package\RootPackageInterface;
use Composer\Package\Version\VersionParser;

class Plugin extends RootPackage implements RootPackageInterface {
	protected $plugin_name;
	protected $plugin_header = array();

	public function __construct( $name, $header = null ) {
		$this->plugin_name = $name;

		if ( $header !== null ) {
			$this->plugin_header = $header;
		}
	}

	public function parse_basic_syntax( $args ) {
		$version_parser = new VersionParser();

		$requires = array();

		foreach ( $args as $requirement ) {
			$name_length = strcspn( $requirement, '>=|~' );
			if ( strlen( $requirement ) === $name_length ) {
				// No version constraint specified, use wildcard
				$dependency_name = $requirement;
				$constraint_text = '*';
			}
			else {
				$dependency_name = substr( $requirement, 0, $name_length );
				$constraint_text = substr( $requirement, $name_length );

				// If the constraint is exactly '=', trim it for
				// Composer syntax compatibility
				if ( strlen( $constraint_text ) >= 2 && $constraint_text[0] === '=' && is_numeric( $constraint_text[1] ) ) {
					$constraint_text = substr( $constraint_text, 1 );
				}
			}

			if ( strpos( $dependency_name, '/' ) === false ) {
				$dependency_name = 'wpackagist-plugin/' . $dependency_name;
			}

			$constraint = $version_parser->parseConstraints( $constraint_text );
			$requires[] = new Link(
				$this->plugin_name,
				$dependency_name,
				$constraint,
				'requires',
				$constraint_text
			);
		}

		$this->setRequires( $requires );
	}

	/**
	 * Returns the package's name without version info, thus not a unique identifier
	 *
	 * @return string package name
	 */
	public function getName() {
		return 'wpackagist-plugin/' . $this->plugin_name;
	}

	/**
	 * Returns the package's pretty (i.e. with proper case) name
	 *
	 * @return string package name
	 */
	public function getPrettyName() {
		return $this->plugin_header['Name'];
	}

	/**
	 * Returns whether the package is a development virtual package or a concrete one
	 *
	 * @return bool
	 */
	public function isDev() {
		return false;
	}

	/**
	 * Returns the package type, e.g. library
	 *
	 * @return string The package type
	 */
	public function getType() {
		return 'wordpress-plugin';
	}

	/**
	 * Returns the version of this package
	 *
	 * @return string version
	 */
	public function getVersion() {
		$parser = new VersionParser();
		return $parser->normalize( $this->getPrettyVersion() );
	}

	/**
	 * Returns the pretty (i.e. non-normalized) version string of this package
	 *
	 * @return string version
	 */
	public function getPrettyVersion() {
		if ( empty( $this->plugin_header['Version'] ) ) {
			return '0.0-dev';
		}

		return $this->plugin_header['Version'];
	}

	/**
	 * Returns the stability of this package: one of (dev, alpha, beta, RC, stable)
	 *
	 * @return string
	 */
	public function getStability() {
		return 'stable';
	}

	/**
	 * Returns package unique name, constructed from name and version.
	 *
	 * @return string
	 */
	public function getUniqueName() {
		return 'uniq-name';
	}

	/**
	 * Converts the package into a pretty readable string
	 *
	 * @return string
	 */
	public function getPrettyString() {
		return 'pretty-string';
	}

	/**
	 * {@inheritDoc}
	 */
	public function getMinimumStability() {
		return 'stable';
	}

	/**
	 * Returns the package license, e.g. MIT, BSD, GPL
	 *
	 * @return array The package licenses
	 */
	public function getLicense() {
		return 'GPL';
	}

	/**
	 * Returns an array of keywords relating to the package
	 *
	 * @return array
	 */
	public function getKeywords() {
		return array();
	}

	/**
	 * Returns the package description
	 *
	 * @return string
	 */
	public function getDescription() {
		return $this->plugin_header['Description'];
	}
}
