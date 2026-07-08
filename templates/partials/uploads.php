<?php
/**
 * Partial: per-section upload slot + attachment list.
 *
 * Expects: $pppd_section (WP_Post), $pppd_sub_level (int).
 *
 * @package PPPD
 */

defined( 'ABSPATH' ) || exit;

$pppd_upload_heading = 'h' . min( max( (int) $pppd_sub_level, 3 ), 6 );

$pppd_attachments = get_children(
	array(
		'post_parent' => $pppd_section->ID,
		'post_type'   => 'attachment',
		'orderby'     => 'date',
		'order'       => 'DESC',
	)
);

// Read-only display of a redirect flag; no state change, so no nonce.
// phpcs:disable WordPress.Security.NonceVerification.Recommended
$pppd_upload_notice  = isset( $_GET['pppd_upload'] ) ? sanitize_key( wp_unslash( $_GET['pppd_upload'] ) ) : '';
$pppd_notice_section = isset( $_GET['pppd_section'] ) ? absint( wp_unslash( $_GET['pppd_section'] ) ) : 0;
// phpcs:enable WordPress.Security.NonceVerification.Recommended

$pppd_show_notice = ( $pppd_notice_section === (int) $pppd_section->ID && in_array( $pppd_upload_notice, array( 'success', 'error' ), true ) );

$pppd_accept = '.' . implode( ',.', pppd_allowed_upload_extensions() );
?>
<div class="pppd-uploads no-print">
	<<?php echo esc_html( $pppd_upload_heading ); ?>><?php esc_html_e( 'Attachments', 'pretty-professional-process-docs' ); ?></<?php echo esc_html( $pppd_upload_heading ); ?>>

	<?php if ( $pppd_show_notice ) : ?>
		<?php if ( 'success' === $pppd_upload_notice ) : ?>
			<p class="pppd-notice pppd-notice--success" role="status"><?php esc_html_e( 'File uploaded.', 'pretty-professional-process-docs' ); ?></p>
		<?php else : ?>
			<p class="pppd-notice pppd-notice--error" role="status"><?php esc_html_e( 'The file could not be uploaded. Check the type and size, then try again.', 'pretty-professional-process-docs' ); ?></p>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ( ! empty( $pppd_attachments ) ) : ?>
		<ul class="pppd-attachment-list">
			<?php foreach ( $pppd_attachments as $pppd_attachment ) : ?>
				<?php
				$pppd_file_url  = wp_get_attachment_url( $pppd_attachment->ID );
				$pppd_file_path = get_attached_file( $pppd_attachment->ID );
				$pppd_file_size = ( is_string( $pppd_file_path ) && file_exists( $pppd_file_path ) ) ? size_format( (int) filesize( $pppd_file_path ) ) : '';
				?>
				<?php if ( is_string( $pppd_file_url ) && '' !== $pppd_file_url ) : ?>
					<li>
						<a href="<?php echo esc_url( $pppd_file_url ); ?>"><?php echo esc_html( wp_basename( $pppd_file_url ) ); ?></a>
						<?php if ( '' !== (string) $pppd_file_size ) : ?>
							<span class="report-meta">(<?php echo esc_html( (string) $pppd_file_size ); ?>)</span>
						<?php endif; ?>
					</li>
				<?php endif; ?>
			<?php endforeach; ?>
		</ul>
	<?php else : ?>
		<p class="pppd-no-attachments"><?php esc_html_e( 'No attachments yet.', 'pretty-professional-process-docs' ); ?></p>
	<?php endif; ?>

	<?php if ( is_user_logged_in() && current_user_can( 'upload_files' ) ) : ?>
		<form class="pppd-upload-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" enctype="multipart/form-data">
			<input type="hidden" name="action" value="pppd_upload_attachment" />
			<input type="hidden" name="pppd_section_id" value="<?php echo esc_attr( (string) $pppd_section->ID ); ?>" />
			<?php wp_nonce_field( 'pppd_upload_' . $pppd_section->ID, '_pppd_upload_nonce' ); ?>
			<p>
				<label for="pppd-file-<?php echo esc_attr( (string) $pppd_section->ID ); ?>"><?php esc_html_e( 'Attach a file', 'pretty-professional-process-docs' ); ?></label><br />
				<input type="file" id="pppd-file-<?php echo esc_attr( (string) $pppd_section->ID ); ?>" name="pppd_file" accept="<?php echo esc_attr( $pppd_accept ); ?>" required />
			</p>
			<p><button type="submit" class="btn"><?php esc_html_e( 'Upload', 'pretty-professional-process-docs' ); ?></button></p>
		</form>
	<?php endif; ?>
</div>
