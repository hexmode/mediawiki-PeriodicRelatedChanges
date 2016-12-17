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

use DatabaseUpdater;

class Hook {
	/**
	 * Fired when MediaWiki is updated to allow extensions to update
	 * the database.
	 *
	 * @param DatabaseUpdater $updater the db handle
	 * @return bool always true
	 */
	public static function onLoadExtensionSchemaUpdates(
		DatabaseUpdater $updater
	) {
		$updater->addExtensionTable( 'weekly_changes', __DIR__
									 . "/../sql/weekly_changes.sql" );
		return true;
	}

	/**
	 * Any extension-specific initialisation?
	 */
	public static function initExtension() {
	}

	/**
	 * Register a config thingy
	 *
	 * @return GlobalVarConfig
	 */
	public function makeConfig() {
		return new GlobalVarConfig( "WeeklyRelatedChanges" );
	}
}
