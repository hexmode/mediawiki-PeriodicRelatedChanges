<?php

/**
 * Get related pages and changes
 *
 * Copyright (C) 2017  NicheWork, LLC
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

class RelatedPageList extends ResultWrapper {
	protected $title;
	protected $user;

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
	 * Setter for the title.
	 *
	 * @param Title $title title
	 */
	public function setTitle( Title $title ) {
		$this->title = $title;
	}

	/**
	 * Return an iterable list of watches for a user
	 *
	 * @param Title $title that is the basis for related pages
	 * @return RelatedPageList
	 */
	public static function newFromTitle( Title $title ) {
		$dbr = wfGetDB( DB_SLAVE );

		$linkedRC = new LinkedRecentChangeQuery( $title );
		$self = new self( $dbr, $linkedRC->runQuery() );
		$self->setTitle( $title );

		return $self;
	}

	/**
	 * Get the current title
	 *
	 * @return Title
	 */
	public function currentTitle() {
		return Title::newFromText(
			$this->current()->rc_title, $this->current()->rc_title
		);
	}

	/**
	 * Get a list of changes for a page
	 *
	 * @param int $startTime to check
	 * @param string $style which direction
	 * @return array
	 */
	public function getRelatedChangesSince(
		$startTime = 0, $style = "to"
	) {
		global $wgHooks;

		# Don't let any hooks mess us up
		$oldHooks = [];
		if ( isset( $wgHooks['ChangesListSpecialPageQuery'] ) ) {
			$oldHooks = $wgHooks['ChangesListSpecialPageQuery'];
		}

		$rcl = new MySpecialRelatedChanges(
			$this->title->getPrefixedText(), $startTime
		);
		$rcl->linkedTo( false );
		if ( $style === "to" ) {
			$rcl->linkedTo( true );
		}
		$rows = $rcl->getRows();

		if ( count( $oldHooks ) == 0 ) {
			unset( $wgHooks['ChangesListSpecialPageQuery'] );
		} else {
			$wgHooks['ChangesListSpecialPageQuery'] = $oldHooks;
		}

		if ( $rows === false ) {
			return [];
		}

		return $this->collateChanges( $rows );
	}

	/**
	 * Gather the changes per-page.
	 * @param Iterator $watches list of page watches
	 * @return array contains report on pages.
	 */
	protected function collateChanges( \Iterator $watches ) {
		$change = [];

		foreach ( $watches as $watch ) {
			$title = Title::newFromText(
				$watch->rc_title, $watch->rc_namespace
			)->getPrefixedText();
			$rev = $watch->rc_id;

			if ( !isset( $change[$title] ) ) {
				$change[$title] = [ 'old' => $watch->rc_last_oldid,
									'new' => $watch->rc_this_oldid,
									'ts'  => [ $watch->rc_timestamp ] ];
				continue;
			}
			$change[$title]['ts'][] = $watch->rc_timestamp;
			if ( $change[$title]['old'] > $watch->rc_last_oldid ) {
				$change[$title]['old'] = $watch->rc_last_oldid;
			}
			if ( $change[$title]['new'] < $watch->rc_this_oldid ) {
				$change[$title]['new'] = $watch->rc_this_oldid;
			}
		}
		ksort( $change );

		return $change;
	}

}
