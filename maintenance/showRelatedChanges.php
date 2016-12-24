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
	die( "Please set the enviorment variable MW_INSTALL_PATH to\n" .
		 "the location of your mediawiki installation.\n" );
}
require_once $maint;

class ShowRelatedChanges extends Maintenance {

	/**
	 * The constructor, of course.
	 */
	public function __construct() {
		parent::__construct();
		$this->addDescription( "List out the related changes for a page that a user wants ".
							   "to be notified about. The user must have already added ".
							   "the page to their periodic watches." );
		$this->addArg( "user", "The user who list last-seen time will be used.",
					   true );
		$this->addArg( "page", "The page to summarize RelatedChanges for. Must exist. ".
					   "Must be on the user's list of periodic watches.",
					   true );
		$this->addOption( "period", "The period, in days, to cover. (7)", false, true,
						  "p" );
	}

	/**
	 * Where all the business happens.
	 */
	public function execute() {
		$wrc = PeriodicRelatedChanges::getManager();

		$user = User::newFromName( $this->getArg( 0 ) );
		$page = WikiPage::factory( Title::newFromText( $this->getArg( 1 ) ) );

		if ( $user === false || $user->getID() === 0 ) {
			$this->error( "Invalid user.", 1 );
		}

		if ( $page !== null && !$page->exists() ) {
			$this->error( "Page does not exist.", 1 );
		}

		try {
			$watcher = $wrc->getRelatedWatcher( $user, $page );
			$watcher->setSince( $this->getOption( "period", 7 ) );
			$watcher->setLimit( 200 );

			$this->displayChanges( $wrc->getCollectedChanges( $watcher ) );
		} catch ( MWException $e ) {
			$this->error( $e->getMessage(), 1 );
		}
	}

	/**
	 * List the editors
	 * @param string $editors to list
	 * @param string $prefix to print first, default "\t"
	 */
	public function showEditors( array $editors, string $prefix = "\t" ) {
		$this->output( "{$prefix}Editors (edits):\n" );
		arsort( $editors );
		foreach ( $editors as $editor => $editCount ) {
			$this->output( "$prefix\t$editor ($editCount)\n" );
		}
	}

	/**
	 * List out the pages
	 * @param array $pages to list
	 */
	public function showPages( array $pages ) {
		$this->output( "Pages:\n" );
		foreach ( $pages as $title => $info ) {
			$this->output( "\tTitle: $title (" );
			$t = Title::newFromText( $title );
			$this->output( $t->countRevisionsBetween( $info['oldestId'], 0 ) );
			$this->output( ")\n\t\tLink to diff: " . $info["oldestId"] . "\n" );
			$this->showEditors( $info['editors'], "\t\t" );
		}
	}

	/**
	 * Show the report
	 * @param array $changes to show
	 */
	public function displayChanges( array $changes ) {
		if ( isset( $changes['user'] ) ) {
			$this->showEditors( $changes['user'] );
			$this->output( "\n" );
		}
		if ( isset( $changes['page'] ) ) {
			$this->showPages( $changes['page'] );
		}
	}
}

$maintClass = "PeriodicRelatedChanges\\ShowRelatedChanges";
require_once DO_MAINTENANCE;
