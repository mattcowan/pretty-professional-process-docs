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
$pppd_dev_prompt = (string) get_post_meta( $pppd_section->ID, '_pppd_dev_prompt', true );
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

	<?php if ( '' !== $pppd_dev_prompt ) : ?>
		<div class="callout callout--evidence">
			<p><strong><?php esc_html_e( 'Developer prompt', 'pretty-professional-process-docs' ); ?></strong></p>
			<pre><code><?php echo esc_html( $pppd_dev_prompt ); ?></code></pre>
		</div>
	<?php endif; ?>
</div>
