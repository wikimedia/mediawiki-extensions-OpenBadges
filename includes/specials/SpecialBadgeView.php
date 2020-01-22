<?php
/**
 * OpenBadges special page to view all the badges issued to a user.
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
	 */
	public function execute( $subpage ) {
		$this->setHeaders();
		$this->checkPermissions();
		$this->outputHeader();

		$pager = new BadgesPager();
		$html = $this->getOutput();
		$html->addHTML(
			$pager->getNavigationBar() . '<ol>' .
			$pager->getBody() . '</ol>' .
			$pager->getNavigationBar()
		);
	}

	/**
	 * @return string
	 */
	protected function getGroupName() {
		return 'other';
	}
}

/**
 * @ingroup SpecialPage Pager
 */
class BadgesPager extends TablePager {

	private $mFieldNames;

	/**
	 * Request all badges issued to the current user
	 *
	 * @return array
	 */
	function getQueryInfo() {
		global $wgUser;
		$userId = $wgUser->getId();

		return [
			'tables' => [ 'openbadges_assertion', 'openbadges_class' ],
			'fields' => [
				'obl_name',
				'obl_badge_image',
				'openbadges_assertion.obl_badge_id AS badge_id',
				'obl_badge_evidence' ],
			'conds' => 'obl_receiver = ' . $userId,
			'join_conds' => [
				'openbadges_class' => [
					'INNER JOIN',
					'openbadges_assertion.obl_badge_id = openbadges_class.obl_badge_id' ] ]
		];
	}

	/**
	 * @return string
	 */
	function getIndexField() {
		return 'obl_name';
	}

	/**
	 * @param string $field
	 * @return bool
	 */
	function isFieldSortable( $field ) {
		$sortable = [ 'obl_name', 'obl_badge_evidence' ];
		return in_array( $field, $sortable );
	}

	/**
	 * @return string
	 */
	function getDefaultSort() {
		return 'obl_name';
	}

	/**
	 * @return array
	 */
	function getFieldNames() {
		if ( !$this->mFieldNames ) {
			$this->mFieldNames = [
				'obl_name' => $this->msg( 'ob-view-name' )->text(),
				'obl_badge_image' => $this->msg( 'ob-view-image' )->text(),
				'badge_id' => $this->msg( 'ob-view-proof-header' )->text(),
				'obl_badge_evidence' => $this->msg( 'ob-view-evidence-header' )->text(),
			];
		}
		return $this->mFieldNames;
	}

	/**
	 * @param string $field
	 * @param string|null $value
	 * @return string
	 * @throws MWException
	 */
	function formatValue( $field, $value ) {
		global $wgScriptPath, $wgCanonicalServer;
		global $wgUser;
		$apiUrl = $wgCanonicalServer . $wgScriptPath . '/api.php?';
		$userId = $wgUser->getId();

		switch ( $field ) {
			case 'obl_name':
				return htmlspecialchars( $value );
			case 'obl_badge_image':
				$file = wfFindFile( $value );
				$badgeImage = $file->transform( [ 'width' => 180, 'height' => 360 ] );
				$thumb = $badgeImage->toHtml( [ 'desc-link' => true ] );
				return $thumb;
			case 'badge_id':
				$assertCall = [
					'action' => 'openbadges',
					'format' => 'json',
					'type' => 'assertion',
					'obl_badge_id' => $value,
					'obl_receiver' => $userId
				];
				$assertLink = Html::rawElement(
					'a',
					[ 'href' => $apiUrl . http_build_query( $assertCall ) ],
					wfMessage( 'ob-view-proof' )->text()
				);
				return $assertLink;
			case 'obl_badge_evidence':
				if ( empty( $value ) ) {
					return wfMessage( 'ob-view-no-evidence' )->text();
				} else {
					$evidenceLink = Html::rawElement(
						'a',
						[ 'href' => $value ],
						wfMessage( 'ob-view-evidence' )->text()
					);
					return $evidenceLink;
				}
			default:
				throw new MWException( "Unknown field '$field'" );
		}
	}
}
