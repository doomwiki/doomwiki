<?php
/**
 * OpenGraphMeta
 *
 * @file
 * @ingroup Extensions
 * @author Daniel Friesen (http://danf.ca/mw/)
 * @license https://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 * @link https://www.mediawiki.org/wiki/Extension:OpenGraphMeta Documentation
 */

if ( !defined( 'MEDIAWIKI' ) ) die( "This is an extension to the MediaWiki package and cannot be run standalone." );

$wgExtensionCredits['parserhook'][] = array(
	'path' => __FILE__,
	'name' => "OpenGraphMeta",
	'author' => array("[http://danf.ca/mw/ Daniel Friesen]", "[http://doomwiki.org/wiki/User:Quasar James Haley]"),
	'descriptionmsg' => 'opengraphmeta-desc',
	'url' => 'https://www.mediawiki.org/wiki/Extension:OpenGraphMeta',
	'license-name' => 'GPL-2.0+',
);

$dir = dirname( __FILE__ );
$wgExtensionMessagesFiles['OpenGraphMetaMagic'] = $dir . '/OpenGraphMeta.magic.php';
$wgMessagesDirs['OpenGraphMeta'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['OpenGraphMeta'] = $dir . '/OpenGraphMeta.i18n.php';

$wgHooks['ParserFirstCallInit'][] = 'efOpenGraphMetaParserInit';
function efOpenGraphMetaParserInit( $parser ) {
	$parser->setFunctionHook( 'setmainimage', 'efSetMainImagePF' );
	$parser->setFunctionTagHook( 'metakeys', 'OpenGraphMetaSEO::parseKeywordsTag', Parser::SFH_OBJECT_ARGS );
	return true;
}

function efSetMainImagePF( $parser, $mainimage ) {
	$parserOutput = $parser->getOutput();
	if ( isset($parserOutput->eHasMainImageAlready) && $parserOutput->eHasMainImageAlready )
		return $mainimage;
	$file = Title::newFromText( $mainimage, NS_FILE );
	$parserOutput->addOutputHook( 'setmainimage', array( 'dbkey' => $file->getPrefixedDBkey() ) ); // haleyjd: must use prefixed db key
	$parserOutput->eHasMainImageAlready = true;

	return $mainimage;
}

$wgParserOutputHooks['setmainimage'] = 'efSetMainImagePH';
function efSetMainImagePH( $out, $parserOutput, $data ) {
	$out->mMainImage = wfFindFile( Title::newFromDBkey($data['dbkey']) ); // haleyjd: newFromDBkey uses prefixed db key
}

// haleyjd: PageImages integration; some credit due to wikia for ideas.
$egOpenGraphMetaMimeBlacklist = array( 'unknown/unknown', 'text/plain' );
$wgHooks['PageContentSaveComplete'][] = 'OpenGraphMetaPageImage::onPageContentSaveComplete';
class OpenGraphMetaPageImage
{
	const MAX_WIDTH = 1500;

	// Check if a file is a suitable image; not all files are images.
	public static function isAllowedThumb( $file ) {
		global $egOpenGraphMetaMimeBlacklist;
		if( is_object( $file ) ) {
			if( in_array( $file->getMimeType(), $egOpenGraphMetaMimeBlacklist ) ) {
				return false;
			}
		}
		return true; // is allowed, or cannot discern MIME type in this context.
	}

	// Get a thumbnail URL if the image is larger than the maximum recommended
	// size for og:image; otherwise, return the full file URL
	public static function getThumbUrl( $file ) {
		$url = false;
		if( is_object( $file ) ) {
			$width = $file->getWidth();
			if ( $width > self::MAX_WIDTH ) {
				$url = wfExpandUrl( $file->createThumb( self::MAX_WIDTH, -1 ) );
			} else {
				$url = $file->getFullUrl();
			}
		} else {
			// In some edge-cases we won't have defined an object but rather a full URL.
			$url = $file;
		}
		return $url;
	}

	// Obtain the PageImages extension's opinion of the best page image
	public static function getPageImage( &$meta, $title ) {
		$cache = wfGetMainCache();
		$cacheKey = wfMemcKey( 'OpenGraphMetaPageImage', md5( $title->getDBkey() ) );
		$imageUrl = $cache->get( $cacheKey );
		if ( is_null($imageUrl) || $imageUrl === false ) {
			$imageUrl = '';
			$file = PageImages::getPageImage( $title );
			if( $file && self::isAllowedThumb( $file ) ) {
				$imageUrl = self::getThumbUrl( $file );
			}
			$cache->set( $cacheKey, $imageUrl );
		}
		if( !empty( $imageUrl ) ) {
			$meta["og:image"] = $imageUrl;
		}
	}

	// Hook function to delete cached PageImages result when an article is edited
	public static function onPageContentSaveComplete( $article, $user, $content, $summary, $isMinor, $isWatch, $section, $flags, $revision, $status, $baseRevId ) {
		$title = $article->getTitle();
		$cacheKey = wfMemcKey( 'OpenGraphMetaPageImage', md5( $title->getDBkey() ) );
		wfGetMainCache()->delete( $cacheKey );
		return true;
	}
	
	// Set og:image property in the meta array
	public static function getSocialImage($title, $out, &$meta) {
		global $wgLogo;
		$isMainpage = $title->isMainPage();
		if (isset( $out->mMainImage ) && ( $out->mMainImage !== false ) ) {
			$meta["og:image"] = self::getThumbUrl( $out->mMainImage );
		} elseif ( $isMainpage ) {
			$meta["og:image"] = wfExpandUrl($wgLogo);
		} elseif ( $title->inNamespace( NS_FILE ) ) { // haleyjd: NS_FILE is trivial
			$file = wfFindFile( $title->getDBkey() );
			if ( $file && self::isAllowedThumb( $file ) ) {
				$meta["og:image"] = self::getThumbUrl( $file );
			}
		} elseif ( defined('PAGE_IMAGES_INSTALLED') ) { // haleyjd: integrate with Extension:PageImages
			self::getPageImage( $meta, $title );
		}
	}
}

// haleyjd: General SEO enhancements; derived from Curse SEO extension
class OpenGraphMetaSEO
{
	public static $itemprop = [];
	
	/**
	 * Adds itemprop attributes to the <head>.
	 *
	 * @access	public
	 * @param	object	Output Object
	 * @param	object	Skin Object
	 * @return	boolean	True
	 */
	public static function onBeforePageDisplay(OutputPage &$out, &$skin = false) {
		global $wgSitename;
		//Author
		$out->addHeadItem('itemprop-author', '<meta itemprop="author" content="' . $wgSitename . '" />');

		if (isset($out->mItemprops) && is_array($out->mItemprops)) {
			foreach ($out->mItemprops as $itemprop) {
				$out->addHeadItem('itemprop-'.md5($itemprop['name'].'-'.$itemprop['content']), '<meta itemprop="'.$itemprop['itemprop'].'" content="'.$itemprop['content'].'" />');
			}
		}

		//Keywords
		if (isset($out->mKeywords)) {
			$out->addMeta('keywords', implode(',', $out->mKeywords));
		}

		return true;
	}
	
	/**
	 * Modify template variables.
	 *
	 * @access	public
	 * @param	object	SkinTemplate Object
	 * @param	object	Initialized QuickTemplate Object
	 * @return	boolean True
	 */
	public static function onSkinTemplateOutputPageBeforeExec(&$skinTemplate, &$template) {
		global $wgSitename, $wgLogo, $egSocialSettings;

		if (isset($template->data['headelement'])) {
			$timestamp = $skinTemplate->getOutput()->getRevisionTimestamp();

			// No cached timestamp, load it from the database
			if ($timestamp === null) {
				$timestamp = Revision::getTimestampFromId($skinTemplate->getTitle(), $skinTemplate->getRevisionId());
			}
			
			// Get page image info
			$meta = array();
			OpenGraphMetaPageImage::getSocialImage($skinTemplate->getTitle(), $skinTemplate->getOutput(), $meta);
			
			// Get site logo
			$file = wfLocalFile(Title::newFromText('File:Wiki.png'));

			$width = 150;
			$height = 150;
			$image = $wgLogo;
			$cacheHash = '';
			if ($file !== null && $file->exists()) {
				$cacheHash = '?version='.md5($file->getTimestamp().$file->getWidth().$file->getHeight());
				$width = $file->getWidth();
				$height = $file->getHeight();
				$image = $file->getFullUrl();
			}

			$searchPage = Title::newFromText('Special:Search');
			$search = $searchPage->getFullUrl(['search' => 'search_term'], false, PROTO_HTTPS);
			$search = str_replace('search_term', '{search_term}', $search);

			if (isset($egSocialSettings['profile_urls'])) {
				$profiles = '"'.implode('", "', $egSocialSettings['profile_urls']).'"';
			} else {
				$profiles = '';
			}
			
			if (!isset($meta['og:image'])) {
				$meta['og:image'] = $image;
			}
			
			$title = json_encode($template->get('title'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

			$ldJson = '
<script type="application/ld+json">
{
	"@context": "http://schema.org/",
	"@type": "Article",
	"name": '.$title.',
	"headline": '.$title.',
	"image": {
		"@type": "ImageObject",
		"url": "'.$meta['og:image'].'"
	},
	"author": {
		"@type": "Organization",
		"name": "'.$wgSitename.'"
	},
	"publisher": {
		"@type": "Organization",
		"name": "'.$wgSitename.'",
		"logo": {
			"@type": "ImageObject",
			"url": "'.$image.$cacheHash.'",
			"width": "'.$width.'",
			"height": "'.$height.'"
		},
		"sameAs": [
			'.$profiles.'
		]
	},
	"potentialAction": {
		"@type": "SearchAction",
		"target": "'.$search.'",
		"query-input": "required name=search_term"
	},
	"datePublished": "'.wfTimestamp(TS_ISO_8601, $timestamp).'",
	"dateModified": "'.wfTimestamp(TS_ISO_8601, $timestamp).'",
	"mainEntityOfPage": "'.$skinTemplate->getTitle()->getFullUrl('', false, PROTO_HTTPS).'"
}
</script>
';

			$template->set('headelement', $template->data['headelement'].$ldJson);
		}
	}

	/**
	 * Adds itemprop attributes to HTML
	 *
	 * @access	public
	 * @param	object	OutputPage
	 * @param	object  ParserOutput
	 * @return	void
	 */
	public static function addTagsToHead($out, $parserOutput) {
		if (!isset($out->mItemprops)) {
			$out->mItemprops = [];
		}

		$out->mItemprops = $parserOutput->getProperty('itemprops');
	}
	
	/**
	 * gets keywords and adds them to object
	 *
	 * @access	public
	 * @param	object	Parser Object
	 * @param	object  frame
	 * @param   string of content
	 * @return	return message on error
	 */
	public static function parseKeywordsTag(Parser $parser, $frame, $content) {
		if (empty($content)) {
			return wfMessage('seo-keywords-missing')->inContentLanguage()->plain();
		}
		
		$out = $parser->getOutput();
		$out->setProperty('keywords', $content);
	}
	
	/**
	 * Adds keywords attributes to HTML
	 *
	 * @access	public
	 * @param	object	OutputPage
	 * @param	object	ParserOutput
	 * @return	void
	 */
	public static function addKeywordsToHead($out, $parserOutput) {
		if (!isset($out->mKeywords)) {
			$out->mKeywords = array();
		}
		$keywords = $parserOutput->getProperty('keywords');
		if (empty($keywords)) {
			$keywords = wfMessage('seo-default-keywords')->inContentLanguage()->plain();
		}
		$out->mKeywords = array_map("trim", explode(',', $keywords));
	}
	
	/**
	 * OutputPage ParserOutput Hook that puts all parseroutput page hooks into action.
	 *
	 * @see		https://www.mediawiki.org/wiki/Manual:Hooks/OutputPageParserOutput
	 * @access	public
	 * @param	OutputPage		$out
	 * @param	ParserOutput	$parseroutput
	 * @return	boolean
	 */
	public static function onOutputPageParserOutput(OutputPage &$out, ParserOutput $parseroutput) {
		self::addKeywordsToHead($out, $parseroutput);
		self::addTagsToHead($out, $parseroutput);
		return true;
	}
}

// haleyjd: Add additional hooks for SEO
$wgHooks['OutputPageParserOutput'          ][] = 'OpenGraphMetaSEO::onOutputPageParserOutput';
$wgHooks['SkinTemplateOutputPageBeforeExec'][] = 'OpenGraphMetaSEO::onSkinTemplateOutputPageBeforeExec';

$wgHooks['BeforePageDisplay'][] = 'efOpenGraphMetaPageHook';
function efOpenGraphMetaPageHook( &$out, &$sk ) {
	global $wgSitename, $wgXhtmlNamespaces, $egFacebookAppId, $egFacebookAdmins;
	$wgXhtmlNamespaces["og"] = "http://opengraphprotocol.org/schema/";
	$title = $out->getTitle();
	$isMainpage = $title->isMainPage();

	$meta = array();

	if ( $isMainpage ) {
		$meta["og:type"] = "website";
		$meta["og:title"] = $wgSitename;
	} else {
		$meta["og:type"] = "article";
		$meta["og:site_name"] = $wgSitename;
		// Try to chose the most appropriate title for showing in news feeds.
		if ( ( defined('NS_BLOG_ARTICLE') && $title->getNamespace() == NS_BLOG_ARTICLE ) ||
			( defined('NS_BLOG_ARTICLE_TALK') && $title->getNamespace() == NS_BLOG_ARTICLE_TALK ) ){
			$meta["og:title"] = $title->getSubpageText();
		} else {
			$meta["og:title"] = $title->getText();
		}
	}

	// haleyjd: Get og:image property
	OpenGraphMetaPageImage::getSocialImage($title, $out, $meta);
	
	if ( isset($out->mDescription) ) { // set by Description2 extension, install it if you want proper og:description support
		$meta["og:description"] = $out->mDescription;
	}
	$meta["og:url"] = $title->getFullURL();
	if ( $egFacebookAppId ) {
		$meta["fb:app_id"] = $egFacebookAppId;
	}
	if ( $egFacebookAdmins ) {
		$meta["fb:admins"] = $egFacebookAdmins;
	}

	foreach( $meta as $property => $value ) {
		if ( $value ) {
			if ( isset( OutputPage::$metaAttrPrefixes ) && isset( OutputPage::$metaAttrPrefixes['property'] ) ) {
				$out->addMeta( "property:$property", $value );
			} else {
				$out->addHeadItem("meta:property:$property", Html::element( 'meta', array( 'property' => $property, 'content' => $value ) ) ); // haleyjd: rem unnecessary whitespace
			}
		}
	}
	
	// haleyjd: Add additional SEO items
	OpenGraphMetaSEO::onBeforePageDisplay($out, $sk);

	return true;
}

$egFacebookAppId = null;
$egFacebookAdmins = null;
$egSocialSettings = array('profile_urls' => array());
