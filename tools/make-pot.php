<?php
/**
 * make-pot.php — generate languages/pretty-professional-process-docs.pot.
 *
 * `wp i18n make-pot . languages/pretty-professional-process-docs.pot` is the
 * canonical way to do this and should be preferred when WP-CLI is available.
 * This script exists so the POT can be regenerated without it: it tokenizes
 * the plugin's PHP with token_get_all() rather than guessing with regex.
 *
 * Usage: php tools/make-pot.php
 *
 * @package PPPD
 */

declare( strict_types = 1 );

const TEXT_DOMAIN = 'pretty-professional-process-docs';
const PACKAGE     = 'Pretty Professional Process Docs';

/**
 * Translation functions => [ arg index of singular, plural, context, domain ].
 *
 * Indexes are zero-based positions in the argument list. null = not applicable.
 */
const FUNCTIONS = array(
	'__'             => array( 0, null, null, 1 ),
	'_e'             => array( 0, null, null, 1 ),
	'esc_attr__'     => array( 0, null, null, 1 ),
	'esc_attr_e'     => array( 0, null, null, 1 ),
	'esc_html__'     => array( 0, null, null, 1 ),
	'esc_html_e'     => array( 0, null, null, 1 ),
	'_x'             => array( 0, null, 1, 2 ),
	'_ex'            => array( 0, null, 1, 2 ),
	'esc_attr_x'     => array( 0, null, 1, 2 ),
	'esc_html_x'     => array( 0, null, 1, 2 ),
	'_n'             => array( 0, 1, null, 3 ),
	'_nx'            => array( 0, 1, 3, 4 ),
	'_n_noop'        => array( 0, 1, null, 2 ),
	'_nx_noop'       => array( 0, 1, 2, 3 ),
);

$root = dirname( __DIR__ );

/** Directories never scanned. */
$skip_dirs = array( 'vendor', 'node_modules', '.git', 'tools', 'agent-layer', 'languages' );

$files = array();
$iter  = new RecursiveIteratorIterator(
	new RecursiveCallbackFilterIterator(
		new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
		static function ( SplFileInfo $file ) use ( $skip_dirs ): bool {
			if ( $file->isDir() ) {
				return ! in_array( $file->getFilename(), $skip_dirs, true );
			}
			return 'php' === strtolower( $file->getExtension() );
		}
	)
);
foreach ( $iter as $file ) {
	$files[] = $file->getPathname();
}
sort( $files );

$entries = array();

foreach ( $files as $path ) {
	$rel    = str_replace( '\\', '/', substr( $path, strlen( $root ) + 1 ) );
	$tokens = token_get_all( (string) file_get_contents( $path ) );
	$count  = count( $tokens );

	for ( $i = 0; $i < $count; $i++ ) {
		$token = $tokens[ $i ];

		if ( ! is_array( $token ) || T_STRING !== $token[0] || ! isset( FUNCTIONS[ $token[1] ] ) ) {
			continue;
		}

		// Skip method/property access ($obj->__(), Foo::__()) — not the WP functions.
		$prev = prev_significant( $tokens, $i );
		if ( is_array( $prev ) && in_array( $prev[0], array( T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION ), true ) ) {
			continue;
		}

		$open = next_significant_index( $tokens, $i );
		if ( null === $open || '(' !== $tokens[ $open ] ) {
			continue;
		}

		$args = collect_args( $tokens, $open );
		if ( null === $args ) {
			continue;
		}

		list( $sing_i, $plur_i, $ctx_i, $dom_i ) = FUNCTIONS[ $token[1] ];

		// Only our text domain. A non-literal domain arg is skipped, not guessed.
		if ( ! isset( $args[ $dom_i ] ) || TEXT_DOMAIN !== $args[ $dom_i ] ) {
			continue;
		}
		if ( ! isset( $args[ $sing_i ] ) ) {
			continue;
		}

		$msgid  = $args[ $sing_i ];
		$plural = ( null !== $plur_i && isset( $args[ $plur_i ] ) ) ? $args[ $plur_i ] : null;
		$ctx    = ( null !== $ctx_i && isset( $args[ $ctx_i ] ) ) ? $args[ $ctx_i ] : null;

		$key = ( null === $ctx ? '' : $ctx . "\4" ) . $msgid;

		if ( ! isset( $entries[ $key ] ) ) {
			$entries[ $key ] = array(
				'msgid'      => $msgid,
				'plural'     => $plural,
				'context'    => $ctx,
				'refs'       => array(),
				'translator' => null,
			);
		}
		$entries[ $key ]['refs'][] = $rel . ':' . $token[2];

		if ( null === $entries[ $key ]['plural'] && null !== $plural ) {
			$entries[ $key ]['plural'] = $plural;
		}

		$comment = translator_comment( $tokens, $i );
		if ( null !== $comment && null === $entries[ $key ]['translator'] ) {
			$entries[ $key ]['translator'] = $comment;
		}
	}
}

