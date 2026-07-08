<?php
/**
 * Admin Review Queue for pending proposed changes.
 *
 * @package PPPD
 */

defined( 'ABSPATH' ) || exit;

/**
 * Review Queue page + admin-post approve/reject handlers.
 *
 * Approve/reject delegate to includes/change-actions.php — the same code
 * paths the REST controller uses.
 */
class PPPD_Review_Queue {

	/**
	 * Menu page slug.
	 *
	 * @var string
	 */
	const PAGE_SLUG = 'pppd-review-queue';

	/**
	 * Hook everything up.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_post_pppd_approve_change', array( $this, 'handle_approve' ) );
		add_action( 'admin_post_pppd_reject_change', array( $this, 'handle_reject' ) );
		add_action( 'admin_notices', array( $this, 'render_notices' ) );
	}

	/**
	 * Register the submenu page.
	 *
	 * @return void
	 */
	public function add_menu_page() {
		add_submenu_page(
			'edit.php?post_type=pppd_report',
			__( 'Review Queue', 'pretty-professional-process-docs' ),
			__( 'Review Queue', 'pretty-professional-process-docs' ),
			'pppd_approve_changes',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * URL of the Review Queue page.
	 *
	 * @return string
	 */
	protected function page_url() {
		return add_query_arg(
			array(
				'post_type' => 'pppd_report',
				'page'      => self::PAGE_SLUG,
			),
			admin_url( 'edit.php' )
		);
	}

	/**
	 * Render the queue.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'pppd_approve_changes' ) ) {
			wp_die( esc_html__( 'You are not allowed to review proposed changes.', 'pretty-professional-process-docs' ) );
		}

		$changes = get_posts(
			array(
				'post_type'      => 'pppd_change',
				'post_status'    => 'pending',
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'ASC',
			)
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Review Queue', 'pretty-professional-process-docs' ); ?></h1>
			<p><?php esc_html_e( 'Pending proposed changes awaiting human review. Approving copies the proposed content onto the target section (a revision is kept).', 'pretty-professional-process-docs' ); ?></p>

			<?php if ( empty( $changes ) ) : ?>
				<p><strong><?php esc_html_e( 'The queue is empty — nothing awaiting review.', 'pretty-professional-process-docs' ); ?></strong></p>
			<?php endif; ?>

			<?php foreach ( $changes as $change ) : ?>
				<?php
				$section_id = absint( get_post_meta( $change->ID, '_pppd_target_section', true ) );
				$section    = ( $section_id > 0 ) ? get_post( $section_id ) : null;
				$is_section = $section instanceof WP_Post && 'pppd_section' === $section->post_type;
				$source     = (string) get_post_meta( $change->ID, '_pppd_source', true );
				$rationale  = (string) get_post_meta( $change->ID, '_pppd_rationale', true );
				$author     = get_the_author_meta( 'display_name', (int) $change->post_author );

				$diff = '';
				if ( $is_section ) {
					$diff = wp_text_diff(
						$section->post_content,
						$change->post_content,
						array(
							'title_left'  => __( 'Current', 'pretty-professional-process-docs' ),
							'title_right' => __( 'Proposed', 'pretty-professional-process-docs' ),
						)
					);
				}
				?>
				<div class="pppd-change-card" style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:1em 1.5em;margin:1em 0;">
					<h2><?php echo esc_html( get_the_title( $change ) ); ?></h2>
					<p>
						<?php
						printf(
							/* translators: 1: author name, 2: date. */
							esc_html__( 'Proposed by %1$s on %2$s.', 'pretty-professional-process-docs' ),
							esc_html( $author ),
							esc_html( get_the_date( '', $change ) )
						);
						?>
					</p>
					<ul>
						<?php if ( '' !== $source ) : ?>
							<li><strong><?php esc_html_e( 'Source:', 'pretty-professional-process-docs' ); ?></strong> <?php echo esc_html( $source ); ?></li>
						<?php endif; ?>
						<?php if ( '' !== $rationale ) : ?>
							<li><strong><?php esc_html_e( 'Rationale:', 'pretty-professional-process-docs' ); ?></strong> <?php echo esc_html( $rationale ); ?></li>
						<?php endif; ?>
						<li>
							<strong><?php esc_html_e( 'Target section:', 'pretty-professional-process-docs' ); ?></strong>
							<?php if ( $is_section ) : ?>
								<a href="<?php echo esc_url( (string) get_edit_post_link( $section->ID ) ); ?>"><?php echo esc_html( get_the_title( $section ) ); ?></a>
							<?php else : ?>
								<em><?php esc_html_e( 'Missing or invalid — this change cannot be approved.', 'pretty-professional-process-docs' ); ?></em>
							<?php endif; ?>
						</li>
					</ul>

					<?php if ( '' !== $diff ) : ?>
						<?php echo wp_kses_post( $diff ); ?>
					<?php elseif ( $is_section ) : ?>
						<p><em><?php esc_html_e( 'No textual difference between current and proposed content.', 'pretty-professional-process-docs' ); ?></em></p>
					<?php endif; ?>

					<div style="display:flex;gap:1em;align-items:flex-end;flex-wrap:wrap;">
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="pppd_approve_change" />
							<input type="hidden" name="change_id" value="<?php echo esc_attr( (string) $change->ID ); ?>" />
							<?php wp_nonce_field( 'pppd_change_action_' . $change->ID ); ?>
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Approve', 'pretty-professional-process-docs' ); ?></button>
						</form>

						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="pppd_reject_change" />
							<input type="hidden" name="change_id" value="<?php echo esc_attr( (string) $change->ID ); ?>" />
							<?php wp_nonce_field( 'pppd_change_action_' . $change->ID ); ?>
							<label for="pppd-reject-reason-<?php echo esc_attr( (string) $change->ID ); ?>"><?php esc_html_e( 'Reason (optional):', 'pretty-professional-process-docs' ); ?></label>
							<input type="text" name="reason" id="pppd-reject-reason-<?php echo esc_attr( (string) $change->ID ); ?>" class="regular-text" />
							<button type="submit" class="button"><?php esc_html_e( 'Reject', 'pretty-professional-process-docs' ); ?></button>
						</form>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * admin-post handler: approve.
	 *
	 * @return void
	 */
	public function handle_approve() {
		$change_id = isset( $_POST['change_id'] ) ? absint( wp_unslash( $_POST['change_id'] ) ) : 0;

		check_admin_referer( 'pppd_change_action_' . $change_id );

		if ( ! current_user_can( 'pppd_approve_changes' ) ) {
			wp_die( esc_html__( 'You are not allowed to review proposed changes.', 'pretty-professional-process-docs' ) );
		}

		$result = pppd_approve_change( $change_id );

		$this->redirect_with_notice( is_wp_error( $result ) ? 'error' : 'approved', $result );
	}

	/**
	 * admin-post handler: reject.
	 *
	 * @return void
	 */
	public function handle_reject() {
		$change_id = isset( $_POST['change_id'] ) ? absint( wp_unslash( $_POST['change_id'] ) ) : 0;

		check_admin_referer( 'pppd_change_action_' . $change_id );

		if ( ! current_user_can( 'pppd_approve_changes' ) ) {
			wp_die( esc_html__( 'You are not allowed to review proposed changes.', 'pretty-professional-process-docs' ) );
		}

		$reason = isset( $_POST['reason'] ) ? sanitize_text_field( wp_unslash( $_POST['reason'] ) ) : '';
		$result = pppd_reject_change( $change_id, $reason );

		$this->redirect_with_notice( is_wp_error( $result ) ? 'error' : 'rejected', $result );
	}

	/**
	 * Redirect back to the queue with a notice code.
	 *
	 * @param string               $notice Notice slug: approved|rejected|error.
	 * @param array|WP_Error|mixed $result Action result.
	 * @return void
	 */
	protected function redirect_with_notice( $notice, $result ) {
		$url = add_query_arg( 'pppd_notice', rawurlencode( $notice ), $this->page_url() );

		if ( is_wp_error( $result ) ) {
			$url = add_query_arg( 'pppd_message', rawurlencode( $result->get_error_message() ), $url );
		}

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Render admin notices on the Review Queue page.
	 *
	 * @return void
	 */
	public function render_notices() {
		// Read-only display of a redirect flag; no state change, so no nonce.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$page   = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$notice = isset( $_GET['pppd_notice'] ) ? sanitize_key( wp_unslash( $_GET['pppd_notice'] ) ) : '';
		$detail = isset( $_GET['pppd_message'] ) ? sanitize_text_field( wp_unslash( $_GET['pppd_message'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( self::PAGE_SLUG !== $page || '' === $notice ) {
			return;
		}

		if ( 'approved' === $notice ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html__( 'Change approved and applied to the target section.', 'pretty-professional-process-docs' )
			);
		} elseif ( 'rejected' === $notice ) {
			printf(
				'<div class="notice notice-info is-dismissible"><p>%s</p></div>',
				esc_html__( 'Change rejected.', 'pretty-professional-process-docs' )
			);
		} elseif ( 'error' === $notice ) {
			printf(
				'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
				'' !== $detail
					? esc_html( $detail )
					: esc_html__( 'The action could not be completed.', 'pretty-professional-process-docs' )
			);
		}
	}
}
