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

namespace PeriodicRelatedChanges;

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

	/**
	 * The constructor, of course.
	 */
	public function __construct() {
		parent::__construct();
		$this->addDescription( "Manage watches on the periodic watchlist." );
		$this->addOption(
			"add", "(default) Add the page to the watchlist.", false, false, "a"
		);
		$this->addOption(
			"remove", "(default) Add the page to the watchlist.",
			false, false, "r"
		);
		$this->addArg(
			"user", "User to send notices to.  Must have an email address.",
			true
		);
		$this->addArg(
			"page", "The page to summarize RelatedChanges for.  Must exist.",
			true
		);
	}

	/**
	 * Where all the business happens.
	 */
	public function execute() {
		$userName = $this->getArg( 0 );
		$titleText = $this->getArg( 1 );

		$user = User::newFromName( $userName );
		if ( !( $user && $user instanceof User ) ) {
			$this->error( "Couldn't find $userName!", 1 );
		}

		$title = Title::newFromText( $titleText );
		if ( !( $title && $title instanceof Title ) ) {
			$this->error( "No such title ($titleText)!", 1 );
		}

		$prc = PeriodicRelatedChanges::getManager();
		if ( $this->hasOption( "remove" ) ) {
			$stat = $prc->removeWatch( $user, $title );
		} else {
			$stat = $prc->addWatch( $user, $title );
		}
		if ( $stat->isOK() ) {
			$this->output( "Success!\n" );
		} else {
			$this->error( $stat->getMessage()->plain() );
		}
	}
}

$maintClass = "PeriodicRelatedChanges\\ManageWatch";
require_once DO_MAINTENANCE;