/**
 * Walk back to the previous non-whitespace, non-comment token.
 *
 * @param array $tokens Token list.
 * @param int   $i      Current index.
 * @return array|string|null
 */
function prev_significant( array $tokens, int $i ) {
	for ( $j = $i - 1; $j >= 0; $j-- ) {
		if ( is_array( $tokens[ $j ] ) && in_array( $tokens[ $j ][0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
			continue;
		}
		return $tokens[ $j ];
	}
	return null;
}

/**
 * Index of the next non-whitespace, non-comment token.
 *
 * @param array $tokens Token list.
 * @param int   $i      Current index.
 * @return int|null
 */
function next_significant_index( array $tokens, int $i ): ?int {
	for ( $j = $i + 1; $j < count( $tokens ); $j++ ) {
		if ( is_array( $tokens[ $j ] ) && in_array( $tokens[ $j ][0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
			continue;
		}
		return $j;
	}
	return null;
}

/**
 * Collect a call's top-level arguments, resolving literal strings only.
 *
 * An argument that is not a literal string (or a concatenation of literal
 * strings) resolves to null — the caller decides whether that matters.
 *
 * @param array $tokens Token list.
 * @param int   $open   Index of the opening parenthesis.
 * @return array<int, string|null>|null Null if the call never closes.
 */
function collect_args( array $tokens, int $open ): ?array {
	$depth   = 0;
	$args    = array();
	$current = array();
	$count   = count( $tokens );

	for ( $j = $open; $j < $count; $j++ ) {
		$token = $tokens[ $j ];

		if ( is_string( $token ) ) {
			if ( '(' === $token || '[' === $token ) {
				++$depth;
				if ( 1 === $depth ) {
					continue;
				}
			} elseif ( ')' === $token || ']' === $token ) {
				--$depth;
				if ( 0 === $depth ) {
					$args[] = resolve_arg( $current );
					return $args;
				}
			} elseif ( ',' === $token && 1 === $depth ) {
				$args[]  = resolve_arg( $current );
				$current = array();
				continue;
			}
		}

		if ( $depth >= 1 ) {
			$current[] = $token;
		}
	}

	return null;
}

/**
 * Resolve a single argument's tokens to a literal string, or null.
 *
 * @param array $tokens Tokens making up one argument.
 * @return string|null
 */
function resolve_arg( array $tokens ): ?string {
	$parts = array();

	foreach ( $tokens as $token ) {
		if ( is_array( $token ) ) {
			if ( in_array( $token[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}
			if ( T_CONSTANT_ENCAPSED_STRING === $token[0] ) {
				$parts[] = unquote( $token[1] );
				continue;
			}
			return null;
		}
		if ( '.' === $token ) {
			continue;
		}
		return null;
	}

	return array() === $parts ? null : implode( '', $parts );
}

/**
 * Turn a PHP single- or double-quoted literal into its runtime value.
 *
 * @param string $raw Raw token text, including its quotes.
 * @return string
 */
function unquote( string $raw ): string {
	$quote = $raw[0];
	$body  = substr( $raw, 1, -1 );

	if ( "'" === $quote ) {
		return str_replace( array( "\\'", '\\\\' ), array( "'", '\\' ), $body );
	}

	return str_replace(
		array( '\\"', '\\n', '\\t', '\\r', '\\$', '\\\\' ),
		array( '"', "\n", "\t", "\r", '$', '\\' ),
		$body
	);
}

/**
 * Find a `translators:` comment immediately preceding a call.
 *
 * @param array $tokens Token list.
 * @param int   $i      Index of the function-name token.
 * @return string|null
 */
function translator_comment( array $tokens, int $i ): ?string {
	for ( $j = $i - 1; $j >= 0 && $j >= $i - 12; $j-- ) {
		$token = $tokens[ $j ];
		if ( ! is_array( $token ) ) {
			continue;
		}
		if ( T_WHITESPACE === $token[0] ) {
			continue;
		}
		if ( T_COMMENT === $token[0] || T_DOC_COMMENT === $token[0] ) {
			$text = trim( preg_replace( '#^(/\*+|//|\#)|(\*+/)$#', '', $token[1] ) ?? '' );
			$text = trim( preg_replace( '#^\s*\*\s?#m', '', $text ) ?? '' );
			if ( 0 === stripos( $text, 'translators:' ) ) {
				return preg_replace( '/\s+/', ' ', $text ) ?? $text;
			}
			continue;
		}
		break;
	}
	return null;
}

/**
 * Escape a string for a PO/POT msgid.
 *
 * @param string $value Raw string.
 * @return string
 */
function po_escape( string $value ): string {
	return str_replace(
		array( '\\', '"', "\n", "\t", "\r" ),
		array( '\\\\', '\\"', '\\n', '\\t', '\\r' ),
		$value
	);
}

// Stable output: sort by first source reference, then msgid.
uasort(
	$entries,
	static function ( array $a, array $b ): int {
		return array( $a['refs'][0], $a['msgid'] ) <=> array( $b['refs'][0], $b['msgid'] );
	}
);

$out  = "# Copyright (C) " . gmdate( 'Y' ) . " " . PACKAGE . "\n";
$out .= "# This file is distributed under the GPL-2.0-or-later license.\n";
$out .= "msgid \"\"\nmsgstr \"\"\n";
$out .= '"Project-Id-Version: ' . PACKAGE . "\\n\"\n";
$out .= '"Report-Msgid-Bugs-To: https://github.com/mattcowan/pretty-professional-process-docs/issues\n"' . "\n";
$out .= '"POT-Creation-Date: ' . gmdate( 'Y-m-d H:i' ) . "+0000\\n\"\n";
$out .= "\"MIME-Version: 1.0\\n\"\n";
$out .= "\"Content-Type: text/plain; charset=UTF-8\\n\"\n";
$out .= "\"Content-Transfer-Encoding: 8bit\\n\"\n";
$out .= "\"PO-Revision-Date: YEAR-MO-DA HO:MI+ZONE\\n\"\n";
$out .= "\"Last-Translator: FULL NAME <EMAIL@ADDRESS>\\n\"\n";
$out .= "\"Language-Team: LANGUAGE <LL@li.org>\\n\"\n";
$out .= "\"Plural-Forms: nplurals=2; plural=(n != 1);\\n\"\n";
$out .= '"X-Generator: tools/make-pot.php' . "\\n\"\n";
$out .= '"X-Domain: ' . TEXT_DOMAIN . "\\n\"\n";

foreach ( $entries as $entry ) {
	$out .= "\n";
	if ( null !== $entry['translator'] ) {
		$out .= '#. ' . $entry['translator'] . "\n";
	}
	foreach ( array_unique( $entry['refs'] ) as $ref ) {
		$out .= '#: ' . $ref . "\n";
	}
	if ( null !== $entry['context'] ) {
		$out .= 'msgctxt "' . po_escape( $entry['context'] ) . "\"\n";
	}
	$out .= 'msgid "' . po_escape( $entry['msgid'] ) . "\"\n";
	if ( null !== $entry['plural'] ) {
		$out .= 'msgid_plural "' . po_escape( $entry['plural'] ) . "\"\n";
		$out .= "msgstr[0] \"\"\n";
		$out .= "msgstr[1] \"\"\n";
	} else {
		$out .= "msgstr \"\"\n";
	}
}

$target = $root . '/languages/' . TEXT_DOMAIN . '.pot';
if ( ! is_dir( dirname( $target ) ) ) {
	mkdir( dirname( $target ), 0755, true );
}
file_put_contents( $target, $out );

printf(
	"Wrote %s — %d strings from %d files.%s",
	'languages/' . TEXT_DOMAIN . '.pot',
	count( $entries ),
	count( $files ),
	PHP_EOL
);
