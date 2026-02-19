<?php
/**
 * ReplaceSet extension to MediaWiki
 *
 * Allows for multiple patterns of text, either plain or RegEx based to be replaced
 * using a parser function
 *
 * @link https://www.mediawiki.org/wiki/Extension:ReplaceSet Documentation
 * @link https://www.mediawiki.org/wiki/Extension_talk:ReplaceSet Support
 * @link https://phabricator.wikimedia.org/diffusion/ERPS/ Scource code
 *
 * @file
 * @defgroup ReplaceSet
 * @ingroup Extensions
 * @package MediaWiki
 *
 * @author Daniel Friesen (Dantman)
 * @copyright Copyright © 2009-2012 – Daniel Friesen
 * @license https://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

// Ensure that the script cannot be executed outside of MediaWiki.
if ( !defined( 'MEDIAWIKI' ) ) {
	die( 'This is an extension to the MediaWiki package and cannot be run standalone.' );
}

// Display extension properties on MediaWiki.
$wgExtensionCredits['parserhook'][] = array(
	'path' => __FILE__,
	'name' => 'ReplaceSet',
	'url' => 'https://www.mediawiki.org/wiki/Extension:ReplaceSet',
	'version' => '1.5.0',
	'author' => array(
		'[https://www.mediawiki.org/wiki/User:Dantman Daniel Friesen]',
		'...'
	),
	'descriptionmsg' => 'replaceset-desc',
	'license-name' => 'GPL-2.0+'
);

// Register extension hooks.
$wgHooks['ParserFirstCallInit'][] = 'efReplaceSetRegisterParser';

// Load extension's classes.
$wgAutoloadClasses['ReplaceSet'] = __DIR__ . '/ReplaceSet.class.php';

// Register extension messages and other localisation.
$wgMessagesDirs['ReplaceSet'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['ReplaceSetMagic'] = __DIR__ . '/ReplaceSet.i18n.magic.php';

// Provide extension's tests.
$wgParserTestFiles[] = __DIR__ . '/parserTests.txt';

// Do some action.
function efReplaceSetRegisterParser( &$parser ) {
	$parser->setFunctionHook( 'replaceset', array( 'ReplaceSet', 'parserFunctionObj' ), Parser::SFH_OBJECT_ARGS );
	return true;
}

// Provide configuration settings.
/**
 * Max number of {{#replaceset:}} calls in a page.
 */
$egReplaceSetCallLimit = 25;

/**
 * Max number of replacements preg will make in a single string.
 */
$egReplaceSetPregLimit = 50;
