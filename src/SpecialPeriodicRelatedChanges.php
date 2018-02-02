<?php

/**
 * Special page for PRC
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

namespace MediaWiki\Extensions\PeriodicRelatedChanges;

use ErrorPageError;
use HTML;
use HTMLForm;
use Linker;
use SpecialPage;
use PermissionsError;
use Title;
use User;
use WikiPage;
use Xml;

/**
 * Complexity here could be reduced by abstracting out stuff.  what?
 */
class SpecialPeriodicRelatedChanges extends SpecialPage {
	// The user under examination
	protected $userSubject;

	// The title under examination
	protected $titleSubject;

	// Does this user have permisssion to change any user's watchlist
	protected $canChangeAnyUser;

	// Can this user change themselves
	protected $canChangeSelf;

	// Possible actions and the method they call
	protected $actionMap = [];

	/**
	 * @param string $name Name of the special page, as seen in links and URLs
	 * @param string $restriction User right required
	 */
	public function __construct(
		$name = 'PeriodicRelatedChanges',
		$restriction = "periodic-related-changes"
	) {
		parent::__construct( $name, $restriction );
	}

	/**
	 * We do some writes
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

	private function init() {
		$this->prc = Manager::getManager();

		$this->canChangeAnyUser = $this->getUser()->isAllowed(
			'periodic-related-changes-any-user'
		);

		$this->canChangeSelf
			= $this->getUser()->isAllowed( 'periodic-related-changes' );

		$this->days = $this->getRequest()
					->getVal( "days", $this->getRequest()
							  ->getVal( "wpdays", 7 ) );

		if ( $this->canChangeAnyUser ) {
			$this->actionMap = [
				"finduser" => 'showFindUserForm',
				"listusers" => 'listAllUsers',
				"listwatchgroups" => 'listWatchGroups',
				"importusers" => 'importUsers',
			];
		}
	}

	/**
	 * Actually show the form and let people do stuff
	 *
	 * @param string|null $userName parameters passed to the page
	 */
	public function execute( $userName = null ) {
		$this->init();
		$this->setHeaders();
		$this->checkPermissions();
		$this->outputHeader();
		$this->addOtherActions();

		$page = null;
		if ( strstr( $userName, "/" ) !== false ) {
			list( $userName, $page ) = explode( "/", $userName, 2 );
		}

		if ( !$userName && $this->delegateAction() === true ) {
			return;
		}

		$user = $this->findUser( $userName );
		if ( $user ) {
			if ( !$page && $this->manageWatchList() ) {
				return;
			}

			$this->showRelatedChanges( $user, $page );
		}
	}

	/**
	 * Delegate an action based on the action parameter.
	 *
	 * @return bool true if nothing else needs to be done.
	 */
	protected function delegateAction() {
		$action = $this->getRequest()->getVal( "action" );
		$method = isset( $this->actionMap[$action] )
				? $this->actionMap[$action]
				: null;
		$ret = false;

		if ( $method && method_exists( $this, $method ) ) {
			$ret = $this->$method();
		}
		return $ret;
	}

	/**
	 * List all PRCs, grouping users into lists for each one
	 * @return bool false if no permission or not requested
	 */
	protected function listWatchGroups() {
		$out = $this->getOutput();
		if ( !$this->canChangeAnyUser ) {
			return false;
		}

		$groups = $this->prc->getWatchGroups();

		if ( count( $groups ) === 0 ) {
			$out->addWikiMsg( "periodic-related-changes-no-users" );
			return true;
		}

		$out->addWikiMsg( "periodic-related-changes-listwatchgroups-header" );

		foreach ( $groups as $pageName => $watchers ) {
			$out->addWikiMsg(
				"periodic-related-changes-group-header",
				Title::newFromText( $pageName )
			);
			$this->listUsers( $watchers );
		}

		return true;
	}

	/**
	 * Show subscribers to this group
	 * @param array $watcher list of users
	 */
	protected function showGroupSubscribers( array $watcher ) {
		$out = $this->getOutput();
		foreach ( $watcher as $user ) {
			$out->addWikiMsg(
				"periodic-related-changes-group-subscriber",
				User::newFromId( $user )
			);
		}
	}

	/**
	 * Import users and their watches.
	 * @return bool false if no permission or not requested
	 */
	protected function importUsers() {
		$this->checkReadOnly();

		$request = $this->getRequest();
		$importer = new UserImporter();
		$user = $this->getUser();
		$perm = 'periodic-related-changes-any-user';

		if ( !$user->isAllowed( $perm ) ) {
			throw new PermissionsError( $perm );
		}

		$status = $importer->check();
		if ( ! $status->isOk() ) {
			$this->getOutput()->addWikiMsg( $status->getMessage() );
			return true;
		}

		if (
			$request->wasPosted()
			&& $request->getVal( 'upload' ) == 'submit'
		) {
			$importer->doImport( $this );
		}

		$this->showForm();
		return true;
	}

