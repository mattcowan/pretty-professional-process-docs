<?php
/**
 * Partial: status badge.
 *
 * Expects: $pppd_section (WP_Post).
 *
 * Contract (WCAG AA — status is never colour alone):
 * <span class="badge badge--{slug}"><span aria-hidden="true">{symbol}</span> {Label}</span>
 *
 * @package PPPD
 */

defined( 'ABSPATH' ) || exit;

$pppd_status_term = pppd_get_section_status_term( $pppd_section );

if ( $pppd_status_term instanceof WP_Term ) :
	$pppd_symbols = pppd_status_symbols();
	$pppd_symbol  = isset( $pppd_symbols[ $pppd_status_term->slug ] ) ? $pppd_symbols[ $pppd_status_term->slug ] : '';
	?>
	<span class="badge badge--<?php echo esc_attr( $pppd_status_term->slug ); ?>"><span aria-hidden="true"><?php echo esc_html( $pppd_symbol ); ?></span> <?php echo esc_html( $pppd_status_term->name ); ?></span>
	<?php
endif;
