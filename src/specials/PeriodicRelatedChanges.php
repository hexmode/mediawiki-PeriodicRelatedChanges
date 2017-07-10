<?php

/**
 * Special page for PRC
 *
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

use ErrorPageError;
use HTML;
use HTMLForm;
use Linker;
use MWException;
use SpecialPage;
use PermissionsError;
use PHPExcel_IOFactory;
use PHPExcel_Reader_Exception;
use PHPExcel_Worksheet_Row;
use Title;
use User;
use WikiPage;
use Xml;

class SpecialPeriodicRelatedChanges extends SpecialPage {
	// The user under examination
	protected $userSubject;

	// The title under examination
	protected $titleSubject;

	// Does this user have permisssion to change any user's watchlist
	protected $canChangeAnyUser;

	// Can this user change themselves
	protected $canChangeSelf;

	// The PRCs we have queued
	protected $queuedPRCs;

	/**
	 * Constructor
	 * @param string $name Name of the special page, as seen in links and URLs
	 * @param string $restriction User right required, e.g. "block" or "delete"
	 */
	public function __construct(
		$name = 'PeriodicRelatedChanges',
		$restriction = 'periodic-related-changes'
	) {
		parent::__construct( $name, $restriction );

		$this->canChangeAnyUser = $this->getUser()->isAllowed(
			'periodic-related-changes-any-user'
		);
		$this->canChangeSelf
			= $this->getUser()->isAllowed( 'periodic-related-changes' );

		$this->days = $this->getRequest()->getVal( "days", 7 );
	}

	/**
	 * We'll be doing some writes
	 *
	 * @return bool
	 */
	public function doesWrites() {
		return true;
	}

	/**
	 * Where to classify this guy.
	 *
	 * @return string
	 */
	public function getGroupName() {
		return "changes";
	}

	/**
	 * Actually show the form and let people do stuff
	 *
	 * @param string|null $userName parameters passed to the page
	 */
	public function execute( $userName = null ) {
		parent::execute( $userName );

		/**
		 * These first two should be their own special page.
		 */
		if ( $this->listUsers() ) {
			return;
		}

		if ( $this->importUsers() ) {
			return;
		}

		$page = null;
		if ( strstr( $userName, "/" ) !== false ) {
			list( $userName, $page ) = explode( "/", $userName, 2 );
		}
		$user = $this->findUser( $userName );

		if ( $user ) {
			if ( $this->sendEmail( $user ) ) {
				return;
			}

			if ( $this->showFullReport( $user ) ) {
				return;
			}

			if ( !$page && $this->manageWatchList( $user ) ) {
				return;
			}

			$this->showRelatedChanges( $user, $page );
		}
	}

	/**
	 * Import users and their watches.
	 * @return boolean false if no permission or not requested
	 */
	public function importUsers() {
		$request = $this->getRequest();
		if ( $request->getVal( "importusers" ) !== "true" ) {
			return false;
		}
		$user = $this->getUser();
		$perm = 'periodic-related-changes-any-user';

		if ( !$user->isAllowed( $perm ) ) {
			throw new PermissionsError( $perm );
		}

		$this->checkReadOnly();

		if ( $request->wasPosted() && $request->getVal( 'action' ) == 'submit' ) {
			$this->doImport();
		}

		$this->showForm();
		return true;
	}

	/**
	 * Given an email, return a matching user.
	 * @param string $email to find
	 * @return User|null first one found
	 */
	protected function findUserByEmail( $email ) {
		$db = wfGetDB( DB_SLAVE );
		$id = $db->selectField( 'user', 'user_id', [ 'user_email' => $email ] );

		return $id ? User::newFromId( $id ) : null;
	}

	/**
	 * Actually handle the import.
	 */
	protected function doImport() {
		$request = $this->getRequest();
		$file = $request->getUpload( "fileimport" );
		$out = $this->getOutput();
		$this->queuedPRCs = [];

		if ( $file->getSize() ) {
			try {
				$xl = PHPExcel_IOFactory::load( $file->getTempName() );
			} catch ( PHPExcel_Reader_Exception $e ) {
				$out->addWikiMsg( 'periodic-related-changes-import-file-error', $e );
				return;
			}

			if ( $xl->getSheetCount() === 0 ) {
				$out->addWikiText(
					$this->msg( 'periodic-related-changes-import-no-data' )
				);
				return;
			}

			$rowIter = $xl->getActiveSheet()->getRowIterator( 2 );
			$errors = [];
			foreach ( $rowIter as $row ) {
				$errors = array_merge(
					$errors, $this->readRow( $rowIter->key(), $row )
				);
			}

			if ( count( $errors ) ) {
				$out->addWikiMsg( 'periodic-related-changes-import-error-intro' );
				foreach ( $errors as $error ) {
					$out->addWikiMsg(
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
	 * @param string $catval value from cell
	 * @return array|Title array with errror if there are problems
	 */
	protected function parseTitleCell( $catVal ) {
		try {
			$title = Title::newFromTextThrow( $catVal, NS_CATEGORY );
		} catch ( MWException $e ) {
			return [ $this->msg(
				'periodic-related-changes-bad-title', $e->getMessage()
			)->text() ];
		}

		if ( !$title->exists() ) {
			return [ $this->msg(
				'periodic-related-changes-title-not-exist', $title
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
		if ( $user->loadFromDatabase() === false
			 && strstr( $userVal, '@' ) !== false ) {
			$user = $this->findUserByEmail( $userVal );
			if ( !$user ) {
				return $this->msg(
					'periodic-related-changes-no-matching-email', $userVal
				)->text();
			}
		}

		if ( !$user ) {
			return $this->msg(
				'periodic-related-changes-no-user', $userVal
			)->text();
		}

		return $user;
	}

	/**
	 * Add a watch
	 * @param User $user who is watching
	 * @param Title $title being watched
	 * @return null|string string if an error
	 */
	protected function addWatch( User $user, Title $title ) {
		$prc = PeriodicRelatedChanges::getManager();
		$watch = $prc->get( $user, $title );
		if ( $watch->exists() ) {
			return $this->msg(
				"periodic-related-changes-already-watches", $user, $user->getEmail(), $title
			);
		}
		try {
			!$prc->add( $user, $title );
		} catch ( Exception $e ) {
			return $this->msg(
				"periodic-related-changes-add-fail", $user, $user->getEmail(), $title
			);
		}
	}

	/**
	 * Parse a single row
	 * format is:
	 *    ID, CATEGORY, USER|EMAIL*
	 * @param string $rowName for reference
	 * @param PHPExcel_Worksheet_Row $row to parse
	 * @return array problems
	 */
	protected function readRow( $rowName, PHPExcel_Worksheet_Row $row ) {
		$cellIter = $row->getCellIterator();
		$cellIter->setIterateOnlyExistingCells( true );
		$errors = [];
		$title = null;

		foreach ( $cellIter as $cell ) {
			$key = $cellIter->key();
			if ( $key === "A" ) {
				continue;
			}

			if ( $key === "B" ) {
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

			$error = $this->addWatch( $user, $title );
			if ( $error !== false ) {
				$errors[] = $error;
				continue;
			}

			$this->getOutput()->addWikiMsg(
				"periodic-related-changes-add-fail", $user, $title
			);

		}
		return $errors;
	}

	/**
	 * The actual form. Cribbed from SpecialImport.
	 */
	public function showForm() {
		$filename = $this->getRequest()->getUpload( "fileimport" )->getName();
		$comment = $this->getRequest()->getVal(
			"log-comment", $this->msg( "periodic-related-changes-import-log-msg" )
		);

		$action = $this->getPageTitle()->getLocalURL( [ 'action' => 'submit' ] );
		$user = $this->getUser();
		$this->getOutput()->addHTML(
			Xml::fieldset( $this->msg( 'periodic-related-changes-import' )->text() ) .
			Xml::openElement(
				'form',
				[
					'enctype' => 'multipart/form-data',
					'method' => 'post',
					'action' => $action,
					'id' => 'mw-import-upload-form'
				]
			) .
			$this->msg( 'periodic-related-changes-import-intro' )->parseAsBlock() .
			Html::hidden( 'action', 'submit' ) .
			Html::hidden( 'importusers', 'true' ) .
			Html::hidden( 'editToken', $user->getEditToken() ) .
			Xml::openElement( 'table', [ 'id' => 'mw-import-table-upload' ] ) .
			"<tr><td class='mw-label'>" .
			Xml::label( $this->msg( 'import-upload-filename' )->text(), 'fileimport' ) .
			"</td><td class='mw-input'>" .
			Html::input( 'fileimport', $filename, 'file', [ 'id' => 'fileimport' ] ) .
			" </td></tr><tr><td class='mw-label'>" .
			Xml::label( $this->msg( 'import-comment' )->text(), 'mw-import-comment' ) .
			"</td><td class='mw-input'>" .
			Xml::input(
				'log-comment', 50, $comment,
				[ 'id' => 'mw-import-comment', 'type' => 'text' ]
			) . "</td><td></td><td class='mw-submit'>" .
			Xml::submitButton( $this->msg( 'uploadbtn' )->text() ) .
			"</td></tr>" .
			Xml::closeElement( 'table' ) .
			Xml::closeElement( 'form' ) .
			Xml::closeElement( 'fieldset' )
		);
	}

	/**
	 * List out all the users who have watches.
	 * @return boolean false if no permission or not requested
	 */
	public function listUsers() {
		if ( $this->getRequest()->getVal( "listusers" ) !== "true" ) {
			return false;
		}
		if ( $this->canChangeAnyUser ) {
			$out = $this->getOutput();
			$dbr = wfGetDB( DB_SLAVE );
			$users = $dbr->select(
				'periodic_related_change', [ 'DISTINCT wc_user as id' ],
				[], __METHOD__
			);
			if ( $users->numRows() > 0 ) {
				$out->addHTML(
					wfMessage( 'periodic-related-changes-userlist-prefix' )
				);

				foreach ( $users as $user ) {
					$out->addHTML( wfMessage(
							'periodic-related-changes-list-user',
							User::newFromId( $user->id )
						) );
				}
				return true;
			}
			$out->addHTML(
				wfMessage( 'periodic-related-changes-no-users' )
			);
		}
		return false;
	}

	/**
	 * Send the full report to a user
	 * @param User $user to send to
	 * @return bool false if not sent
	 */
	public function sendEmail( User $user ) {
		if ( !$this->getRequest()->getCheck( "sendemail" ) ) {
			return false;
		}
		$out = $this->getOutput();
		$watches = RelatedChangeWatchList::newFromUser( $user );
		$status = $watches->sendEmail( $user, $this->days );
		$out->setPageTitle( wfMessage(
			"periodic-related-changes-fullreport", $user, $this->days
		) );

		if ( !$status->isOk() ) {
			$out->addWikiMsg( "periodic-related-changes-sent-email-problem" );
			$out->addWikiMsg( $status->getWikiText() );
			return false;
		}
		$out->addWikiMsg(
			"periodic-related-changes-sent-email-success", $user
		);
		return true;
	}

	/**
	 * Show the full report for all changes
	 * @param User $user report is for
	 * @return bool true if report was shown
	 */
	public function showFullReport( User $user ) {
		$doFullReport = $this->getRequest()->getCheck( "fullreport" );
		if ( !$user || !$doFullReport ) {
			return false;
		}

		$out = $this->getOutput();
		if (
			!$this->getRequest()->getCheck( "printable" ) && $user->getEmail()
		) {
			$out->addWikiMsg( "periodic-related-changes-link-to-email", $user );
		}
		foreach (
			PeriodicRelatedChanges::getManager()->getCurrentWatches( $user )
			as $watch
		) {
			$this->showRelatedChanges( $user, $watch->getTitle() );
		}
		$out->setPageTitle( wfMessage(
			"periodic-related-changes-fullreport", $user, $this->days
		) );

		return true;
	}

	/**
	 * Show the changes for this article
	 * @param User $user to examine
	 * @param string $page to find related changes for
	 * @return bool
	 */
	public function showRelatedChanges( User $user, $page ) {
		$out = $this->getOutput();
		try {
			$title = Title::newFromTextThrow( $page );
		} catch ( MalFormedTitleException $ex ) {
			$out->addWikiMsg(
				"periodic-related-changes-invalid-title", $page, $ex
			);
			return true;
		}
		$watches = RelatedChangeWatchList::newFromUser( $user );
		if ( !$watches->hasTitle( $title ) ) {
			$out->addWikiMsg(
				"periodic-related-changes-no-user-title", $user, $title
			);
			return true;
		}

		$page = WikiPage::factory( $title );
		$changesTo = $watches->getChangesFor( $page, $this->days, "to" );
		$changesFrom = $watches->getChangesFor( $page, $this->days, "from" );

		if ( !$changesTo && !$changesFrom ) {
			$out->addWikiMsg(
				"periodic-related-changes-no-user-title-days", $user, $title,
				$this->days
			);
			return true;
		}

		$seen = [];
		if ( $changesTo ) {
			$out->addWikiMsg( "periodic-related-changes-link-to", $title );
			foreach ( $changesTo as $changeTitle => $change ) {
				$this->showChange( $changeTitle, $change );
				$seen[$changeTitle] = true;
			}
		}

		$shown = false;
		foreach ( $changesFrom as $changeTitle => $change ) {
			if ( !isset( $seen[$changeTitle] ) ) {
				if ( !$shown ) {
					$out->addWikiMsg(
						"periodic-related-changes-link-from", $title
					);
					$shown = true;
				}
				$this->showChange( $changeTitle, $change );
			}
		}
		return true;
	}

	/**
	 * Show a page with a link to the changes
	 * @param string $changeTitle the changed page
	 * @param array $diff array containing the diff information
	 * @return bool always true
	 */
	public function showChange( $changeTitle, array $diff ) {
		$count = count( $diff['ts'] );
		$old = $diff['old'];
		$new = $diff['new'];

		$diffLink = $this->getDiffLink( $old, $new );

		if ( $old != 0 ) {
			$this->getOutput()->addWikiMsg(
				"periodic-related-changes-linked-item", $changeTitle,
				$count, $diffLink
			);
		} else {
			$this->getOutput()->addWikiMsg(
				"periodic-related-changes-page-created", $changeTitle
			);
		}
		return true;
	}

	/**
	 * Return the link needed to see this group of diffs
	 * @FIXME copypasta with listWatches
	 * @param int $old revision #
	 * @param int $new revision #
	 * @return string
	 */
	protected function getDiffLink( $old, $new ) {
		global $wgServer, $wgScript;

		return $wgServer . $wgScript . "?diff=$new&oldid=$old";
	}

	/**
	 * Add other possible actions to the form.
	 */
	protected function addOtherActions() {
		$thisTitle = Title::newFromText( "PeriodicRelatedChanges", NS_SPECIAL );
		$action = [
			Linker::link(
				$thisTitle,
				wfMessage( "periodic-related-changes-listusers" ),
				[], [ 'listusers' => 'true' ] ),
			Linker::link(
				$thisTitle,
				wfMessage( "periodic-related-changes-importusers" ),
				[], [ 'importusers' => "true" ] )
		];

		$this->getOutput()->setSubTitle( $this->getLanguage()->commaList( $action ) );
	}

	/**
	 * Display the auto-complete form for a user
	 *
	 * @param string|bool $userName false if form is needed, username otherwise
	 *
	 * @return User|bool
	 */
	public function findUser( $userName ) {
		$out = $this->getOutput();
		if ( !$userName && $this->canChangeAnyUser ) {
			$out->addModules( 'ext.periodicRelatedChanges.user' );

			$formDescriptor = [
				'username' => [
					'label-message'       =>
					'periodic-related-changes-user-editname',
					'type'                => 'user',
					'size'                => 30,
					'autofocus'           => true,
					'validation-callback' => [ $this, 'findUserValidate' ],
					'required'            => true
				] ];
			$form = HTMLForm::factory( 'ooui', $formDescriptor,
									   $this->getContext() );
			$form->setFormIdentifier( __METHOD__ );
			$form->setSubmitCallback( [ $this, 'findUserSubmit' ] );
			$form->setSubmitTextMsg( 'periodic-related-changes-getuser' );
			$this->addOtherActions();

			$form->show();
			return false;
		}

		// If you can't edit just anyone's, you can only see your own.
		if ( !$this->canChangeAnyUser
			 && $this->getUser()->isAllowed( 'periodic-related-changes' )
			 && ( !isset( $userName )
				  || $userName !== $this->getUser()->getName() )
		) {
			$out->redirect(
				$this->getTitle()->getLinkURL() . "/"
				. $this->getUser()->getName()
			);
			return false;
		}

		$user = User::newFromName( trim( $userName, "/" ) );
		if ( !$this->canChangeAnyUser ) {
			if ( !$userName || $user->getName()
				 !== $this->getUser()->getName() ) {
				$this->findUserSubmit(
					[ "username" => $this->getUser()->getName() ]
				);
				return false;
			}
			$user = $this->getUser();
		}

		if ( !$user || $user->getId() === 0 ) {
			throw new ErrorPageError(
				"periodic-related-changes-error",
				"periodic-related-changes-userdoesnotexist",
				[ $user ]
			);
		}
		$this->userSubject = $user;
		return $user;
	}

	/**
	 * Handle user form validation
	 * @param string|null $userName to validate
	 * @param array $formData contains the rest of the form
	 * @return bool|string true if valid, error message otherwise
	 */
	public function findUserValidate( $userName, array $formData ) {
		if ( $userName ) {
			$user = User::newFromName( $userName );
			if ( $user->getID() === 0 ) {
				return wfMessage( 'periodic-related-changes-nosuchuser' );
			}
		}
		return true;
	}

	/**
	 * Handle user form submission
	 * @param array $formData from the request
	 */
	public function findUserSubmit( array $formData ) {
		if ( isset( $formData['username'] ) && $formData['username'] != '' ) {
			$this->getOutput()->redirect(
				$this->getTitle()->getLinkURL() . "/" . $formData['username']
			);
		}
	}

	/**
	 * Manage the watchlist for this user
	 * @param User $user to list watches for
	 * @return bool Did we show this?
	 */
	public function manageWatchList( User $user ) {
		$out = $this->getOutput();
		if ( $this->canChangeAnyUser ) {
			$out->setSubTitle(
				wfMessage( "periodic-related-changes-lookupuser" )
			);
		}

		$this->addTitleFormHandler();
		$this->listAndRemoveTitlesFormHandler();
		return true;
	}

	/**
	 * Here's the form for adding titles to the watch list.
	 */
	public function addTitleFormHandler() {
		$formDescriptor = [
			'addTitle' => [
				'section'             => 'addtitle',
				'label-message'       =>
				wfMessage( 'periodic-related-changes-user-addtitle',
						   [ $this->userSubject->getName() ]
				),
				'type'                => 'title',
				'autofocus'           => true,
				'validation-callback' => [ $this, 'addTitleValidate' ],
			] ];

		$form = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext(),
								   'periodic-related-changes' );
		$form->setFormIdentifier( __METHOD__ );
		$form->setSubmitCallback( [ $this, 'addTitleSubmit' ] );
		$form->setSubmitTextMsg( 'periodic-related-changes-addtitle' );
		$form->show();
	}

	/**
	 * Handle title validation for the form
	 * @param string|null $titleString to validate
	 * @return bool|string true if valid, error message otherwise
	 */
	public function addTitleValidate( $titleString ) {
		$title = null;
		if ( $titleString ) {
			$title = Title::newFromText( $titleString );
			if ( !$title->exists() ) {
				return wfMessage( 'periodic-related-changes-nosuchtitle' );
			}
		}

		if ( $title ) {
			$watch
				= PeriodicRelatedChanges::getManager()->getRelatedChangeWatcher(
					$this->userSubject, WikiPage::factory( $title )
			);

			if ( $watch->exists() ) {
				return wfMessage( 'periodic-related-changes-watchalreadyexists',
								  [ $titleString ] );
			}
			$this->titleSubject = $title;
		}

		return true;
	}

	/**
	 * Handle title form submission
	 * @param array $formData from the request
	 * @return bool for form handling
	 */
	public function addTitleSubmit( array $formData ) {
		$prc = PeriodicRelatedChanges::getManager();
		// Chance of race condition here that would result in an exception?
		if ( $prc->add( $this->userSubject, $this->titleSubject ) ) {
			$this->getOutput()->addWikiMsg( "periodic-related-changes-added",
											$this->titleSubject,
											$this->userSubject
			);
			$this->getOutput()->addWikiMsg( "periodic-related-changes-return" );

			return true;
		}
		return wfMessage( "periodic-related-changes-add-fail",
						  [ $this->titleSubject, $this->userSubject ] );
	}

	/**
	 * Handle watch display and removal form
	 */
	public function listAndRemoveTitlesFormHandler() {
		$prc = PeriodicRelatedChanges::getManager();
		$formDescriptor = [];
		foreach ( $prc->getCurrentWatches( $this->userSubject ) as $watch ) {
			$formDescriptor[$watch->getFormID()] = [
				'section' => 'currentwatchlist',
				'label'   => $watch->getTitle(),
				'type'    => 'check',
				'size'    => 30,
			];
		}
		$form = HTMLForm::factory(
			'ooui', $formDescriptor,
			$this->getContext(),
			"periodic-related-changes"
		);
		$form->setFormIdentifier( __METHOD__ );
		$form->setSubmitDestructive();
		$form->setSubmitCallback( [ $this, 'handleWatchRemoval' ] );
		$form->setSubmitTextMsg( 'periodic-related-changes-removetitles' );
		$form->show();
	}

	/**
	 * Handle removal of watch.
	 * @param array $formData from the form
	 * @return null
	 */
	public function handleWatchRemoval( array $formData ) {
		wfDebugLog( __METHOD__, serialize( $formData ) );

		$watchesToRemove = array_filter(
			array_map(
					function ( $item ) use ( $formData ) {
						if ( $formData[$item] === true )
							return RelatedChangeWatcher::newFromFormID( $item );
					}, array_keys( $formData )
			) );
		wfDebugLog( __METHOD__, var_export( $watchesToRemove, true ) );

		foreach ( $watchesToRemove as $watch ) {
			$watch->remove();
		}
		return true;
	}
}
