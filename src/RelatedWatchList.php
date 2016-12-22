<?php

/*
 * Copyright (C) 2016  Mark A. Hershberger
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
 */

namespace PeriodicRelatedChanges;

use IDatabase;
use ResultWrapper;
use User;
use WikiPage;

class RelatedWatchList extends ResultWrapper {
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
	 * Return an iterable list of watches for a user
	 *
	 * @param User $user whose related watches to fetch.
	 * @return RelatedWatchList
	 */
	public static function newFromUser( User $user ) {
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select( 'periodic_changes',
							 [ 'wc_page', 'wc_timestamp' ],
							 [ 'wc_user' => $user->getId() ],
							 __METHOD__ );
		return new self( $dbr, $res );
	}

	/**
	 * Returns an array of the current result
	 *
	 * @return array|boolean
	 */
	public function current() {
		$cur = parent::current();
		$res = false;

		if ( $cur !== false ) {
			$res = [ 'page' => WikiPage::newFromID( $cur->wc_page ),
					 'timestamp' => $cur->wc_timestamp ];
		}
		return $res;
	}

	/**
	 * Get a list of changes for a page
	 *
	 * @param WikiPage $page to check
	 * @return array
	 */
	public function getChangesFor ( WikiPage $page ) {
		
	}
}
