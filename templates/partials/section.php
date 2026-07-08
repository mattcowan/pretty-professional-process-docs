<?php
/**
 * Partial: a single section.
 *
 * Expects: $pppd_section (WP_Post), $pppd_depth (int), $pppd_report (WP_Post).
 *
 * Heading level: h2 at depth 0, h3 at depth 1, h4 at depth 2+.
 *
 * @package PPPD
 */

defined( 'ABSPATH' ) || exit;

$pppd_heading_level = min( 2 + $pppd_depth, 4 );
$pppd_heading_tag   = 'h' . $pppd_heading_level;
$pppd_sub_level     = min( $pppd_heading_level + 1, 6 );
$pppd_anchor        = pppd_section_anchor( $pppd_section );
$pppd_is_requirement = ( 'requirement' === pppd_get_section_term_slug( $pppd_section, 'pppd_section_type' ) );
?>
<section id="<?php echo esc_attr( $pppd_anchor ); ?>" class="pppd-section pppd-section--depth-<?php echo esc_attr( (string) $pppd_depth ); ?>" aria-labelledby="<?php echo esc_attr( $pppd_anchor . '-heading' ); ?>">
	<<?php echo esc_html( $pppd_heading_tag ); ?> id="<?php echo esc_attr( $pppd_anchor . '-heading' ); ?>">
		<?php echo esc_html( get_the_title( $pppd_section ) ); ?>
		<?php require PPPD_PLUGIN_DIR . 'templates/partials/status-badge.php'; ?>
	</<?php echo esc_html( $pppd_heading_tag ); ?>>

	<?php echo wp_kses_post( apply_filters( 'the_content', $pppd_section->post_content ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core filter. ?>

	<?php if ( $pppd_is_requirement ) : ?>
		<?php require PPPD_PLUGIN_DIR . 'templates/partials/requirement-meta.php'; ?>
	<?php endif; ?>

	<?php require PPPD_PLUGIN_DIR . 'templates/partials/comments.php'; ?>
	<?php require PPPD_PLUGIN_DIR . 'templates/partials/uploads.php'; ?>
</section>
