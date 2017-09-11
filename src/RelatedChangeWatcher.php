<?php

/**
 * Keeps track of related changes.
 *
 * Copyright (C) 2016, 2017  Mark A. Hershberger
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
use WikiPage;

class RelatedChangeWatcher {
	protected $user;
	protected $page;
	protected $sinceDays;
	protected $limit;
	protected $collectedChanges;
	protected $exists;
	protected static $table = "periodic_related_change";
	protected static $titleCache = [];

	/**
	 * Constructor
	 *
	 * @param User $user who is watching
	 * @param Page $page what they're watching
	 * @param int|null $timestamp of change
	 */
	public function __construct( User $user, Page $page, $timestamp = null ) {
		$this->user = $user;
		$this->page = $page;
		$this->timestamp = $timestamp;
		$this->exists = null;
	}

	/**
	 * Do we have them or not?
	 *
	 * @param Title $title to check
	 * @return bool
	 */
	public static function titleHasCategoryWatchers( Title $title ) {
		return count( self::titleCategoryWatchers( $title ) ) > 0;
	}

	/**
	 * Get the people watching this title's categories
	 *
	 * @param Title $title to check
	 * @return array
	 */
	public static function titleCategoryWatchers( Title $title ) {
		$key = $title->getPrefixedText();
		if ( !isset( self::$titleCache[$key] ) ) {
			self::$titleCache[$key] = [];
			$categoryIDs = array_map(
				function ( $category ) use ( $linkCache ) {
					$title = Title::newFromText( $category );
					return $title->getArticleID();
				}, $title->getParentCategories()
			);

			$dbr = wfGetDB( DB_SLAVE );
			$res = $dbr->select(
				self::$table, 'wc_user', [ 'wc_title' => $categoryIDs ],
				__METHOD__, [ 'DISTINCT' ]
			);
			foreach ( $res as $row ) {
				self::$titleCache[$key][] = $row->wc_user;
			}
		}
		return self::$titleCache[$key];
	}

	/**
	 * Constructor if you have a title.
	 *
	 * @param User $user who is watching
	 * @param Title $title what they're watching
	 * @return RelatedChangeWatcher
	 */
	public static function newFromUserTitle( User $user, Title $title ) {
		return new self( $user, WikiPage::factory( $title ) );
	}

	/**
	 * Construct from a form id.
	 *
	 * @param string $formID that we're given
	 * @param string $prefix defaults to "watch"
	 * @return RelatedChangeWatcher
	 */
	public static function newFromFormID( $formID, $prefix = "watch" ) {
		list( $watch, $userId, $titleNS, $title ) = explode( "-", $formID, 4 );
		if ( $watch === $prefix ) {
			return self::newFromUserTitle(
				User::newFromID( $userId ),
				Title::newFromTextThrow( $title, $titleNS )
			);
		}
	}

	/**
	 * Construct from a DB row of RelatedChangeWatchList
	 *
	 * @param StdObj $row result
	 * @return RelatedChangeWatcher
	 */
	public static function newFromRow( $row ) {
		return new self(
			User::newFromID( $row->user ), WikiPage::newFromID( $row->page ),
			$row->timestamp
		);
	}

	/**
	 * Get an identifier for a form.
	 *
	 * @param string $prefix defaults to "watch"
	 * @return string
	 */
	public function getFormID( $prefix = "watch" ) {
		$title = $this->getTitle();
		return implode(
			"-", [ $prefix, $this->user->getId(), $title->getNamespace(),
				   $title->getDBkey() ]
		);
	}

	/**
	 * Get data ready for row query and insert
	 *
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
		$dbw->insert( self::$table, $this->getRowData(), __METHOD__ );
		return true;
	}

	/**
	 * See if this watch pair already exists
	 *
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
			$res = $dbr->select(
				self::$table, array_keys( $row ), $row, __METHOD__
			);

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
			$dbw->delete( self::$table, $row, __METHOD__ );
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
	 * Get the corresponding Title object.
	 * @return Title
	 */
	public function getTitle() {
		return $this->page->getTitle();
	}

	/**
	 * Limit the query in time
	 * @param int $days days to look at
	 */
	public function setSince( $days ) {
		$this->sinceDays = $days;
	}

	/**
	 * Limit number of query results
	 * @param int $limit days to look at
	 */
	public function setLimit( $limit ) {
		$this->limit = $limit;
	}

	/**
	 * Get a list of related changes
	 * @return LinkedRecentChanges to list the changes
	 */
	public function getRelatedChanges() {
		$changes = new LinkedRecentChanges( $this->page->getTitle() );
		if ( is_integer( $this->limit ) ) {
			$changes->setLimit( $this->limit );
		}
		$changes->addCond(
			"rc_timestamp > now() - " . abs( $this->sinceDays ) * 24 * 3600
		);
		return $changes->getResult();
	}
}
