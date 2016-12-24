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

use MWException;
use Page;
use ResultWrapper;
use Title;
use User;
use WikiPage;

class PeriodicRelatedChanges {
	protected $collectedChanges = [];

	/**
	 * Get the manager for this
	 *
	 * @return PeriodicRelatedChanges
	 */
	public static function getManager() {
		return new self();
	}

	/**
	 * Add a watcher.
	 *
	 * @param string $userName text form of username.
	 * @param string $pageName text form of page name that will be made into a title object.
	 *
	 * @return bool
	 */
	public function add( string $userName, string $pageName ) {
		$user = User::newFromName( $userName );
		if ( $user === false ) {
			throw new MWException( "Invalid user name." );
		}
		if ( $user->getID() === 0 ) {
			throw new MWException( "User doesn't exist." );
		}

		$title = Title::newFromTextThrow( $pageName );
		$page = WikiPage::factory( $title );
		if ( !$page->exists() ) {
			throw new MWException( "Page doesn't exist." );
		}

		return $this->addWatch( $user, $page );
	}

	/**
	 * Store a watch for the user.  SQL schema ensures that there can only be one.
	 *
	 * @param User $user the user
	 * @param Page $page what to watch for related changes.
	 *
	 * @return bool
	 */
	public function addWatch( User $user, Page $page ) {
		$watch = new RelatedWatcher( $user, $page );
		return $watch->save();
	}

	/**
	 * Get related pages
	 * @param User $user the user
	 * @param Page $page what to watch for related changes.
	 *
	 * @return RelatedWatcher
	 */
	public function getRelatedWatcher( User $user, Page $page ) {
		return new RelatedWatcher( $user, $page );
	}
	/**
	 * Given a list of individual changes, collect them into a batch
	 * FIXME: should replace this with some SQL queries
	 * @param LinkedRecentChanges $changes to collect
	 */
	public function collectChanges( ResultWrapper $changes ) {
		foreach ( $changes as $change ) {
			$title = $change->rc_title;
			$user  = $change->rc_user_text;

			if ( !isset( $this->collectedChanges['page'][$title] ) ) {
				$this->collectedChanges['page'][$title]['editors'] = [];
			}

			if ( !isset( $this->collectedChanges['page'][$title][$user] ) ) {
				$this->collectedChanges['page'][$title]['editors'][$user] = 0;
			}

			if ( !isset( $this->collectedChanges['user'][$user] ) ) {
				$this->collectedChanges['user'][$user] = 0;
			}

			$this->collectedChanges['user'][$user]++;
			if (
				!isset( $this->collectedChanges['page'][$title]['oldestId'] ) ||
				$this->collectedChanges['page'][$title]['oldestId'] > $change->rc_this_oldid
			) {
				$this->collectedChanges['page'][$title]['oldestId']
					= $change->rc_this_oldid;
			}
			$this->collectedChanges['page'][$title]['editors'][$user]++;
		}
	}

	/**
	 * Return an aggreate list of changes
	 * @param RelatedWatcher $changes the list of individual changes to aggregate
	 * @return Iterator
	 */
	public function getCollectedChanges( RelatedWatcher $changes ) : array {
		$this->collectChanges( $changes->getRelatedChanges() );
		return $this->collectedChanges;
	}
}
