<?php
if(!defined('MEDIAWIKI'))
  die( "This is an extension to the MediaWiki package and cannot be run standalone." );
  
// Extension info
$wgExtensionCredits['other'][] = array (
  'path' => __FILE__,
  'name' => 'DoomWikiAnalytics',
  'description' => 'Google Analytics provider for DoomWiki.org',
  'version' => '1.0',
  'author' => '[http://doomwiki.org/wiki/User:Quasar James Haley]',
  'url' => 'http://github.com/haleyjd/DoomWikiAnalytics',
);
  
  
// Configuration
$wgGroupPermissions['*']['noanalytics'] = false;
$wgGroupPermissions['bot']['noanalytics'] = true;
$wgGroupPermissions['sysop']['noanalytics'] = true;
$wgGroupPermissions['bureaucrat']['noanalytics'] = true;

$wgDoomWikiGAAccount = '';

class DoomWikiAnalytics
{
  // exclude sensitive special pages
  static function isTitleSafe($title)
  {
    $pages = array('UserLogin', 'UserLogout', 'Preferences', 'PasswordReset', 
                   'CreateAccount', 'ResetTokens', 'ChangeEmail');
    foreach($pages as $page)
    {
      if($title->isSpecial($page))
        return false;
    }
    return true;
  }

  // write Google Analytics loading script at end of body
  public static function gaScript($sk, &$text = '')
  {
    global $wgDoomWikiGAAccount;
    if(!self::isTitleSafe($sk->getTitle()))
      return true;
    if($sk->getUser()->isAllowed('noanalytics'))
      return true;
    $text .= <<<GASCRIPT
<script>
  (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
  (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
  m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
  })(window,document,'script','//www.google-analytics.com/analytics.js','ga');

  ga('create', '{$wgDoomWikiGAAccount}', 'auto');
  ga('send', 'pageview', { 'anonymizeIp': true });

</script>

GASCRIPT;
    return true;
  }
}

// Set hooks
$wgHooks['SkinAfterBottomScripts'][] = 'DoomWikiAnalytics::gaScript';
