<?php
/**
 * Partial: per-section sign-off record + approve action.
 *
 * Expects: $pppd_section (WP_Post), $pppd_report (WP_Post).
 *
 * The record is shown to every viewer; the approve/re-approve button to any
 * logged-in viewer (clients sign off their own sections; approval is never
 * an edit). State is conveyed in text, never by colour alone.
 *
 * @package PPPD
 */

defined( 'ABSPATH' ) || exit;

$pppd_signoff = pppd_get_section_signoff( $pppd_section );

// Only published sections participate in sign-off.
if ( 'publish' !== $pppd_section->post_status ) {
	return;
}

$pppd_approver = ( $pppd_signoff['by'] > 0 ) ? get_userdata( $pppd_signoff['by'] ) : false;
$pppd_approved_local = '';

if ( '' !== $pppd_signoff['at'] ) {
	$pppd_approved_local = wp_date(
		get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
		(int) strtotime( $pppd_signoff['at'] . ' UTC' )
	);
}
?>
<div class="pppd-signoff no-print">
	<?php if ( 'approved' === $pppd_signoff['state'] ) : ?>
		<p class="pppd-signoff-state pppd-signoff-state--approved">
			<span aria-hidden="true">✔</span>
			<?php
			printf(
				/* translators: 1: approver display name, 2: local date/time. */
				esc_html__( 'Approved by %1$s on %2$s.', 'pretty-professional-process-docs' ),
				esc_html( $pppd_approver ? $pppd_approver->display_name : __( 'a removed user', 'pretty-professional-process-docs' ) ),
				esc_html( $pppd_approved_local )
			);
			?>
		</p>
	<?php elseif ( 'stale' === $pppd_signoff['state'] ) : ?>
		<p class="pppd-signoff-state pppd-signoff-state--stale">
			<span aria-hidden="true">▲</span>
			<?php
			printf(
				/* translators: 1: approver display name, 2: local date/time. */
				esc_html__( 'Changed since approval — re-approval required. Previously approved by %1$s on %2$s.', 'pretty-professional-process-docs' ),
				esc_html( $pppd_approver ? $pppd_approver->display_name : __( 'a removed user', 'pretty-professional-process-docs' ) ),
				esc_html( $pppd_approved_local )
			);
			?>
			<?php if ( pppd_is_team_viewer() && $pppd_signoff['source'] > 0 ) : ?>
				<?php
				printf(
					/* translators: %d: proposed-change post ID. */
					esc_html__( '(Invalidated by approved change #%d.)', 'pretty-professional-process-docs' ),
					absint( $pppd_signoff['source'] )
				);
				?>
			<?php endif; ?>
		</p>
	<?php endif; ?>

	<?php
	// GitHub queue state + dev/PM queue action (team-only UI).
	$pppd_gh_status = (string) get_post_meta( $pppd_section->ID, '_pppd_github_status', true );
	$pppd_gh_url    = (string) get_post_meta( $pppd_section->ID, '_pppd_github_issue_url', true );
	?>
	<?php if ( pppd_is_team_viewer() && 'pushed' === $pppd_gh_status && '' !== $pppd_gh_url ) : ?>
		<p class="pppd-github-state pppd-internal-only">
			<a href="<?php echo esc_url( $pppd_gh_url ); ?>">
				<?php
				printf(
					/* translators: %d: GitHub issue number. */
					esc_html__( 'GitHub issue #%d', 'pretty-professional-process-docs' ),
					absint( get_post_meta( $pppd_section->ID, '_pppd_github_issue_number', true ) )
				);
				?>
			</a>
		</p>
	<?php elseif ( pppd_is_team_viewer() && 'queued' === $pppd_gh_status ) : ?>
		<p class="pppd-github-state pppd-internal-only">
			<?php esc_html_e( 'Queued for GitHub — the issue will be created on the next agent run.', 'pretty-professional-process-docs' ); ?>
		</p>
	<?php elseif ( current_user_can( 'pppd_approve_changes' ) && ! is_wp_error( pppd_github_queue_eligibility( $pppd_section->ID ) ) ) : ?>
		<form class="pppd-github-form pppd-internal-only" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
			<input type="hidden" name="action" value="pppd_queue_github" />
			<input type="hidden" name="pppd_section" value="<?php echo esc_attr( (string) $pppd_section->ID ); ?>" />
			<?php wp_nonce_field( 'pppd_github_' . $pppd_section->ID, '_pppd_github_nonce' ); ?>
			<button type="submit" class="btn">
				<?php
				printf(
					/* translators: %s: section title. */
					esc_html__( 'Queue “%s” for GitHub', 'pretty-professional-process-docs' ),
					esc_html( get_the_title( $pppd_section ) )
				);
				?>
			</button>
		</form>
	<?php endif; ?>

	<?php if ( is_user_logged_in() ) : ?>
		<form class="pppd-signoff-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
			<input type="hidden" name="action" value="pppd_approve_section" />
			<input type="hidden" name="pppd_section" value="<?php echo esc_attr( (string) $pppd_section->ID ); ?>" />
			<?php wp_nonce_field( 'pppd_signoff_' . $pppd_section->ID, '_pppd_signoff_nonce' ); ?>
			<?php if ( 'approved' !== $pppd_signoff['state'] ) : ?>
				<button type="submit" class="btn">
					<?php
					if ( 'stale' === $pppd_signoff['state'] ) {
						printf(
							/* translators: %s: section title. */
							esc_html__( 'Re-approve “%s”', 'pretty-professional-process-docs' ),
							esc_html( get_the_title( $pppd_section ) )
						);
					} else {
						printf(
							/* translators: %s: section title. */
							esc_html__( 'Approve “%s”', 'pretty-professional-process-docs' ),
							esc_html( get_the_title( $pppd_section ) )
						);
					}
					?>
				</button>
			<?php endif; ?>
		</form>
	<?php endif; ?>
</div>
