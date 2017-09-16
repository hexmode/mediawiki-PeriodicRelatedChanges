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
$maint = "$IP/maintenance/Maintenance.php";
if ( !file_exists( $maint ) ) {
	die( "Please set the environment variable MW_INSTALL_PATH to\n" .
		 "the location of your mediawiki installation.\n" );
}
require_once $maint;

class ListWatch extends Maintenance {

	// The number of days to look back
	protected $days;

	/**
	 * The constructor, of course.
	 */
	public function __construct() {
		parent::__construct();
		$this->addDescription( "Given a user, show all that user's watches.  Provided" .
							   "with a title as well, show the related page changes" );
		$this->addOption( "days", "How many days back to look (7)", false, true, "d" );
		$this->addOption( "mail", "Send an email instead of printing out.",
						  false, false, "m" );
		$this->addArg( "user", "User to list watch for.", true );
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

		$watches = RelatedChangeWatchList::newFromUser( $user );

		$this->days = $this->getOption( "days", 7 );

		if ( $this->hasOption( "mail" ) ) {
			$watches->sendEmail( $user, $this->days );
			return;
		}

		if ( $page === null ) {
			$this->printWatchedTitles( $user, $watches );
			return;
		}

		$this->printRelatedChanges( $user, $watches, $page );
	}

	/**
	 * Produce a report of related changes for a page
	 * @param User $user to print for
	 * @param RelatedChangeWatchList $watches object
	 * @param WikiPage $page to examine
	 * @return null
	 */
	protected function printRelatedChanges(
		User $user, RelatedChangeWatchList $watches, WikiPage $page
	) {
		$title = $page->getTitle();
		$changesTo = $watches->getChangesFor( $page, $this->days, "to" );
		$changesFrom = $watches->getChangesFor( $page, $this->days, "from" );
		if ( !$changesTo && !$changesFrom ) {
			$this->error( "$user has no changes on $title!\n", 1 );
		}

		$width = $this->getLongestInList(
			5, array_keys( array_merge( $changesTo, $changesFrom ) ),
						   function( $arg ) {
							   return $arg;
						   } );

		$this->output( "Changes for $title:\n" );
		$this->output( "Title" . str_repeat( " ", $width - 4 ) );
		$this->output( "# revs  Diff URL\n" );
		$this->output( "-----" . str_repeat( "-", $width - 4 ) );
		$this->output( "------------------\n" );
		$this->output( "  Linked From:\n" );
		foreach ( $changesFrom as $title => $change ) {
			$this->printChange( $width, $title, $change );
		}
		$this->output( "\n  Linked To:\n" );
		foreach ( $changesTo as $title => $change ) {
			$this->printChange( $width, $title, $change );
		}
	}

	/**
	 * Produce a report of the titles being watched changes for a page
	 * @param User $user to print for
	 * @param RelatedChangeWatchList $watches object
	 * @return null
	 */
	protected function printWatchedTitles( User $user, RelatedChangeWatchList $watches ) {
		if ( $watches->numRows() === 0 ) {
			$this->error( "$user does not have any periodic notices!\n", 1 );
		}

		$width = $this->getLongestInList(
            5, $watches, function ( $arg ) {
                return $arg->getTitle();
            }
        );

		$this->output( "$user has these periodic notices:\n" );
		$this->output( "Title" . str_repeat( " ", $width - 4 ) );
		$this->output( "Last Seen\n" );
		$this->output( "------------------------------\n" );
		foreach ( $watches as $watch ) {
			$this->printWatch( $width, $watch );
		}
	}

	/**
	 * Get the longest string
	 * @param int $init integer to start
	 * @param array|Iterator $list to look in
	 * @param function $extractor callback to get the value
	 * @return int length of longest
	 */
	protected function getLongestInList( $init, $list, $extractor ) {
		$longest =  $init;
		foreach ( $list as $item ) {
			$length = strlen( call_user_func( $extractor, $item ) );
			if ( $length > $longest ) {
				$longest = $length;
			}
		}
		return $longest;
	}

	/**
	 * Handle the display of a single watch name.
	 *
	 * @param int $width to pad to
	 * @param array $res a single watch
	 * @return bool
	 */
	public function printWatch( $width, $res ) {
		$titleLen = strlen( $res->getTitle() );
		$title = $res->getTitle()
			   . str_repeat( " ", $width - $titleLen + 1 );
		$timestamp = $res->getTimestamp() ? $res->getTimestamp() : "(not checked)";
		$this->output( $title );
		$this->output( "$timestamp\n" );

		return true;
	}

	/**
	 * Handle the display of a list of changes
	 * @FIXME copy-pasta with PeriodicRelatedChanges special page
	 * @param int $width to pad to
	 * @param string $title to display
	 * @param array $res a list of changes
	 * @return bool
	 */
	public function printChange( $width, $title, $res ) {
		$titleLen = strlen( $title );
		$title = $title
			   . str_repeat( " ", $width - $titleLen + 1 );
		$count = count( $res['ts'] );

		$old = $res['old'];
		$new = $res['new'];

		$this->output( $title . sprintf( " %5d  ", $count ) .
					   $this->getDiffLink( $old, $new ) . "\n" );

		return true;
	}

	/**
	 * Return the link needed to see this group of diffs
	 * @FIXME copypasta with special page
	 * @param int $old revision #
	 * @param int $new revision #
	 * @return string
	 */
	protected function getDiffLink( $old, $new ) {
		global $wgServer, $wgScript;

		return $wgServer . $wgScript . "?diff=$new&oldid=$old";
	}
}

$maintClass = "PeriodicRelatedChanges\\ListWatch";
require_once DO_MAINTENANCE;
