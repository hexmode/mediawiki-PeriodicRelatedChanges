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

	/**
	 * Constructor
	 * @param string $name Name of the special page, as seen in links and URLs
	 * @param string $restriction User right required, e.g. "block" or "delete"
	 */
	public function __construct( $name = 'PeriodicRelatedChanges', $restriction = 'periodic-related-changes' ) {
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
	 * @param string|null $userName user to lookup
	 */
	public function execute( $userName = null ) {
		parent::execute( $userName );
		$this->canChangeAnyUser = $this->getUser()->isAllowed( 'periodic-related-changes-any-user' );

		if ( $this->getUser()->isAnon() ) {
			throw new ErrorPageError( "periodic-related-changes-error",
									  "periodic-related-changes-anons-not-allowed" );
		}

		$user = $this->findUser( $userName );
		if ( $user !== false && !$user->isAnon() ) {
			$this->manageWatchList( $user );
		}
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
					'label-message'       => 'periodic-related-changes-user-editname',
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
		if ( !$this->canChangeAnyUser && $this->getUser()->isAllowed( 'periodic-related-changes' )
			 && ( !isset( $userName ) || $userName !== $this->getUser()->getName() ) ) {
			$this->getOutput()->redirect(
				$this->getTitle()->getLinkURL() . "/" . $this->getUser()->getName()
			);
			return false;
		}

		$user = User::newFromName( trim( $userName, "/" ) );
		if ( !$this->canChangeAnyUser ) {
			if ( !$userName || $user->getName() !== $this->getUser()->getName() ) {
				$this->findUserSubmit( [ "username" => $this->getUser()->getName() ] );
				return false;
			}
			$user = $this->getUser();
		}

		if ( !$user || $user->getId() === 0 ) {
			throw new ErrorPageError( "periodic-related-changes-error",
									  "periodic-related-changes-userdoesnotexist",
									  [ $user ] );
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
			$out->setSubTitle( wfMessage( "periodic-related-changes-lookupuser" ) );
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
		$userName = ( $user->getID() === $this->getUser()->getID() )
				  ? "you"
				  : $user->getName();

		if ( $watches->numRows() === 0 ) {
			$out->setPageTitle( wfMessage( "periodic-related-changes-nowatches-title" ) );
			$out->addHTML( wfMessage( "periodic-related-changes-nowatches", [ $userName ] ) );
			return 0;
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
			$watch = PeriodicRelatedChanges::getManager()->getRelatedChangeWatcher(
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
		if ( $this->titleSubject instanceof Title
			 && $formData['addTitle'] == $this->titleSubject->getText() ) {
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
		return wfMessage( "periodic-related-changes-invalid-form-data" );
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
			$formDescriptor['watch-' . $watch['page']->getTitle()->getDBKey()] = [
				'section' => 'currentwatchlist',
				'label'   => $watch['page']->getTitle(),
				'type'    => 'check',
				'size'    => 30,
			];
		}
		$form = HTMLForm::factory( 'ooui', $formDescriptor,
								   $this->getContext(), "periodic-related-changes" );
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
										   if ( substr( $item, 0, 6 ) === "watch-"
												&& $formData[$item] ) {
											   return true;
										   }
									   } ) );
		foreach ( $pagesToRemove as $page ) {
			$watch = PeriodicRelatedChanges::getManager()->getRelatedChangeWatcher(
				$this->userSubject, WikiPage::factory( Title::newFromText( $page ) )
			);
			$watch->remove();
		}
	}
}
