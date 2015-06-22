<?php
/**
 * OpenBadges special page to assign new badges to users
 *
 * @file
 * @ingroup Extensions
 */

class SpecialBadgeIssue extends FormSpecialPage {
	/** @var LoginForm **/
	private $mLoginForm;

	public function __construct() {
		parent::__construct( 'BadgeIssue', 'issuebadge' );
		$this->mLoginForm = new LoginForm();
	}

	/**
	 * @return string
	 */
	public function getMessagePrefix() {
		return 'badge-issue';
	}

	/**
	 * @return array form fields
	 */
	public function getFormFields() {
		return array(
			'Name' => array(
				'type' => 'text',
				'label-message' => 'ob-issue-user',
				'required' => true,
				'filter-callback' => array( 'SpecialBadgeIssue', 'toDBkey' ),
				'validation-callback' => array( 'SpecialBadgeIssue', 'validateUser' ),
			),
			'BadgeId' => array(
				'type' => 'select',
				'label-message' => 'ob-issue-type',
				'required' => true,
				'options' => self::getAllBadges(),
			),
		);
	}

	/**
	 * Converts the suggested title to the needed db form
	 *
	 * @return string
	 */
	static function toDBkey( $title, $alldata ) {
		if ( !$title ) {
			return;
		}
		$titleObject = Title::newFromText( $title );
		return $titleObject->getDBKey();
	}

	/**
	 * query DB for all badge titles
	 *
	 * @return array
	 */
	public function getAllBadges() {
		$dbr = wfGetDB( DB_MASTER );
		$res = $dbr->select(
			'openbadges_class',
			array( 'obl_name', 'obl_badge_id' )
		);
		$names = array();
		// MWDebug::log(print_r($res));
		foreach ( $res as $row ) {
			$names[$row->obl_name] = $row->obl_badge_id ;
		}
		return $names;
	}

	/**
	 * @param array $data
	 * @return Status|bool
	 */
	public function onSubmit( array $data ) {
		$status = self::validateFormFields( $data );

		if ( !$status->isOK() ) {
			return $status;
		}

		// Inserts the new assertion into the database
		$dbw = wfGetDB( DB_MASTER );
		$dbw->begin();
		$result = $dbw->insert(
			'openbadges_assertion',
			array(
				'obl_timestamp' => wfTimestamp(),
				'obl_receiver' => $status->value['Receiver'],
				'obl_badge_id' => $status->value['BadgeId']
			),
			__METHOD__
		);
		$dbw->commit();
		return $result;
	}

	/**
	 * Check if the user exists
	 *
	 * @return bool
	 */
	public function validateUser( $userName, $alldata ) {
		if ( $userName == '' ) {
			return wfMessage( 'htmlform-required' );
		}
		$dbr = wfGetDB( DB_MASTER );
		$userRow = $dbr->selectRow(
			'user',
			'*',
			array( 'user_name' => $userName )
		);
		if ( !$userRow ) {
			return wfMessage( 'ob-db-user-not-found' );
		}
		return true;
	}


	/**
	 * Validates whether the user and badge exists. Returns a good Status and
	 * the relevant Open Badge assertion fields if it does. Otherwise, returns
	 * an error Status.
	 *
	 * @return Status
	 */
	public function validateFormFields( array $data ) {
		$fields = '*';

		$dbr = wfGetDB( DB_MASTER );
		$userRow = $dbr->selectRow(
			'user',
			$fields,
			array( 'user_name' => $data['Name'] )
		);

		$badgeRow = $dbr->selectRow(
			'openbadges_class',
			$fields,
			array( 'obl_badge_id' => $data['BadgeId'] )
		);

		if ( $userRow && $badgeRow ) {
			// check if same badge-user combo already issued
			$issued = $dbr->selectRow(
				'openbadges_assertion',
				$fields,
				array(
					'obl_badge_id' => $data['BadgeId'],
					'obl_receiver' => $userRow->user_id,
				)
			);
			if ( $issued ) {
				$status = Status::newFatal( 'ob-db-error-issued' );
			}
			else {
				$assertionRes = array(
					'Receiver' => $userRow->user_id,
					'BadgeId' => $badgeRow->obl_badge_id
				);
				$status = Status::newGood( $assertionRes );
			}
		}
		// Error handling
		else {
			$status = Status::newGood();

			// Error case was not caught, error unknown
			if ( $status->isOK() ) {
				$status->fatal( 'ob-db-unknown-error' );
			}
		}

		return $status;
	}

	public function onSuccess() {
		$this->getOutput()->addWikiMsg( 'ob-issue-success' );
	}

	protected function getGroupName() {
		return 'other';
	}
}
