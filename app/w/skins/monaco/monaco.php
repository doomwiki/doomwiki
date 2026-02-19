<?php
/**
 * Monaco skin
 *
 * @package MediaWiki
 * @subpackage Skins
 *
 * @author Inez Korczynski <inez@wikia.com>
 * @author Christian Williams
 * @author Daniel Friesen
 * @author James Haley
 */

if ( function_exists( 'wfLoadSkin' ) ) {
	wfLoadSkin( 'Monaco' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['Monaco'] = __DIR__ . '/i18n';
	wfWarn(
		'Deprecated PHP entry point used for Monaco skin. Please use wfLoadSkin instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	);
	return true;
} else {
	die( 'This version of the Monaco skin requires MediaWiki 1.27+' );
}
