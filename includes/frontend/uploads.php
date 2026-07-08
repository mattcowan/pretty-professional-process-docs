<?php
/**
 * Per-section attachment uploads from the front end.
 *
 * @package PPPD
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the upload handler.
 *
 * No nopriv variant on purpose: only logged-in users may upload.
 *
 * @return void
 */
function pppd_uploads_init() {
	add_action( 'admin_post_pppd_upload_attachment', 'pppd_handle_upload' );
}

/**
 * Allowed upload extensions.
 *
 * @return string[]
 */
function pppd_allowed_upload_extensions() {
	return array( 'pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'csv', 'txt', 'md', 'docx', 'xlsx' );
}

/**
 * Redirect back to the report with an upload notice.
 *
 * @param WP_Post|null $section Section post (for the anchor), or null.
 * @param string       $status  Notice status: success|error.
 * @return void
 */
function pppd_upload_redirect( $section, $status ) {
	$url = home_url( '/' );

	if ( $section instanceof WP_Post ) {
		$report_id = absint( get_post_meta( $section->ID, '_pppd_report_id', true ) );
		$permalink = ( $report_id > 0 ) ? get_permalink( $report_id ) : false;

		if ( false !== $permalink ) {
			$url = add_query_arg(
				array(
					'pppd_upload'  => rawurlencode( $status ),
					'pppd_section' => (int) $section->ID,
				),
				$permalink
			) . '#' . pppd_section_anchor( $section );
		}
	}

	wp_safe_redirect( $url );
	exit;
}

/**
 * admin-post handler for section attachment uploads.
 *
 * @return void
 */
function pppd_handle_upload() {
	if ( ! is_user_logged_in() || ! current_user_can( 'upload_files' ) ) {
		wp_die(
			esc_html__( 'You are not allowed to upload files.', 'pretty-professional-process-docs' ),
			esc_html__( 'Upload blocked', 'pretty-professional-process-docs' ),
			array( 'response' => 403 )
		);
	}

	$section_id = isset( $_POST['pppd_section_id'] ) ? absint( wp_unslash( $_POST['pppd_section_id'] ) ) : 0;

	check_admin_referer( 'pppd_upload_' . $section_id, '_pppd_upload_nonce' );

	$section = ( $section_id > 0 ) ? get_post( $section_id ) : null;

	if ( ! $section instanceof WP_Post || 'pppd_section' !== $section->post_type ) {
		wp_die(
			esc_html__( 'Invalid section.', 'pretty-professional-process-docs' ),
			esc_html__( 'Upload blocked', 'pretty-professional-process-docs' ),
			array( 'response' => 404 )
		);
	}

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- $_FILES is consumed by media_handle_upload(); we only inspect name/size here.
	$file = isset( $_FILES['pppd_file'] ) ? $_FILES['pppd_file'] : null;

	if ( null === $file || ! isset( $file['name'], $file['size'] ) || '' === $file['name'] ) {
		pppd_upload_redirect( $section, 'error' );
	}

	// Size sanity: respect the WordPress upload ceiling.
	if ( (int) $file['size'] <= 0 || (int) $file['size'] > wp_max_upload_size() ) {
		pppd_upload_redirect( $section, 'error' );
	}

	// Extension/type whitelist.
	$filetype = wp_check_filetype( sanitize_file_name( (string) $file['name'] ) );

	if ( empty( $filetype['ext'] ) || empty( $filetype['type'] ) || ! in_array( strtolower( $filetype['ext'] ), pppd_allowed_upload_extensions(), true ) ) {
		pppd_upload_redirect( $section, 'error' );
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$attachment_id = media_handle_upload( 'pppd_file', $section->ID );

	if ( is_wp_error( $attachment_id ) ) {
		pppd_upload_redirect( $section, 'error' );
	}

	pppd_upload_redirect( $section, 'success' );
}
