<?php
/**
 * Report authoring dashboard: the outline metabox on the report edit screen
 * (table of contents + keyboard-operable reordering + add-section buttons)
 * and the report/prev/next navigation box on section edit screens.
 *
 * Sections stay discrete posts — Gutenberg remains the only content-editing
 * surface (inheriting core's ATAG work); this class only makes the scattered
 * posts navigable as one document.
 *
 * @package PPPD
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the outline/navigation metaboxes and their supporting pieces.
 */
class PPPD_Report_Outline {

	/**
	 * Hook everything up.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'save_post_pppd_section', array( $this, 'prefill_new_section' ), 5, 3 );
	}

	/**
	 * Register the boxes.
	 *
	 * @return void
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'pppd_report_outline',
			__( 'Report Sections', 'pretty-professional-process-docs' ),
			array( $this, 'render_outline_box' ),
			'pppd_report',
			'normal',
			'high'
		);

		add_meta_box(
			'pppd_section_nav',
			__( 'Report Navigation', 'pretty-professional-process-docs' ),
			array( $this, 'render_section_nav_box' ),
			'pppd_section',
			'side',
			'high'
		);
	}

	/**
	 * Enqueue the outline script/styles on the report edit screen only.
	 *
	 * @param string $hook_suffix Current admin page.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();

		if ( ! $screen || ! in_array( $screen->post_type, array( 'pppd_report', 'pppd_section' ), true ) ) {
			return;
		}

		wp_enqueue_style(
			'pppd-admin-outline',
			PPPD_PLUGIN_URL . 'assets/css/admin-outline.css',
			array(),
			PPPD_VERSION
		);

		if ( 'pppd_report' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_script(
			'pppd-admin-outline',
			PPPD_PLUGIN_URL . 'assets/js/admin-outline.js',
			array( 'wp-a11y', 'wp-api-fetch' ),
			PPPD_VERSION,
			true
		);

		wp_localize_script(
			'pppd-admin-outline',
			'pppdOutline',
			array(
				'i18n' => array(
					'up'      => __( 'Move up', 'pretty-professional-process-docs' ),
					'down'    => __( 'Move down', 'pretty-professional-process-docs' ),
					'indent'  => __( 'Indent (nest under previous section)', 'pretty-professional-process-docs' ),
					'outdent' => __( 'Outdent (move to parent level)', 'pretty-professional-process-docs' ),
					'edit'    => __( 'Edit section', 'pretty-professional-process-docs' ),
					'moving'  => __( 'Moving…', 'pretty-professional-process-docs' ),
					'failed'  => __( 'The move failed. The outline has not changed.', 'pretty-professional-process-docs' ),
				),
			)
		);
	}

	/**
	 * Render the outline metabox: ordered section tree with badges,
	 * keyboard-operable move buttons, and add-section buttons.
	 *
	 * @param WP_Post $post Report post.
	 * @return void
	 */
	public function render_outline_box( $post ) {
		$entries = pppd_get_authoring_outline( $post->ID );
		?>
		<div
			class="pppd-outline"
			data-pppd-outline
			data-report="<?php echo esc_attr( (string) $post->ID ); ?>"
			data-endpoint="<?php echo esc_url( rest_url( 'pppd/v1/reports/' . $post->ID . '/reorder' ) ); ?>"
		>
			<p class="description">
				<?php esc_html_e( 'The full report body lives in these sections, in this order. Use the move buttons (keyboard accessible) to restructure; edit a section to change its content.', 'pretty-professional-process-docs' ); ?>
			</p>

			<?php if ( empty( $entries ) ) : ?>
				<p data-pppd-outline-empty><?php esc_html_e( 'No sections yet — add the first one below.', 'pretty-professional-process-docs' ); ?></p>
			<?php endif; ?>

			<ol class="pppd-outline-list" data-pppd-outline-list>
				<?php foreach ( $entries as $entry ) : ?>
					<?php $this->render_outline_row( $entry ); ?>
				<?php endforeach; ?>
			</ol>

			<?php $this->render_add_buttons( $post ); ?>
		</div>
		<?php
	}

