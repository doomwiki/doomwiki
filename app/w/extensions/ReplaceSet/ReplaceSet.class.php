<?php
/**
 * ReplaceSet class for ReplaceSet extension
 *
 * @file
 * @ingroup ReplaceSet
 * @author Daniel Friesen (http://danf.ca/mw/)
 * @copyright Copyright © 2009-2012 – Daniel Friesen
 * @license https://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

if ( !defined( 'MEDIAWIKI' ) ) {
	die( "This is an extension to the MediaWiki package and cannot be run standalone." );
}

class ReplaceSet {
	public static function parserFunctionObj(  $parser, $frame, $args ) {
		global $egReplaceSetCallLimit, $egReplaceSetPregLimit;
		static $called = 0;
		$called++;
		if ( $called > $egReplaceSetCallLimit ) {
			return self::error( 'replaceset-error-calllimit', $egReplaceSetCallLimit );
		}
		// Set basic statics
		static $regexStarts = '/!#([{';
		static $regexEnds   = '/!#)]}';
		static $regexModifiers = 'imsxADU';
		// Grab our args
		$string = isset( $args[0] ) ? trim( $frame->expand( $args[0] ) ) : '';
		array_shift( $args ); // Shift off the String

		// Create our list of replacements
		$replacements = array();
		$withs = array();
		foreach ( $args as $arg ) {
			$argArr = $arg->splitArg();
			if ( $argArr['index'] ) {
				continue;
			}
			$replace = $parser->mStripState->unstripNoWiki( trim( $frame->expand( $argArr['name'] ) ) );
			$with = trim( $frame->expand( $argArr['value'] ) );
			if ( false !== $delimPos = strpos( $regexStarts, $replace[0] ) ) {
				// Is Regex Replace
				$start = $replace[0];
				$end = $regexEnds[$delimPos];

				$pos = 1;
				$endPos = null;
				while ( !isset( $endPos ) ) {
					$pos = strpos( $replace, $end, $pos );
					if ( $pos === false ) return self::error( 'replaceset-error-regexnoend', $replace, $end );
					$backslashes = 0;
					for ( $l = $pos - 1; $l >= 0; $l-- ) {
						if ( $replace[$l] == '\\' ) $backslashes++;
						else break;
					}
					if ( $backslashes % 2 == 0 ) $endPos = $pos;
					$pos++;
				}
				$startRegex = (string)substr( $replace, 0, $endPos ) . $end;
				$endRegex = (string)substr( $replace, $endPos + 1 );
				$len = strlen( $endRegex );
				for ( $c = 0; $c < $len; $c++ ) {
					if ( strpos( $regexModifiers, $endRegex[$c] ) === false )
						return self::error( 'replaceset-error-regexbadmodifier', $endRegex[$c] );
				}
				$finalRegex = $startRegex . $endRegex . 'u';

				$replacements[] = $finalRegex;
				$withs[] = $with;
			} else {
				// Is String Replace
				$replacements[] = '/' . preg_quote( $replace, '/' ) . '/';
				$withs[] = str_replace( '\\', '\\\\', $with );
			}
		}
		return preg_replace( $replacements, $withs, $string, $egReplaceSetPregLimit );
	}

	static function error( $msg /*, ... */ ) {
		$args = func_get_args();
		return '<strong class="error">' . wfMessage( $args )->inContentLanguage()->escaped() . '</strong>';
	}
}
