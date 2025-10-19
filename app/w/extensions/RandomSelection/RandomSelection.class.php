<?php
/**
 * RandomSelection -- randomly displays one of the given options.
 * Usage: <choose><option>A</option><option>B</option></choose>
 * Optional parameter: <option weight="3"> == 3x weight given
 *
 * @file
 * @ingroup Extensions
 * @version 2.2.2
 * @date 24 July 2017
 * @author Ross McClure <http://www.mediawiki.org/wiki/User:Algorithm>
 * @link https://www.mediawiki.org/wiki/Extension:RandomSelection Documentation
 */
class RandomSelection {
	/**
	 * Register the <choose> tag and {{#choose:option 1|...|option N}} function
	 * with the Parser.
	 *
	 * @param Parser $parser
	 * @return bool
	 */
	public static function register( &$parser ) {
		$parser->setHook( 'choose', array( __CLASS__, 'render' ) );
		$parser->setFunctionHook( 'choose', array( __CLASS__, 'renderParserFunction' ), Parser::SFH_OBJECT_ARGS );
		return true;
	}

	/**
	 * Register the magic word ID.
	 *
	 * @param array $variableIds
	 * @return bool
	 */
	public static function variableIds( &$variableIds ) {
		$variableIds[] = 'choose';
		return true;
	}

	/**
	 * Callback for register() which actually does all the processing.
	 *
	 * @param string $input User-supplied input
	 * @param array $argv User-supplied arguments to the tag, e.g. <choose uncached>...</choose>
	 * @param Parser $parser
	 */
	public static function render( $input, $argv, $parser ) {
		# Prevent caching if specified so by the user
		if ( isset( $argv['uncached'] ) ) {
			$parser->disableCache();
		}

		# Parse the options and calculate total weight
		$len = preg_match_all(
			"/<option(?:(?:\\s[^>]*?)?\\sweight=[\"']?([^\\s>]+))?"
				. "(?:\\s[^>]*)?>([\\s\\S]*?)<\\/option>/",
			$input,
			$out
		);

		# Find any references to a surrounding template
		preg_match_all(
			"/<choicetemplate(?:(?:\\s[^>]*?)?\\sweight=[\"']?([^\\s>]+))?"
				. "(?:\\s[^>]*)?>([\\s\\S]*?)<\\/choicetemplate>/",
			$input,
			$outTemplate
		);

		$r = 0;
		for ( $i = 0; $i < $len; $i++ ) {
			if ( strlen( $out[1][$i] ) == 0 ) {
				$out[1][$i] = 1;
			} else {
				$out[1][$i] = intval( $out[1][$i] );
			}
			$r += $out[1][$i];
		}

		# Choose an option at random
		if ( $r <= 0 ) {
			return '';
		}
		$r = mt_rand( 1, $r );
		for ( $i = 0; $i < $len; $i++ ) {
			$r -= $out[1][$i];
			if ( $r <= 0 ) {
				$input = $out[2][$i];
				break;
			}
		}

		# Surround by template if applicable
		if ( isset( $outTemplate[2][0] ) ) {
			$input = '{{' . $outTemplate[2][0] . '|' . $input . '}}';
		}

		# Parse tags and return
		if ( isset( $argv['before'] ) ) {
			$input = $argv['before'] . $input;
		}
		if ( isset( $argv['after'] ) ) {
			$input .= $argv['after'];
		}

		return $parser->recursiveTagParse( $input );
	}

	/**
	 * Callback for the {{#choose:}} magic word magic (see register() in this file)
	 *
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @param array $args User-supplied arguments
	 */
	public static function renderParserFunction( &$parser, $frame, $args ) {
		$options = array();
		$r = 0;

		// First one is not an object
		$arg = array_shift( $args );
		$parts = explode( '=', $arg, 2 );
		if ( count( $parts ) == 2 ) {
			$options[] = array( intval( trim( $parts[0] ) ), $parts[1] );
			$r += intval( trim( $parts[0] ) );
		} elseif ( count( $parts ) == 1 ) {
			$options[] = array( 1, $parts[0] );
			$r += 1;
		}

		foreach ( $args as $arg ) {
			$bits = $arg->splitArg();
			$nameNode = $bits['name'];
			$index = $bits['index'];
			$valueNode = $bits['value'];
			if ( $index === '' ) {
				$name = trim( $frame->expand( $nameNode ) );
				$options[] = array( intval( $name ), $valueNode );
				$r += intval( $name );
			} else {
				$options[] = array( 1, $valueNode );
				$r += 1;
			}
		}

		# Choose an option at random
		if ( $r <= 0 ) {
			return '';
		}
		$r = mt_rand( 1, $r );
		for ( $i = 0; $i < count( $options ); $i++ ) {
			$r -= $options[$i][0];
			if ( $r <= 0 ) {
				$output = $options[$i][1];
				break;
			}
		}

		return trim( $frame->expand( $output ) );
	}

}