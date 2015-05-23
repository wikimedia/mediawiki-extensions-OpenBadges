<?php
/**
 * OpenBadges special page to view all the badges assigned to a user.
 *
 * TODO: rebuild using TablePager or something like SpecialListFiles
 *
 * @file
 * @ingroup Extensions
 */

class SpecialBadgeView extends SpecialPage {
	public function __construct() {
		parent::__construct( 'BadgeView', 'viewbadge' );
	}

	/**
	 * Shows the page to the user.
	 * @param string $sub: The subpage string argument (if any).
	 *  [[Special:BadgeManager/subpage]].
	 */
	public function execute( $sub ) {
		$this->setHeaders();
		$this->checkPermissions();
		$this->outputHeader();

		$html = $this->getOutput();
		$html->addHtml( $this->getBadgeHtml() );
	}

	public function getBadgeHtml() {
		global $wgUser;
		global $wgScriptPath;
		$apiUrl = $wgCanonicalServer . $wgScriptPath . '/api.php?';

		$userId = $wgUser->getId();

		$dbr = wfGetDB( DB_SLAVE );
		$badgeRes = $dbr->select(
			array( 'openbadges_assertion', 'openbadges_class' ),
			array(
				'obl_name',
				'openbadges_class.obl_badge_image',
				'openbadges_assertion.obl_badge_id' ),
			'obl_receiver = ' . $userId,
			__METHOD__,
			array(),
			array(
				'openbadges_class' => array(
					'INNER JOIN', array (
						'openbadges_assertion.obl_badge_id=openbadges_class.obl_badge_id' ) ) )
		);

		$badgeTr = '';
		foreach ( $badgeRes as $row ) {
			$badgeName = Html::element( 'td', array(), $row->obl_name );
			$file = wfFindFile( $row->obl_badge_image );
			$badgeImage = $file->transform( array( 'width' => 180, 'height' => 360 ) );
			$thumb = $badgeImage->toHtml( array( 'desc-link' => true ) );
			$thumb = Html::rawElement( 'td', array(), $thumb );
			$assertCall = array(
				'action' => 'openbadges',
				'format' => 'json',
				'obl_badge_id' => $row->obl_badge_id,
				'obl_receiver' => $userId
			);
			$assertLink = Html::rawElement(
				'a',
				array( 'href' => $apiUrl . http_build_query( $assertCall ) ),
				wfMessage( 'ob-view-proof' )
			);
			$assertLink =  Html::rawElement( 'td', array(), $assertLink );
			$badgeTr .= Html::rawElement( 'tr', array(), $badgeName . $thumb . $assertLink );
		}

		$badgeTable = Html::rawElement(
			'table',
			array( 'style' => 'width:100%', 'border' => '1' ),
			$badgeTr
		);

		return $badgeTable;
	}

}