	/**
	 * Render one outline row (also mirrored client-side in admin-outline.js —
	 * keep the structures in sync).
	 *
	 * @param array<string, mixed> $entry Authoring outline entry.
	 * @return void
	 */
	protected function render_outline_row( $entry ) {
		$type      = pppd_registry()->get_section_type( (string) $entry['type'] );
		$type_name = null !== $type ? (string) $type['label'] : (string) $entry['type'];
		$title     = '' !== (string) $entry['title'] ? (string) $entry['title'] : __( '(no title)', 'pretty-professional-process-docs' );

		$moves = array(
			'up'      => array( __( 'Move up', 'pretty-professional-process-docs' ), '↑', (bool) $entry['can_up'] ),
			'down'    => array( __( 'Move down', 'pretty-professional-process-docs' ), '↓', (bool) $entry['can_down'] ),
			'indent'  => array( __( 'Indent (nest under previous section)', 'pretty-professional-process-docs' ), '→', (bool) $entry['can_indent'] ),
			'outdent' => array( __( 'Outdent (move to parent level)', 'pretty-professional-process-docs' ), '←', (bool) $entry['can_outdent'] ),
		);
		?>
		<li class="pppd-outline-row" data-section="<?php echo esc_attr( (string) $entry['id'] ); ?>" style="--pppd-depth: <?php echo esc_attr( (string) (int) $entry['depth'] ); ?>;">
			<span class="pppd-outline-main">
				<?php if ( '' !== (string) $entry['edit_link'] ) : ?>
					<a class="pppd-outline-title" href="<?php echo esc_url( (string) $entry['edit_link'] ); ?>">
						<?php echo esc_html( $title ); ?>
					</a>
				<?php else : ?>
					<span class="pppd-outline-title"><?php echo esc_html( $title ); ?></span>
				<?php endif; ?>

				<span class="pppd-badge pppd-badge--type"><?php echo esc_html( $type_name ); ?></span>

				<?php if ( '' !== (string) $entry['req_id'] ) : ?>
					<code class="pppd-outline-reqid"><?php echo esc_html( (string) $entry['req_id'] ); ?></code>
				<?php endif; ?>

				<?php if ( 'publish' !== (string) $entry['post_status'] ) : ?>
					<span class="pppd-badge pppd-badge--draft"><?php echo esc_html( (string) $entry['post_status'] ); ?></span>
				<?php endif; ?>

				<?php if ( '' !== (string) $entry['status'] ) : ?>
					<span class="pppd-badge pppd-badge--status"><?php echo esc_html( (string) $entry['status'] ); ?></span>
				<?php endif; ?>
			</span>

			<span class="pppd-outline-actions">
				<?php foreach ( $moves as $direction => $move ) : ?>
					<button
						type="button"
						class="button button-small"
						data-direction="<?php echo esc_attr( $direction ); ?>"
						aria-label="<?php echo esc_attr( $move[0] . ': ' . $title ); ?>"
						<?php disabled( ! $move[2] ); ?>
					><span aria-hidden="true"><?php echo esc_html( $move[1] ); ?></span></button>
				<?php endforeach; ?>
			</span>
		</li>
		<?php
	}

	/**
	 * Render the add-section buttons for the report's allowed section types.
	 *
	 * @param WP_Post $post Report post.
	 * @return void
	 */
	protected function render_add_buttons( $post ) {
		$report_type = pppd_get_report_type_object( $post );
		$allowed     = ( null !== $report_type ) ? (array) $report_type['section_types'] : array();

		if ( empty( $allowed ) ) {
			return;
		}
		?>
		<p class="pppd-outline-add">
			<strong><?php esc_html_e( 'Add section:', 'pretty-professional-process-docs' ); ?></strong>
			<?php foreach ( $allowed as $slug ) : ?>
				<?php
				$type = pppd_registry()->get_section_type( (string) $slug );

				if ( null === $type ) {
					continue;
				}

				$url = add_query_arg(
					array(
						'post_type'         => 'pppd_section',
						'pppd_report'       => $post->ID,
						'pppd_section_type' => rawurlencode( (string) $slug ),
						'pppd_prefill'      => wp_create_nonce( 'pppd_prefill_' . $post->ID ),
					),
					admin_url( 'post-new.php' )
				);
				?>
				<a class="button" href="<?php echo esc_url( $url ); ?>">
					<?php echo esc_html( (string) $type['label'] ); ?>
				</a>
			<?php endforeach; ?>
		</p>
		<?php
	}

