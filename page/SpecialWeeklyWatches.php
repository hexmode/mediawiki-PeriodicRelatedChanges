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

use Html;
use SpecialPage;
use Xml;

class SpecialWeeklyWatches extends SpecialPage {
	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct( 'WeeklyWatches', 'add-weekly-changes' );
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
	 * @param string|null $subPage the bit after the pagename.  We'll have a username.
	 */
	public function execute( $userName ) {
		parent::execute( $userName );

		if ( $this->findUser( $userName ) ) {
            $this->getTargetPage( );
            $this->listCurrentWatches( $userName );
        }
	}

    /**
     * Display the auto-complete form for a user
     *
     * @param string|null the username if already selected
     *
     * @return bool
     */
    public function findUser( $userName ) {
		$this->getOutput()->addModules( 'mediawiki.userSuggest' );

		$this->getOutput()->addHTML(
			Html::openElement(
				'form',
				[ 'method' => 'post',
				  'action' => $this->getPageTitle( $userName )->getLocalUrl(),
				  'name' => 'uluser',
				  'id' => 'mw-weeklywatches-form1' ]
			) .
			Html::hidden( 'addToken', $this->getUser()->getEditToken( __CLASS__ ) ) .
			Xml::fieldset( $this->msg( 'weeklywatches-lookup-user' )->text() ) .
			Xml::inputLabel(
				$this->msg( 'weeklywatches-user-editname' )->text(),
				'user',
				'username',
				30,
				'',
				[ 'autofocus' => true,
				  'class' => 'mw-autocomplete-user' ] // used by mediawiki.userSuggest
			) . ' ' .
			Xml::submitButton( $this->msg( 'weeklywatches-getuser' )->text() ) .
			Html::closeElement( 'fieldset' ) .
			Html::closeElement( 'form' ) . "\n"
		);

		return false;
    }

}
