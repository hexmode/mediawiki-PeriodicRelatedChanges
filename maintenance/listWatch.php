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

use Iterator;
use Maintenance;
use Title;
use User;
use WikiPage;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class ListWatch extends Maintenance {

	/**
	 * The constructor, of course.
	 */
	public function __construct() {
		parent::__construct();
		$this->addDescription( "Show a watch." );
		$this->addArg( "user", "User to list watch for.",
					   true );
		$this->addArg( "page", "The watch to show a report for.  If not " .
					   "given, list the user's watches.",
					   false );
	}

	/**
	 * Where all the business happens.
	 */
	public function execute() {
		$user = User::newFromName( $this->getArg( 0 ) );
		$page = null;
		if ( $this->getArg( 1 ) ) {
			$page = WikiPage::factory( Title::newFromText( $this->getArg( 1 ) ) );
		}

		if ( $user === false || $user->getID() === 0 ) {
			$this->error( "Invalid user.", 1 );
		}

		if ( $page !== null && !$page->exists() ) {
			$this->error( "Page does not exist.", 1 );
		}

		$watches = RelatedWatchList::newFromUser( $user );

		if ( $watches->numRows() === 0 ) {
			$this->error( "$user does not have any periodic notices!\n", 1 );
		}

		if ( $page === null ) {
			$this->output( "$user has these periodic notices:\n" );
			$this->output( "Title\t\tLast Seen\n" );
			$this->output( "------------------------------\n" );
			iterator_apply( $watches, [ $this, 'printWatch' ], [ $watches ] );
			return;
		}

		$changes = $watches->getChangesFor( $page );

		if ( $changes->numRows() === 0 ) {
			$this->error( "$user has no changes on $page!\n", 1 );
		}

		$this->output( "Changes for $page:\n" );
		$this->output( "Title\t\tLast Seen\t\tDiff URL" );
		$this->output( "------------------------------\n" );
		iterator_apply( $changes, [ $this, 'printChange' ], [ $changes ] );
	}

	/**
	 * Handle the display of a single watch name.
	 *
	 * @param Iterator $obj a single watch
	 * @return bool
	 */
	public function printWatch( Iterator $obj ) {
		$res = $obj->current();
		$title = $res['page']->getTitle();
		$timestamp = $res['timestamp'] ? $res['timestamp'] : "(not checked)";
		$this->output( "$title\t" );
		$this->output( "$timestamp\n" );

		return true;
	}
}

$maintClass = "PeriodicRelatedChanges\\ListWatch";
require_once DO_MAINTENANCE;