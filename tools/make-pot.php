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

/** Directories never scanned. Keep tools/README.md's list in step with this. */
$skip_dirs = array( 'vendor', 'node_modules', '.git', 'tools', 'agent-layer', 'languages', 'tests' );

/**
 * Anything that would make the POT quietly wrong.
 *
 * Collected rather than thrown so one run reports every problem, then exits
 * non-zero. A silent success here means a stale POT ships.
 *
 * @var string[]
 */
$problems = array();

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
	$rel = str_replace( '\\', '/', substr( $path, strlen( $root ) + 1 ) );

	$source = file_get_contents( $path );
	if ( false === $source ) {
		// Never cast this to string. `(string) false` is '', which tokenizes to
		// zero strings — indistinguishable from "this file had nothing to
		// translate", while the file still counts toward the summary total.
		$problems[] = "could not read {$rel} — its strings are missing from this POT";
		continue;
	}

	$tokens = token_get_all( $source );
	$count  = count( $tokens );

	// Translator comments are attached by LINE, not by walking back through
	// tokens. A backward token walk breaks on the first T_STRING or
	// T_DOUBLE_ARROW, which drops every comment sitting above a wrapped call
	// (`esc_html( _n( … ) )`, `sprintf( __( … ) )`) or an array value
	// (`'label_count' => _n_noop( … )`) — i.e. exactly the annotated cases.
	$comments_by_line = array();
	foreach ( $tokens as $token ) {
		if ( ! is_array( $token ) || ! in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
			continue;
		}
		$text = trim( preg_replace( '#^(/\*+|//|\#)|(\*+/)$#', '', $token[1] ) ?? '' );
		$text = trim( preg_replace( '#^\s*\*\s?#m', '', $text ) ?? '' );
		if ( 0 !== stripos( $text, 'translators:' ) ) {
			continue;
		}
		// Key on the comment's LAST line, so a multi-line comment attaches to
		// the call that follows it rather than the one that follows its start.
		$last_line = $token[2] + substr_count( $token[1], "\n" );
		$comments_by_line[ $last_line ] = preg_replace( '/\s+/', ' ', $text ) ?? $text;
	}
	$claimed_comments = array();

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
			$problems[] = "{$rel}:{$token[2]}: unterminated call to {$token[1]}() — skipped";
			continue;
		}

		list( $sing_i, $plur_i, $ctx_i, $dom_i ) = FUNCTIONS[ $token[1] ];

		// The domain argument decides whether this string is ours. A missing or
		// non-literal domain is reported, never guessed at — silently skipping
		// it is how a string goes missing from the POT with no diff to explain it.
		if ( ! array_key_exists( $dom_i, $args ) ) {
			$problems[] = "{$rel}:{$token[2]}: {$token[1]}() has no text-domain argument — skipped";
			continue;
		}
		if ( null === $args[ $dom_i ] ) {
			$problems[] = "{$rel}:{$token[2]}: {$token[1]}() has a non-literal text domain — skipped";
			continue;
		}
		if ( TEXT_DOMAIN !== $args[ $dom_i ] ) {
			continue; // Another plugin's/core's string. Correct to ignore, silently.
		}
		if ( ! isset( $args[ $sing_i ] ) ) {
			$problems[] = "{$rel}:{$token[2]}: {$token[1]}() msgid is not a literal string — skipped";
			continue;
		}
		if ( null !== $plur_i && ! isset( $args[ $plur_i ] ) ) {
			$problems[] = "{$rel}:{$token[2]}: {$token[1]}() plural form is not a literal string";
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

		$comment = translator_comment( $comments_by_line, $token[2], $claimed_comments );
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

	// Must be a single left-to-right pass. Sequential str_replace() is wrong
	// here: replacing '\n' before '\\' lets the second backslash of an escaped
	// pair be eaten as the start of a new escape, so "C:\\temp" decodes to
	// "C:\<TAB>emp".
	if ( "'" === $quote ) {
		return preg_replace_callback(
			'/\\\\([\\\\\'])/',
			static function ( array $m ): string {
				return $m[1];
			},
			$body
		) ?? $body;
	}

	$map = array(
		'n'  => "\n",
		't'  => "\t",
		'r'  => "\r",
		'v'  => "\v",
		'f'  => "\f",
		'e'  => "\e",
		'"'  => '"',
		'$'  => '$',
		'\\' => '\\',
	);

	return preg_replace_callback(
		'/\\\\(x[0-9A-Fa-f]{1,2}|u\{[0-9A-Fa-f]+\}|[0-7]{1,3}|.)/s',
		static function ( array $m ) use ( $map ): string {
			$seq = $m[1];
			if ( isset( $map[ $seq ] ) ) {
				return $map[ $seq ];
			}
			if ( 'x' === $seq[0] ) {
				return chr( (int) hexdec( substr( $seq, 1 ) ) );
			}
			if ( 'u' === $seq[0] ) {
				return utf8_codepoint( (int) hexdec( trim( substr( $seq, 1 ), '{}' ) ) );
			}
			if ( 1 === preg_match( '/^[0-7]{1,3}$/', $seq ) ) {
				return chr( (int) octdec( $seq ) );
			}
			// Not a recognised escape: PHP keeps the backslash verbatim.
			return '\\' . $seq;
		},
		$body
	) ?? $body;
}

