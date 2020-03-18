<?php
/**
 * @file
 * @ingroup Extensions
 */

// OpenBadges hooks
class OpenBadgesHooks {

	/**
	 * LoadExtensionSchemaUpdates hook
	 *
	 * @param DatabaseUpdater|null $updater
	 *
	 * @return bool
	 */
	public static function onLoadExtensionSchemaUpdates( $updater = null ) {
		$updater->addExtensionTable( 'openbadges_class',
			__DIR__ . '/../sql/OpenBadgesClass.sql' );
		$updater->addExtensionTable( 'openbadges_assertion',
			__DIR__ . '/../sql/OpenBadgesAssertion.sql' );
		return true;
	}
}
