<?php
/**
 * Meta boxes for reports, sections, and proposed changes.
 *
 * @package PPPD
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers meta boxes and their (nonce-verified, sanitized) save handlers.
 */
class PPPD_Meta_Boxes {

	/**
	 * Hook everything up.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_pppd_report', array( $this, 'save_report' ), 10, 2 );
		add_action( 'save_post_pppd_section', array( $this, 'save_section' ), 10, 2 );
		add_action( 'save_post_pppd_change', array( $this, 'save_change' ), 10, 2 );
	}

	/**
	 * Register the boxes.
	 *
	 * @return void
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'pppd_report_settings',
			__( 'Report Settings', 'pretty-professional-process-docs' ),
			array( $this, 'render_report_box' ),
			'pppd_report',
			'normal',
			'default'
		);

		add_meta_box(
			'pppd_section_settings',
			__( 'Section Settings', 'pretty-professional-process-docs' ),
			array( $this, 'render_section_box' ),
			'pppd_section',
			'normal',
			'default'
		);

		add_meta_box(
			'pppd_change_settings',
			__( 'Change Settings', 'pretty-professional-process-docs' ),
			array( $this, 'render_change_box' ),
			'pppd_change',
			'normal',
			'default'
		);
	}

	/**
	 * Print a lines-textarea for an array meta value.
	 *
	 * @param string   $name  Field name/id.
	 * @param string   $label Field label.
	 * @param string[] $value Current values.
	 * @param string   $hint  Description text.
	 * @return void
	 */
	protected function lines_textarea( $name, $label, $value, $hint ) {
		?>
		<p>
			<label for="<?php echo esc_attr( $name ); ?>"><strong><?php echo esc_html( $label ); ?></strong></label><br />
			<textarea name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $name ); ?>" rows="4" class="large-text code"><?php echo esc_textarea( implode( "\n", is_array( $value ) ? $value : array() ) ); ?></textarea>
			<span class="description"><?php echo esc_html( $hint ); ?></span>
		</p>
		<?php
	}

	/**
	 * Parse a one-per-line textarea POST value into a clean string array.
	 *
	 * @param string $key POST key.
	 * @return string[]
	 */
	protected function lines_from_post( $key ) {
		if ( ! isset( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by callers.
			return array();
		}

		$raw   = sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by callers.
		$lines = preg_split( '/\r\n|\r|\n/', $raw );

		return pppd_sanitize_string_array( is_array( $lines ) ? array_map( 'trim', $lines ) : array() );
	}

	// -------------------------------------------------------------------
	// Report.
	// -------------------------------------------------------------------

	/**
	 * Render the Report Settings box.
	 *
	 * @param WP_Post $post Report post.
	 * @return void
	 */
	public function render_report_box( $post ) {
		wp_nonce_field( 'pppd_report_settings', '_pppd_report_settings_nonce' );

		$repo_paths = get_post_meta( $post->ID, '_pppd_repo_paths', true );
		$prefix     = get_post_meta( $post->ID, '_pppd_req_prefix', true );
		$slug       = get_post_meta( $post->ID, '_pppd_project_slug', true );
		$counter    = absint( get_post_meta( $post->ID, '_pppd_req_counter', true ) );

		$this->lines_textarea(
			'pppd_repo_paths',
			__( 'Repository paths', 'pretty-professional-process-docs' ),
			is_array( $repo_paths ) ? $repo_paths : array(),
			__( 'One path per line. Used by drift tooling to know which code this report traces against.', 'pretty-professional-process-docs' )
		);
		?>
		<p>
			<label for="pppd_req_prefix"><strong><?php esc_html_e( 'Requirement ID prefix', 'pretty-professional-process-docs' ); ?></strong></label><br />
			<input type="text" name="pppd_req_prefix" id="pppd_req_prefix" class="regular-text" value="<?php echo esc_attr( is_string( $prefix ) && '' !== $prefix ? $prefix : 'FR' ); ?>" />
			<span class="description"><?php esc_html_e( 'Default: FR. New requirement sections get IDs like FR-001.', 'pretty-professional-process-docs' ); ?></span>
		</p>
		<p>
			<label for="pppd_project_slug"><strong><?php esc_html_e( 'Project slug', 'pretty-professional-process-docs' ); ?></strong></label><br />
			<input type="text" name="pppd_project_slug" id="pppd_project_slug" class="regular-text" value="<?php echo esc_attr( is_string( $slug ) ? $slug : '' ); ?>" />
			<span class="description"><?php esc_html_e( 'Machine-friendly identifier for tooling (lowercase, hyphens).', 'pretty-professional-process-docs' ); ?></span>
		</p>
		<p>
			<strong><?php esc_html_e( 'Requirement counter', 'pretty-professional-process-docs' ); ?></strong>
			<?php
			printf(
				/* translators: %d: current requirement counter. */
				esc_html__( 'Currently at %d. Managed automatically — IDs are never reassigned or reused.', 'pretty-professional-process-docs' ),
				absint( $counter )
			);
			?>
		</p>
		<?php
	}

	/**
	 * Save the Report Settings box.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public function save_report( $post_id, $post ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( ! isset( $_POST['_pppd_report_settings_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_pppd_report_settings_nonce'] ) ), 'pppd_report_settings' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		update_post_meta( $post_id, '_pppd_repo_paths', $this->lines_from_post( 'pppd_repo_paths' ) );

		$prefix = isset( $_POST['pppd_req_prefix'] ) ? sanitize_text_field( wp_unslash( $_POST['pppd_req_prefix'] ) ) : '';
		update_post_meta( $post_id, '_pppd_req_prefix', '' !== $prefix ? $prefix : 'FR' );

		$slug = isset( $_POST['pppd_project_slug'] ) ? sanitize_title( wp_unslash( $_POST['pppd_project_slug'] ) ) : '';
		update_post_meta( $post_id, '_pppd_project_slug', $slug );
	}

	// -------------------------------------------------------------------
	// Section.
	// -------------------------------------------------------------------

	/**
	 * Render the Section Settings box.
	 *
	 * @param WP_Post $post Section post.
	 * @return void
	 */
	public function render_section_box( $post ) {
		wp_nonce_field( 'pppd_section_settings', '_pppd_section_settings_nonce' );

		$report_id  = absint( get_post_meta( $post->ID, '_pppd_report_id', true ) );
		$req_id     = (string) get_post_meta( $post->ID, '_pppd_req_id', true );
		$acceptance = get_post_meta( $post->ID, '_pppd_acceptance', true );
		$dev_prompt = (string) get_post_meta( $post->ID, '_pppd_dev_prompt', true );
		$code_refs  = get_post_meta( $post->ID, '_pppd_code_refs', true );
		$test_refs  = get_post_meta( $post->ID, '_pppd_test_refs', true );

		$reports = get_posts(
			array(
				'post_type'      => 'pppd_report',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		?>
		<p>
			<label for="pppd_report_id"><strong><?php esc_html_e( 'Parent report', 'pretty-professional-process-docs' ); ?></strong></label><br />
			<select name="pppd_report_id" id="pppd_report_id">
				<option value="0"><?php esc_html_e( '— None —', 'pretty-professional-process-docs' ); ?></option>
				<?php foreach ( $reports as $report ) : ?>
					<option value="<?php echo esc_attr( (string) $report->ID ); ?>" <?php selected( $report_id, (int) $report->ID ); ?>><?php echo esc_html( get_the_title( $report ) ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>

		<?php if ( '' !== $req_id ) : ?>
			<p>
				<strong><?php esc_html_e( 'Requirement ID', 'pretty-professional-process-docs' ); ?></strong>
				<code><?php echo esc_html( $req_id ); ?></code>
				<span class="description"><?php esc_html_e( '(assigned automatically; never changes)', 'pretty-professional-process-docs' ); ?></span>
			</p>
		<?php endif; ?>

		<?php
		$this->lines_textarea(
			'pppd_acceptance',
			__( 'Acceptance criteria', 'pretty-professional-process-docs' ),
			is_array( $acceptance ) ? $acceptance : array(),
			__( 'One criterion per line.', 'pretty-professional-process-docs' )
		);
		?>

		<p>
			<label for="pppd_dev_prompt"><strong><?php esc_html_e( 'Developer prompt', 'pretty-professional-process-docs' ); ?></strong></label><br />
			<textarea name="pppd_dev_prompt" id="pppd_dev_prompt" rows="4" class="large-text code"><?php echo esc_textarea( $dev_prompt ); ?></textarea>
			<span class="description"><?php esc_html_e( 'Hand-off prompt for a developer or coding agent implementing this section.', 'pretty-professional-process-docs' ); ?></span>
		</p>

		<?php
		$this->lines_textarea(
			'pppd_code_refs',
			__( 'Code references', 'pretty-professional-process-docs' ),
			is_array( $code_refs ) ? $code_refs : array(),
			__( 'One file/symbol reference per line.', 'pretty-professional-process-docs' )
		);

		$this->lines_textarea(
			'pppd_test_refs',
			__( 'Test references', 'pretty-professional-process-docs' ),
			is_array( $test_refs ) ? $test_refs : array(),
			__( 'One test reference per line.', 'pretty-professional-process-docs' )
		);
	}

	/**
	 * Save the Section Settings box.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public function save_section( $post_id, $post ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( ! isset( $_POST['_pppd_section_settings_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_pppd_section_settings_nonce'] ) ), 'pppd_section_settings' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$report_id = isset( $_POST['pppd_report_id'] ) ? absint( wp_unslash( $_POST['pppd_report_id'] ) ) : 0;
		update_post_meta( $post_id, '_pppd_report_id', $report_id );

		update_post_meta( $post_id, '_pppd_acceptance', $this->lines_from_post( 'pppd_acceptance' ) );

		$dev_prompt = isset( $_POST['pppd_dev_prompt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['pppd_dev_prompt'] ) ) : '';
		update_post_meta( $post_id, '_pppd_dev_prompt', $dev_prompt );

		update_post_meta( $post_id, '_pppd_code_refs', $this->lines_from_post( 'pppd_code_refs' ) );
		update_post_meta( $post_id, '_pppd_test_refs', $this->lines_from_post( 'pppd_test_refs' ) );
	}

	// -------------------------------------------------------------------
	// Change.
	// -------------------------------------------------------------------

	/**
	 * Render the Change Settings box.
	 *
	 * @param WP_Post $post Change post.
	 * @return void
	 */
	public function render_change_box( $post ) {
		wp_nonce_field( 'pppd_change_settings', '_pppd_change_settings_nonce' );

		$target    = absint( get_post_meta( $post->ID, '_pppd_target_section', true ) );
		$source    = (string) get_post_meta( $post->ID, '_pppd_source', true );
		$rationale = (string) get_post_meta( $post->ID, '_pppd_rationale', true );

		$reports = get_posts(
			array(
				'post_type'      => 'pppd_report',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$all_sections = get_posts(
			array(
				'post_type'      => 'pppd_section',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => -1,
				'orderby'        => array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				),
			)
		);

		$by_report = array();

		foreach ( $all_sections as $section ) {
			$rid = absint( get_post_meta( $section->ID, '_pppd_report_id', true ) );

			if ( ! isset( $by_report[ $rid ] ) ) {
				$by_report[ $rid ] = array();
			}

			$by_report[ $rid ][] = $section;
		}
		?>
		<p>
			<label for="pppd_target_section"><strong><?php esc_html_e( 'Target section', 'pretty-professional-process-docs' ); ?></strong></label><br />
			<select name="pppd_target_section" id="pppd_target_section">
				<option value="0"><?php esc_html_e( '— Select a section —', 'pretty-professional-process-docs' ); ?></option>
				<?php foreach ( $reports as $report ) : ?>
					<?php if ( ! empty( $by_report[ $report->ID ] ) ) : ?>
						<optgroup label="<?php echo esc_attr( get_the_title( $report ) ); ?>">
							<?php foreach ( $by_report[ $report->ID ] as $section ) : ?>
								<option value="<?php echo esc_attr( (string) $section->ID ); ?>" <?php selected( $target, (int) $section->ID ); ?>><?php echo esc_html( get_the_title( $section ) ); ?></option>
							<?php endforeach; ?>
						</optgroup>
					<?php endif; ?>
				<?php endforeach; ?>
				<?php if ( ! empty( $by_report[0] ) ) : ?>
					<optgroup label="<?php esc_attr_e( 'Unassigned sections', 'pretty-professional-process-docs' ); ?>">
						<?php foreach ( $by_report[0] as $section ) : ?>
							<option value="<?php echo esc_attr( (string) $section->ID ); ?>" <?php selected( $target, (int) $section->ID ); ?>><?php echo esc_html( get_the_title( $section ) ); ?></option>
						<?php endforeach; ?>
					</optgroup>
				<?php endif; ?>
			</select>
		</p>
		<p>
			<label for="pppd_source"><strong><?php esc_html_e( 'Source', 'pretty-professional-process-docs' ); ?></strong></label><br />
			<input type="text" name="pppd_source" id="pppd_source" class="regular-text" value="<?php echo esc_attr( $source ); ?>" />
			<span class="description"><?php esc_html_e( 'Where this change came from — e.g. a meeting or document identifier.', 'pretty-professional-process-docs' ); ?></span>
		</p>
		<p>
			<label for="pppd_rationale"><strong><?php esc_html_e( 'Rationale', 'pretty-professional-process-docs' ); ?></strong></label><br />
			<textarea name="pppd_rationale" id="pppd_rationale" rows="4" class="large-text"><?php echo esc_textarea( $rationale ); ?></textarea>
		</p>
		<?php
	}

	/**
	 * Save the Change Settings box.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public function save_change( $post_id, $post ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( ! isset( $_POST['_pppd_change_settings_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_pppd_change_settings_nonce'] ) ), 'pppd_change_settings' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$target = isset( $_POST['pppd_target_section'] ) ? absint( wp_unslash( $_POST['pppd_target_section'] ) ) : 0;
		update_post_meta( $post_id, '_pppd_target_section', $target );

		$source = isset( $_POST['pppd_source'] ) ? sanitize_text_field( wp_unslash( $_POST['pppd_source'] ) ) : '';
		update_post_meta( $post_id, '_pppd_source', $source );

		$rationale = isset( $_POST['pppd_rationale'] ) ? sanitize_textarea_field( wp_unslash( $_POST['pppd_rationale'] ) ) : '';
		update_post_meta( $post_id, '_pppd_rationale', $rationale );
	}
}