/**
 * Claim the `translators:` comment attached to a call, by line.
 *
 * gettext's convention: the comment sits on the line above the call, or on the
 * same line. A small look-back window covers a call whose own line is a
 * continuation. Each comment is claimed once, so one annotation can't smear
 * across several unrelated strings.
 *
 * @param array<int, string> $comments_by_line Comment text keyed by its last line.
 * @param int                $line             Line of the function-name token.
 * @param array<int, bool>   $claimed          Lines already consumed, by reference.
 * @return string|null
 */
function translator_comment( array $comments_by_line, int $line, array &$claimed ): ?string {
	for ( $l = $line; $l >= $line - 3; $l-- ) {
		if ( isset( $comments_by_line[ $l ] ) && ! isset( $claimed[ $l ] ) ) {
			$claimed[ $l ] = true;
			return $comments_by_line[ $l ];
		}
	}
	return null;
}

/**
 * Encode a Unicode code point as UTF-8, without mbstring.
 *
 * mb_chr() would be the obvious call, but mbstring is not a guaranteed
 * extension and this script has to run wherever someone checks the repo out —
 * a missing extension must not turn a `\u{…}` escape into a fatal.
 *
 * @param int $cp Code point.
 * @return string UTF-8 bytes, or '' for an out-of-range value.
 */
function utf8_codepoint( int $cp ): string {
	if ( $cp < 0 || $cp > 0x10FFFF || ( $cp >= 0xD800 && $cp <= 0xDFFF ) ) {
		return ''; // Out of range or a surrogate half — not encodable.
	}
	if ( $cp < 0x80 ) {
		return chr( $cp );
	}
	if ( $cp < 0x800 ) {
		return chr( 0xC0 | ( $cp >> 6 ) ) . chr( 0x80 | ( $cp & 0x3F ) );
	}
	if ( $cp < 0x10000 ) {
		return chr( 0xE0 | ( $cp >> 12 ) )
			. chr( 0x80 | ( ( $cp >> 6 ) & 0x3F ) )
			. chr( 0x80 | ( $cp & 0x3F ) );
	}
	return chr( 0xF0 | ( $cp >> 18 ) )
		. chr( 0x80 | ( ( $cp >> 12 ) & 0x3F ) )
		. chr( 0x80 | ( ( $cp >> 6 ) & 0x3F ) )
		. chr( 0x80 | ( $cp & 0x3F ) );
}

/**
 * Does this msgid carry printf placeholders?
 *
 * Drives the `#, php-format` flag, which is what lets `msgfmt --check-format`
 * reject a translation that drops or reorders a placeholder. Without the flag
 * a bad translation compiles clean and blows up at runtime in printf().
 *
 * @param string $msgid The string.
 * @return bool
 */
function is_php_format( string $msgid ): bool {
	// A real conversion spec, ignoring the literal '%%'.
	return 1 === preg_match(
		'/(?<!%)%(?:\d+\$)?[-+ 0]*(?:\d+|\*)?(?:\.\d+)?[bcdeEfFgGosuxX]/',
		str_replace( '%%', '', $msgid )
	);
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
	if ( is_php_format( $entry['msgid'] ) || ( null !== $entry['plural'] && is_php_format( $entry['plural'] ) ) ) {
		$out .= "#, php-format\n";
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
$rel_target = 'languages/' . TEXT_DOMAIN . '.pot';

if ( ! is_dir( dirname( $target ) ) && ! mkdir( dirname( $target ), 0755, true ) && ! is_dir( dirname( $target ) ) ) {
	fwrite( STDERR, "ERROR: could not create " . dirname( $rel_target ) . "/\n" );
	exit( 1 );
}

// Report the write by its actual result. A read-only directory, a file locked
// by an editor, or a full disk all make this return false — announcing success
// anyway is how a CI step ships a stale POT on a green exit code.
$written = file_put_contents( $target, $out );
if ( false === $written ) {
	fwrite( STDERR, "ERROR: could not write {$rel_target} (permissions? file locked? disk full?)\n" );
	exit( 1 );
}
if ( strlen( $out ) !== $written ) {
	fwrite( STDERR, sprintf( "ERROR: short write to %s — %d of %d bytes.\n", $rel_target, $written, strlen( $out ) ) );
	exit( 1 );
}

printf(
	"Wrote %s — %d strings from %d files.%s",
	$rel_target,
	count( $entries ),
	count( $files ),
	PHP_EOL
);

if ( array() !== $problems ) {
	fwrite( STDERR, sprintf( "%s%d problem(s) — the POT above is INCOMPLETE:%s", PHP_EOL, count( $problems ), PHP_EOL ) );
	foreach ( $problems as $problem ) {
		fwrite( STDERR, "  - {$problem}\n" );
	}
	exit( 1 );
}
