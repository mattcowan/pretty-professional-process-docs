/**
 * Report outline metabox: keyboard-operable section reordering.
 *
 * The server is the single source of truth: every move POSTs to
 * pppd/v1/reports/{id}/reorder and re-renders the list from the returned
 * outline. Announcements go through wp.a11y.speak; focus stays on the moved
 * row's same action button.
 */
( function () {
	'use strict';

	var container = document.querySelector( '[data-pppd-outline]' );

	if ( ! container || ! window.wp || ! window.wp.apiFetch ) {
		return;
	}

	var list = container.querySelector( '[data-pppd-outline-list]' );
	var i18n = ( window.pppdOutline && window.pppdOutline.i18n ) || {};
	var busy = false;

	var MOVES = [
		{ direction: 'up', label: i18n.up || 'Move up', glyph: '↑', flag: 'can_up' },
		{ direction: 'down', label: i18n.down || 'Move down', glyph: '↓', flag: 'can_down' },
		{ direction: 'indent', label: i18n.indent || 'Indent', glyph: '→', flag: 'can_indent' },
		{ direction: 'outdent', label: i18n.outdent || 'Outdent', glyph: '←', flag: 'can_outdent' }
	];

	/**
	 * Build one outline row. Mirrors PPPD_Report_Outline::render_outline_row()
	 * — keep the structures in sync. Uses textContent throughout; no markup is
	 * ever built from data strings.
	 *
	 * @param {Object} entry Outline entry from the REST response.
	 * @return {HTMLElement} List item.
	 */
	function buildRow( entry ) {
		var li = document.createElement( 'li' );
		li.className = 'pppd-outline-row';
		li.dataset.section = String( entry.id );
		li.style.setProperty( '--pppd-depth', String( entry.depth ) );

		var main = document.createElement( 'span' );
		main.className = 'pppd-outline-main';

		var title;
		if ( entry.edit_link ) {
			title = document.createElement( 'a' );
			title.href = entry.edit_link;
		} else {
			title = document.createElement( 'span' );
		}
		title.className = 'pppd-outline-title';
		title.textContent = entry.title || '(no title)';
		main.appendChild( title );

		var typeBadge = document.createElement( 'span' );
		typeBadge.className = 'pppd-badge pppd-badge--type';
		typeBadge.textContent = entry.type;
		main.appendChild( typeBadge );

		if ( entry.req_id ) {
			var req = document.createElement( 'code' );
			req.className = 'pppd-outline-reqid';
			req.textContent = entry.req_id;
			main.appendChild( req );
		}

		if ( entry.post_status && 'publish' !== entry.post_status ) {
			var draft = document.createElement( 'span' );
			draft.className = 'pppd-badge pppd-badge--draft';
			draft.textContent = entry.post_status;
			main.appendChild( draft );
		}

		if ( entry.status ) {
			var status = document.createElement( 'span' );
			status.className = 'pppd-badge pppd-badge--status';
			status.textContent = entry.status;
			main.appendChild( status );
		}

		li.appendChild( main );

		var actions = document.createElement( 'span' );
		actions.className = 'pppd-outline-actions';

		MOVES.forEach( function ( move ) {
			var button = document.createElement( 'button' );
			button.type = 'button';
			button.className = 'button button-small';
			button.dataset.direction = move.direction;
			button.setAttribute( 'aria-label', move.label + ': ' + ( entry.title || '(no title)' ) );
			button.disabled = ! entry[ move.flag ];

			var glyph = document.createElement( 'span' );
			glyph.setAttribute( 'aria-hidden', 'true' );
			glyph.textContent = move.glyph;
			button.appendChild( glyph );

			actions.appendChild( button );
		} );

		li.appendChild( actions );

		return li;
	}

	/**
	 * Re-render the list from a fresh outline, then restore focus to the same
	 * action button of the moved section (falling back to the row's first
	 * enabled button so keyboard users are never dropped).
	 *
	 * @param {Array}  sections  Outline entries.
	 * @param {number} sectionId Moved section ID.
	 * @param {string} direction The action that was performed.
	 */
	function render( sections, sectionId, direction ) {
		list.textContent = '';

		sections.forEach( function ( entry ) {
			list.appendChild( buildRow( entry ) );
		} );

		var row = list.querySelector( '[data-section="' + String( sectionId ) + '"]' );

		if ( ! row ) {
			return;
		}

		var button = row.querySelector( '[data-direction="' + direction + '"]' );

		if ( ! button || button.disabled ) {
			button = row.querySelector( '.pppd-outline-actions button:not([disabled])' );
		}

		if ( button ) {
			button.focus();
		}
	}

	container.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( 'button[data-direction]' );

		if ( ! button || busy ) {
			return;
		}

		var row = button.closest( '[data-section]' );

		if ( ! row ) {
			return;
		}

		var sectionId = parseInt( row.dataset.section, 10 );
		var direction = button.dataset.direction;

		busy = true;
		container.setAttribute( 'aria-busy', 'true' );

		window.wp.apiFetch( {
			url: container.dataset.endpoint,
			method: 'POST',
			data: { section: sectionId, direction: direction }
		} ).then( function ( response ) {
			render( response.sections, sectionId, direction );

			if ( window.wp.a11y && window.wp.a11y.speak ) {
				window.wp.a11y.speak( response.message, 'polite' );
			}
		} ).catch( function ( error ) {
			var message = ( error && error.message ) || i18n.failed || 'The move failed.';

			if ( window.wp.a11y && window.wp.a11y.speak ) {
				window.wp.a11y.speak( message, 'assertive' );
			}

			button.focus();
		} ).finally( function () {
			busy = false;
			container.setAttribute( 'aria-busy', 'false' );
		} );
	} );
} )();
