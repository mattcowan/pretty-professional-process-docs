<?php
/**
 * PHPUnit bootstrap: loads the wp-phpunit test suite and this plugin.
 *
 * Requires a wp-tests-config.php next to this file (see
 * wp-tests-config-sample.php) pointing at a DISPOSABLE test database — the
 * suite drops its tables on every run.
 *
 * @package PPPD
 */

$pppd_plugin_root = dirname( __DIR__, 2 );

require_once $pppd_plugin_root . '/vendor/autoload.php';

$pppd_tests_dir = getenv( 'WP_PHPUNIT__DIR' );

if ( false === $pppd_tests_dir || '' === $pppd_tests_dir ) {
	$pppd_tests_dir = $pppd_plugin_root . '/vendor/wp-phpunit/wp-phpunit';
}

$pppd_tests_config = getenv( 'WP_PHPUNIT__TESTS_CONFIG' );

if ( false === $pppd_tests_config || '' === $pppd_tests_config ) {
	putenv( 'WP_PHPUNIT__TESTS_CONFIG=' . __DIR__ . '/wp-tests-config.php' );
}

require_once $pppd_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () use ( $pppd_plugin_root ) {
		require $pppd_plugin_root . '/pretty-professional-process-docs.php';
	}
);

require $pppd_tests_dir . '/includes/bootstrap.php';
