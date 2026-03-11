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
if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'ReplaceSet' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['ReplaceSet'] = __DIR__ . '/i18n';
	wfWarn(
		'Deprecated PHP entry point used for the ReplaceSet extension. ' .
		'Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	);
	return;
} else {
	die( 'This version of the ReplaceSet extension requires MediaWiki 1.29+' );
}