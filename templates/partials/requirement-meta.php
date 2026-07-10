<?php
/**
 * Partial: requirement metadata block.
 *
 * Expects: $pppd_section (WP_Post), $pppd_sub_level (int).
 *
 * @package PPPD
 */

defined( 'ABSPATH' ) || exit;

$pppd_req_id     = (string) get_post_meta( $pppd_section->ID, '_pppd_req_id', true );
$pppd_acceptance = get_post_meta( $pppd_section->ID, '_pppd_acceptance', true );
$pppd_acceptance = is_array( $pppd_acceptance ) ? $pppd_acceptance : array();

// Internal-only: implementation notes render for the team, never for clients.
// (The old client-facing _pppd_dev_prompt block was retired in 0.3.0.)
$pppd_impl_notes = pppd_is_team_viewer() ? (string) get_post_meta( $pppd_section->ID, '_pppd_impl_notes', true ) : '';
?>
<div class="pppd-requirement-meta">
	<?php if ( '' !== $pppd_req_id ) : ?>
		<p>
			<strong><?php esc_html_e( 'Requirement ID:', 'pretty-professional-process-docs' ); ?></strong>
			<code><?php echo esc_html( $pppd_req_id ); ?></code>
		</p>
	<?php endif; ?>

	<?php if ( ! empty( $pppd_acceptance ) ) : ?>
		<div class="table-scroll" tabindex="0">
			<table>
				<caption>
					<?php
					if ( '' !== $pppd_req_id ) {
						printf(
							/* translators: %s: requirement ID. */
							esc_html__( 'Acceptance criteria for %s', 'pretty-professional-process-docs' ),
							esc_html( $pppd_req_id )
						);
					} else {
						esc_html_e( 'Acceptance criteria', 'pretty-professional-process-docs' );
					}
					?>
				</caption>
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( '#', 'pretty-professional-process-docs' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Criterion', 'pretty-professional-process-docs' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( array_values( $pppd_acceptance ) as $pppd_index => $pppd_criterion ) : ?>
						<tr>
							<th scope="row"><?php echo esc_html( (string) ( $pppd_index + 1 ) ); ?></th>
							<td><?php echo esc_html( $pppd_criterion ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>

	<?php if ( '' !== $pppd_impl_notes ) : ?>
		<div class="callout callout--evidence pppd-internal-only">
			<p><strong><?php esc_html_e( 'Implementation notes (internal — not shown to clients)', 'pretty-professional-process-docs' ); ?></strong></p>
			<?php // tabindex + region name: the pre can scroll horizontally, so keyboard users need to reach it (WCAG 2.1.1). ?>
			<pre tabindex="0" role="region" aria-label="<?php esc_attr_e( 'Implementation notes', 'pretty-professional-process-docs' ); ?>"><code><?php echo esc_html( $pppd_impl_notes ); ?></code></pre>
		</div>
	<?php endif; ?>
</div>
