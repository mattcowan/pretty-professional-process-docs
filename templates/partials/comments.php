<?php
/**
 * Partial: per-section discussion (static comment list + form).
 *
 * Expects: $pppd_section (WP_Post), $pppd_report (WP_Post), $pppd_sub_level (int).
 *
 * @package PPPD
 */

defined( 'ABSPATH' ) || exit;

$pppd_comment_heading = 'h' . min( max( (int) $pppd_sub_level, 3 ), 6 );

$pppd_comments = get_comments(
	array(
		'post_id' => $pppd_section->ID,
		'status'  => 'approve',
		'orderby' => 'comment_date_gmt',
		'order'   => 'ASC',
	)
);
?>
<div class="pppd-comments no-print">
	<<?php echo esc_html( $pppd_comment_heading ); ?>><?php esc_html_e( 'Discussion', 'pretty-professional-process-docs' ); ?></<?php echo esc_html( $pppd_comment_heading ); ?>>

	<?php if ( ! empty( $pppd_comments ) ) : ?>
		<ol class="pppd-comment-list">
			<?php
			wp_list_comments(
				array(
					'style'       => 'ol',
					'short_ping'  => true,
					'avatar_size' => 32,
					'max_depth'   => 1,
				),
				$pppd_comments
			);
			?>
		</ol>
	<?php else : ?>
		<p class="pppd-no-comments"><?php esc_html_e( 'No comments yet.', 'pretty-professional-process-docs' ); ?></p>
	<?php endif; ?>

	<?php if ( is_user_logged_in() && comments_open( $pppd_section ) ) : ?>
		<form class="pppd-comment-form" action="<?php echo esc_url( site_url( '/wp-comments-post.php' ) ); ?>" method="post">
			<p>
				<label for="pppd-comment-<?php echo esc_attr( (string) $pppd_section->ID ); ?>">
					<?php
					printf(
						/* translators: %s: section title. */
						esc_html__( 'Comment on “%s”', 'pretty-professional-process-docs' ),
						esc_html( get_the_title( $pppd_section ) )
					);
					?>
				</label><br />
				<textarea id="pppd-comment-<?php echo esc_attr( (string) $pppd_section->ID ); ?>" name="comment" rows="4" required></textarea>
			</p>
			<input type="hidden" name="comment_post_ID" value="<?php echo esc_attr( (string) $pppd_section->ID ); ?>" />
			<input type="hidden" name="comment_parent" value="0" />
			<?php wp_nonce_field( 'pppd_comment_' . $pppd_section->ID, '_pppd_comment_nonce' ); ?>
			<p><button type="submit" class="btn"><?php esc_html_e( 'Post comment', 'pretty-professional-process-docs' ); ?></button></p>
		</form>
	<?php elseif ( ! is_user_logged_in() ) : ?>
		<p>
			<a href="<?php echo esc_url( wp_login_url( get_permalink( $pppd_report ) . '#' . pppd_section_anchor( $pppd_section ) ) ); ?>">
				<?php esc_html_e( 'Log in to join the discussion.', 'pretty-professional-process-docs' ); ?>
			</a>
		</p>
	<?php endif; ?>
</div>
