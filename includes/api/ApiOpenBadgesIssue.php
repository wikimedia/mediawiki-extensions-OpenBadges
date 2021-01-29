<?php
/**
 * OpenBadges API module to issue badges
 */

class ApiOpenBadgesIssue extends ApiOpenBadges {

	/** @inheritDoc */
	public function needsToken() {
		return 'csrf';
	}

	/**
	 * Writes to the OpenBadges database and in the future log tables.
	 * @inheritDoc
	 */
	public function isWriteMode() {
		return true;
	}

	/** @inheritDoc */
	public function getTokenSalt() {
		return '';
	}

	/** @inheritDoc */
	public function getAllowedParams() {
		return [
			'obl_badge_id' => [
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_REQUIRED => true
			],
			'obl_receiver' => [
				ApiBase::PARAM_TYPE => 'user',
				ApiBase::PARAM_REQUIRED => false
			],
			'obl_evidence_url' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => false
			]
		];
	}

	public function execute() {
		// not all users are allowed to issue badges
		$user = $this->getUser();
		$this->dieOnBadUser( $user );

		$params = $this->extractRequestParams();
		$badgeID = $params['obl_badge_id'];
		$recipient = $this->getRecipientFromName( $params['obl_receiver'] );
		$evidenceUrl = $params['obl_evidence_url'];

		$this->dieOnBadBadge( $badgeID );
		$this->dieOnBadRecipient( $recipient );

		if ( $this->userAlreadyAwardedBadge( $recipient, $badgeID ) ) {
			$this->markResultSuccess( $recipient, $badgeID );
		} else {
			$this->dieOnBadEvidence( $evidenceUrl );
			$this->issueBadge( $badgeID, $recipient, $evidenceUrl );
		}
	}

	/**
	 * Verify that the user is allowed to make this action
	 *
	 * @param int $badgeID
	 * @param User $recipient
	 * @param string $evidenceUrl
	 */
	public function issueBadge( $badgeID, User $recipient, $evidenceUrl ) {
		$dbw = wfGetDB( DB_MASTER );
		$dbw->startAtomic( __METHOD__ );
		$result = $dbw->insert(
			'openbadges_assertion',
			[
				'obl_timestamp' => $dbw->timestamp(),
				'obl_receiver' => $recipient->getId(),
				'obl_badge_id' => $badgeID,
				'obl_badge_evidence' => $evidenceUrl
			],
			__METHOD__
		);
		$dbw->endAtomic( __METHOD__ );

		$this->markResultSuccess( $recipient, $badgeID );
	}

	/**
	 * Turn userId into a User object or die if invalid
	 *
	 * @param string|null $recipientName
	 * @return User
	 */
	public function getRecipientFromName( $recipientName ) {
		$recipient = User::newFromName( $recipientName );
		if ( !$recipient || $recipient->getId() == 0 ) {
			$this->dieWithError( 'apierror-openbadges-inputerror-norecipient', 'inputerror' );
		}
		return $recipient;
	}

	/**
	 * Check if the user has already been issued the badge
	 *
	 * @param User $recipient
	 * @param int $badgeID
	 * @return bool
	 */
	public function userAlreadyAwardedBadge( User $recipient, $badgeID ) {
		$res = $this->queryIssuedBadge( $badgeID, $recipient );
		if ( $res->current() == 0 ) {
			return false;
		}
		return true;
	}

	/**
	 * Verify that the user is allowed to make this action
	 *
	 * @param User $user
	 */
	public function dieOnBadUser( User $user ) {
		if ( $user->isAnon() ) {
			$this->dieWithError( 'apierror-openbadges-notloggedin', 'notloggedin' );
		} elseif ( !$user->isAllowed( 'issuebadge' ) ) {
			$this->dieWithError( 'apierror-openbadges-notissuebadgeright',
				'notissuebadgeright' );
		}
	}

	/**
	 * Verify that the evidence is correctly formated
	 *
	 * @param string|null $url
	 */
	public function dieOnBadEvidence( $url ) {
		if ( $url != '' && !SpecialBadgeIssue::isURL( $url ) ) {
			$this->dieWithError( 'apierror-openbadges-badevidence', 'badEvidence' );
		}
	}

	/**
	 * Format a successful response
	 *
	 * @param User $recipient
	 * @param int $badgeID
	 */
	protected function markResultSuccess( User $recipient, $badgeID ) {
		$this->getResult()->addValue( null, 'result', [
			'success' => 1,
			'recipient' => $recipient->getName(),
			'badge' => $badgeID,
		] );
	}
}
