<?php
/**
 * Description2.php – Adds meaningful description <meta> tag to MW pages and into the parser output
 *
 * @file
 * @ingroup Extensions
 * @author Daniel Friesen (http://danf.ca/mw/)
 * @copyright Copyright 2010 – Daniel Friesen
 * @license https://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 * @link https://www.mediawiki.org/wiki/Extension:Description2 Documentation
 */

if ( !defined( 'MEDIAWIKI' ) ) die( "This is an extension to the MediaWiki package and cannot be run standalone." );

$wgExtensionCredits['other'][] = array(
	'path' => __FILE__,
	'name' => 'Description2',
	'version' => '0.3.0',
	'author' => "[http://danf.ca/mw/ Daniel Friesen]",
	'url' => 'https://www.mediawiki.org/wiki/Extension:Description2',
	'descriptionmsg' => 'description2-desc',
);

$dir = dirname( __FILE__ );
$wgMessagesDirs['Description2'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['Description2'] = $dir . '/Description2.i18n.php';
$wgExtensionMessagesFiles['Description2Magic'] = $dir . '/Description2.i18n.magic.php';

function efDescription2SetDescription( $parser, $desc ) {
	$pOut = $parser->getOutput();
	if ( $pOut->getProperty("description") !== false ) {
		return;
	}
	$pOut->setProperty("description", $desc);
	$pOut->addOutputHook("setdescription");
}

$wgHooks['ParserAfterTidy'][] = 'egDescription2ParserAfterTidy';
function egDescription2ParserAfterTidy( &$parser, &$text ) {
	$desc = '';

	$myText = preg_replace('%<table\b[^>]*+>(?:(?R)|[^<]*+(?:(?!</?table\b)<[^<]*+)*+)*+</table>%i', '', $text);

	$paragraphs = array();
	if ( preg_match_all('#<p>.*?</p>#is', $myText, $paragraphs) ) {
		foreach ( $paragraphs[0] as $paragraph ) {
			$paragraph = trim(strip_tags($paragraph));
			if ( !$paragraph )
				continue;
			$desc = $paragraph;
			break;
		}
	}

	if ( $desc ) {
		efDescription2SetDescription( $parser, $desc );
	}

	return true;
}

$wgHooks['ParserFirstCallInit'][] = array( 'efDescription2RegisterParser' );
function efDescription2RegisterParser( &$parser ) {
	global $wgEnableMetaDescriptionFunctions;
	if ( !$wgEnableMetaDescriptionFunctions ) {
		// Functions and tags are disabled
		return true;
	}
	$parser->setFunctionHook( 'description2', 'efDescription2Function', Parser::SFH_OBJECT_ARGS );
	$parser->setFunctionTagHook( 'metadesc', 'efDescription2Tag', Parser::SFH_OBJECT_ARGS );
	return true;
}

function efDescription2Function( $parser, $frame, $args ) {
	$desc = isset( $args[0] ) ? $frame->expand( $args[0] ) : '';
	efDescription2SetDescription( $parser, $desc );
	return '';
}
function efDescription2Tag( $parser, $frame, $content, $attributes ) {
	$desc = (isset( $content ) ? $content : (isset($attributes["content"]) ? $attributes["content"] : null));
	if ( isset($desc) ) {
		efDescription2SetDescription( $parser, $desc );
	}
	return '';
}

$wgParserOutputHooks['setdescription'] = 'egDescription2ParserOutputSetDescription';
function egDescription2ParserOutputSetDescription( $out, $parserOutput, $data ) {
	// Export the description from the main parser output into the OutputPage
	$out->mDescription = $parserOutput->getProperty("description");
}

$wgHooks['BeforePageDisplay'][] = 'egDescription2PageHook';
function egDescription2PageHook( &$out, &$sk ) {
	if ( isset($out->mDescription) && $out->mDescription )
		$out->addMeta("description", $out->mDescription);
	return true;
}


## Configuration
$wgEnableMetaDescriptionFunctions = false;

