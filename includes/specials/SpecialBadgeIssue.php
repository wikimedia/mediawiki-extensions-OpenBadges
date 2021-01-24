<?php
/**
 * OpenBadges special page to issue new badges to users
 *
 * @file
 * @ingroup Extensions
 */

class SpecialBadgeIssue extends FormSpecialPage {

	public function __construct() {
		parent::__construct( 'BadgeIssue', 'issuebadge' );
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
		return [
			'Name' => [
				'type' => 'text',
				'label-message' => 'ob-issue-user',
				'required' => true,
				'filter-callback' => [ 'SpecialBadgeIssue', 'toDBkey' ],
				'validation-callback' => [ 'SpecialBadgeIssue', 'validateUser' ],
			],
			'BadgeId' => [
				'type' => 'select',
				'label-message' => 'ob-issue-type',
				'required' => true,
				'options' => self::getAllBadges(),
			],
			'Evidence' => [
				'type' => 'text',
				'label-message' => 'ob-issue-evidence',
				'required' => false,
				'validation-callback' => [ 'SpecialBadgeIssue', 'validateEvidence' ],
			],
		];
	}

	/**
	 * Converts the suggested title to the needed db form
	 *
	 * @param string $title
	 * @param array $alldata
	 * @return string|void
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
			[ 'obl_name', 'obl_badge_id' ]
		);
		$names = [];
		// MWDebug::log(print_r($res));
		foreach ( $res as $row ) {
			$names[$row->obl_name] = $row->obl_badge_id;
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
		$dbw->startAtomic( __METHOD__ );
		$result = $dbw->insert(
			'openbadges_assertion',
			[
				'obl_timestamp' => $dbw->timestamp(),
				'obl_receiver' => $status->value['Receiver'],
				'obl_badge_id' => $status->value['BadgeId'],
				'obl_badge_evidence' => $status->value['Evidence']
			],
			__METHOD__
		);
		$dbw->endAtomic( __METHOD__ );
		return $result;
	}

	/**
	 * Check if the user exists
	 *
	 * @param string $userName
	 * @param array $alldata
	 * @return Message|true
	 */
	public static function validateUser( $userName, $alldata ) {
		global $wgOpenBadgesRequireEmail;
		global $wgOpenBadgesRequireEmailConfirmation;
		if ( $userName == '' ) {
			return wfMessage( 'htmlform-required' );
		}
		$recipient = User::newFromName( $userName );

		// Check that recipient exists
		if ( !$recipient || $recipient->getId() == 0 ) {
			return wfMessage( 'ob-db-user-not-found' );
		}

		// Verify that the recipient e-mail settings are suitable
		if ( $wgOpenBadgesRequireEmail && !$recipient->getEmail() ) {
			return wfMessage( 'ob-db-user-no-email', $recipient->getName() );
		}
		if ( $wgOpenBadgesRequireEmailConfirmation && !$recipient->isEmailConfirmed() ) {
			return wfMessage( 'ob-db-user-no-email-confirmation', $recipient->getName() );
		}

		return true;
	}

	/**
	 * Check if the evidence is a URL or empty
	 *
	 * @param string $url
	 * @param array $alldata
	 * @return Message|true
	 */
	public static function validateEvidence( $url, $alldata ) {
		if ( $url == '' ) {
			return true;
		} elseif ( !self::isURL( $url ) ) {
			return wfMessage( 'ob-db-evidence-not-url' );
		}
		return true;
	}

	/**
	 * Check if string starts with https or http protocol
	 *
	 * @param string $url
	 * @return bool
	 */
	public static function isURL( $url ) {
		if ( substr( $url, 0, strlen( 'http://' ) ) === 'http://' ) {
			return true;
		} elseif ( substr( $url, 0, strlen( 'https://' ) ) === 'https://' ) {
			return true;
		}
		return false;
	}

	/**
	 * Validates whether the user and badge exists. Returns a good Status and
	 * the relevant Open Badge assertion fields if it does. Otherwise, returns
	 * an error Status.
	 *
	 * @param array $data
	 * @return Status
	 */
	public static function validateFormFields( array $data ) {
		$fields = '*';

		$dbr = wfGetDB( DB_MASTER );
		$user = User::newFromName( $data['Name'] );

		$badgeRow = $dbr->selectRow(
			'openbadges_class',
			$fields,
			[ 'obl_badge_id' => $data['BadgeId'] ]
		);

		$evidence = $data['Evidence'] == '' ? null : $data['Evidence'];

		if ( $user->getId() && $badgeRow ) {
			// check if same badge-user combo already issued
			$issued = $dbr->selectRow(
				'openbadges_assertion',
				$fields,
				[
					'obl_badge_id' => $data['BadgeId'],
					'obl_receiver' => $user->getId(),
				]
			);
			if ( $issued ) {
				$status = Status::newFatal( 'ob-db-error-issued' );
			} else {
				$assertionRes = [
					'Receiver' => $user->getId(),
					'BadgeId' => $badgeRow->obl_badge_id,
					'Evidence' => $evidence
				];
				$status = Status::newGood( $assertionRes );
			}
		} else {
			// Error handling
			$status = Status::newGood();

			// Error case was not caught, error unknown
			if ( $status->isOK() ) {
				$status->fatal( 'ob-db-unknown-error' );
			}
		}

		return $status;
	}

	/** @inheritDoc */
	public function onSuccess() {
		$this->getOutput()->addWikiMsg( 'ob-issue-success' );
	}

	/** @inheritDoc */
	protected function getGroupName() {
		return 'other';
	}
}
