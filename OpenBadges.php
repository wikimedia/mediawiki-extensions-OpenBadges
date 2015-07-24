<?php
/**
 * OpenBadges Extension. Based on Mozilla OpenBadges
 *
 * See https://github.com/openbadges/openbadges-specification
 * for specs.
 *
 * @todo Add logging
 *
 * @file
 * @ingroup Extensions
 * @author chococookies, and the rest
 * @license GNU General Public Licence 2.0 or later
 */

$wgExtensionCredits['other'][] = array(
	'path' => __FILE__,
	'name' => 'OpenBadges',
	'author' => array(
		'chococookies',
		'Don Yu',
		'Stephen Zhou',
		'Lokal_Profil'
	),
	'version'  => '0.1',
	'url' => 'https://www.mediawiki.org/wiki/OpenBadges',
	'descriptionmsg' => 'ob-desc',
	'license-name' => 'GPL-2.0+',
);

/* Setup */

// Files
$wgAutoloadClasses['SpecialBadgeIssue'] = __DIR__ . '/SpecialBadgeIssue.php';
$wgAutoloadClasses['SpecialBadgeCreate'] = __DIR__ . '/SpecialBadgeCreate.php';
$wgAutoloadClasses['SpecialBadgeView'] = __DIR__ . '/SpecialBadgeView.php';
$wgAutoloadClasses['BadgesPager'] = __DIR__ . '/SpecialBadgeView.php';
$wgAutoloadClasses['ApiOpenBadges'] = __DIR__ . '/ApiOpenBadges.php';
$wgMessagesDirs['OpenBadges'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['OpenBadges'] = __DIR__ . '/OpenBadges.i18n.php';
$wgExtensionMessagesFiles['OpenBadgesAlias'] = __DIR__ . '/OpenBadges.i18n.alias.php';

// Map module name to class name
$wgAPIModules['openbadges'] = 'ApiOpenBadges';

// Special pages
$wgSpecialPages['BadgeIssue'] = 'SpecialBadgeIssue';
$wgSpecialPages['BadgeCreate'] = 'SpecialBadgeCreate';
$wgSpecialPages['BadgeView'] = 'SpecialBadgeView';

// Permissions
// @todo Add custom create and issue groups
$wgGroupPermissions['sysop']['issuebadge'] = true;
$wgGroupPermissions['sysop']['createbadge'] = true;
$wgGroupPermissions['user']['viewbadge'] = true;
$wgAvailableRights[] = 'createbadge';
$wgAvailableRights[] = 'issuebadge';
$wgAvailableRights[] = 'viewbadge';

// Register hooks
$wgHooks['LoadExtensionSchemaUpdates'][] = 'createTable';
// $wgHooks['BeforePageDisplay'][] = 'efAddOpenBadgesModule';


// Function to hook up our tables
function createTable( DatabaseUpdater $dbU ) {
	$dbU->addExtensionTable( 'openbadges_class',
		__DIR__ . '/OpenBadgesClass.sql' );
	$dbU->addExtensionTable( 'openbadges_assertion',
		__DIR__ . '/OpenBadgesAssertion.sql' );
	return true;
}

// /**
//  * Add the OpenBadges JS module and variables to the output page. Also make sure a session
//  * is started and a login token is set.
//  *
//  * @param User $user Current user that is logged in
//  * @param OutputPage $out Output page to add scripts to
//  */
// function efPersonaAddScripts( User $user, OutputPage $out ) {
// 	global $wgVersion;

// 	if ( !isset( $_SESSION ) ) {
// 		wfSetupSession();
// 	}
// 	if ( !LoginForm::getLoginToken() ) {
// 		LoginForm::setLoginToken();
// 	}

// 	// Persona requires that IE compatibility mode be disabled
// 	// Add the meta tag here in case MediaWiki core doesn't do it
// 	$out->addMeta( 'http:X-UA-Compatible', 'IE=Edge' );

// 	if ( ResourceLoader::inDebugMode() ) {
// 		$out->addHeadItem( 'openbadges',
// 			Html::linkedScript( 'https://login.persona.org/include.orig.js' ) );
// 	} else {
// 		$out->addHeadItem( 'persona',
// 			Html::linkedScript( 'https://login.persona.org/include.js' ) );
// 	}

// 	if ( version_compare( $wgVersion, '1.20', '<' ) ) {
// 		$out->addModules( 'ext.persona.old' );
// 	} else {
// 		$out->addModules( 'ext.persona' );
// 	}

// 	$out->addJsConfigVars( 'wgPersonaUserEmail',
// 		$user->isEmailConfirmed() ? $user->getEmail() : null );
// }

// /**
//  * Add the OpenBadges module to the OutputPage
//  *
//  * @param OutputPage &$out
//  *
//  * @return bool true
//  */
// function efAddOpenBadgesModule( OutputPage &$out ) {

// 	// Only add the modules if user is logged in


// 	$context = RequestContext::getMain();
// 	efPersonaAddScripts( $context->getUser(), $out );

// 	$out->addHTML( Html::input(
// 		'wpLoginToken',
// 		LoginForm::getLoginToken(),
// 		'hidden'
// 	) );

// 	return true;
// }

