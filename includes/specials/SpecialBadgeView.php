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
	 * @param string|null $subpage
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
