<?php

/**
 * Handle the user's list of watches
 *
 * Copyright (C) 2016, 2017  NicheWork, LLC
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Mark A. Hershberger <mah@nichework.com>
 */

namespace MediaWiki\Extension\PeriodicRelatedChanges;

use FauxRequest;
use IDatabase;
use MWException;
use MailAddress;
use ResultWrapper;
use RequestContext;
use Title;
use User;
use UserMailer;
use WikiPage;

class RelatedChangeWatchlist extends ResultWrapper {
	protected $user;
	protected $title;

	/**
	 * A constructor!
	 *
	 * @param IDatabase|null $dbh optional
	 * @param ResultWrapper $res a DB result.
	 */
	public function __construct( IDatabase $dbh = null, ResultWrapper $res ) {
		parent::__construct( $dbh, $res );
	}

	/**
	 * Returns an array of the current result
	 *
	 * @return array|bool
	 */
	public function current() {
		$cur = parent::current();
		$res = false;
		if ( $cur !== false ) {
			return RelatedChangeWatcher::newFromRow( $cur );
		}
		return $res;
	}

	/**
	 * Return an iterable list of watches for a user
	 *
	 * @param User $user whose related watches to fetch.
	 * @return RelatedChangeWatchlist
	 */
	public static function newFromUser( User $user ) {
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select( 'periodic_related_change',
							 [
								 'wc_namespace as namespace',
								 'wc_title as title',
								 'wc_timestamp as timestamp',
								 'wc_user as user'
							 ],
							 [ 'wc_user' => $user->getId() ],
							 __METHOD__ );

		$watches = new self( $dbr, $res );
		$watches->setUser( $user );
		return $watches;
	}

	/**
	 * Return true if this watchlist contains the given page.
	 * @param Title $title to look for
	 * @return bool
	 */
	public function hasTitle( Title $title ) {
		$obj = $this->fetchObject();
		while ( $obj !== false ) {
			if ( $title->equals( Title::newFromId( $obj->page ) ) ) {
				$this->rewind();
				return true;
			}
			$obj = $this->fetchObject();
		}
		return false;
	}

	/**
	 * Set the user associated with this watchlist
	 * @param User $user to associate
	 */
	public function setUser( User $user ) {
		$this->user = $user;
	}

	/**
	 * Send an email with the relevant pages to the user.
	 * @param User $user the user to send this to
	 * @param int $days days we look back.
	 * @return Status
	 */
	public function sendEmail( User $user = null, $days = 7 ) {
		if ( $this->user === null && $user === null ) {
			throw new MWException( "No user to send to." );
		}
		if ( $user === null ) {
			$user = $this->user;
		}
		$to = $user->getEmail();

		if ( !$to ) {
			throw new MWException( "No email for $user.\n" );
		}
		$thisPage = Title::newFromText(
			"PeriodicRelatedChanges/$user", NS_SPECIAL
		);
		$req = RequestContext::newExtraneousContext(
			$thisPage,
			[
				"fullreport" => true,
				"printable" => "yes",
				"days" => $days
			] );
		$req->setUser( $user );
		\SpecialPageFactory::executePath( $thisPage, $req );

		global $wgAllowHTMLEmail, $wgPasswordSender;
		$wgAllowHTMLEmail = true;

		return UserMailer::send( MailAddress::newFromUser( $user ),
						  new MailAddress( $wgPasswordSender ),
						  $req->getOutput()->getPageTitle(),
						  [
							  "text" => "nothing here ... See the HTML part!",
							  "html" => $req->getOutput()->getHTML()
						  ], [ "contentType" => "multipart/alternative" ]
		);
	}
}
