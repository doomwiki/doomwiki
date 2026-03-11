<?php

class CharInsert {
	public static function onParserFirstCallInit( Parser $parser ) {
		$parser->setHook( 'charinsert', 'CharInsert::charInsertHook' );
		return true;
	}

	/**
	 * Things like edittools message are added to output directly,
	 * instead of using something like OutputPage::addWikiText.
	 * As a result, modules sometimes aren't transferred over.
	 * @param OutputPage $out OutputPage to work on
	 */
	public static function onBeforePageDisplay( $out ) {
		$addModules = false;
		$title = $out->getTitle();
		if ( $title->isSpecial( 'Upload' ) ) {
			$addModules = true;
		} else {
			$action = Action::getActionName( $out );
			if ( in_array( $action, [ 'edit', 'submit' ] ) ) {
				$addModules = true;
			}
		}
		if ( $addModules ) {
			$out->addModules( 'ext.charinsert' );
			$out->addModuleStyles( 'ext.charinsert.styles' );
		}
	}

	public static function charInsertHook( $data, $params, Parser $parser ) {
		$data = $parser->mStripState->unstripBoth( $data );
		$parser->getOutput()->addModules( 'ext.charinsert' );
		$parser->getOutput()->addModuleStyles( 'ext.charinsert.styles' );
		return implode( "<br />\n",
			array_map( 'CharInsert::charInsertLine',
				explode( "\n", trim( $data ) ) ) );
	}
	public static function charInsertLine( $data ) {
		return implode( "\n",
			array_map( 'CharInsert::charInsertItem',
				preg_split( '/\\s+/', self::charInsertArmor( $data ) ) ) );
	}

	public static function charInsertArmor( $data ) {
		return preg_replace_callback(
			'!<nowiki>(.*?)</nowiki>!i',
			'CharInsert::charInsertNowiki',
			$data );
	}

	public static function charInsertNowiki( $matches ) {
		return str_replace(
			[ '\t', '\r', ' ' ],
			[ '&#9;', '&#12;', '&#32;' ],
			$matches[1] );
	}

	public static function charInsertItem( $data ) {
		$chars = explode( '+', $data );
		if ( count( $chars ) > 1 && $chars[0] !== '' ) {
			return self::charInsertChar( $chars[0], $chars[1] );
		} elseif ( count( $chars ) === 1 ) {
			return self::charInsertChar( $chars[0] );
		} else {
			return self::charInsertChar( '+' );
		}
	}

	public static function charInsertChar( $start, $end = '' ) {
		$estart = self::charInsertDisplay( $start );
		$eend = self::charInsertDisplay( $end );
		$inline = $estart . $eend;

		// Having no href attribute makes the link be more
		// easily copy and pasteable for non-js users.
		return Xml::element( 'a',
			[
				'data-mw-charinsert-start' => $estart,
				'data-mw-charinsert-end' => $eend,
				'class' => 'mw-charinsert-item'
			], $inline
		);
	}

	public static function charInsertDisplay( $text ) {
		static $invisibles = [ '&nbsp;', '&#160;' ];
		static $visibles = [ '&amp;nbsp;', '&amp;#160;' ];
		return Sanitizer::decodeCharReferences(
			str_replace( $invisibles, $visibles, $text ) );
	}
}
