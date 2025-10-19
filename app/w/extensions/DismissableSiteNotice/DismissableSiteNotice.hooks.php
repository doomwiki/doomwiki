<?php

class DismissableSiteNoticeHooks {

	/**
	 * @param string $notice
	 * @param Skin $skin
	 * @return bool true
	 */
	public static function onSiteNoticeAfter( &$notice, $skin ) {
		global $wgMajorSiteNoticeID, $wgDismissableSiteNoticeForAnons;

		if ( !$notice ) {
			return true;
		}

		// Dismissal for anons is configurable
		if ( $wgDismissableSiteNoticeForAnons || $skin->getUser()->isLoggedIn() ) {
			// Cookie value consists of two parts
			$major = (int) $wgMajorSiteNoticeID;
			$minor = (int) $skin->msg( 'sitenotice_id' )->inContentLanguage()->text();

			$out = $skin->getOutput();
			$out->addModuleStyles( 'ext.dismissableSiteNotice.styles' );
			$out->addModules( 'ext.dismissableSiteNotice' );
			$out->addJsConfigVars( 'wgSiteNoticeId', "$major.$minor" );

			$notice = Html::rawElement( 'div', array( 'class' => 'mw-dismissable-notice' ),
				Html::rawElement( 'div', array( 'class' => 'mw-dismissable-notice-close' ),
					$skin->msg( 'sitenotice_close-brackets' )
						->rawParams(
							Html::element( 'a', array( 'href' => '#' ), $skin->msg( 'sitenotice_close' )->text() )
						)
						->escaped()
				) .
				Html::rawElement( 'div', array( 'class' => 'mw-dismissable-notice-body' ), $notice )
			);
		}

		if ( $skin->getUser()->isAnon() ) {
			// Hide the sitenotice from search engines (see bug T11209 comment 4)
			// XXX: Does this actually work?
			$notice = Html::inlineScript( Xml::encodeJsCall( 'document.write', array( $notice ) ) );
		}

		return true;
	}
}
