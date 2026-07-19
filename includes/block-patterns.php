<?php
/**
 * Block patterns: starter skeletons for each built-in section type, so
 * hand-authored sections match agent-authored ones.
 *
 * Section content is core-block markup only (paragraph, heading, list, table,
 * code). Structured data — acceptance criteria, requirement IDs, code/test
 * refs — lives in post meta and renders via the section meta partials; the
 * patterns remind authors of that split instead of duplicating it into
 * content.
 *
 * @package PPPD
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the pattern category and one starter pattern per built-in section
 * type. Patterns are scoped to the pppd_section editor.
 *
 * @return void
 */
function pppd_register_block_patterns() {
	if ( ! function_exists( 'register_block_pattern' ) ) {
		return;
	}

	register_block_pattern_category(
		'pppd',
		array( 'label' => __( 'Process Docs', 'pretty-professional-process-docs' ) )
	);

	register_block_pattern(
		'pppd/section-narrative',
		array(
			'title'       => __( 'PPPD: Narrative section', 'pretty-professional-process-docs' ),
			'description' => __( 'Context prose: what the reader needs to understand before the requirements.', 'pretty-professional-process-docs' ),
			'categories'  => array( 'pppd' ),
			'postTypes'   => array( 'pppd_section' ),
			'content'     => '<!-- wp:paragraph -->
<p>' . esc_html__( 'One idea per paragraph. Say what is true today, then what this document changes.', 'pretty-professional-process-docs' ) . '</p>
<!-- /wp:paragraph -->

<!-- wp:list -->
<ul class="wp-block-list"><!-- wp:list-item -->
<li>' . esc_html__( 'Key point readers should retain.', 'pretty-professional-process-docs' ) . '</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->',
		)
	);

	register_block_pattern(
		'pppd/section-requirement',
		array(
			'title'       => __( 'PPPD: Requirement section', 'pretty-professional-process-docs' ),
			'description' => __( 'What must exist and why. Acceptance criteria belong in the Section settings box, not in the content.', 'pretty-professional-process-docs' ),
			'categories'  => array( 'pppd' ),
			'postTypes'   => array( 'pppd_section' ),
			'content'     => '<!-- wp:paragraph -->
<p>' . esc_html__( 'What must exist, in one or two sentences, followed by the one-sentence WHY.', 'pretty-professional-process-docs' ) . '</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><em>' . esc_html__( 'Reminder: acceptance criteria, code refs, and test refs live in the Section settings meta box — the requirement ID and criteria render below the content automatically. Do not repeat them here.', 'pretty-professional-process-docs' ) . '</em></p>
<!-- /wp:paragraph -->',
		)
	);

	register_block_pattern(
		'pppd/section-decision',
		array(
			'title'       => __( 'PPPD: Decision section', 'pretty-professional-process-docs' ),
			'description' => __( 'A choice that was made: what was decided, by which role, when, and why.', 'pretty-professional-process-docs' ),
			'categories'  => array( 'pppd' ),
			'postTypes'   => array( 'pppd_section' ),
			'content'     => '<!-- wp:paragraph -->
<p><strong>' . esc_html__( 'Decided:', 'pretty-professional-process-docs' ) . '</strong> ' . esc_html__( 'the choice, in one sentence.', 'pretty-professional-process-docs' ) . '</p>
<!-- /wp:paragraph -->

<!-- wp:list -->
<ul class="wp-block-list"><!-- wp:list-item -->
<li>' . esc_html__( 'Owner (role, never a personal name) and date.', 'pretty-professional-process-docs' ) . '</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>' . esc_html__( 'Why this option won, and what was ruled out.', 'pretty-professional-process-docs' ) . '</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->',
		)
	);
}
