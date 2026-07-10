<?php
/**
 * Uninstall routine for Pretty Professional Process Docs.
 *
 * Removes roles, capabilities, and plugin options.
 *
 * DELIBERATE CHOICE: content (reports, sections, proposed changes, drift
 * runs, their revisions, comments, and attachments) is NOT deleted. These
 * documents are the durable record of a project's requirements and review
 * history; destroying them on uninstall would be far more damaging than
 * leaving orphaned rows behind. Remove the posts manually (or via WP-CLI)
 * if you truly want them gone.
 *
 * @package PPPD
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;
defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/capabilities.php';

pppd_remove_capabilities();

delete_option( 'pppd_version' );
delete_option( 'pppd_registered_type_hash' );
