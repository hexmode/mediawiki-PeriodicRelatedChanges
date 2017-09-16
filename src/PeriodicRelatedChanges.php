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
use Status;
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
	 * Get a RelatedChangeWatcher object
	 * @param User $user the user.
	 * @param Title $title the page name.
	 * @return RelatedChangeWatcher object
	 */
	public function get( User $user, Title $title ) {
		return RelatedChangeWatcher::newFromUserTitle( $user, $title );
	}

	/**
	 * Make sure user is known and not anon.  Make sure title exists.
	 *
	 * @param User $user the user.
	 * @param Title $title the page name.
	 *
	 * @return Status
	 */
	public function checkUserTitle( User $user, Title $title ) {
		if ( $user->isAnon() ) {
			return Status::newFatal(
				"periodic-related-changes-no-anon", $user
			);
		}
		if ( $user->getID() === 0 ) {
			return Status::newFatal(
				"periodic-related-changes-user-not-exist", $user
			);
		}

		if ( !$title->exists() ) {
			return Status::newFatal(
				"periodic-related-changes-title-not-exist", $title
			);
		}
		return Status::newGood();
	}

	/**
	 * Remove a watcher.
	 *
	 * @param User $user the user.
	 * @param Title $title the page name.
	 *
	 * @return Status
	 */
	public function removeWatch( User $user, Title $title ) {
		$check = $this->checkUserTitle( $user, $title );
		if ( !$check->isGood() ) {
			return $check;
		}

		$watch = new RelatedChangeWatcher( $user, WikiPage::factory( $title ) );
		if ( !$watch->exists() ) {
			return Status::newFatal(
				"periodic-related-changes-does-not-exist", $title, $user
			);
		}
		return $watch->remove();
	}

	/**
	 * Store a watch for the user.  SQL schema ensures that there can
	 * only be one.
	 *
	 * @param User $user the user
	 * @param Title $title what to watch for related changes.
	 *
	 * @return Status
	 */
	public function addWatch( User $user, Title $title ) {
		$check = $this->checkUserTitle( $user, $title );
		if ( !$check->isGood() ) {
			return $check;
		}

		$watch = new RelatedChangeWatcher( $user, WikiPage::factory( $title ) );
		if ( !$watch->exists() ) {
			return $watch->save();
		}
		return Status::newFatal(
			"periodic-related-changes-already-exists", $title, $user
		);
	}

	/**
	 * Get related pages
	 * @param User $user the user
	 * @param Page $page what to watch for related changes.
	 *
	 * @return RelatedChangeWatcher
	 */
	public function getRelatedChangeWatcher( User $user, Page $page ) {
		return new RelatedChangeWatcher( $user, $page );
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
				$this->collectedChanges['page'][$title]['oldestId']
				> $change->rc_this_oldid
			) {
				$this->collectedChanges['page'][$title]['oldestId']
					= $change->rc_this_oldid;
			}
			$this->collectedChanges['page'][$title]['editors'][$user]++;
		}
	}

	/**
	 * Return an aggreate list of changes
	 * @param RelatedChangeWatcher $changes the list of individual
	 *        changes to aggregate
	 * @return Iterator
	 */
	public function getCollectedChanges( RelatedChangeWatcher $changes ) {
		$this->collectChanges( $changes->getRelatedChanges() );
		return $this->collectedChanges;
	}

	/**
	 * Return the list of titles currently being watched by the user
	 * @param User $user we want info on
	 * @return Iterator
	 */
	public function getCurrentWatches( User $user ) {
		return RelatedChangeWatchList::newFromUser( $user );
	}
}
