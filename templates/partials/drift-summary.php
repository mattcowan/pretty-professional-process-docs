<?php
/**
 * Partial: latest drift run summary.
 *
 * Expects: $pppd_report (WP_Post).
 *
 * @package PPPD
 */

defined( 'ABSPATH' ) || exit;

$pppd_drift = pppd_get_latest_drift( $pppd_report->ID );

if ( $pppd_drift instanceof WP_Post ) :
	$pppd_drift_items = pppd_get_drift_items( $pppd_drift );
	?>
<section id="drift-summary" aria-labelledby="drift-summary-heading">
	<h2 id="drift-summary-heading"><?php esc_html_e( 'Latest drift check', 'pretty-professional-process-docs' ); ?></h2>

	<p class="report-meta">
		<?php
		printf(
			/* translators: %s: drift run date. */
			esc_html__( 'Recorded %s', 'pretty-professional-process-docs' ),
			esc_html( get_the_date( '', $pppd_drift ) )
		);
		?>
	</p>

	<?php echo wp_kses_post( wpautop( $pppd_drift->post_content ) ); ?>

	<?php if ( ! empty( $pppd_drift_items ) ) : ?>
		<div class="table-scroll" tabindex="0">
			<table>
				<caption><?php esc_html_e( 'Per-requirement drift verdicts', 'pretty-professional-process-docs' ); ?></caption>
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Requirement', 'pretty-professional-process-docs' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Verdict', 'pretty-professional-process-docs' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Notes', 'pretty-professional-process-docs' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $pppd_drift_items as $pppd_item ) : ?>
						<tr>
							<th scope="row"><code><?php echo esc_html( isset( $pppd_item['req_id'] ) ? (string) $pppd_item['req_id'] : '' ); ?></code></th>
							<td><?php echo esc_html( isset( $pppd_item['verdict'] ) ? (string) $pppd_item['verdict'] : '' ); ?></td>
							<td><?php echo esc_html( isset( $pppd_item['notes'] ) ? (string) $pppd_item['notes'] : '' ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</section>
	<?php
endif;
