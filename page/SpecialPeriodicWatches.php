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
use User;

class SpecialPeriodicWatches extends SpecialPage {
	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct( 'PeriodicWatches' );
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
	public function execute( $userName ) {
		parent::execute( $userName );

		$user = $this->findUser( $userName );
		if ( $user !== false ) {
			$this->listCurrentWatches( $user );
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
		if ( !$userName && $this->getUser()->isAllowed( 'periodic-changes-any-user' ) ) {
			$this->getOutput()->addModules( 'ext.periodicRelatedChanges.user' );

			$formDescriptor = [
				'username' => [
					'label-message'       => 'periodicwatches-user-editname',
					'type'                => 'user',
					'size'                => 30,
					'autofocus'           => true,
					'validation-callback' => [ $this, 'findUserValidate' ],
					'required'            => true
				] ];
			$form = HTMLForm::factory( 'ooui', $formDescriptor,
									   $this->getContext() );
			$form->setSubmitCallback( [ $this, 'findUserSubmit' ] );
			$form->setSubmitTextMsg( 'periodicwatches-getuser' );
			$form->show();
			return false;
		}

		$user = User::newFromName( $userName );
		if ( !$this->getUser()->isAllowed( 'periodic-changes-any-user' ) ) {
			if ( !$userName || $user->getName() !== $this->getUser()->getName() ) {
				$this->findUserSubmit( [ "username" => $this->getUser()->getName() ] );
				return false;
			}
			$user = $this->getUser();
		}

		if ( $user && $user->getId() === 0 ) {
			throw new ErrorPageError( "periodicwatches-error",
									  "periodicwatches-userdoesnotexist",
									  [ $user ] );
		}
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
				return wfMessage( 'periodicwatches-nosuchuser' );
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
			$this->getOutput()->redirect( $this->getTitle()->getLinkURL() . "/" . $formData['username'] );
		}
	}

	/**
	 * List the watches for this user
	 * @param User $user to list watches for
	 */
	public function listCurrentWatches( User $user ) {
	}
}
