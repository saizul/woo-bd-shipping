<?php
/**
 * PHPUnit bootstrap. The pure classes need no WordPress.
 */

define( 'WOO_BD_SHIPPING_TESTS', true );

$autoload = dirname( __DIR__ ) . '/vendor/autoload.php';
if ( is_readable( $autoload ) ) {
	require $autoload;
	return;
}

spl_autoload_register(
	static function ( string $class ): void {
		$prefix = 'WooBDShipping\\';
		if ( 0 === strncmp( $class, $prefix, strlen( $prefix ) ) ) {
			$file = dirname( __DIR__ ) . '/src/' . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
			if ( is_readable( $file ) ) {
				require $file;
			}
		}
	}
);
