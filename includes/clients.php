<?php
/**
 * Client entity: term meta, user membership, per-report assignment, and the
 * shared access-check helper.
 *
 * A client is a pppd_client taxonomy term on pppd_report (registered in
 * post-types.php). Membership ("this user belongs to client X") is user meta;
 * per-report assignment ("this user may view this specific report") is report
 * meta. The two are deliberately separate axes — conflating them would leak
 * access when a user is assigned to a single report of an unrelated client.
 *
 * @package PPPD
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register client term meta, user membership meta, and report assignment meta.
 *
 * Called from PPPD_Plugin::register_objects() after pppd_register_meta().
 *
 * @return void
 */
function pppd_register_client_meta() {
	// Default GitHub repository (owner/name) seeded into new reports for this
	// client. Report-level settings stay authoritative.
	register_term_meta(
		'pppd_client',
		'_pppd_client_repo_default',
		array(
			'type'              => 'string',
			'single'            => true,
			'sanitize_callback' => 'sanitize_text_field',
			'show_in_rest'      => true,
		)
	);

	// Attachment ID of the client's logo (future client-view branding).
	register_term_meta(
		'pppd_client',
		'_pppd_client_logo_id',
		array(
			'type'              => 'integer',
			'single'            => true,
			'sanitize_callback' => 'absint',
			'show_in_rest'      => true,
		)
	);

	// Client membership: pppd_client term IDs the user belongs to.
	register_meta(
		'user',
		'_pppd_client_ids',
		array(
			'type'              => 'array',
			'single'            => true,
			'sanitize_callback' => 'pppd_sanitize_int_array',
			'show_in_rest'      => array(
				'schema' => array(
					'type'  => 'array',
					'items' => array( 'type' => 'integer' ),
				),
			),
			'auth_callback'     => static function () {
				return current_user_can( 'edit_users' );
			},
		)
	);

	// Per-report assignment: user IDs granted access to this specific report
	// regardless of client membership.
	register_post_meta(
		'pppd_report',
		'_pppd_assigned_user_ids',
		array(
			'type'              => 'array',
			'single'            => true,
			'sanitize_callback' => 'pppd_sanitize_int_array',
			'show_in_rest'      => array(
				'schema' => array(
					'type'  => 'array',
					'items' => array( 'type' => 'integer' ),
				),
			),
			'auth_callback'     => 'pppd_meta_auth_callback',
		)
	);
}

/**
 * Sanitize a flat array of positive integers.
 *
 * @param mixed $value Raw meta value.
 * @return int[]
 */
function pppd_sanitize_int_array( $value ) {
	if ( ! is_array( $value ) ) {
		return array();
	}

	$clean = array();

	foreach ( $value as $item ) {
		$item = absint( $item );

		if ( $item > 0 && ! in_array( $item, $clean, true ) ) {
			$clean[] = $item;
		}
	}

	return $clean;
}

/**
 * Hook up client admin UI and lifecycle handlers.
 *
 * @return void
 */
function pppd_clients_init() {
	add_action( 'show_user_profile', 'pppd_render_user_client_field' );
	add_action( 'edit_user_profile', 'pppd_render_user_client_field' );
	add_action( 'personal_options_update', 'pppd_save_user_client_field' );
	add_action( 'edit_user_profile_update', 'pppd_save_user_client_field' );
	add_action( 'delete_pppd_client', 'pppd_cleanup_client_membership' );
}

/**
 * Get the pppd_client term IDs a user belongs to.
 *
 * @param int $user_id User ID.
 * @return int[]
 */
function pppd_get_user_client_ids( $user_id ) {
	$ids = get_user_meta( absint( $user_id ), '_pppd_client_ids', true );

	return pppd_sanitize_int_array( is_array( $ids ) ? $ids : array() );
}

/**
 * Whether a user may view a report.
 *
 * True when the user can edit reports (admin/editor/PM/agent), is a member of
 * the report's client, or is individually assigned to the report. This is the
 * single source of truth the client-facing view, query filters, and REST
 * guards must all route through.
 *
 * @param int $user_id   User ID.
 * @param int $report_id Report post ID.
 * @return bool
 */
