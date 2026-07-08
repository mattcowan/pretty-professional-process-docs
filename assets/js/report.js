/**
 * Pretty Professional Process Docs — report view enhancements.
 *
 * Vanilla JS, no dependencies. Everything here is progressive enhancement:
 * the report is fully usable with JavaScript disabled.
 *
 * 1. [data-pppd-print] buttons trigger window.print(). This is only the
 *    FALLBACK offered when no exporter-produced tagged PDF is available:
 *    browser print does not reliably produce a tagged/navigable PDF. The
 *    real accessible download is the "Download accessible PDF" link, which
 *    points at the export-pdf.mjs output stored in `_pppd_pdf_url`.
 * 2. The TOC highlights the section currently in view by setting
 *    aria-current="true" on the matching link via IntersectionObserver.
 *    Smooth scrolling is CSS-driven (html { scroll-behavior }) and is
 *    already disabled by the prefers-reduced-motion media query in
 *    report.css, so reduced-motion users get instant jumps.
 */
( function () {
	'use strict';

	// 1. Print button.
	document.addEventListener( 'click', function ( event ) {
		if ( event.target.closest( '[data-pppd-print]' ) ) {
			window.print();
		}
	} );

	// 2. TOC current-section highlight.
	var tocLinks = document.querySelectorAll( '.report-toc a[href^="#"]' );

	if ( ! tocLinks.length || ! ( 'IntersectionObserver' in window ) ) {
		return;
	}

	var linksById = {};

	tocLinks.forEach( function ( link ) {
		var id = decodeURIComponent( link.getAttribute( 'href' ).slice( 1 ) );

		if ( id ) {
			linksById[ id ] = link;
		}
	} );

	var setCurrent = function ( id ) {
		if ( ! linksById[ id ] ) {
			return;
		}

		tocLinks.forEach( function ( link ) {
			link.removeAttribute( 'aria-current' );
		} );

		linksById[ id ].setAttribute( 'aria-current', 'true' );
	};

	var observer = new IntersectionObserver(
		function ( entries ) {
			entries.forEach( function ( entry ) {
				if ( entry.isIntersecting ) {
					setCurrent( entry.target.id );
				}
			} );
		},
		{
			// Treat the top band of the viewport as "current".
			rootMargin: '0px 0px -70% 0px',
			threshold: 0,
		}
	);

	Object.keys( linksById ).forEach( function ( id ) {
		var target = document.getElementById( id );

		if ( target ) {
			observer.observe( target );
		}
	} );
} )();
