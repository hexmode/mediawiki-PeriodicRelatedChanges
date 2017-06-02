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

use ErrorPageError;
use HTMLForm;
use MWException;
use SpecialPage;
use Title;
use User;
use WikiPage;

class SpecialPeriodicRelatedChanges extends SpecialPage {
	// The user under examination
	protected $userSubject;

	// The title under examination
	protected $titleSubject;

	// Does this user have permisssion to change any user's watchlist
	protected $canChangeAnyUser;

	// Can this user change themselves
	protected $canChangeSelf;

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
	 * @param string|null $par parameters passed to the page
	 */
	public function execute( $par = null ) {
		parent::execute( $par );

		$userName = $par;
		$page = null;
		if ( strstr( $par, "/" ) !== false ) {
			list( $userName, $page ) = explode( "/", $par, 2 );
		}

		$this->canChangeAnyUser = $this->getUser()->isAllowed(
			'periodic-related-changes-any-user'
		);
		$this->canChangeSelf
			= $this->getUser()->isAllowed( 'periodic-related-changes' );

		$this->days = $this->getRequest()->getVal( "days", 7 );
		$doFullReport = $this->getRequest()->getCheck( "fullreport" );
		$sendEmail = $this->getRequest()->getCheck( "sendemail" );
		$user = $this->findUser( $userName );

		if ( $sendEmail && !$this->sendEmail( $user ) ) {
			return;
		}

		if ( $user && $doFullReport ) {
			$this->showFullReport( $user );
			return;
		}

		if ( !$page && $user !== false ) {
			$this->manageWatchList( $user );
			return;
		}

		$title = Title::newFromText( $page );
		if ( $title ) {
			$this->showRelatedChanges( $user, $title );
		}
	}

	/**
	 * Send the full report to a user
	 * @param User $user to send to
	 * @return bool false if problem
	 */
	public function sendEmail( User $user ) {
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
		$out->addWikiMsg( "periodic-related-changes-sent-email-success", $user );
		return true;
	}

	/**
	 * Show the full report for all changes
	 * @param User $user report is for
	 */
	public function showFullReport( User $user ) {
		$out = $this->getOutput();
		if ( !$this->getRequest()->getCheck( "printable" ) && $user->getEmail() ) {
			$out->addWikiMsg( "periodic-related-changes-link-to-email", $user );
		}
		foreach(
			PeriodicRelatedChanges::getManager()->getCurrentWatches( $user )
			as $page
		) {
			$this->showRelatedChanges( $user, $page['page']->getTitle() );
		}
		$out->setPageTitle( wfMessage(
			"periodic-related-changes-fullreport", $user, $this->days
		) );
	}

