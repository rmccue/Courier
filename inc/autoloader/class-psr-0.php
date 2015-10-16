<?php

namespace Courier\Autoloader;

class PSR_0 implements Loader {
	const NS_SEPARATOR = '\\';

	protected $prefix;
	protected $prefix_length;
	protected $path;

	public function __construct( $prefix, $path ) {
		$this->prefix        = $prefix;
		$this->prefix_length = strlen( $prefix );
		$this->path          = trailingslashit( $path );
	}

	public function load( $class ) {
		if ( strpos( $class, $this->prefix . self::NS_SEPARATOR ) !== 0 ) {
			return;
		}

		$file = '';

		if ( false !== ( $last_ns_pos = strripos( $class, self::NS_SEPARATOR ) ) ) {
			$namespace = substr( $class, 0, $last_ns_pos );
			$class     = substr( $class, $last_ns_pos + 1 );
			$file      = str_replace( self::NS_SEPARATOR, DIRECTORY_SEPARATOR, $namespace ) . DIRECTORY_SEPARATOR;
		}
		$file .= $class . '.php';

		$path = $this->path . $file;

		if ( file_exists( $path ) ) {
			require_once $path;
		}
	}
}
