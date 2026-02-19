<?php
/**
 * Description2 – Adds meaningful description <meta> tag to MW pages and into the parser output
 *
 * @file
 * @ingroup Extensions
 * @author Daniel Friesen (http://danf.ca/mw/)
 * @copyright Copyright 2010 – Daniel Friesen
 * @license https://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 * @link https://www.mediawiki.org/wiki/Extension:Description2 Documentation
 */

class Description2 {

	/**
	 * @param Parser $parser
	 * @param string $desc
	 */
	public static function setDescription( Parser $parser, $desc ) {
		$parserOutput = $parser->getOutput();
		if ( $parserOutput->getProperty( 'description' ) !== false ) {
			return;
		}
		$parserOutput->setProperty( 'description', $desc );
	}

	/**
	 * @param Parser $parser
	 * @param string $text
	 * @return bool
	 */
	public static function onParserAfterTidy( Parser &$parser, &$text ) {
		$desc = '';

		$myText = preg_replace( '%<table\b[^>]*+>(?:(?R)|[^<]*+(?:(?!</?table\b)<[^<]*+)*+)*+</table>%i', '', $text );

		$paragraphs = array();
		if ( preg_match_all( '#<p>.*?</p>#is', $myText, $paragraphs ) ) {
			foreach ( $paragraphs[0] as $paragraph ) {
				$paragraph = trim( strip_tags( $paragraph ) );
				if ( !$paragraph ) {
					continue;
				}
				$desc = $paragraph;
				break;
			}
		}

		if ( $desc ) {
			self::setDescription( $parser, $desc );
		}

		return true;
	}

	/**
	 * @param Parser $parser
	 * @return bool
	 */
	public static function onParserFirstCallInit( Parser &$parser ) {
		global $wgEnableMetaDescriptionFunctions;
		if ( !$wgEnableMetaDescriptionFunctions ) {
			// Functions and tags are disabled
			return true;
		}
		$parser->setFunctionHook( 'description2', array( 'Description2', 'parserFunctionCallback' ), Parser::SFH_OBJECT_ARGS );
		$parser->setFunctionTagHook( 'metadesc', array( 'Description2', 'tagCallback' ), Parser::SFH_OBJECT_ARGS );
		return true;
	}

	/**
	 * @param Parser $parser
	 * @param $frame
	 * @param $args
	 * @return string
	 */
	public static function parserFunctionCallback( Parser $parser, $frame, $args ) {
		$desc = isset( $args[0] ) ? $frame->expand( $args[0] ) : '';
		self::setDescription( $parser, $desc );
		return '';
	}

	/**
	 * @param Parser $parser
	 * @param $frame
	 * @param $content
	 * @param $attributes
	 * @return string
	 */
	public static function tagCallback( Parser $parser, $frame, $content, $attributes ) {
		$desc = ( isset( $content ) ? $content : ( isset( $attributes['content'] ) ? $attributes['content'] : null ) );
		if ( isset( $desc ) ) {
			self::setDescription( $parser, $desc );
		}
		return '';
	}

	public static function onOutputPageParserOutput( OutputPage &$out, ParserOutput $parserOutput ) {
		// Export the description from the main parser output into the OutputPage
		$description = $parserOutput->getProperty( 'description' );
		if ( $description !== false ) {
			$out->addMeta( 'description', $description );
		}
	}
}
