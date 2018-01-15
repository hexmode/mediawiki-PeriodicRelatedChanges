<?php

/**
 * Keeps track of related changes.
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

namespace MediaWiki\Extensions\PeriodicRelatedChanges;

use MediaWiki\Changes\LinkedRecentChanges;
use Page;
use ResultWrapper;
use Status;
use Title;
use User;
use WikiPage;

class RelatedChangeWatcher {
	protected $user;
	protected $namespace;
	protected $title;
	protected $timestamp;
	protected $sinceDays;
	protected $limit;
	protected $collectedChanges;
	protected $exists;
	protected static $table = "periodic_related_change";
	protected static $titleCache = [];
	protected static $rowMap = [
		'wc_title as title', 'wc_namespace as namespace', 'wc_user as user',
		'wc_timestamp as timestamp'
	];

	/**
	 * Constructor
	 *
	 * @param User $user who is watching
	 * @param Title $title what they're watching
	 * @param int|null $timestamp of change
	 */
	public function __construct( User $user, Title $title, $timestamp = null ) {
		wfDebugLog( 'PeriodicRelatedChanges', __METHOD__ );
		$this->user = $user;
		$this->namespace = $title->getNamespace();
		$this->title = $title->getDBKey();
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
		wfDebugLog( 'PeriodicRelatedChanges', __METHOD__ );
		return count( self::titleCategoryWatchers( $title ) ) > 0;
	}

	/**
	 * Get the people watching this title's categories
	 *
	 * @param Title $title to check
	 * @return array
	 */
	public static function titleCategoryWatchers( Title $title ) {
		wfDebugLog( 'PeriodicRelatedChanges', __METHOD__ );
		$key = $title->getPrefixedText();
		if ( !isset( self::$titleCache[$key] ) ) {
			self::$titleCache[$key] = [];
			$categoryCond = [];
			foreach ( $title->getParentCategories() as $category => $title ) {
				$categoryCond['wc_title'][] = $category;
				$categoryCond['wc_namespace'][] = NS_CATEGORY;
			}
			if ( count( $categoryCond ) ) {
				$dbr = wfGetDB( DB_SLAVE );
				$res = $dbr->select(
					self::$table, 'wc_user', $categoryCond,
					__METHOD__, [ 'DISTINCT' ]
				);
				foreach ( $res as $row ) {
					self::$titleCache[$key][$row->wc_user] = 1;
				}
			}
		}
		return array_keys( self::$titleCache[$key] );
	}

	/**
	 * Constructor if you have a title.
	 *
	 * @param User $user who is watching
	 * @param Title $title what they're watching
	 * @return RelatedChangeWatcher
	 */
	public static function newFromUserTitle( User $user, Title $title ) {
		wfDebugLog( 'PeriodicRelatedChanges', __METHOD__ );
		return new self( $user, $title );
	}

	/**
	 * Construct from a form id.
	 *
	 * @param string $formID that we're given
	 * @param string $prefix defaults to "watch"
	 * @return RelatedChangeWatcher
	 */
	public static function newFromFormID( $formID, $prefix = "watch" ) {
		wfDebugLog( 'PeriodicRelatedChanges', __METHOD__ );
		list( $watch, $userId, $titleNS, $title ) = explode( '-', $formID, 4 );
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
		wfDebugLog( 'PeriodicRelatedChanges', __METHOD__ );
		return new self(
			User::newFromID( $row->user ),
			Title::newFromText( $row->title, $row->namespace ),
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
		wfDebugLog( 'PeriodicRelatedChanges', __METHOD__ );
		$title = $this->getTitle();
		return implode(
			"-", [ $prefix, $this->user->getID(), $title->getNamespace(),
				   $title->getDBkey() ]
		);
	}

	/**
	 * Get data ready for row query and insert
	 *
	 * @return array
	 */
	protected function getRowData() {
		wfDebugLog( 'PeriodicRelatedChanges', __METHOD__ );
		return [
			'wc_user' => $this->user->getID(),
			'wc_title' => $this->title,
			'wc_namespace' => $this->namespace
		];
	}

	/**
	 * Get data ready for row query and insert
	 *
	 * @param $title Title to query for
	 * @return array
	 */
	protected static function getRowDataForTitle( Title $title ) {
		wfDebugLog( 'PeriodicRelatedChanges', __METHOD__ );
		return [
			'wc_title' => $title->getDBkey(),
			'wc_namespace' => $title->getNamespace()
		];
	}

	/**
	 * Save the watch
	 *
	 * @return Status
	 */
	public function save() {
		wfDebugLog( 'PeriodicRelatedChanges', __METHOD__ );
		$dbw = wfGetDB( DB_MASTER );
		$ret = Status::newGood();
		if ( !$dbw->insert( self::$table, $this->getRowData(), __METHOD__ ) ) {
			$ret->setResult( false, "periodic-relatedchanges-no-save" );
		}
		return $ret;
	}

	/**
	 * See if this watch pair already exists
	 *
	 * @param Database $dbr requestor
	 * @return bool
	 */
	public function exists( $dbr = null ) {
		wfDebugLog( 'PeriodicRelatedChanges', __METHOD__ );
		if ( !$this->user->isAllowed( 'periodic-related-changes' ) ) {
			return false;
		}
		if ( $this->exists === null ) {
			$this->exists = false;

			if ( $dbr === null ) {
				$dbr = wfGetDB( DB_SLAVE );
			}
			$row = $this->getRowData();
			$res = $dbr->select(
				self::$table, self::$rowMap, $row, __METHOD__
			);

			if ( $res->numRows() > 0 ) {
				$this->exists = true;
				foreach ( $res as $row ) {
					# There can be only one.
					$this->fillFromRow( $row );
				}
			}
		}
		return $this->exists;
	}

	public function fillFromRow( $row ) {
		wfDebugLog( 'PeriodicRelatedChanges', __METHOD__ );
		$this->title = $row->title;
		$this->namespace = $row->namespace;
		$this->user = User::newFromID( $row->user );
		$this->timestamp = $row->timestamp;
		$this->exists = true;
	}

	/**
	 * remove a watch
	 */
	public function remove() {
		wfDebugLog( 'PeriodicRelatedChanges', __METHOD__ );
		$dbw = wfGetDB( DB_MASTER );
		$dbw->startAtomic( __METHOD__ );
		$ret = Status::newGood();
		if ( $this->exists( $dbw ) ) {
			$row = $this->getRowData();
			$result = $dbw->delete( self::$table, $row, __METHOD__ );
			if ( !$result ) {
				$ret->setResult(
					false, "periodic-related-changes-no-remove",
					$this->getUser(), $this->getTitle()
				);
			}
		}
		$dbw->endAtomic( __METHOD__ );
		return $ret;
	}

	/**
	 * @return User the user
	 */
	public function getUser() {
		wfDebugLog( 'PeriodicRelatedChanges', __METHOD__ );
		return $this->user;
	}

	/**
	 * @return Title the title
	 */
	public function getTitle() {
		wfDebugLog( 'PeriodicRelatedChanges', __METHOD__ );
		return Title::newFromText( $this->title, $this->namespace );
	}

	/**
	 * Get the corresponding timestamp
	 * @return string
	 */
	public function getTimestamp() {
		wfDebugLog( 'PeriodicRelatedChanges', __METHOD__ );
		return $this->timestamp;
	}

	/**
	 * Set the corresponding timestamp
	 * @param string $ts timestamp
	 * @return bool
	 */
	public function setTimestamp( $ts ) {
		wfDebugLog( 'PeriodicRelatedChanges', __METHOD__ );
		$dbw = wfGetDB( DB_MASTER );
		$res = $dbw->update(
			self::$table, [ 'wc_timestamp' => $ts ], $this->getRowData(),
			__METHOD__
		);
		if ( $res ) {
			$this->timestamp = $ts;
		}
		return $res;
	}

	/**
	 * Limit the query in time
	 * @param int $days days to look at
	 */
	public function setSince( $days ) {
		wfDebugLog( 'PeriodicRelatedChanges', __METHOD__ );
		$this->sinceDays = $days;
	}

	/**
	 * Limit number of query results
	 * @param int $limit days to look at
	 */
	public function setLimit( $limit ) {
		wfDebugLog( 'PeriodicRelatedChanges', __METHOD__ );
		$this->limit = $limit;
	}

	/**
	 * Get a list of related changes
	 * @return LinkedRecentChanges to list the changes
	 */
	public function getRelatedChanges() {
		wfDebugLog( 'PeriodicRelatedChanges', __METHOD__ );
		$changes = new LinkedRecentChanges( $this->getTitle() );
		if ( is_integer( $this->limit ) ) {
			$changes->setLimit( $this->limit );
		}
		$changes->addCond(
			"rc_timestamp > now() - " . abs( $this->sinceDays ) * 24 * 3600
		);
		return $changes->getResult();
	}

	/**
	 * Get a list of pages that are being watched and, for each page,
	 * a list of people watching them.
	 *
	 * @return array
	 */
	public static function getWatchGroups() {
		wfDebugLog( 'PeriodicRelatedChanges', __METHOD__ );
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select(
			self::$table, self::$rowMap, null, __METHOD__, [ 'DISTINCT' ]
		);
		$group = [];
		foreach ( $res as $row ) {
			$title = Title::newFromText(
				$row->title, $row->namespace
			)->getPrefixedDBkey();
			$group[$title][] = User::newFromId( $row->user );
		}
		return $group;
	}

	/**
	 * Internal method to find links to and from pages
	 * @param Title $title to get page links for
	 * @param IDatabase $dbr handle
	 * @return array
	 */
	protected static function getPagelinks( Title $title, \IDatabase $dbr ) {
		$match = [];

		$pageID = WikiPage::factory( $title )->getID();
		$res = $dbr->select(
			'pagelinks',
			[
				'pl_title as title',
				'pl_namespace as namespace'
			],
			'pl_from = ' . $pageID,
			__METHOD__ . '->getLinksFrom'
		);
		foreach ( $res as $row ) {
			$match['wc_namespace'][$row->namespace] = true;
			$match['wc_title'][$row->title] = true;
		}
		$res = $dbr->select(
			'pagelinks',
			'pl_from as id',
			[
				'pl_title' => $title->getDBKey(),
				'pl_namespace' => $title->getNamespace()
			],
			__METHOD__ . '-getLinksTo'
		);
		foreach ( $res as $row ) {
			$page = WikiPage::newFromID( $row->id );
			if ( $page ) {
				$title = $page->getTitle();
				$match['wc_namespace'][$title->getNamespace()] = true;
				$match['wc_title'][$title->getDBkey()] = true;
				// Add redirects?
			}
			// FIXME: Handle deleted pages?
		}
		return $match;
	}

	/**
	 * True if users are watching this page's categories or pages
	 * that link to this one.
	 *
	 * @param Title $title to check for
	 * @return bool
	 */
	public static function hasRelatedChangeWatchers( Title $title ) {
		wfDebugLog( 'PeriodicRelatedChanges', __METHOD__ );
		return count( self::getRelatedChangeWatchers( $title ) ) > 0;
	}

	/**
	 * Get a list of users watching this page's categories or pages
	 * that link to this one.
	 *
	 * @param Title $title to check for
	 * @return array
	 */
	public static function getRelatedChangeWatchers( Title $title ) {
		wfDebugLog( 'PeriodicRelatedChanges', __METHOD__ );
		$dbr = wfGetDB( DB_SLAVE );
		$match = self::getPageLinks( $title, $dbr );
		foreach ( $title->getParentCategories() as $category ) {
			$title = Title::newFromText( $category );
			$match['wc_namespace'][NS_CATEGORY] = true;
			$match['wc_title'][$title->getDBkey()] = true;
		}

		file_put_contents( "/tmp/st", var_export( [ $title->getDBKey(), $match ], true ) . "\n\n\n" );
		$ret = [];
		if ( isset( $match['wc_namespace'] ) && count( $match['wc_namespace'] ) > 0 ) {
			$match['wc_namespace'] = array_keys( $match['wc_namespace'] );
			$match['wc_title'] = array_keys( $match['wc_title'] );
			$res = $dbr->select(
				self::$table, self::$rowMap, $match,
				__METHOD__ . '-getWatchers', [ 'DISTINCT' ]
			);

			foreach ( $res as $row ) {
				$ret[] = User::newFromID( $row->user );
			}
		}
		return $ret;
	}

	/**
	 * Get the watchers for this title
	 *
	 * @param Title $title to check for
	 * @return array of user ids
	 */
	public static function getWatcherIDs( Title $title ) {
		wfDebugLog( 'PeriodicRelatedChanges', __METHOD__ );
		$dbr = wfGetDB( DB_MASTER );
		$res = $dbr->select(
			self::$table, self::$rowMap, self::getRowDataForTitle( $title ),
			__METHOD__
		);

		$ret = [];
		foreach ( $res as $row ) {
			$ret[] = $row->user;
		}
		return $ret;
	}

	/**
	 * Make the watchers watch this title
	 *
	 * @param Title $title to check for
	 * @param array $watchers user ids to set
	 * @return bool
	 */
	public static function setWatcherIDs( Title $title, array $watchers ) {
		wfDebugLog( 'PeriodicRelatedChanges', __METHOD__ );
		$dbw = wfGetDB( DB_SLAVE );
		$titleFields = [
			'wc_namespace' => $title->getNamespace(),
			'wc_title' => $title->getDBkey()
		];
		$insertions = [];
		foreach ( $watchers as $watcher ) {
			$insertions[] = array_merge(
				[ 'wc_user' => $watcher ], $titleFields
			);
		}
		return $dbw->insert(
			self::$table, $insertions, __METHOD__, [ 'IGNORE' ]
		);
	}

	/**
	 * Duplicate the watchers
	 *
	 * @param Title $oldTitle to copy from
	 * @param Title $newTitle to copy to
	 * @return bool
	 */
	public static function duplicateEntries(
		Title $oldTitle, Title $newTitle
	) {
		wfDebugLog( 'PeriodicRelatedChanges', __METHOD__ );
		return self::setWatcherIDs(
			$newTitle, self::getWatcherIDs( $oldTitle )
		);
	}
}