	/**
	 * Prefill _pppd_report_id and the section type term on the auto-draft a
	 * new section starts as, when arriving via an outline add-section button.
	 *
	 * Hooked to save_post_pppd_section at priority 5 (before the ID assigner).
	 *
	 * @param int     $post_id Section post ID.
	 * @param WP_Post $post    Section post.
	 * @param bool    $update  Whether this is an update.
	 * @return void
	 */
	public function prefill_new_section( $post_id, $post, $update ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( 'auto-draft' !== $post->post_status ) {
			return;
		}

		if ( ! isset( $_GET['pppd_report'], $_GET['pppd_prefill'] ) ) {
			return;
		}

		$report_id = absint( wp_unslash( $_GET['pppd_report'] ) );

		if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_GET['pppd_prefill'] ) ), 'pppd_prefill_' . $report_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_pppd_reports' ) ) {
			return;
		}

		$report = get_post( $report_id );

		if ( ! $report instanceof WP_Post || 'pppd_report' !== $report->post_type ) {
			return;
		}

		update_post_meta( $post_id, '_pppd_report_id', $report_id );

		$type_slug   = isset( $_GET['pppd_section_type'] ) ? sanitize_key( wp_unslash( $_GET['pppd_section_type'] ) ) : '';
		$report_type = pppd_get_report_type_object( $report );

		if ( '' !== $type_slug && null !== $report_type && in_array( $type_slug, (array) $report_type['section_types'], true ) ) {
			wp_set_object_terms( $post_id, $type_slug, 'pppd_section_type' );
		}
	}

	/**
	 * Render the report/prev/next navigation box on section edit screens.
	 *
	 * @param WP_Post $post Section post.
	 * @return void
	 */
	public function render_section_nav_box( $post ) {
		$report_id = absint( get_post_meta( $post->ID, '_pppd_report_id', true ) );

		if ( 0 === $report_id ) {
			echo '<p>' . esc_html__( 'Not attached to a report yet — pick one in Section Settings below.', 'pretty-professional-process-docs' ) . '</p>';
			return;
		}

		$report = get_post( $report_id );

		if ( ! $report instanceof WP_Post ) {
			echo '<p>' . esc_html__( 'The attached report no longer exists.', 'pretty-professional-process-docs' ) . '</p>';
			return;
		}

		$entries = pppd_get_authoring_outline( $report_id );
		$prev    = null;
		$next    = null;
		$found   = false;

		foreach ( $entries as $entry ) {
			if ( $found ) {
				$next = $entry;
				break;
			}

			if ( (int) $entry['id'] === (int) $post->ID ) {
				$found = true;
				continue;
			}

			$prev = $entry;
		}

		$report_edit = get_edit_post_link( $report_id, 'raw' );
		?>
		<nav aria-label="<?php esc_attr_e( 'Report document navigation', 'pretty-professional-process-docs' ); ?>">
			<p>
				<?php esc_html_e( 'Part of:', 'pretty-professional-process-docs' ); ?>
				<?php if ( is_string( $report_edit ) ) : ?>
					<a href="<?php echo esc_url( $report_edit ); ?>"><strong><?php echo esc_html( get_the_title( $report ) ); ?></strong></a>
				<?php else : ?>
					<strong><?php echo esc_html( get_the_title( $report ) ); ?></strong>
				<?php endif; ?>
			</p>
			<p>
				<?php if ( null !== $prev && '' !== (string) $prev['edit_link'] ) : ?>
					<a class="button" href="<?php echo esc_url( (string) $prev['edit_link'] ); ?>">
						<?php
						printf(
							/* translators: %s: previous section title. */
							esc_html__( '← Previous: %s', 'pretty-professional-process-docs' ),
							esc_html( (string) $prev['title'] )
						);
						?>
					</a>
				<?php endif; ?>
				<?php if ( null !== $next && '' !== (string) $next['edit_link'] ) : ?>
					<a class="button" href="<?php echo esc_url( (string) $next['edit_link'] ); ?>">
						<?php
						printf(
							/* translators: %s: next section title. */
							esc_html__( 'Next: %s →', 'pretty-professional-process-docs' ),
							esc_html( (string) $next['title'] )
						);
						?>
					</a>
				<?php endif; ?>
			</p>
			<?php if ( 'publish' === $post->post_status && 'publish' === $report->post_status ) : ?>
				<p>
					<a href="<?php echo esc_url( get_permalink( $report ) . '#' . pppd_section_anchor( $post ) ); ?>">
						<?php esc_html_e( 'View on report', 'pretty-professional-process-docs' ); ?>
					</a>
				</p>
			<?php endif; ?>
		</nav>
		<?php
	}
}
