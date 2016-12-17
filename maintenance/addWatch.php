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

namespace WeeklyRelatedChanges;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

use Maintenance;

class AddWatch extends Maintenance {

	/**
	 * The constructor, of course.
	 */
	public function __construct() {
		parent::__construct();
		$this->addDescription( "Add a watch to the weekly watches." );
		$this->addArg( "user", "User to send notices to.  Must have an email address.",
					   true );
		$this->addArg( "page", "The page to summarize RelatedChanges for.  Must exist.",
					   true );
	}

	/**
	 * Where all the business happens.
	 */
	public function execute() {
		$wrc = WeeklyRelatedChanges::getManager();

		try {
			$wrc->add( $this->getArg( 0 ), $this->getArg( 1 ) );
		} catch ( Exception $e ) {
			$this->error( $e->getMessage() );
		}
	}
}

$maintClass = "WeeklyRelatedChanges\\AddWatch";
require_once ( DO_MAINTENANCE );
