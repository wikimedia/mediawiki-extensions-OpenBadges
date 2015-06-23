<?php
/**
 * OpenBadges API module to expose BadgeAssertions
 *
 * If just class is given then this returns a BadgeClass
 * if user is also given then this returns a BadgeAssertion
 *
 */

class ApiOpenBadges extends ApiBase {

	public function getVersion() {
		return __CLASS__ . ': $Id$';
	}

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
		$requestType = $this->getMain()->getVal( 'type' );
		$badgeID = $this->getMain()->getVal( 'obl_badge_id' );
		$receiverID = $this->getMain()->getVal( 'obl_receiver' );

		if ( $requestType == 'assertion' ) {
			if ( $receiverID ) {
				$this::returnBadgeAssertion( $badgeID, $receiverID );
			}
			else {
				 $this->dieUsage( 'The obl_receiver parameter must be ' .
					'set for type assertion', 'noobl_receiver' );
			}
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
	 */
	public function returnBadgeAssertion( $badgeID, $receiverID ) {
		global $wgCanonicalServer;
		global $wgScriptPath;
		$apiUrl = $wgCanonicalServer . $wgScriptPath . '/api.php?';
		// run SQL query to get all relevant info for a BadgeAssertion JSON
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select(
			array( 'openbadges_assertion', 'openbadges_class', 'user' ),
			'*',
			array(
				'openbadges_assertion.obl_badge_id = ' . $badgeID,
				'obl_receiver = ' . $receiverID,
			),
			__METHOD__,
			array(),
			array(
				'user' => array(
					'INNER JOIN', array (
						'openbadges_assertion.obl_receiver=user.user_id'
					)
				),
				'openbadges_class' => array(
					'INNER JOIN', array (
						'openbadges_assertion.obl_badge_id=openbadges_class.obl_badge_id'
					)
				)
			)
		);

		// return error if no hits
		if ( $res->current() == 0 ) {
			$this->dieUsage( 'No badge assertion found for this badge and user', 'inputerror' );
			return;
		}

		// get the unique identifier for this assertion
		$this->getResult()->addValue( null, 'uid',  $res->current()->obl_id );

		// add information about the recipient user
		// @todo email could also be salted
		$hashAlgo = "sha256";
		$hashedEmail = hash( $hashAlgo, $res->current()->user_email );
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
			'obl_receiver' => $receiverID
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
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select(
			array( 'openbadges_class' ),
			'*',
			array( 'obl_badge_id = ' . $badgeID )
		);

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
	 * Given an image filename this returns the file url if a png
	 * or a thumb-file url if an svg
	 *
	 * @todo don't hardcode thumb width
	 *
	 * @return string
	 */
	public function imageUrl( $filename ) {
		global $wgCanonicalServer;
		global $wgScriptPath;
		$thumbUrl = $wgCanonicalServer . $wgScriptPath . '/thumb.php?';
		$file = wfFindFile( $filename );
		$mimetype = $file->getMimeType();

		if ( $mimetype == 'image/png' ) {
			return $file->getCanonicalUrl();
		}
		elseif ( $mimetype == 'image/svg+xml' ) {
			// need to get png thumb
			$thumbCall = array(
				'f' => $file->getName(),
				'width' => 400
			);
			return $thumbUrl . http_build_query( $thumbCall );
		}
		else {
			// you should never end up here, throw an error
			$this->dieUsage( 'Illegal filetyp for badge', 'imageerror' );
		}

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
			'obl_receiver' => 'User who received the OpenBadge.',
		);
	}
}
