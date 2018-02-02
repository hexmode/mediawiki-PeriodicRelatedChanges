<?php

/*
 * Copyright (C) 2018  NicheWork, LLC
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

use MWException;
use PHPExcel_IOFactory;
use PHPExcel_Reader_Exception;
use PHPExcel_Settings;
use PHPExcel_Worksheet_Row;
use SpecialPage;
use Status;
use Title;
use User;

class UserImporter {

	// The PRCs we have queued
	protected $queuedPRCs;

	private $output;

	public function __construct() {
		// Nothing to do!
	}

	/**
	 * Verify extensions, etc are in place for this to work.
	 * @return Status
	 */
	public function check() {
		$status = new Status();
		if ( !class_exists( 'PHPExcel_IOFactory' ) ) {
			$status->error( "periodic-related-changes-run-composer" );
		}
		return $status;
	}

	/**
	 * Actually handle the import.
	 * @param SpecialPage $page where the import messages are displayed
	 */
	public function doImport( SpecialPage $page ) {
		$request = $page->getRequest();
		$file = $request->getUpload( "fileimport" );
		$this->output = $page->getOutput();
		$this->queuedPRCs = [];

		if ( !class_exists( 'ZipArchive' ) ) {
			PHPExcel_Settings::setZipClass( PHPExcel_Settings::PCLZIP );
		}
		if ( $file->getSize() ) {
			try {
				$sheet = PHPExcel_IOFactory::load( $file->getTempName() );
			} catch ( PHPExcel_Reader_Exception $e ) {
				$this->output->addWikiMsg(
					'periodic-related-changes-import-file-error', $e
				);
				return;
			}

			if ( $sheet->getSheetCount() === 0 ) {
				$this->output->addWikiText(
					wfMessage( 'periodic-related-changes-import-no-data' )
				);
				return;
			}

			$rowIter = $sheet->getActiveSheet()->getRowIterator( 1 );
			$errors = [];
			foreach ( $rowIter as $row ) {
				$errors = array_merge(
					$errors, $this->readRow( $row )
				);
			}

			if ( count( $errors ) ) {
				$this->output->addWikiMsg(
					'periodic-related-changes-import-error-intro'
				);
				foreach ( $errors as $error ) {
					$this->output->addWikiMsg(
						'periodic-related-changes-import-error-item', $error
					);
				}
			}
		}
	}

	/**
	 * Parse a single row
	 * format is:
	 *    ID, CATEGORY, USER|EMAIL*
	 * @param string $catVal value from cell
	 * @return array|Title array with errror if there are problems
	 */
	protected function parseTitleCell( $catVal ) {
		try {
			$title = Title::newFromTextThrow( $catVal );
		} catch ( MWException $e ) {
			return [ wfMessage(
				'periodic-related-changes-bad-title', $e->getMessage()
			)->text() ];
		}

		return $title;
	}

	/**
	 * Parse a single row
	 * format is:
	 *    ID, CATEGORY, USER|EMAIL*
	 * @param string $userVal value from cell
	 * @return null|array|User array with errror if there are problems
	 */
	protected function parseUserCell( $userVal ) {
		if ( !$userVal ) {
			return null;
		}

		$user = User::newFromName( $userVal );
		if ( $user->getId() === 0
			 && strstr( $userVal, '@' ) !== false ) {
			$user = $this->findUserByEmail( $userVal );
			if ( !$user ) {
				return wfMessage(
					'periodic-related-changes-no-matching-email', $userVal
				)->text();
			}
		}

		if ( !$user ) {
			return wfMessage(
				'periodic-related-changes-no-user', $userVal
			)->text();
		}

		return $user;
	}

	/**
	 * Given an email, return a matching user.
	 * @param string $email to find
	 * @return User|null first one found
	 */
	protected function findUserByEmail( $email ) {
		$dbh = wfGetDB( DB_SLAVE );
		$userId = $dbh->selectField( 'user', 'user_id', [ 'user_email' => $email ] );

		return $userId ? User::newFromId( $userId ) : null;
	}

	/**
	 * Parse a single row
	 * format is:
	 *    ID, CATEGORY, USER|EMAIL*
	 * @param PHPExcel_Worksheet_Row $row to parse
	 * @return array problems
	 */
	/**
	 * Add a watch
	 * @param User $user who is watching
	 * @param Title $title being watched
	 * @return null|string string if an error
	 */
	protected function addWatch( User $user, Title $title ) {
		$prc = Manager::getManager();
		$watch = $prc->get( $user, $title );
		if ( $watch->exists() ) {
			return wfMessage(
				"periodic-related-changes-already-watches", $user,
				$user->getEmail(), $title
			);
		}
		try {
			$prc->addWatch( $user, $title );
		} catch ( Exception $e ) {
			return wfMessage(
				"periodic-related-changes-add-fail", $user,
				$user->getEmail(), $title
			);
		}
		return true;
	}

	/**
	 * @param PHPExcel_Worksheet_Row $row under examination
	 * @return array of errors
	 */
	protected function readRow( PHPExcel_Worksheet_Row $row ) {
		$cellIter = $row->getCellIterator();
		$errors = [];
		$title = null;

		foreach ( $cellIter as $cell ) {
			$key = $cellIter->key();
			if ( $key === "A" ) {
				continue;
			}

			if ( $key === "B" ) {
				if ( ! $cell->getValue() ) {
					wfDebugLog( "PRC Import", "Skipping empty row" );
					break;
				}
				$title = $this->parseTitleCell( $cell->getValue() );
				if ( is_array( $title ) ) {
					return $title;
				}
				continue;
			}

			$user = $this->parseUserCell( $cell->getValue() );
			if ( $user === null ) {
				continue;
			}
			if ( !( $user instanceof User ) ) {
				$errors[] = $user;
				continue;
			}

			$ret = $this->addWatch( $user, $title );
			if ( $ret !== true ) {
				$errors[] = $ret;
				continue;
			}

			$this->output->addWikiMsg(
				"periodic-related-changes-add-success", $user, $title
			);

		}
		return $errors;
	}

}
