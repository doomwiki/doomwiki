<?php
/**
 * ReplaceSet extension
 *
 * @file
 * @defgroup ReplaceSet
 * @package MediaWiki
 * @author Daniel Friesen (http://danf.ca/mw/)
 * @copyright Copyright © 2009-2012 – Daniel Friesen
 * @license https://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 * @link https://www.mediawiki.org/wiki/Extension:ReplaceSet Documentation
 */

if ( !defined( 'MEDIAWIKI' ) ) die( "This is an extension to the MediaWiki package and cannot be run standalone." );

$wgExtensionCredits['parserhook'][] = array(
	'path' => __FILE__,
	'name' => 'ReplaceSet',
	'url' => 'https://www.mediawiki.org/wiki/Extension:ReplaceSet',
	'version' => '1.4.0',
	'author' => "[http://danf.ca/mw/ Daniel Friesen]",
	'descriptionmsg' => 'replaceset-desc',
	'license-name' => 'GPL-2.0+'
);

$wgHooks['ParserFirstCallInit'][] = 'efReplaceSetRegisterParser';

$wgAutoloadClasses['ReplaceSet'] = __DIR__ . '/ReplaceSet.class.php';
$wgMessagesDirs['ReplaceSet'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['ReplaceSet'] = __DIR__ . '/ReplaceSet.i18n.php';
$wgExtensionMessagesFiles['ReplaceSetMagic'] = __DIR__ . '/ReplaceSet.i18n.magic.php';

$wgParserTestFiles[] = __DIR__ . '/parserTests.txt';

function efReplaceSetRegisterParser( &$parser ) {
	$parser->setFunctionHook( 'replaceset', array( 'ReplaceSet', 'parserFunctionObj' ), Parser::SFH_OBJECT_ARGS );
	return true;
}

/*************************************************************************//**
 * @name   ReplaceSet configuration settings
 * @{
 */

/**
 * Max number of {{#replaceset:}} calls in a page.
 */
$egReplaceSetCallLimit = 25;

/**
 * Max number of replacements preg will make in a single string.
 */
$egReplaceSetPregLimit = 50;

/** @} */
