<?php
/**
 * Parser hook extension to add a <randomimage> tag
 *
 * @file
 * @ingroup Extensions
 * @author Rob Church <robchur@gmail.com>
 * @copyright © 2006 Rob Church
 * @licence GNU General Public Licence 2.0
 */

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'RandomImage' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['RandomImage'] = __DIR__ . '/i18n';
	wfWarn(
		'Deprecated PHP entry point used for RandomImage extension. ' .
		'Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	);
	return;
} else {
	die( 'This version of the RandomImage extension requires MediaWiki 1.25+' );
}