function pppd_user_can_view_report( $user_id, $report_id ) {
	$user_id   = absint( $user_id );
	$report_id = absint( $report_id );

	if ( 0 === $user_id || 0 === $report_id ) {
		return false;
	}

	if ( user_can( $user_id, 'edit_pppd_reports' ) ) {
		return true;
	}

	$assigned = get_post_meta( $report_id, '_pppd_assigned_user_ids', true );

	if ( is_array( $assigned ) && in_array( $user_id, array_map( 'absint', $assigned ), true ) ) {
		return true;
	}

	$client_ids = pppd_get_user_client_ids( $user_id );

	if ( empty( $client_ids ) ) {
		return false;
	}

	$report_clients = get_the_terms( $report_id, 'pppd_client' );

	if ( ! is_array( $report_clients ) ) {
		return false;
	}

	foreach ( $report_clients as $term ) {
		if ( in_array( (int) $term->term_id, $client_ids, true ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Render the client-membership checklist on the user profile screen.
 *
 * @param WP_User $user User being edited.
 * @return void
 */
function pppd_render_user_client_field( $user ) {
	if ( ! current_user_can( 'edit_users' ) ) {
		return;
	}

	$clients = get_terms(
		array(
			'taxonomy'   => 'pppd_client',
			'hide_empty' => false,
		)
	);

	if ( is_wp_error( $clients ) || empty( $clients ) ) {
		return;
	}

	$member_of = pppd_get_user_client_ids( $user->ID );

	wp_nonce_field( 'pppd_user_clients', '_pppd_user_clients_nonce' );
	?>
	<h2><?php esc_html_e( 'Process Docs client', 'pretty-professional-process-docs' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Client membership', 'pretty-professional-process-docs' ); ?></th>
			<td>
				<fieldset>
					<legend class="screen-reader-text"><?php esc_html_e( 'Clients this user belongs to', 'pretty-professional-process-docs' ); ?></legend>
					<?php foreach ( $clients as $client ) : ?>
						<label>
							<input type="checkbox" name="pppd_client_ids[]" value="<?php echo esc_attr( (string) $client->term_id ); ?>" <?php checked( in_array( (int) $client->term_id, $member_of, true ) ); ?> />
							<?php echo esc_html( $client->name ); ?>
						</label><br />
					<?php endforeach; ?>
					<p class="description"><?php esc_html_e( 'Members can view every report belonging to the client on the client-facing view.', 'pretty-professional-process-docs' ); ?></p>
				</fieldset>
			</td>
		</tr>
	</table>
	<?php
}

/**
 * Save the client-membership checklist.
 *
 * @param int $user_id User being saved.
 * @return void
 */
function pppd_save_user_client_field( $user_id ) {
	if ( ! current_user_can( 'edit_users' ) ) {
		return;
	}

	if ( ! isset( $_POST['_pppd_user_clients_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_pppd_user_clients_nonce'] ) ), 'pppd_user_clients' ) ) {
		return;
	}

	$raw = isset( $_POST['pppd_client_ids'] ) ? wp_unslash( (array) $_POST['pppd_client_ids'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized below.

	update_user_meta( $user_id, '_pppd_client_ids', pppd_sanitize_int_array( $raw ) );
}

/**
 * Remove a deleted client term from every user's membership meta.
 *
 * Hooked to delete_pppd_client (fires after a pppd_client term is deleted,
 * receiving the term ID).
 *
 * @param int $term_id Deleted term ID.
 * @return void
 */
function pppd_cleanup_client_membership( $term_id ) {
	$term_id = absint( $term_id );

	$users = get_users(
		array(
			'meta_key'     => '_pppd_client_ids', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- uninstall-scale cleanup, runs once per term deletion.
			'meta_compare' => 'EXISTS',
			'fields'       => 'ID',
		)
	);

	foreach ( $users as $user_id ) {
		$ids = pppd_get_user_client_ids( (int) $user_id );

		if ( in_array( $term_id, $ids, true ) ) {
			update_user_meta( (int) $user_id, '_pppd_client_ids', array_values( array_diff( $ids, array( $term_id ) ) ) );
		}
	}
}
