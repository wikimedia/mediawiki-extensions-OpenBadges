<?php
/**
 * OpenBadges API module to expose BadgeAssertions
 */

class ApiOpenBadgesAssertions extends ApiOpenBadges {

	public function isReadMode() {
		return true;
	}

	public function getAllowedParams() {
		return [
			'type' => [
				ApiBase::PARAM_TYPE => [
					'assertion',
					'badge',
					'issuer',
					'criteria'
				],
				ApiBase::PARAM_REQUIRED => true
			],
			'obl_badge_id' => [
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_REQUIRED => false
			],
			'obl_receiver' => [
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_REQUIRED => false
			]
		];
	}

	public function execute() {
		$params = $this->extractRequestParams();
		$requestType = $params['type'];
		$badgeID = $params['obl_badge_id'];

		// obl_badge_id is only optional for issuer
		if ( !$badgeID && $requestType != 'issuer' ) {
			$this->dieUsage( 'The obl_badge_id parameter must be ' .
				'set for any type other than issuer', 'noobl_badge_id' );
		}

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
		elseif ( $requestType == 'criteria' ) {
			$this::returnCriteria( $badgeID );
		}
		elseif ( $requestType == 'issuer' ) {
			$this::returnIssuer();
		}
		// else case is handled automatically by API

	}

	/**
	 * Returns the IssuerOrganization for this wiki
	 */
	public function returnIssuer() {
		global $wgSitename;
		global $wgCanonicalServer;

		// Required for v. 1.1
		$this->getResult()->addValue( null, '@context', 'https://w3id.org/openbadges/v1' );
		$this->getResult()->addValue( null, 'type', 'Issuer' );
		$this->getResult()->addValue( null, 'id', $this->issuerUrl() );

		$this->getResult()->addValue( null, 'name', $wgSitename );
		$this->getResult()->addValue( null, 'url', $wgCanonicalServer );
	}

	/**
	 * Returns the criteria for a certain badge
	 *
	 * @param int $badgeID
	 */
	public function returnCriteria( $badgeID ) {
		global $wgSitename;
		global $wgCanonicalServer;
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select(
			[ 'openbadges_class' ],
			'obl_criteria',
			[ 'obl_badge_id = ' . $badgeID ]
		);
		$this->getResult()->addValue( null, 'criteria', $res->current()->obl_criteria );
	}

	/**
	 * Returns a specific BadgeAssertion
	 *
	 * Requires a validated e-mail (since e-mail could have been changed
	 * after the badge was awarded)
	 *
	 * @param int $badgeID
	 * @param User $recipient
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

		// Api url for this call
		$assertionUrl = $this->assertionUrl( $badgeID, $recipient );

		// Required for v. 1.1
		$this->getResult()->addValue( null, '@context', 'https://w3id.org/openbadges/v1' );
		$this->getResult()->addValue( null, 'type', 'Assertion' );
		$this->getResult()->addValue( null, 'id', $assertionUrl );

		// get the unique identifier for this assertion
		$this->getResult()->addValue( null, 'uid', $res->current()->obl_id	 );

		// add information about the recipient user
		$hashAlgo = "sha256";
		$hashedEmail = hash( $hashAlgo, $recipient->getEmail() );
		$this->getResult()->addValue( null, 'recipient', [
				'type' => 'email',
				'hashed' => true,
				'identity' => $hashAlgo . '$' . $hashedEmail,
			]
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

		// get the date that the badge was issued on
		$this->getResult()->addValue(
			null,
			'issuedOn',
			wfTimestamp( TS_ISO_8601, $res->current()->obl_timestamp )
		);

		// get the url for the badge class JSON
		$this->getResult()->addValue( null, 'badge', $this->classUrl( $badgeID ) );

		// set how the badge will be verified
		$this->getResult()->addValue( null, 'verify', [
				'type' => 'hosted',
				'url' => $assertionUrl,
			]
		);

		// only a baked image should be provided here
		// get the url to the badge image
		// $this->getResult()->addValue(
		//	null,
		//	'image',
		//	$this->imageUrl( $res->current()->obl_badge_image )
		// );
	}

	/**
	 * Returns a specific BadgeClass
	 *
	 * @param int $badgeID
	 */
	public function returnBadgeClass( $badgeID ) {
		// run SQL query to get all relevant info for a BadgeClass JSON
		$res = $this->queryBadge( $badgeID );

		// return error if no hits
		if ( $res->current() == 0 ) {
			$this->dieUsage( 'Badge id not found', 'inputerror' );
			return;
		}

		// Required for v. 1.1
		$this->getResult()->addValue( null, '@context', 'https://w3id.org/openbadges/v1' );
		$this->getResult()->addValue( null, 'type', 'BadgeClass' );
		$this->getResult()->addValue( null, 'id', $this->classUrl( $badgeID ) );

		// get the name of this class
		$this->getResult()->addValue( null, 'name', $res->current()->obl_name );

		// get the description of this class
		$this->getResult()->addValue( null, 'description', $res->current()->obl_description );

		// get the url to the badge image
		$this->getResult()->addValue(
				null,
				'image',
				$this->imageUrl( $res->current()->obl_badge_image )
		);

		// get the criteria for this class (an URL)
		$this->getResult()->addValue( null, 'criteria', $this->criteriaUrl( $badgeID ) );

		// get the issuer of this class
		$this->getResult()->addValue( null, 'issuer', $this->issuerUrl() );
	}

	/**
	 * Generate url for an api assertion call
	 *
	 * @param int $badgeID
	 * @param User $recipient
	 * @return string
	 */
	public function assertionUrl( $badgeID, User $recipient ) {
		$call = [
			'action' => 'openbadges',
			'format' => 'json',
			'type' => 'assertion',
			'obl_badge_id' => $badgeID,
			'obl_receiver' => $recipient->getId()
		];
		return $this->callToUrl( $call );
	}

	/**
	 * Generate url for an api class call
	 *
	 * @param int $badgeID
	 * @return string
	 */
	public function classUrl( $badgeID ) {
		$call = [
			'action' => 'openbadges',
			'format' => 'json',
			'type' => 'badge',
			'obl_badge_id' => $badgeID
		];
		return $this->callToUrl( $call );
	}

	/**
	 * Generate url for an api criteria call
	 *
	 * @param int $badgeID
	 * @return string
	 */
	public function criteriaUrl( $badgeID ) {
		$call = [
			'action' => 'openbadges',
			'format' => 'json',
			'type' => 'criteria',
			'obl_badge_id' => $badgeID
		];
		return $this->callToUrl( $call );
	}

	/**
	 * Generate url for an api issuer call
	 *
	 * @return string
	 */
	public function issuerUrl() {
		$call = [
			'action' => 'openbadges',
			'format' => 'json',
			'type' => 'issuer'
		];
		return $this->callToUrl( $call );
	}

	/**
	 * Create a full url from an api call
	 *
	 * @param array $call
	 * @return string
	 */
	public function callToUrl( Array $call ) {
		global $wgCanonicalServer;
		global $wgScriptPath;
		$apiUrl = $wgCanonicalServer . $wgScriptPath . '/api.php?';
		return $apiUrl . http_build_query( $call );
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
		return [
			'type' => 'Type of request',
			'obl_badge_id' => 'OpenBadge received from this Wiki',
			'obl_receiver' => 'User id of the user who received the OpenBadge.',
		];
	}
}
