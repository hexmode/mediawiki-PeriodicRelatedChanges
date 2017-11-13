<?php

/**
 * Copyright (C) 2017  Mark A. Hershberger
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

namespace MediaWiki\Extension\PeriodicRelatedChanges;

use ChangeTags;
use FormOptions;
use RecentChange;
use RecentChangeQuery;
use Title;
use User;

class LinkedRecentChangeQuery extends RecentChange {

	protected $title;
	protected $tables = [];
	protected $select = [];
	protected $conds = [];
	protected $query_options = [];
	protected $join_conds;
	protected $target;
	protected $showlinkedto;
	protected $tagFilter;
	protected $order;
	protected $dbr;

	public function __construct( Title $title ) {
		wfDebugLog( 'PeriodicRelatedChanges::LinkedRecentChangeQuery', __METHOD__ );
		$this->title = $title;

		if ( !$this->title || $this->title->isExternal() ) {
			return false;
		}

		// nonexistent pages can't link to any pages
		if ( $this->title->getArticleID() == 0 ) {
			return false;
		}
	}

	public function &getTitle() {
		wfDebugLog( 'PeriodicRelatedChanges::LinkedRecentChangeQuery', __METHOD__ );
		return $this->title;
	}

	protected function getDB() {
		wfDebugLog( 'PeriodicRelatedChanges::LinkedRecentChangeQuery', __METHOD__ );
		if ( !$this->dbr ) {
			$this->dbr = wfGetDB( DB_REPLICA, 'recentchanges' );
		}
		return $this->dbr;
	}

	public function setupDBParams() {
		wfDebugLog( 'PeriodicRelatedChanges::LinkedRecentChangeQuery', __METHOD__ );
		/*
		 * Ordinary links are in the pagelinks table, while transclusions are
		 * in the templatelinks table, categorizations in categorylinks and
		 * image use in imagelinks.  We need to somehow combine all these.
		 * Special:Whatlinkshere does this by firing multiple queries and
		 * merging the results, but the code we inherit from our parent class
		 * expects only one result set so we use UNION instead.
		 */

		$this->select = array_merge( self::selectFields(), $this->select );

		// JOIN on page, used for 'last revision' filter highlight
		$this->tables = [ 'recentchanges', 'page' ];
		$this->join_conds['page'] = [ 'LEFT JOIN', 'rc_cur_id=page_id' ];
		$this->select[] = 'page_latest';

		$this->order = [];
	}

	protected function getLinkTables() {
		wfDebugLog( 'PeriodicRelatedChanges::LinkedRecentChangeQuery', __METHOD__ );
		$ns = $this->title->getNamespace();
		if ( $ns == NS_CATEGORY && !$this->showlinkedto ) {
			// special handling for categories
			// XXX: should try to make this less kludgy
			$link_tables = [ 'categorylinks' ];
			$this->showlinkedto = true;
		} else {
			// for now, always join on these tables; really should be configurable as in whatlinkshere
			$link_tables = [ 'pagelinks', 'templatelinks' ];
			// imagelinks only contains links to pages in NS_FILE
			if ( $ns == NS_FILE || !$this->showlinkedto ) {
				$link_tables[] = 'imagelinks';
			}
		}
		return $link_tables;
	}

	protected function getLinkTableNS( $link_table ) {
		wfDebugLog( 'PeriodicRelatedChanges::LinkedRecentChangeQuery', __METHOD__ );
		// imagelinks and categorylinks tables have no xx_namespace field,
		// and have xx_to instead of xx_title
		$link_ns = 0;
		if ( $link_table == 'imagelinks' ) {
			$link_ns = NS_FILE;
		} elseif ( $link_table == 'categorylinks' ) {
			$link_ns = NS_CATEGORY;
		}
		return $link_ns;
	}

	protected function getTablePrefix( $link_table ) {
		wfDebugLog( 'PeriodicRelatedChanges::LinkedRecentChangeQuery', __METHOD__ );
		// field name prefixes for all the various tables we might want to join with
		$prefix = [
			'pagelinks' => 'pl',
			'templatelinks' => 'tl',
			'categorylinks' => 'cl',
			'imagelinks' => 'il'
		];
		return $prefix[$link_table];
	}

	protected function getSubCondJoin( $link_table, $link_ns ) {
		wfDebugLog( 'PeriodicRelatedChanges::LinkedRecentChangeQuery', __METHOD__ );
		$pfx = $this->getTablePrefix( $link_table );

		if ( $this->showlinkedto ) {
			// find changes to pages linking to this page
			$ns = $this->title->getNamespace();
			$dbkey = $this->title->getDBkey();
			if ( $link_ns ) {
				// should never happen, but check anyway
				if ( $ns != $link_ns ) {
					return null;
				}
				$subconds = [ "{$pfx}_to" => $dbkey ];
			} else {
				$subconds = [ "{$pfx}_namespace" => $ns, "{$pfx}_title" => $dbkey ];
			}
			$subjoin = "rc_cur_id = {$pfx}_from";
		} else {
			// find changes to pages linked from this page
			$subconds = [ "{$pfx}_from" => $this->title->getArticleID() ];
			if ( $link_table == 'imagelinks' || $link_table == 'categorylinks' ) {
				$subconds["rc_namespace"] = $link_ns;
				$subjoin = "rc_title = {$pfx}_to";
			} else {
				$subjoin = [ "rc_namespace = {$pfx}_namespace", "rc_title = {$pfx}_title" ];
			}
		}
		return [ $subconds, $subjoin ];
	}

	protected function getSubQueryForTable( $link_table ) {
		wfDebugLog( 'PeriodicRelatedChanges::LinkedRecentChangeQuery', __METHOD__ );
		$pfx = $this->getTablePrefix( $link_table );

		$link_ns = $this->getLinkTableNS( $link_table );
		$subcondjoin = $this->getSubCondJoin( $link_table, $link_ns );
		if ( $subcondjoin === null ) {
			return null;
		}
		list( $subconds, $subjoin ) = $subcondjoin;

		$dbr = $this->getDB();
		$query = $dbr->selectSQLText(
			array_merge( $this->tables, [ $link_table ] ),
			$this->select,
			$this->conds + $subconds,
			__METHOD__,
			$this->order + $this->query_options,
			$this->join_conds + [ $link_table => [ 'INNER JOIN', $subjoin ] ]
		);

		return $query;
	}

	public function runQuery() {
		wfDebugLog( 'PeriodicRelatedChanges::LinkedRecentChangeQuery', __METHOD__ );
		$this->setupDBParams();
		$link_tables = $this->getLinkTables();

		// SELECT statements to combine with UNION1
		$subsql = [];

		foreach ( $link_tables as $link_table ) {
			$query = $this->getSubQueryForTable( $link_table );
			if ( $query ) {
				$subsql[] = $query;
			}
		}

		// should never happen
		if ( count( $subsql ) == 0 ) {
			return false;
		}

		$dbr = $this->getDB();
		if ( count( $subsql ) == 1 ) {
			$sql = $subsql[0];
		} else {
			// need to resort and relimit after union
			$sql = $dbr->unionQueries( $subsql, false ) . ' ORDER BY rc_timestamp DESC';
		}

		$res = $dbr->query( $sql, 'LinkedRecentchange::runQuery' );

		if ( $res->numRows() == 0 ) {
			$this->mResultEmpty = true;
		}

		return $res;
	}
}
