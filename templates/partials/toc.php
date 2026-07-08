<?php
/**
 * Partial: table of contents.
 *
 * Expects: $pppd_grouped (sections grouped by parent).
 *
 * @package PPPD
 */

defined( 'ABSPATH' ) || exit;
?>
<nav class="report-toc" aria-label="<?php esc_attr_e( 'Contents', 'pretty-professional-process-docs' ); ?>">
	<ol>
		<li><a href="#executive-summary"><?php esc_html_e( 'Executive summary', 'pretty-professional-process-docs' ); ?></a></li>
	</ol>
	<?php pppd_render_toc_list( $pppd_grouped ); ?>
</nav>
