<?php
/**
 * OpenBadges API module to issue badges
 */

class ApiOpenBadgesIssue extends ApiOpenBadges {

	public function needsToken() {
		return 'csrf';
	}

	// Writes to the OpenBadges database and in the future log tables.
	public function isWriteMode() {
		return true;
	}

	public function getTokenSalt() {
		return '';
	}

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
	 * @param string|NULL $recipientName
	 * @return User
	 */
	public function getRecipientFromName( $recipientName ) {
		$recipient = User::newFromName( $recipientName );
		if ( !$recipient || $recipient->getId() == 0 ) {
			$this->dieUsage( 'Could not find a recipient with that id', 'inputerror' );
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
			$this->dieUsage( 'Anonymous users cannot issue badges', 'notloggedin' );
		} elseif ( !$user->isAllowed( 'issuebadge' ) ) {
			$this->dieUsage( "The 'issuebadge' right is required to issue badges",
				'notissuebadgeright' );
		}
	}

	/**
	 * Verify that the evidence is correctly formated
	 *
	 * @param string|NULL $url
	 */
	public function dieOnBadEvidence( $url ) {
		if ( $url != '' && !SpecialBadgeIssue::isURL( $url ) ) {
			$this->dieUsage( 'Evidence must be blank or an url', 'badEvidence' );
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

	/**
	 * @deprecated since MediaWiki core 1.25
	 */
	public function getDescription() {
		return 'Issue an OpenBadge to a user.';
	}

	/**
	 * @deprecated since MediaWiki core 1.25
	 */
	public function getParamDescription() {
		return [
			'obl_badge_id' => 'OpenBadge to issue from this Wiki.',
			'obl_receiver' => 'User name of the user who will receive the OpenBadge.',
			'obl_evidence_url' => 'Url to evidence for user meeting the OpenBadge criteria.'
		];
	}
}
