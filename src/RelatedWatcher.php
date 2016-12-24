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

	/**
	 * Constructor
	 *
	 * @param User $user who is watching
	 * @param Page $page what they're watching
	 */
	public function __construct( User $user, Page $page ) {
		$this->user = $user;
		$this->page = $page;
	}

	/**
	 * Save the watch
	 *
	 * @return bool
	 */
	public function save() {
		$dbw = wfGetDB( DB_MASTER );
		$dbw->insert( 'periodic_changes',
					  [ 'wc_user' => $this->user->getId(),
						'wc_page' => $this->page->getId() ],
					  __METHOD__,
					  [ 'IGNORE' ] );
		return true;
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
	 * Limit the query in time
	 * @param int $days days to look at
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
		$changes->setLimit( $this->limit );
		$changes->addCond( "rc_timestamp > now() - " + abs( $this->sinceDays ) * 24 * 3600 );
		return $changes->getResult();
	}

}