	/**
	 * Show the changes for this article
	 * @param User $user to examine
	 * @param Title $title to find related changes for
	 */
	public function showRelatedChanges( User $user, Title $title ) {
		$out = $this->getOutput();
		$watches = RelatedChangeWatchList::newFromUser( $user );
		if ( !$watches->hasTitle( $title ) ) {
			$out->addWikiMsg(
				"periodic-related-changes-no-user-title", $user, $title
			);
			return;
		}

		$page = WikiPage::factory( $title );
		$changesTo = $watches->getChangesFor( $page, $this->days, "to" );
		$changesFrom = $watches->getChangesFor( $page, $this->days, "from" );

		if ( !$changesTo && !$changesFrom ) {
			$out->addWikiMsg(
				"periodic-related-changes-no-user-title-days", $user, $title,
				$this->days
			);
			return;
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
	}

	/**
	 * Show a page with a link to the changes
	 * @param string $changeTitle the changed page
	 * @param array $diff array containing the diff information
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
	 * Display the auto-complete form for a user
	 *
	 * @param string|bool $userName false if form is needed, username otherwise
	 *
	 * @return User|bool
	 */
	public function findUser( $userName ) {
		if ( !$userName && $this->canChangeAnyUser ) {
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
			$form->setSubmitCallback( [ $this, 'findUserSubmit' ] );
			$form->setSubmitTextMsg( 'periodic-related-changes-getuser' );
			$form->show();
			return false;
		}

		// If you can't edit just anyone's, you can only see your own.
		if ( !$this->canChangeAnyUser
			 && $this->getUser()->isAllowed( 'periodic-related-changes' )
			 && ( !isset( $userName )
				  || $userName !== $this->getUser()->getName() )
		) {
			$this->getOutput()->redirect(
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
	 * Manage the watchlist for this user for this user
	 * @param User $user to list watches for
	 */
	public function manageWatchList( User $user ) {
		$out = $this->getOutput();
		if ( $this->canChangeAnyUser ) {
			$out->setSubTitle(
				wfMessage( "periodic-related-changes-lookupuser" )
			);
		}

		$this->listCurrentWatches( $user );
		if ( $this->getRequest()->getVal( "wpRemovePage" ) !== "1" ) {
			$this->addTitleFormHandler();
		} else {
			$this->listAndRemoveTitlesFormHandler();
		}
	}

	/**
	 * List the watches for this user
	 * @param User $user to list watches for
	 * @return int number of pages watched
	 */
	public function listCurrentWatches( User $user ) {
		$watches = RelatedChangeWatchList::newFromUser( $user );
		$out = $this->getOutput();
		$userName = $user->getName();

		if ( $watches->numRows() === 0 ) {
			$out->setPageTitle(
				wfMessage( "periodic-related-changes-nowatches-title" )
			);
			$out->addWikiMsg( "periodic-related-changes-nowatches", $userName );
			return 0;
		}

		$out->addWikiMsg( "periodic-related-changes-watch-count", $userName,
						  $watches->numRows()
		);

		$titles
			= PeriodicRelatedChanges::getManager()->getCurrentWatches( $user );
		foreach ( $titles as $titleRow ) {
			$out->addWikiMsg(
				'periodic-related-changes-watch-item',
				$titleRow['page']->getTitle()->getPrefixedText()
			);
		}
		return $watches->numRows();
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
		$form->setSubmitCallback( [ $this, 'addTitleSubmit' ] );
		$form->setSubmitTextMsg( 'periodic-related-changes-addtitle' );
		$form->show();
	}

	/**
	 * Form to remove pages.
	 */
	public function removePageFormHandler() {
		$prc = PeriodicRelatedChanges::getManager();
		foreach ( $prc->getCurrentWatches( $this->userSubject ) as $watch ) {
			$formDescriptor['watch-' . $watch['page']->getTitle()->getDBKey()] = [
				'section' => 'currentwatchlist',
				'label'   => $watch['page']->getTitle(),
				'type'    => 'check',
				'size'    => 30,
			];
		}
		$form = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext(),
								   'periodic-related-changes' );
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
		$formDescriptor['RemovePage'] = [
			'type' => 'hidden',
			'default' => '1'
		];
		foreach ( $prc->getCurrentWatches( $this->userSubject ) as $watch ) {
			$formDescriptor['watch-' . $watch['page']
							->getTitle()->getDBKey()] = [
								'section' => 'currentwatchlist',
								'label'   => $watch['page']->getTitle(),
								'type'    => 'check',
								'size'    => 30,
			];
		}
		$form = HTMLForm::factory(
			'ooui', $formDescriptor,
			$this->getContext(),
			"periodic-related-changes"
		);
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
		$pagesToRemove = array_map(
			function ( $key ) {
				return substr( $key, 6 );
			},
			array_filter( array_keys( $formData ),
									   function ( $item ) use ( $formData ) {
										   if ( substr( $item, 0, 6 )
												=== "watch-"
												&& $formData[$item] ) {
											   return true;
										   }
									   } ) );
		foreach ( $pagesToRemove as $page ) {
			$watch
				= PeriodicRelatedChanges::getManager()->getRelatedChangeWatcher(
					$this->userSubject,
					WikiPage::factory( Title::newFromText( $page ) )
				);
			$watch->remove();
		}
	}
}
