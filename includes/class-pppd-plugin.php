<?php
/**
 * Central plugin wiring.
 *
 * @package PPPD
 */

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin class (singleton). Requires every include and hooks it up.
 */
final class PPPD_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var PPPD_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get (and lazily create) the singleton instance.
	 *
	 * @return PPPD_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Wire everything up.
	 */
	private function __construct() {
		$this->includes();
		$this->hooks();
	}

	/**
	 * Require every include file.
	 *
	 * @return void
	 */
	private function includes() {
		require_once PPPD_PLUGIN_DIR . 'includes/post-types.php';
		require_once PPPD_PLUGIN_DIR . 'includes/capabilities.php';
		require_once PPPD_PLUGIN_DIR . 'includes/helpers.php';
		require_once PPPD_PLUGIN_DIR . 'includes/requirement-ids.php';
		require_once PPPD_PLUGIN_DIR . 'includes/revisions.php';
		require_once PPPD_PLUGIN_DIR . 'includes/change-actions.php';
		require_once PPPD_PLUGIN_DIR . 'includes/rest/class-pppd-outline-controller.php';
		require_once PPPD_PLUGIN_DIR . 'includes/rest/class-pppd-changes-controller.php';
		require_once PPPD_PLUGIN_DIR . 'includes/rest/class-pppd-drift-controller.php';
		require_once PPPD_PLUGIN_DIR . 'includes/rest/class-pppd-traceability-controller.php';
		require_once PPPD_PLUGIN_DIR . 'includes/admin/class-pppd-review-queue.php';
		require_once PPPD_PLUGIN_DIR . 'includes/admin/class-pppd-meta-boxes.php';
		require_once PPPD_PLUGIN_DIR . 'includes/admin/class-pppd-list-columns.php';
		require_once PPPD_PLUGIN_DIR . 'includes/frontend/class-pppd-template-loader.php';
		require_once PPPD_PLUGIN_DIR . 'includes/frontend/comments.php';
		require_once PPPD_PLUGIN_DIR . 'includes/frontend/uploads.php';
	}

	/**
	 * Register every hook.
	 *
	 * @return void
	 */
	private function hooks() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'register_objects' ) );

		add_action( 'save_post_pppd_section', 'pppd_maybe_assign_requirement_id', 20, 3 );
		add_action( 'rest_after_insert_pppd_section', 'pppd_maybe_assign_requirement_id_rest', 20 );
		add_filter( 'rest_pre_insert_pppd_section', 'pppd_guard_published_section_updates', 10, 2 );
		add_filter( 'wp_revisions_to_keep', 'pppd_unlimited_revisions', 10, 2 );

		add_action( 'rest_api_init', array( $this, 'register_rest_controllers' ) );

		// Admin UI + admin-post handlers (admin-post.php runs with is_admin() true).
		if ( is_admin() ) {
			new PPPD_Review_Queue();
			new PPPD_Meta_Boxes();
			new PPPD_List_Columns();
		}

		// Front end.
		new PPPD_Template_Loader();
		pppd_comments_init();
		pppd_uploads_init();
	}

	/**
	 * Load the plugin text domain.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'pretty-professional-process-docs',
			false,
			dirname( plugin_basename( PPPD_PLUGIN_DIR . 'pretty-professional-process-docs.php' ) ) . '/languages'
		);
	}

	/**
	 * Register post types, statuses, taxonomies, and meta (in that order).
	 *
	 * @return void
	 */
	public function register_objects() {
		pppd_register_post_types();
		pppd_register_post_statuses();
		pppd_register_taxonomies();
		pppd_register_meta();
	}

	/**
	 * Register the REST controllers.
	 *
	 * @return void
	 */
	public function register_rest_controllers() {
		( new PPPD_Outline_Controller() )->register_routes();
		( new PPPD_Changes_Controller() )->register_routes();
		( new PPPD_Drift_Controller() )->register_routes();
		( new PPPD_Traceability_Controller() )->register_routes();
	}
}
