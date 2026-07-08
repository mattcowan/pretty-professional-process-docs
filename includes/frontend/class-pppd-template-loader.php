<?php
/**
 * Front-end template loader + asset enqueueing for single reports.
 *
 * @package PPPD
 */

defined( 'ABSPATH' ) || exit;

/**
 * Serves templates/single-pppd_report.php for single report views and
 * enqueues the report assets on that view only.
 */
class PPPD_Template_Loader {

	/**
	 * Hook everything up.
	 */
	public function __construct() {
		add_filter( 'template_include', array( $this, 'maybe_use_report_template' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_assets' ) );
	}

	/**
	 * Swap in the plugin's single report template.
	 *
	 * A theme can override it by shipping its own single-pppd_report.php.
	 *
	 * @param string $template Resolved template path.
	 * @return string
	 */
	public function maybe_use_report_template( $template ) {
		if ( ! is_singular( 'pppd_report' ) ) {
			return $template;
		}

		$theme_template = locate_template( array( 'single-pppd_report.php' ) );

		if ( '' !== $theme_template ) {
			return $theme_template;
		}

		return PPPD_PLUGIN_DIR . 'templates/single-pppd_report.php';
	}

	/**
	 * Enqueue report CSS/JS on single report views only.
	 *
	 * @return void
	 */
	public function maybe_enqueue_assets() {
		if ( ! is_singular( 'pppd_report' ) ) {
			return;
		}

		wp_enqueue_style(
			'pppd-report',
			PPPD_PLUGIN_URL . 'assets/css/report.css',
			array(),
			PPPD_VERSION,
			'all'
		);

		wp_enqueue_style(
			'pppd-print',
			PPPD_PLUGIN_URL . 'assets/css/print.css',
			array( 'pppd-report' ),
			PPPD_VERSION,
			'print'
		);

		wp_enqueue_script(
			'pppd-report',
			PPPD_PLUGIN_URL . 'assets/js/report.js',
			array(),
			PPPD_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}
}
