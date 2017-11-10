<?php

/**
 * Utility to add a page to the watchlist.
 *
 * Copyright (C) 2016  NicheWork, LLC
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

namespace MediaWiki\Extension\PeriodicRelatedChanges;

use MWException;
use Maintenance;
use Title;
use User;

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

class ManageWatch extends Maintenance {

	protected $prc;
	protected $user;
	protected $page;

	/**
	 * The constructor, of course.
	 */
	public function __construct() {
		parent::__construct();
		$this->addDescription( "Manage watches on the periodic watchlist.\n"
							   . "• If no user is given, then users watching "
							   . "the page will be listed.\n"
							   . "• If no page is given, the user's watchlist "
							   . "will be shown.\n"
							   . "• Otherwise all pages being watched with "
							   . "the PeriodicRelatedChanges extension will be "
							   . "listed." );
		$this->addOption(
			"add", "(default) Add the page to the watchlist.", false, false, "a"
		);
		$this->addOption(
			"remove", "Remove the page from the watchlist.", false, false, "r"
		);
		$this->addOption(
			"user", "User's watchlist to change.", false, true, "u"
		);
		$this->addOption(
			"page", "The page to summarize RelatedChanges for.  Must exist.",
			false, true, "p"
		);
	}

	/**
	 * Obtain a user object from the argument passed.
	 * @param string $userName the user's username.
	 */
	public function getUserFromArg( $userName ) {
		$this->user = User::newFromName( $userName );
		if ( !$this->user ) {
			$this->error( wfMessage(
				"periodic-related-changes-invalid-user", $userName
			)->plain(), 1 );
		}
		if ( $this->user->getId() === 0 ) {
			$this->error( wfMessage(
				"periodic-related-changes-user-not-exist", $userName
			)->plain(), 1 );
		}
	}

	/**
	 * Obtain a title object from the argument passed.
	 * @param string $titleText the title's name
	 */
	public function getTitleFromArg( $titleText ) {
		$this->title = Title::newFromText( $titleText );
		if ( !$this->title ) {
			$this->error( wfMessage(
				"periodic-related-changes-title-not-valid", $titleText
			)->plain(), 1 );
		}
		if ( !$this->title->exists() ) {
			$this->error( wfMessage(
				"periodic-related-changes-title-not-exist", $titleText
			)->plain(), 1 );
		}
	}

	/**
	 * Where all the business happens.
	 * @return null
	 */
	public function execute() {
		if ( $this->hasOption( "user" ) ) {
			$this->getUserFromArg( $this->getOption( "user" ) );
		}

		if ( $this->hasOption( "page" ) ) {
			$this->getTitleFromArg( $this->getOption( "page" ) );
		}

		$this->prc = Manager::getManager();
		if ( $this->user && $this->title ) {
			if ( $this->hasOption( "remove" ) ) {
				$stat = $this->prc->removeWatch( $this->user, $this->title );
				$msg = "periodic-related-changes-title-removed";
			} else {
				$stat = $this->prc->addWatch( $this->user, $this->title );
				$msg = "periodic-related-changes-title-added";
			}
			if ( $stat->isOK() ) {
				$this->output(
					wfMessage( $msg, $this->user, $this->title )->plain() . "\n"
				);
				return;
			} else {
				$this->error( $stat->getMessage()->plain(), 1 );
			}
		}

		if ( $this->user ) {
			return $this->listUsersWatches();
		}

		if ( $this->title ) {
			return $this->listTitlesWatchers();
		}

		return $this->listTitlesUnderWatch();
	}

	/**
	 * List the user's watches
	 * @return null
	 */
	public function listUsersWatches() {
		foreach (
			$this->prc->getCurrentWatches( $this->user ) as $watch
		) {
			$this->output( $watch->getTitle() . "\n" );
		}
		return null;
	}

	/**
	 * Print all users watching this title on stdout
	 * @return null
	 */
	public function listTitlesWatchers() {
		$watchers = $this->prc->getRelatedChangeWatchers( $this->title );
		var_dump( $watchers);
		return null;
	}

	/**
	 * Print all titles being watched on stdout
	 * @return null
	 */
	public function listTitlesUnderWatch() {
		$groups = $this->prc->getWatchGroups();

		if ( count( $groups ) === 0 ) {
			$this->output(
				wfMessage( "periodic-related-changes-no-users" )->plain() . "\n"
			);
			return;
		}

		foreach ( $groups as $pageName => $watchers ) {
			$this->output( wfMessage(
				"periodic-related-changes-title-list", $pageName
			)->plain() . "\n" );
		}
	}
}

$maintClass = "MediaWiki\\Extension\\PeriodicRelatedChanges\\ManageWatch";
require_once DO_MAINTENANCE;
