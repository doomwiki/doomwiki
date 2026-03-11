<?php
/**
 * Modern skin, derived from monobook template.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

/**
 * @todo document
 * @ingroup Skins
 */
class ModernTemplate extends BaseTemplate {

	/**
	 * Template filter callback for Modern skin.
	 * Takes an associative array of data set from a SkinTemplate-based
	 * class, and a wrapper for MediaWiki's localization database, and
	 * outputs a formatted page.
	 */
	function execute() {
		$this->html( 'headelement' );
		?>

		<!-- heading -->
		<div id="mw_header">
			<?php echo $this->getIndicators(); ?>
			<h1 id="firstHeading" lang="<?php
			$this->data['pageLanguage'] = $this->getSkin()->getTitle()->getPageViewLanguage()->getHtmlCode();
			$this->text( 'pageLanguage' );
			?>"><?php $this->html( 'title' ) ?></h1>
		</div>

		<div id="mw_main">
			<div id="mw_contentwrapper">
				<!-- navigation portlet -->
				<?php $this->cactions(); ?>

				<!-- content -->
				<div id="mw_content" role="main">
					<!-- contentholder does nothing by default, but it allows users to style the text inside
						 the content area without affecting the meaning of 'em' in #mw_content, which is used
						 for the margins -->
					<div id="mw_contentholder" class="mw-body">
						<div class='mw-topboxes'>
							<div id="mw-js-message"
								style="display:none;"<?php $this->html( 'userlangattributes' ) ?>></div>
							<div class="mw-topbox" id="siteSub"><?php $this->msg( 'tagline' ) ?></div>
							<?php
							if ( $this->data['newtalk'] ) {
								?>
								<div class="usermessage mw-topbox"><?php $this->html( 'newtalk' ) ?></div>
							<?php
							}
							?>
							<?php
							if ( $this->data['sitenotice'] ) {
								?>
								<div class="mw-topbox" id="siteNotice"><?php $this->html( 'sitenotice' ) ?></div>
							<?php
							}
							?>
						</div>

						<div id="contentSub"<?php
						$this->html( 'userlangattributes' )
						?>><?php
							$this->html( 'subtitle' )
							?></div>

						<?php
						if ( $this->data['undelete'] ) {
							?>
							<div id="contentSub2"><?php $this->html( 'undelete' ) ?></div><?php
						}
						?>
						<div id="jump-to-nav"><?php $this->msg( 'jumpto' ) ?>
							<a href="#mw_portlets"><?php
								$this->msg( 'jumptonavigation' ) ?></a><?php $this->msg( 'comma-separator' )
							?>
							<a href="#searchInput"><?php $this->msg( 'jumptosearch' ) ?></a>
						</div>

						<?php $this->html( 'bodytext' ) ?>
						<div class='mw_clear'></div>
						<?php
						if ( $this->data['catlinks'] ) {
							$this->html( 'catlinks' );
						}
						?>
						<?php $this->html( 'dataAfterContent' ) ?>
					</div><!-- mw_contentholder -->
				</div><!-- mw_content -->
			</div><!-- mw_contentwrapper -->

			<div id="mw_portlets"<?php $this->html( "userlangattributes" ) ?>>
				<h2><?php $this->msg( 'navigation-heading' ) ?></h2>

				<!-- portlets -->
				<?php $this->renderPortals( $this->data['sidebar'] ); ?>

			</div><!-- mw_portlets -->


		</div><!-- main -->

		<div class="mw_clear"></div>

		<!-- personal portlet -->
		<div class="portlet" id="p-personal" role="navigation">
			<h3><?php $this->msg( 'personaltools' ) ?></h3>

			<div class="pBody">
				<ul>
					<?php
					foreach ( $this->getPersonalTools() as $key => $item ) {
						?>
						<?php echo $this->makeListItem( $key, $item ); ?>

					<?php
					}
					?>
				</ul>
			</div>
		</div>


		<!-- footer -->
		<div id="footer" role="contentinfo"<?php $this->html( 'userlangattributes' ) ?>>
			<ul id="f-list">
				<?php
				foreach ( $this->getFooterLinks( "flat" ) as $aLink ) {
					if ( isset( $this->data[$aLink] ) && $this->data[$aLink] ) {
						?>
						<li id="<?php echo $aLink ?>"><?php $this->html( $aLink ) ?></li>
					<?php
					}
				}
				?>
			</ul>
			<?php
			foreach ( $this->getFooterIcons( "nocopyright" ) as $blockName => $footerIcons ) {
				?>
				<div id="mw_<?php echo htmlspecialchars( $blockName ); ?>">
					<?php
					foreach ( $footerIcons as $icon ) {
						?>
						<?php echo $this->getSkin()->makeFooterIcon( $icon, 'withoutImage' ); ?>

					<?php
					} ?>
				</div>
			<?php
			}
			?>
		</div>

		<?php $this->printTrail(); ?>

	</body>
</html>
		<?php
	}

