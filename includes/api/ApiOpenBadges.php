<?php
/**
 * OpenBadges API base module
 *
 * If just class is given then this returns a BadgeClass
 * if user is also given then this returns a BadgeAssertion
 *
 */

use MediaWiki\MediaWikiServices;

abstract class ApiOpenBadges extends ApiBase {

	/**
	 * Given an image filename this returns the file url if a png
	 * or a thumb-file url if an svg
	 *
	 * @param string $filename
	 * @return string
	 */
	protected function imageUrl( $filename ) {
		global $wgCanonicalServer;
		global $wgScriptPath;
		global $wgOpenBadgesThumb;
		$thumbUrl = $wgCanonicalServer . $wgScriptPath . '/thumb.php?';
		if ( method_exists( MediaWikiServices::class, 'getRepoGroup' ) ) {
			// MediaWiki 1.34+
			$file = MediaWikiServices::getInstance()->getRepoGroup()->findFile( $filename );
		} else {
			$file = wfFindFile( $filename );
		}
		$mimetype = $file->getMimeType();

		if ( $mimetype == 'image/png' ) {
			return $file->getCanonicalUrl();
		} elseif ( $mimetype == 'image/svg+xml' ) {
			// need to get png thumb
			$thumbCall = [
				'f' => $file->getName(),
				'width' => $wgOpenBadgesThumb
			];
			return $thumbUrl . http_build_query( $thumbCall );
		} else {
			// you should never end up here, throw an error
			$this->dieWithError( 'apierror-openbadges-imageerror', 'imageerror' );
		}
	}

	/**
	 * Verify that the badge exists
	 *
	 * @param int $badgeID
	 */
	protected function dieOnBadBadge( $badgeID ) {
		$res = $this->queryBadge( $badgeID );
		if ( $res->current() == 0 ) {
			$this->dieWithError( 'apierror-openbadges-inputerror-nobadgeid', 'inputerror' );
		}
	}

	/**
	 * Verify that the recipient is suitable
	 *
	 * @param User $recipient
	 */
	protected function dieOnBadRecipient( User $recipient ) {
		global $wgOpenBadgesRequireEmail;
		global $wgOpenBadgesRequireEmailConfirmation;
		if ( $wgOpenBadgesRequireEmail && !$recipient->getEmail() ) {
			$this->dieWithError( 'apierror-openbadges-noemail', 'noemail' );
		} elseif ( $wgOpenBadgesRequireEmailConfirmation && !$recipient->isEmailConfirmed() ) {
			$this->dieWithError( 'apierror-openbadges-noemailconfirmed',
				'noemailconfirmed' );
		}
	}

	/**
	 * Run SQL query to get all info about a badge
	 *
	 * @param int $badgeID
	 * @return ResultWrapper|bool
	 */
	protected function queryBadge( $badgeID ) {
		$dbr = wfGetDB( DB_REPLICA );
		$res = $dbr->select(
			[ 'openbadges_class' ],
			'*',
			[ 'obl_badge_id' => $badgeID ]
		);
		return $res;
	}

	/**
	 * Run SQL query to get all relevant info for an issued badge
	 *
	 * @param int $badgeID
	 * @param User $recipient
	 * @return ResultWrapper|bool
	 */
	protected function queryIssuedBadge( $badgeID, User $recipient ) {
		$dbr = wfGetDB( DB_REPLICA );
		$res = $dbr->select(
			[ 'openbadges_assertion', 'openbadges_class' ],
			'*',
			[
				'openbadges_assertion.obl_badge_id' => $badgeID,
				'obl_receiver' => $recipient->getid(),
			],
			__METHOD__,
			[],
			[
				'openbadges_class' => [
					'INNER JOIN',  [
						'openbadges_assertion.obl_badge_id=openbadges_class.obl_badge_id'
					]
				]
			]
		);
		return $res;
	}
}
