<?php
/**
 * OpenBadges API module to expose BadgeAssertions
 */

class ApiOpenBadgesAssertions extends ApiOpenBadges {

	public function isReadMode() {
		return true;
	}

	public function getAllowedParams() {
		return array(
			'type' => array(
				ApiBase::PARAM_TYPE => array(
					'assertion',
					'badge',
					'issuer',
					'criteria'
				),
				ApiBase::PARAM_REQUIRED => true
			),
			'obl_badge_id' => array(
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_REQUIRED => true
			),
			'obl_receiver' => array(
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_REQUIRED => false
			)
		);
	}

	public function execute() {
		$params = $this->extractRequestParams();
		$requestType = $params['type'];
		$badgeID = $params['obl_badge_id'];

		if ( $requestType == 'assertion' ) {
			if ( !$params['obl_receiver'] ) {
				$this->dieUsage( 'The obl_receiver parameter must be ' .
					'set for type assertion', 'noobl_receiver' );
			}
			$recipient = User::newFromId( $params['obl_receiver'] );
			$this::returnBadgeAssertion( $badgeID, $recipient );
		}
		elseif ( $requestType == 'badge' ) {
			$this::returnBadgeClass( $badgeID );
		}
		elseif ( $requestType == 'issuer' ) {
			$this::returnIssuer( $badgeID );
		}
		elseif ( $requestType == 'criteria' ) {
			$this::returnCriteria( $badgeID );
		}
		// else case is handled automatically by API

	}

	/**
	 * Returns the IssuerOrganization for this wiki
	 */
	public function returnIssuer() {
		global $wgSitename;
		global $wgCanonicalServer;
		$this->getResult()->addValue( null, 'issuer', array(
				'name' => $wgSitename,
				'url' => $wgCanonicalServer,
			)
		);
	}

	/**
	 * Returns the criteria for a certain badge
	 */
	public function returnCriteria( $badgeID ) {
		global $wgSitename;
		global $wgCanonicalServer;
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select(
			array( 'openbadges_class' ),
			'obl_criteria',
			array( 'obl_badge_id = ' . $badgeID )
		);
		$this->getResult()->addValue( null, 'criteria', $res->current()->obl_criteria );
	}

	/**
	 * Returns a specific BadgeAssertion
	 *
	 * Requires a validated e-mail (since e-mail could have been changed
	 * after the badge was awarded)
	 */
	public function returnBadgeAssertion( $badgeID, User $recipient ) {
		global $wgCanonicalServer;
		global $wgScriptPath;
		$apiUrl = $wgCanonicalServer . $wgScriptPath . '/api.php?';
		$res = $this->queryIssuedBadge( $badgeID, $recipient );

		// return error if no hits
		if ( $res->current() == 0 ) {
			$this->dieUsage( 'No badge assertion found for this badge and user', 'inputerror' );
			return;
		}

		// only output for valid users
		$this->dieOnBadRecipient( $recipient );

		// get the unique identifier for this assertion
		$this->getResult()->addValue( null, 'uid',  $res->current()->obl_id );

		// add information about the recipient user
		$hashAlgo = "sha256";
		$hashedEmail = hash( $hashAlgo, $recipient->getEmail() );
		$this->getResult()->addValue( null, 'recipient', array(
				'type' => 'email',
				'hashed' => true,
				'identify' => $hashAlgo . '$' . $hashedEmail,
			)
		);

		// get the url for the badge class JSON
		$classCall = array(
			'action' => 'openbadges',
			'format' => 'json',
			'type' => 'badge',
			'obl_badge_id' => $badgeID
		);
		$this->getResult()->addValue( null, 'badge', $apiUrl . http_build_query( $classCall ) );

		// set how the badge will be verified, same api call as generated this
		$issueCall = array(
			'action' => 'openbadges',
			'format' => 'json',
			'type' => 'assertion',
			'obl_badge_id' => $badgeID,
			'obl_receiver' => $recipient->getId()
		);
		$this->getResult()->addValue( null, 'verify', array(
				'type' => 'hosted',
				'url' => $apiUrl . http_build_query( $issueCall ),
			)
		);

		// get the date that the badge was issued on
		$this->getResult()->addValue(
			null,
			'issuedOn',
			wfTimestamp( TS_ISO_8601, $res->current()->obl_timestamp )
		);

		// get evidence based on which the badge was issued
		// only show if not empty
		if ( !empty( $res->current()->obl_badge_evidence ) ) {
			$this->getResult()->addValue(
				null,
				'evidence',
				$res->current()->obl_badge_evidence
			);
		}

		// get the url to the badge image
		$this->getResult()->addValue(
			null,
			'image',
			$this->imageUrl( $res->current()->obl_badge_image )
		);
	}

	/**
	 * Returns a specific BadgeClass
	 */
	public function returnBadgeClass( $badgeID ) {
		global $wgCanonicalServer;
		global $wgScriptPath;
		$apiUrl = $wgCanonicalServer . $wgScriptPath . '/api.php?';
		// run SQL query to get all relevant info for a BadgeClass JSON
		$res = $this->queryBadge( $badgeID );

		// return error if no hits
		if ( $res->current() == 0 ) {
			$this->dieUsage( 'Badge id not found', 'inputerror' );
			return;
		}
		// get the name of this class
		$this->getResult()->addValue( null, 'name',  $res->current()->obl_name );

		// get the description of this class
		$this->getResult()->addValue( null, 'description',  $res->current()->obl_description );

		// get the url to the badge image
		$this->getResult()->addValue(
				null,
				'image',
				$this->imageUrl( $res->current()->obl_badge_image )
		);

		// get the criteria for this class (an URL)
		$criteriaCall = array(
			'action' => 'openbadges',
			'format' => 'json',
			'type' => 'criteria',
			'obl_badge_id' => $badgeID
		);
		$this->getResult()->addValue( null, 'criteria', $apiUrl . http_build_query( $criteriaCall ) );

		// get the issuer of this class
		$issuerCall = array(
			'action' => 'openbadges',
			'format' => 'json',
			'type' => 'issuer',
			'obl_badge_id' => $badgeID
		);
		$this->getResult()->addValue( null, 'issuer', $apiUrl . http_build_query( $issuerCall ) );
	}

	/**
    * @deprecated since MediaWiki core 1.25
    */
	public function getDescription() {
		return 'Get hosted assertion for an OpenBadge.';
	}

	/**
    * @deprecated since MediaWiki core 1.25
    */
	public function getParamDescription() {
		return array(
			'type' => 'Type of request',
			'obl_badge_id' => 'OpenBadge received from this Wiki',
			'obl_receiver' => 'User id of the user who received the OpenBadge.',
		);
	}
}
