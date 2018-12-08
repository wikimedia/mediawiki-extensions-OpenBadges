<?php
if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'OpenBadges' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['OpenBadges'] = __DIR__ . '/i18n';
	$wgExtensionMessagesFiles['OpenBadgesAlias'] = __DIR__ . '/OpenBadges.i18n.alias.php';
	wfWarn(
		'Deprecated PHP entry point used for the OpenBadges extension. ' .
		'Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	);
	return;
} else {
	die( 'This version of the OpenBadges extension requires MediaWiki 1.29+' );
}
