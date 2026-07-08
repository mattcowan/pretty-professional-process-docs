<?php
/**
 * Front-end comment support for report sections.
 *
 * Sections are not public post objects, so comments_open() would normally
 * refuse them; we allow comments for logged-in users only, verify a real
 * nonce on submission, and redirect commenters back to the report anchor.
 *
 * @package PPPD
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the comment hooks.
 *
 * @return void
 */
function pppd_comments_init() {
	add_filter( 'comments_open', 'pppd_section_comments_open', 10, 2 );
	add_filter( 'comment_post_redirect', 'pppd_comment_redirect', 10, 2 );
	add_action( 'pre_comment_on_post', 'pppd_verify_comment_nonce' );
	add_filter( 'get_comment_link', 'pppd_section_comment_link', 10, 2 );
}

/**
 * Allow comments on pppd_section for logged-in users only.
 *
 * @param bool $open    Whether comments are open.
 * @param int  $post_id Post ID.
 * @return bool
 */
function pppd_section_comments_open( $open, $post_id ) {
	$post = get_post( $post_id );

	if ( $post instanceof WP_Post && 'pppd_section' === $post->post_type ) {
		return is_user_logged_in();
	}

	return $open;
}

/**
 * Verify the section comment nonce before core processes the comment.
 *
 * @param int $post_id Post being commented on.
 * @return void
 */
function pppd_verify_comment_nonce( $post_id ) {
	$post = get_post( $post_id );

	if ( ! $post instanceof WP_Post || 'pppd_section' !== $post->post_type ) {
		return;
	}

	$nonce = isset( $_POST['_pppd_comment_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_pppd_comment_nonce'] ) ) : '';

	if ( ! wp_verify_nonce( $nonce, 'pppd_comment_' . $post->ID ) ) {
		wp_die(
			esc_html__( 'Security check failed. Please go back and try again.', 'pretty-professional-process-docs' ),
			esc_html__( 'Comment blocked', 'pretty-professional-process-docs' ),
			array( 'response' => 403 )
		);
	}
}

/**
 * After posting on a section, return the user to the report page anchor.
 *
 * @param string     $location Redirect URL.
 * @param WP_Comment $comment  The new comment.
 * @return string
 */
function pppd_comment_redirect( $location, $comment ) {
	$post = get_post( (int) $comment->comment_post_ID );

	if ( ! $post instanceof WP_Post || 'pppd_section' !== $post->post_type ) {
		return $location;
	}

	$report_id = absint( get_post_meta( $post->ID, '_pppd_report_id', true ) );
	$permalink = ( $report_id > 0 ) ? get_permalink( $report_id ) : false;

	if ( false === $permalink ) {
		return $location;
	}

	return $permalink . '#' . $post->post_name;
}

/**
 * Section permalinks are not publicly viewable, so core's comment permalink
 * would 404. Point comment links at the report page's section anchor instead.
 *
 * @param string     $link    Comment permalink.
 * @param WP_Comment $comment The comment.
 * @return string
 */
function pppd_section_comment_link( $link, $comment ) {
	$post = get_post( (int) $comment->comment_post_ID );

	if ( ! $post instanceof WP_Post || 'pppd_section' !== $post->post_type ) {
		return $link;
	}

	$report_id = absint( get_post_meta( $post->ID, '_pppd_report_id', true ) );
	$permalink = ( $report_id > 0 ) ? get_permalink( $report_id ) : false;

	if ( false === $permalink ) {
		return $link;
	}

	return $permalink . '#comment-' . (int) $comment->comment_ID;
}
