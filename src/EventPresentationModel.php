<?php
/**
 * Presentation for PeriodicRelatedChanges alerts
 *
 * Copyright (C) 2017  NicheWork, LLC
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

namespace MediaWiki\Extension\PeriodicRelatedChanges;

class EventPresentationModel extends \EchoEventPresentationModel {
	/**
	 * Define that we have to have the page this is
	 * refering to as a condition to display this  notification
	 *
	 * @return bool
	 */
	public function canRender() {
		wfDebugLog( 'PeriodicRelatedChanges', __METHOD__ );
		return (bool)$this->event->getTitle();
	}

	/**
	 * You can use existing icons in Echo icon folder
	 * or define your own through the $icons variable
	 * when you define your event in BeforeCreateEchoEvent
	 *
	 * @return string
	 */
	public function getIconType() {
		wfDebugLog( 'PeriodicRelatedChanges', __METHOD__ );
		return 'periodic-related-changes';
	}

	/**
	 * Provide a header message, bundled or no.
	 *
	 * @return Message
	 */
	public function getHeaderMessage() {
		wfDebugLog( 'PeriodicRelatedChanges', __METHOD__ );
		if ( $this->isBundled() ) {
			// This is the header message for the bundle that contains
			// several notifications of this type
			$msg = $this->msg( 'periodic-related-changes-topic-word' );
			$msg->params( $this->getBundleCount() );
			$msg->params( $this->getTruncatedTitleText(
				$this->event->getTitle(), true
			) );
			$msg->params( $this->getViewingUserForGender() );
			return $msg;
		} else {
			// This is the header message for individual non-bundle message
			$msg = $this->getMessageWithAgent(
				'periodic-related-changes-topic-word'
			);
			$msg->params( $this->getTruncatedTitleText(
				$this->event->getTitle(), true
			) );
			$msg->params( $this->getViewingUserForGender() );
			return $msg;
		}
	}

	/**
	 * Header message for individual events inside the header
	 *
	 * @return Message
	 */
	public function getCompactHeaderMessage() {
		wfDebugLog( 'PeriodicRelatedChanges', __METHOD__ );
		$msg = parent::getCompactHeaderMessage();
		$msg->params( $this->getViewingUserForGender() );
		return $msg;
	}

	/**
	 * Body of the event notice.  Summary?
	 *
	 * @return Message
	 */
	public function getBodyMessage() {
		wfDebugLog( 'PeriodicRelatedChanges', __METHOD__ );
		$prcPage = "page they're watching";
		$relatedPage = "page that changed";
		return wfMessage( "periodic-related-changes-summary", $relatedPage, $prcPage );
	}

	/**
	 * Link to page this is about
	 *
	 * @return array
	 */
	public function getPrimaryLink() {
		wfDebugLog( 'PeriodicRelatedChanges', __METHOD__ );
		$link = $this->getPageLink( $this->event->getTitle(), null, true );
		return $link;
	}

	/**
	 * Secondary links in a bundle
	 *
	 * @return array
	 */
	public function getSecondaryLinks() {
		wfDebugLog( 'PeriodicRelatedChanges', __METHOD__ );
		if ( $this->isBundled() ) {
			// For the bundle, we don't need secondary actions
			return [];
		} else {
			// For individual items, display a link to the user
			// that created this page
			return [ $this->getAgentLink() ];
		}
	}
}
