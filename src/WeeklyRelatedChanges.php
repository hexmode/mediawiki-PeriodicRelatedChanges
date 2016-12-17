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

use Title;
use User;
use WikiPage;

class WeeklyRelatedChanges {
	/**
	 * Get the manager for this
	 *
	 * @returns WeeklyRelatedChanges
	 */
	public static function getManager() {
		return new self();
	}

	/**
	 * Constructor
	 */
	public function __construct() {
	}

	/**
	 * Add a watcher.
	 *
	 * @param string $userName text form of username.
	 * @param string $pageName text form of page name that will be made into a title object.
	 *
	 * @return bool
	 */
	public function add( string $userName, string $pageName ) {
		$user = User::newFromName( $userName );
		if ( $user === false ) {
			throw new MWException( "Invalid user name." );
		}

		$title = Title::newFromTextThrow( $pageName );

		return $this->addWatch( $user, $title );
	}

	/**
	 * Store a watch for the user.  SQL schema ensures that there can only be one.
	 *
	 * @param User $user the user
	 * @param Title $title the title to watch for related changes.
	 *
	 * @return bool
	 */
	public function addWatch( User $user, Title $title ) {
		$dbw = wfGetDB( DB_MASTER );
		$dbw->insert( 'weekly_changes',
					  [ 'wc_user' => $user->getId(),
						'wc_page' => WikiPage::factory( $title )->getID() ],
					  __METHOD__,
					  [ 'IGNORE' ] );
		return true;
	}
}
