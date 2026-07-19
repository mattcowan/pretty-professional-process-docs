<?php
/**
 * Single report template — standalone report layout.
 *
 * Accessibility contract: skip link, nav[aria-label], single main landmark,
 * hierarchical headings, sections labelled by their headings, badges that
 * never rely on colour alone, real tables with captions and scoped headers.
 *
 * @package PPPD
 */

defined( 'ABSPATH' ) || exit;

while ( have_posts() ) :
	the_post();

	$pppd_report        = get_post();
	// Team-only sections are stripped for client viewers before grouping, so
	// the TOC, section loop, and counts all agree on what is visible. Team
	// viewers additionally see draft/pending sections (each flagged in the
	// render) unless they switched to the client preview.
	$pppd_section_posts = pppd_filter_visible_sections( pppd_get_report_section_posts( $pppd_report->ID, pppd_report_render_statuses( $pppd_report->ID ) ) );
	$pppd_grouped       = pppd_group_sections_by_parent( $pppd_section_posts );
	$pppd_sections      = pppd_flatten_section_tree( $pppd_grouped );
	$pppd_draft_count   = count(
		array_filter(
			$pppd_section_posts,
			static function ( $pppd_section_post ) {
				return 'publish' !== $pppd_section_post->post_status;
			}
		)
	);
	$pppd_project_slug  = (string) get_post_meta( $pppd_report->ID, '_pppd_project_slug', true );
	$pppd_pdf_url       = (string) get_post_meta( $pppd_report->ID, '_pppd_pdf_url', true );

	$pppd_csv_url = wp_nonce_url(
		add_query_arg( 'format', 'csv', rest_url( 'pppd/v1/reports/' . $pppd_report->ID . '/traceability' ) ),
		'wp_rest'
	);
	?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'pppd-report-view' ); ?>>
<?php wp_body_open(); ?>

<a class="skip-link" href="#main"><?php esc_html_e( 'Skip to content', 'pretty-professional-process-docs' ); ?></a>

<div class="report-shell">

	<?php require PPPD_PLUGIN_DIR . 'templates/partials/toc.php'; ?>

	<main id="main">
		<header>
			<h1><?php echo esc_html( get_the_title( $pppd_report ) ); ?></h1>

			<p class="report-meta">
				<?php if ( '' !== $pppd_project_slug ) : ?>
					<?php
					printf(
						/* translators: %s: project slug. */
						esc_html__( 'Project: %s', 'pretty-professional-process-docs' ),
						esc_html( $pppd_project_slug )
					);
					?>
					&middot;
				<?php endif; ?>
				<?php
				printf(
					/* translators: %s: last modified date. */
					esc_html__( 'Last updated %s', 'pretty-professional-process-docs' ),
					esc_html( get_the_modified_date( '', $pppd_report ) )
				);
				?>
				&middot;
				<?php
				printf(
					/* translators: %d: number of sections. */
					esc_html( _n( '%d section', '%d sections', count( $pppd_sections ), 'pretty-professional-process-docs' ) ),
					count( $pppd_sections )
				);
				?>
			</p>

			<p class="report-actions no-print">
				<?php if ( '' !== $pppd_pdf_url ) : ?>
					<a class="btn" href="<?php echo esc_url( $pppd_pdf_url ); ?>" download>
						<?php esc_html_e( 'Download accessible PDF', 'pretty-professional-process-docs' ); ?>
					</a>
				<?php else : ?>
					<?php // No tagged PDF has been generated yet — offer an honest browser fallback. ?>
					<button type="button" class="btn" data-pppd-print>
						<?php esc_html_e( 'Print / Save as PDF', 'pretty-professional-process-docs' ); ?>
					</button>
				<?php endif; ?>
				<?php if ( pppd_is_team_viewer() ) : ?>
					<a class="btn" href="<?php echo esc_url( $pppd_csv_url ); ?>">
						<?php esc_html_e( 'Download traceability CSV', 'pretty-professional-process-docs' ); ?>
					</a>
					<?php if ( pppd_is_client_preview() ) : ?>
						<a class="btn" href="<?php echo esc_url( remove_query_arg( 'pppd_preview' ) ); ?>">
							<?php esc_html_e( 'Back to team view (includes drafts)', 'pretty-professional-process-docs' ); ?>
						</a>
					<?php else : ?>
						<a class="btn" href="<?php echo esc_url( add_query_arg( 'pppd_preview', 'published' ) ); ?>">
							<?php esc_html_e( 'View as client sees it (published sections only)', 'pretty-professional-process-docs' ); ?>
						</a>
					<?php endif; ?>
				<?php endif; ?>
			</p>

			<?php if ( pppd_is_team_viewer() && pppd_is_client_preview() ) : ?>
				<p class="pppd-view-notice no-print">
					<?php esc_html_e( 'Viewing as a client. Draft sections are hidden.', 'pretty-professional-process-docs' ); ?>
					<a href="<?php echo esc_url( remove_query_arg( 'pppd_preview' ) ); ?>">
						<?php esc_html_e( 'Back to team view', 'pretty-professional-process-docs' ); ?>
					</a>
				</p>
			<?php elseif ( $pppd_draft_count > 0 ) : ?>
				<p class="pppd-view-notice">
					<strong><?php esc_html_e( 'Team view.', 'pretty-professional-process-docs' ); ?></strong>
					<?php
					printf(
						/* translators: %d: number of draft sections. */
						esc_html( _n( 'Includes %d draft section marked “Draft — not part of the signed document.” Clients see published sections only.', 'Includes %d draft sections marked “Draft — not part of the signed document.” Clients see published sections only.', $pppd_draft_count, 'pretty-professional-process-docs' ) ),
						(int) $pppd_draft_count
					);
					?>
				</p>
			<?php endif; ?>
		</header>

		<section id="executive-summary" aria-labelledby="executive-summary-heading">
			<h2 id="executive-summary-heading"><?php esc_html_e( 'Executive summary', 'pretty-professional-process-docs' ); ?></h2>
			<?php echo wp_kses_post( apply_filters( 'the_content', get_the_content( null, false, $pppd_report ) ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core filter. ?>
		</section>

		<?php
		foreach ( $pppd_sections as $pppd_entry ) {
			$pppd_section = $pppd_entry['post'];
			$pppd_depth   = (int) $pppd_entry['depth'];

			require PPPD_PLUGIN_DIR . 'templates/partials/section.php';
		}

		// Drift tracking is internal tooling — never part of the client view.
		if ( pppd_is_team_viewer() ) {
			require PPPD_PLUGIN_DIR . 'templates/partials/drift-summary.php';
		}
		?>
	</main>
</div>

<?php wp_footer(); ?>
</body>
</html>
	<?php
endwhile;
