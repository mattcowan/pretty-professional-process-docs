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
$pppd_meta_partial  = pppd_get_section_meta_partial( $pppd_section );
// Only ever true in the team view — client/anonymous renders are publish-only.
$pppd_is_draft_section = 'publish' !== $pppd_section->post_status;
?>
<section id="<?php echo esc_attr( $pppd_anchor ); ?>" class="pppd-section pppd-section--depth-<?php echo esc_attr( (string) $pppd_depth ); ?><?php echo $pppd_is_draft_section ? ' pppd-section--unpublished' : ''; ?>" aria-labelledby="<?php echo esc_attr( $pppd_anchor . '-heading' ); ?>">
	<<?php echo esc_html( $pppd_heading_tag ); ?> id="<?php echo esc_attr( $pppd_anchor . '-heading' ); ?>">
		<?php echo esc_html( get_the_title( $pppd_section ) ); ?>
		<?php require PPPD_PLUGIN_DIR . 'templates/partials/status-badge.php'; ?>
	</<?php echo esc_html( $pppd_heading_tag ); ?>>

	<?php if ( $pppd_is_draft_section ) : ?>
		<p class="pppd-draft-flag">
			<span aria-hidden="true">◌</span>
			<strong><?php esc_html_e( 'Draft', 'pretty-professional-process-docs' ); ?></strong>
			<?php esc_html_e( '— not part of the signed document', 'pretty-professional-process-docs' ); ?>
		</p>
	<?php endif; ?>

	<?php echo wp_kses_post( apply_filters( 'the_content', $pppd_section->post_content ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core filter. ?>

	<?php if ( '' !== $pppd_meta_partial ) : ?>
		<?php require $pppd_meta_partial; ?>
	<?php endif; ?>

	<?php require PPPD_PLUGIN_DIR . 'templates/partials/signoff.php'; ?>
	<?php require PPPD_PLUGIN_DIR . 'templates/partials/comments.php'; ?>
	<?php require PPPD_PLUGIN_DIR . 'templates/partials/uploads.php'; ?>
</section>
