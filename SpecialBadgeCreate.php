<?php
/**
 * OpenBadges special page to add new badge types to the database.
 *
 * @file
 * @ingroup Extensions
 */

class SpecialBadgeCreate extends FormSpecialPage {

	public function __construct() {
		parent::__construct( 'BadgeCreate', 'createbadge' );
	}

	/**
	 * @return string
	 */
	public function getMessagePrefix() {
		return 'badge-create';
	}

	/**
	 * @return array form fields
	 */
	public function getFormFields() {
		return array(
			'Name' => array(
				'label-message' => 'ob-create-badge-name',
				'type' => 'text',
				'required' => true,
				'validation-callback' => array( 'SpecialBadgeCreate', 'validateName' ),
				'filter-callback' => array( 'SpecialBadgeIssue', 'toDBkey' ),
			),
			'Image' => array(
				'label-message' => 'ob-create-badge-image',
				'type' => 'text',
				'required' => true,
				'validation-callback' => array( 'SpecialBadgeCreate', 'validateImage' ),
			),
			'Description' => array(
				'label-message' => 'ob-create-badge-description',
				'type' => 'textarea',
				'required' => true,
				'cols' => 30,
				'rows' => 5,
			),
			'Criteria' => array(
				'label-message' => 'ob-create-badge-criteria',
				'type' => 'textarea',
				'required' => true,
				'cols' => 30,
				'rows' => 5,
			),
		);
	}

	/**
	 * @param array $data
	 * @return Status|bool
	 */
	public function onSubmit( array $data ) {
		// Inserts the new assertion into the database
		$dbw = wfGetDB( DB_MASTER );
		$dbw->startAtomic( __METHOD__ );
		$result = $dbw->insert(
			'openbadges_class',
			array(
				'obl_name' => $data['Name'],
				'obl_description' => $data['Description'],
				'obl_badge_image' => $data['Image'],
				'obl_criteria' => $data['Criteria'],
			),
			__METHOD__
		);
		$dbw->endAtomic( __METHOD__);
		return $result;
	}

	/**
	 * Validates that a file exists
	 *
	 * error-msg|@bool
	 */
	 public function validateImage( $imageTitle, $allData ) {
		 if ( $imageTitle == '' ) {
			 return wfMessage( 'htmlform-required' );
		 }
		 $badgeFile = wfFindFile( $imageTitle );
		 if ( $badgeFile === false ) {
			 return wfMessage( 'ob-create-no-image' );
		 }
		 $mimetype = $badgeFile->getMimeType();
		 if ( $mimetype != 'image/png' && $mimetype != 'image/svg+xml' ) {
			 return wfMessage( 'ob-create-wrong-mime' );
		 }
		 return true;
	 }

	/**
	 * Validates that a badge name does't exists
	 *
	 * error-msg|@bool
	 */
	public function validateName( $badgeTitle, $allData ) {
		if ( $badgeTitle == '' ) {
			return wfMessage( 'htmlform-required' );
		}
		$dbr = wfGetDB( DB_MASTER );
		$badgeRow = $dbr->selectRow(
			'openbadges_class',
			'*',
			array( 'obl_name' => $badgeTitle )
		);
		if ( $badgeRow ) {
			return wfMessage( 'ob-create-name-exists' );
		}
		return true;
	 }


	public function onSuccess() {
		$this->getOutput()->addWikiMsg( 'ob-create-success' );
	}

	protected function getGroupName() {
		return 'other';
	}
}