	/**
	 * Prints all sidebar boxes
	 *
	 * @param array $sidebar
	 */
	protected function renderPortals( $sidebar ) {
		if ( !isset( $sidebar['SEARCH'] ) ) {
			$sidebar['SEARCH'] = true;
		}
		if ( !isset( $sidebar['TOOLBOX'] ) ) {
			$sidebar['TOOLBOX'] = true;
		}
		if ( !isset( $sidebar['LANGUAGES'] ) ) {
			$sidebar['LANGUAGES'] = true;
		}

		foreach ( $sidebar as $boxName => $content ) {
			if ( $content === false ) {
				continue;
			}

			// Numeric strings gets an integer when set as key, cast back - T73639
			$boxName = (string)$boxName;

			if ( $boxName == 'SEARCH' ) {
				$this->searchBox();
			} elseif ( $boxName == 'TOOLBOX' ) {
				$this->toolbox();
			} elseif ( $boxName == 'LANGUAGES' ) {
				$this->languageBox();
			} else {
				$this->customBox( $boxName, $content );
			}
		}
	}

	/**
	 * Prints the search
	 */
	function searchBox() {
		?>
		<div id="p-search" class="portlet" role="search">
			<h3><label for="searchInput"><?php $this->msg( 'search' ) ?></label></h3>

			<div id="searchBody" class="pBody">
				<form action="<?php $this->text( 'wgScript' ) ?>" id="searchform">
					<input type="hidden" name="title" value="<?php $this->text( 'searchtitle' ) ?>"/>
					<?php echo $this->makeSearchInput( [ 'id' => 'searchInput' ] ); ?>

					<?php
					echo $this->makeSearchButton(
						'go',
						[ 'id' => 'searchGoButton', 'class' => 'searchButton' ]
					);

					if ( $this->config->get( 'UseTwoButtonsSearchForm' ) ) {
						?>&#160;
						<?php echo $this->makeSearchButton(
							'fulltext',
							[ 'id' => 'mw-searchButton', 'class' => 'searchButton' ]
						);
					} else {
						?>

						<div><a href="<?php
						$this->text( 'searchaction' )
						?>" rel="search"><?php $this->msg( 'powersearch-legend' ) ?></a></div><?php
					} ?>

				</form>

				<?php $this->renderAfterPortlet( 'search' ); ?>
			</div>
		</div>
	<?php
	}

	/**
	 * Prints the content actions (cactions) bar.
	 */
	function cactions() {
		?>
		<div id="p-cactions" class="portlet" role="navigation">
			<h3><?php $this->msg( 'views' ) ?></h3>

			<div class="pBody">
				<ul><?php
					foreach ( $this->data['content_actions'] as $key => $tab ) {
						echo '
				' . $this->makeListItem( $key, $tab );
					} ?>

				</ul>
				<?php $this->renderAfterPortlet( 'cactions' ); ?>
			</div>
		</div>
	<?php
	}

	/**
	 * Prints the toolbox
	 */
	function toolbox() {
		?>
		<div class="portlet" id="p-tb" role="navigation">
			<h3><?php $this->msg( 'toolbox' ) ?></h3>

			<div class="pBody">
				<ul>
					<?php
					foreach ( $this->getToolbox() as $key => $tbitem ) {
						?>
						<?php echo $this->makeListItem( $key, $tbitem ); ?>

					<?php
					}
					// Avoid PHP 7.1 warnings
					$skin = $this;
					Hooks::run( 'MonoBookTemplateToolboxEnd', [ &$skin ] );
					Hooks::run( 'SkinTemplateToolboxEnd', [ &$skin, true ] );
					?>
				</ul>
				<?php $this->renderAfterPortlet( 'tb' ); ?>
			</div>
		</div>
	<?php
		Hooks::run( 'MonoBookAfterToolbox' );
	}

	/**
	 * Prints the other languages box
	 */
	function languageBox() {
		if ( $this->data['language_urls'] !== false ) {
			?>
			<div id="p-lang" class="portlet" role="navigation">
				<h3<?php $this->html( 'userlangattributes' ) ?>><?php $this->msg( 'otherlanguages' ) ?></h3>

				<div class="pBody">
					<ul>
						<?php foreach ( $this->data['language_urls'] as $key => $langLink ) { ?>
							<?php echo $this->makeListItem( $key, $langLink ); ?>

						<?php
						}
						?>
					</ul>

					<?php $this->renderAfterPortlet( 'lang' ); ?>
				</div>
			</div>
		<?php
		}
	}

	/**
	 * Prints user-defined navigation boxes
	 *
	 * @param string $bar
	 * @param array|string $cont
	 */
	function customBox( $bar, $cont ) {
		$portletAttribs = [
			'class' => 'generated-sidebar portlet',
			'id' => Sanitizer::escapeId( "p-$bar" ),
			'role' => 'navigation'
		];

		$tooltip = Linker::titleAttrib( "p-$bar" );
		if ( $tooltip !== false ) {
			$portletAttribs['title'] = $tooltip;
		}
		echo '	' . Html::openElement( 'div', $portletAttribs );
		$msgObj = wfMessage( $bar );
		?>

		<h3><?php echo htmlspecialchars( $msgObj->exists() ? $msgObj->text() : $bar ); ?></h3>
		<div class="pBody">
			<?php
			if ( is_array( $cont ) ) {
				?>
				<ul>
					<?php
					foreach ( $cont as $key => $val ) {
						?>
						<?php echo $this->makeListItem( $key, $val ); ?>

					<?php
					}
					?>
				</ul>
			<?php
			} else {
				# allow raw HTML block to be defined by extensions
				print $cont;
			}

			$this->renderAfterPortlet( $bar );
			?>
		</div>
		</div>
	<?php
	}
}
