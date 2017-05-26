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

use MediaWiki\Changes\LinkedRecentChanges;
use Page;
use ResultWrapper;
use Title;
use User;

class RelatedWatcher {
	protected $user;
	protected $page;
	protected $sinceDays;
	protected $limit;
	protected $collectedChanges;
	protected $table;
	protected $exists;

	/**
	 * Constructor
	 *
	 * @param User $user who is watching
	 * @param Page $page what they're watching
	 */
	public function __construct( User $user, Page $page ) {
		$this->user = $user;
		$this->page = $page;
		$this->table = "periodic_changes";
		$this->exists = null;
	}

	/**
	 * Get data ready for row query and insert
	 * @return array
	 */
	protected function getRowData() {
		return [ 'wc_user' => $this->user->getId(),
				 'wc_page' => $this->page->getId() ];
	}

	/**
	 * Save the watch
	 *
	 * @return bool
	 */
	public function save() {
		$dbw = wfGetDB( DB_MASTER );
		$dbw->insert( $this->table, $this->getRowData(), __METHOD__ );
		return true;
	}

	/**
	 * See if this watch pair already exists
	 * @param Database $dbr requestor
	 * @return bool
	 */
	public function exists( $dbr = null ) {
		if ( $this->exists === null ) {
			$this->exists = false;

			if ( $dbr === null ) {
				$dbr = wfGetDB( DB_SLAVE );
			}
			$row = $this->getRowData();
			$res = $dbr->select( $this->table, array_keys( $row ), $row, __METHOD__ );

			if ( $res->numRows() > 0 ) {
				$this->exists = true;
			}
		}
		return $this->exists;
	}

	/**
	 * remove a watch
	 */
	public function remove() {
		$dbw = wfGetDB( DB_MASTER );
		$dbw->startAtomic( __METHOD__ );
		if ( $this->exists( $dbw ) ) {
			$row = $this->getRowData();
			$dbw->delete( $this->table, $row, __METHOD__ );
		}
		$dbw->endAtomic( __METHOD__ );
	}

	/**
	 * @return User the user
	 */
	public function getUser() {
		return $this->user;
	}

	/**
	 * @return Page the page
	 */
	public function getPage() {
		return $this->page;
	}

	/**
	 * Limit the query in time
	 * @param int $days days to look at
	 */
	public function setSince( int $days ) {
		$this->sinceDays = $days;
	}

	/**
	 * Limit number of query results
	 * @param int $limit days to look at
	 */
	public function setLimit( int $limit ) {
		$this->limit = $limit;
	}

	/**
	 * Get a list of related changes
	 * @return LinkedRecentChanges to list the changes
	 */
	public function getRelatedChanges() : ResultWrapper {
		$changes = new LinkedRecentChanges( $this->page->getTitle() );
		if ( is_integer( $this->limit ) ) {
			$changes->setLimit( $this->limit );
		}
		$changes->addCond( "rc_timestamp > now() - " . abs( $this->sinceDays ) * 24 * 3600 );
		return $changes->getResult();
	}

}
