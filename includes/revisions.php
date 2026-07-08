<?php
/**
 * Revision policy: unlimited revisions for reports and sections.
 *
 * @package PPPD
 */

defined( 'ABSPATH' ) || exit;

/**
 * Keep unlimited revisions for pppd_report and pppd_section.
 *
 * @param int     $num  Number of revisions to keep.
 * @param WP_Post $post Post being saved.
 * @return int
 */
function pppd_unlimited_revisions( $num, $post ) {
	if ( $post instanceof WP_Post && in_array( $post->post_type, array( 'pppd_report', 'pppd_section' ), true ) ) {
		return -1;
	}

	return $num;
}