	/**
	 * The actual import form. Cribbed from SpecialImport.
	 */
	protected function showForm() {
		$filename = $this->getRequest()->getUpload( "fileimport" )->getName();
		$comment = $this->getRequest()->getVal(
			"log-comment", $this->msg(
				"periodic-related-changes-import-log-msg"
			)
		);

		$action = $this->getPageTitle()->getLocalURL(
			[ 'action' => 'importusers' ]
		);
		$user = $this->getUser();
		$this->getOutput()->addHTML(
			Xml::fieldset(
				$this->msg( 'periodic-related-changes-import' )->text()
			) .
			Xml::openElement(
				'form',
				[
					'enctype' => 'multipart/form-data',
					'method' => 'post',
					'action' => $action,
					'id' => 'mw-import-upload-form'
				]
			) .
			$this->msg(
				'periodic-related-changes-import-intro'
			)->parseAsBlock() .
			Html::hidden( 'upload', 'submit' ) .
			Html::hidden( 'importusers', 'true' ) .
			Html::hidden( 'editToken', $user->getEditToken() ) .
			Xml::openElement( 'table', [ 'id' => 'mw-import-table-upload' ] ) .
			"<tr><td class='mw-label'>" .
			Xml::label(
				$this->msg( 'import-upload-filename' )->text(), 'fileimport'
			) .
			"</td><td class='mw-input'>" .
			Html::input(
				'fileimport', $filename, 'file', [ 'id' => 'fileimport' ]
			) .
			" </td></tr><tr><td class='mw-label'>" .
			Xml::label(
				$this->msg( 'import-comment' )->text(), 'mw-import-comment'
			) .
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
	 * @return bool false if no permission or not requested
	 */
	public function listAllUsers() {
		if ( $this->canChangeAnyUser ) {
			$out = $this->getOutput();
			$dbr = wfGetDB( DB_SLAVE );
			$users = $dbr->select(
				'periodic_related_change', [ 'DISTINCT wc_user as id' ],
				[], __METHOD__
			);
			if ( $users->numRows() > 0 ) {
				$out->addHTML(
					wfMessage( 'periodic-related-changes-listusers-header' )
				);

				$this->listUsers( $users );
				return true;
			}
			$out->addHTML(
				wfMessage( 'periodic-related-changes-no-users' )
			);
		}
		return false;
	}

	/**
	 * Show a list of users.
	 *
	 * @param array $users list
	 */
	protected function listUsers( $users ) {
		foreach ( $users as $user ) {
			if ( !( $user instanceof User ) ) {
				$user = User::newFromId( $user->id );
			}
			$this->getOutput()->addHTML( wfMessage(
				'periodic-related-changes-list-user', $user
			) );
		}
	}

	/**
	 * Send the full report to a user
	 * @param User $user to send to
	 * @return bool false if not sent
	 */
	public function sendEmail( User $user ) {
	}

	/**
	 * Header for the non-printable full report
	 * @param User $user report is for
	 */
	protected function showFullReportHeader( User $user ) {
		$out = $this->getOutput();
		if ( $user->getEmail() ) {
			$out->addWikiMsg( "periodic-related-changes-link-to-email", $user );
		}
		$formDescriptor = [
			'fullreport' => [
				'type' => "hidden",
				'value' => 1
			],
			'days' => [
				'label-message' =>
				wfMessage( 'periodic-related-changes-change-period' ),
				'type'          => 'text',
				'value'         => $this->days,
				'size'          => 3,
				'width'          => 3,
				'autofocus'     => true
			] ];

		$form = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext(),
								   'periodic-related-changes' );

		$form->setMethod( "GET" );
		$form->setFormIdentifier( __METHOD__ );
		$form->setSubmitCallback( [ $this, 'findUserSubmit' ] );
		$form->show();
	}

	/**
	 * Show the full report for all changes
	 * @param User $user report is for
	 * @return bool true if report was shown
	 */
	public function showFullReport( User $user ) {
	}

	/**
	 * @param string $page to find related changes for
	 * @param OutputPage $out to show messages
	 * @return bool|Title
	 */
	public function getTitleForPage( $page, $out ) {
		if ( $page instanceof Title ) {
			$title = $page;
		} else {
			try {
				$title = Title::newFromTextThrow( $page );
			} catch ( MalFormedTitleException $ex ) {
				$out->addWikiMsg(
					"periodic-related-changes-invalid-title", $page, $ex
				);
				return true;
			}
		}
		return $title;
	}

	/**
	 * Show the changes for this article
	 * @param User $user to examine
	 * @param string $page to find related changes for
	 * @return bool
	 */
	public function showRelatedChanges( User $user, $page ) {
		$out = $this->getOutput();

		$title = $this->getTitleForPage( $page, $out );
		if ( ! ( $title instanceof Title ) ) {
			return true;
		}

		$watches = RelatedChangeWatchlist::newFromUser( $user );
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
		$request = $this->getRequest();

		if ( !$request->getVal( 'action' ) ) {
			$request->setVal( 'action', "finduser" );
		}

		$this->getOutput()->setSubTitle(
			$this->getLanguage()->commaList( $this->getActionLinks() )
		);
	}

	/**
	 * Provide subtitle links on manage watchlist
	 * @return array
	 */
	protected function getActionLinks() {
		$request = $this->getRequest();
		$thisTitle = Title::newFromText( "PeriodicRelatedChanges", NS_SPECIAL );
		$actionList = [];
		$links = array_keys( $this->actionMap );

		foreach ( $links as $action ) {
			$msg = wfMessage( "periodic-related-changes-$action" );
			if ( $request->getVal( 'action' ) !== $action ) {
				$msg = Linker::link(
					$thisTitle, $msg, [], [ "action" => $action ]
				);
			}

			$actionList[] = $msg;
		}
		return $actionList;
	}

	/**
	 * Manage the watchlist for this user
	 * @return bool Did we show this?
	 */
	public function manageWatchList() {
		$this->addOtherActions();
		$this->addTitleFormHandler();
		$this->listTitles();
		return true;
	}

	/**
	 * Redirect to the user's page if that is all their permissions allow.a
	 * @param string $userName so we can get permissions
	 * @return bool
	 */
	protected function maybeRedirectToOwnPage( $userName = null ) {
		// If you can't edit just anyone's, you can only see your own.
		if (
			( $userName === null || !isset( $userName )
			  || $userName !== $this->getUser()->getName() )
			&& !$this->canChangeAnyUser
			&& $this->getUser()->isAllowed( 'periodic-related-changes' )
		) {
			$this->getOutput()->redirect(
				$this->getTitle()->getLinkURL() . "/"
				. $this->getUser()->getName()
			);
			return true;
		}
	}

	/**
	 * Show the find user form
	 * @return bool
	 */
	protected function showFindUserForm() {
		if ( $this->canChangeAnyUser ) {
			$this->getOutput()->addModules( 'ext.periodicRelatedChanges.user' );

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

			$form->show();
			return true;
		}
	}

	/**
	 * Display the auto-complete form for a user
	 *
	 * @param string|null $userName null if form is needed, username otherwise
	 *
	 * @return User|bool
	 */
	public function findUser( $userName = null ) {
		if ( $this->maybeRedirectToOwnPage( $userName ) ) {
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

		if ( $user instanceof User && $user->getId() === 0 ) {
			throw new ErrorPageError(
				"periodic-related-changes-error",
				"periodic-related-changes-user-not-exist",
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
				return wfMessage(
					'periodic-related-changes-user-not-exist', $user
				);
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
	 * Here's the form for adding titles to the watch list.
	 */
	public function addTitleFormHandler() {
		if ( $this->userSubject ) {
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

			$form = HTMLForm::factory(
				'ooui', $formDescriptor, $this->getContext(),
				'periodic-related-changes'
			);
			$form->setFormIdentifier( __METHOD__ );
			$form->setSubmitCallback( [ $this, 'addTitleSubmit' ] );
			$form->setSubmitTextMsg( 'periodic-related-changes-addtitle' );
			$form->show();
		}
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
				return wfMessage(
					'periodic-related-changes-title-not-exist', $title
				);
			}
		}

		if ( $title ) {
			$watch
				= $this->prc->getRelatedChangeWatcher(
					$this->userSubject, WikiPage::factory( $title )
				);

			if ( $watch->exists() ) {
				return wfMessage(
					'periodic-related-changes-watchalreadyexists',
					[ $titleString ]
				);
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
		// Chance of race condition here that would result in an exception?
		if ( $this->prc->addWatch( $this->userSubject, $this->titleSubject ) ) {
			$this->getRequest()->setVal( "addTitle", null );
			$this->getOutput()->addWikiMsg(
				"periodic-related-changes-added", $this->titleSubject,
				$this->userSubject
			);
			$this->addTitleFormHandler();
			return true;
		}
		return wfMessage(
			"periodic-related-changes-add-fail",
			[ $this->titleSubject, $this->userSubject ]
		);
	}

	/**
	 * Handle watch display
	 */
	public function listTitles() {
		$out = $this->getOutput();
		$watches = $this->prc->getCurrentWatches( $this->userSubject );
		if ( $this->userSubject && $watches->numRows() > 0 ) {
			foreach ( $watches as $watch ) {
				$out->addWikiMsg(
					"periodic-related-changes-list-title", $watch->getTitle()
				);
			}
		}
	}

	/**
	 * Form for removing titles
	 */
	public function removeTitlesForm() {
		$formDescriptor = [];
		$watches = $this->prc->getCurrentWatches( $this->userSubject );
		if ( $this->userSubject && $watches->numRows() > 0 ) {
			foreach ( $watches as $watch ) {
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
					if ( $formData[$item] === true ) {
						return RelatedChangeWatcher::newFromFormID( $item );
					}
				}, array_keys( $formData )
			) );

		foreach ( $watchesToRemove as $watch ) {
			$watch->remove();
		}
		return true;
	}
}
