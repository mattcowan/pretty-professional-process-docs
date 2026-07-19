<?php
/**
 * Plugin Name:       Pretty Professional Process Docs
 * Description:       Living, agent-accessible functional requirements documents — wiki-style reports with revision history, review queues, drift tracking, and accessible exports.
 * Version:           0.4.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            Pretty Professional Process Docs Contributors
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       pretty-professional-process-docs
 *
 * @package PPPD
 */

defined( 'ABSPATH' ) || exit;

define( 'PPPD_VERSION', '0.4.0' );

// REST contract version (docs/rest-contract.md). Bumped only on breaking
// changes to the surface the frd skill consumes; additions never bump it.
define( 'PPPD_CONTRACT_VERSION', '1' );

define( 'PPPD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PPPD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once PPPD_PLUGIN_DIR . 'includes/class-pppd-plugin.php';

/**
 * Activation callback.
 *
 * Registers post types and taxonomies so their rewrite rules exist, inserts
 * the default taxonomy terms, grants capabilities/roles, then flushes
 * rewrite rules.
 *
 * @return void
 */
function pppd_activate() {
	pppd_register_post_types();
	pppd_register_post_statuses();
	pppd_register_taxonomies();
	pppd_insert_default_terms();
	pppd_grant_capabilities();

	update_option( 'pppd_version', PPPD_VERSION );

	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'pppd_activate' );

/**
 * Deactivation callback.
 *
 * Only flushes rewrite rules. Roles, capabilities, and content are all kept
 * on deactivation; removal happens in uninstall.php.
 *
 * @return void
 */
function pppd_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'pppd_deactivate' );

PPPD_Plugin::instance();
