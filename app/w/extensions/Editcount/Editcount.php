<?php
/**
 * A Special Page extension that displays edit counts.
 *
 * This page can be accessed from Special:Editcount[/user] as well as being
 * included like {{Special:Editcount/user[/namespace]}}
 *
 * @file
 * @ingroup Extensions
 * @author Ævar Arnfjörð Bjarmason <avarab@gmail.com>
 * @copyright Copyright © 2005, Ævar Arnfjörð Bjarmason
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'Editcount' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['Editcount'] = __DIR__ . '/i18n';
	$wgExtensionMessagesFiles['EditcountAlias'] = __DIR__ . '/Editcount.alias.php';
	wfWarn(
		'Deprecated PHP entry point used for Editcount extension. Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	);
	return;
} else {
	die( 'This version of the Editcount extension requires MediaWiki 1.25+' );
}
