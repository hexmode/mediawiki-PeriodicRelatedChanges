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

use DatabaseUpdater;

class Hook {
	/**
	 * Fired when MediaWiki is updated to allow extensions to update
	 * the database.
	 *
	 * @param DatabaseUpdater $updater the db handle
	 * @return bool always true
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/LoadExtensionSchemaUpdates
	 */
	public static function onLoadExtensionSchemaUpdates(
		DatabaseUpdater $updater
	) {
		$updater->addExtensionTable( 'periodic_related_change', __DIR__
									 . "/../sql/periodic_related_change.sql" );
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
		return new GlobalVarConfig( "PeriodicRelatedChanges" );
	}

	/**
	 * Define the PeriodicRelatedChanges notifications
	 *
	 * @param array &$notifications assoc array of notification types
	 * @param array &$notificationCategories assoc array describing categories
	 * @param array &$icons assoc array of icons we define
	 *
	 * @see https://www.mediawiki.org/wiki/Extension:Echo/BeforeCreateEchoEvent
	 */
	public static function onBeforeCreateEchoEvent(
		array &$notifications, array &$notificationCategories, array &$icons
	) {
		$icons['periodicrelatedchanges']['path']
			= 'PeriodicRelatedChanges/assets/periodicrelatedchanges.svg';

		$notifications['periodicrelatedchanges-page-added'] = [
			'category' => 'periodicrelatedchanges-follow',
			'group' => 'neutral',
			'user-locators' => [ __CLASS__, 'userLocator' ],
			'user-filters' => [ __CLASS__, 'userFilter' ],
			'presentation-model'
			=> 'PeriodicRelatedChanges\EventPresentationModel',
			'bundle' => [ 'web' => true, 'email' => true, 'expandable' => true ]
		];

		$notifications['periodicrelatedchanges-page-removed'] = [
			'category' => 'periodicrelatedchanges-follow',
			'group' => 'neutral',
			'user-locators' => [ __CLASS__, 'userLocator' ],
			'user-filters' => [ __CLASS__, 'userFilter' ],
			'presentation-model'
			=> 'PeriodicRelatedChanges\EventPresentationModel',
			'bundle' => [ 'web' => true, 'email' => true, 'expandable' => true ]
		];

		$notifications['periodicrelatedchanges-page-changed'] = [
			'category' => 'periodicrelatedchanges-follow',
			'group' => 'neutral',
			'user-locators' => [ __CLASS__, 'userLocator' ],
			'user-filters' => [ __CLASS__, 'userFilter' ],
			'presentation-model'
			=> 'PeriodicRelatedChanges\EventPresentationModel',
			'bundle' => [ 'web' => true, 'email' => true, 'expandable' => true ]
		];

		$notificationCategories['periodicrelatedchanges-follow'] = [
			'priority' => 2
		];
	}

	/**
	 * Return those users who should get notified about this category change
	 */
	public static function userLocator() {
		echo "<b>UserLocator</b><pre>";
		debug_print_backtrace();
		exit;
	}

	/**
	 * Return those users who should *not* get notified about this
	 *  category change
	 */
	public static function userFilter() {
		// They'll get two notices if the page is removed.
		// They should only get one.
		echo "<b>UserFilter</b><pre>";
		debug_print_backtrace();
		exit;
	}

	/**
	 * Hook used when a category is added.  Called in a deffered update or job,
	 * not immediately after edit.
	 *
	 * @param Category $cat the category added
	 * @param WikiPage $wikiPage page added
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/CategoryAfterPageAdded
	 */
	public static function onCategoryAfterPageAdded(
		Category $cat, WikiPage $wikiPage
	) {
		if ( RelatedChangeWatcher::titleHasCategoryWatchers(
			$cat->getTitle()
		) ) {
				global $wgContLang;
				EchoEvent::create( [
					'type' => 'periodicrelatedchanges-page-added',
					'title' => $title,
					'extra' => [
						'revid' => $revision->getId(),
						'source' => $source,
						'excerpt' => EchoDiscussionParser::getEditExcerpt(
							$revision, $wgContLang
						)
					],
					'agent' => $user,
				] );
		}
	}

	/**
	 * Hook used when a category is removed. Called in a deffered update or job,
	 * not immediately after edit.
	 *
	 * @param Category $cat the category removed
	 * @param WikiPage $wikiPage page removed
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/CategoryAfterPageRemoved
	 */
	public static function onCategoryAfterPageRemoved(
		Category $cat, WikiPage $wikiPage
	) {
		if ( RelatedChangeWatcher::titleHasCategoryWatchers(
			$cat->getTitle()
		) ) {
			global $wgContLang;
			EchoEvent::create( [
				'type' => 'periodicrelatedchanges-page-removed',
				'title' => $title,
				'extra' => [
					'revid' => $revision->getId(),
					'source' => $source,
					'excerpt' => EchoDiscussionParser::getEditExcerpt(
						$revision, $wgContLang
					),
				],
				'agent' => $user,
			] );
		}
	}

	/**
	 * When a page is modified.
	 * Occurs after the save page request has been processed
	 *
	 * @param Wikipage $article WikiPage modified
	 * @param User $user User performing the modification
	 * @param Content $content New content
	 * @param string $summary Edit summary/comment
	 * @param bool $isMinor Whether or not the edit was marked as minor
	 * @param null $isWatch (No longer used)
	 * @param null $section (No longer used)
	 * @param int &$flags Flags passed to WikiPage::doEditContent()
	 * @param Revision $revision saved content. This parameter may be null
	 * @param Status $status about to be returned by doEditContent()
	 * @param bool|int $baseRevId the rev ID (or false) this edit was based on
	 * @param int $undidRevId the rev id (or 0) this edit undid
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/PageContentSaveComplete
	 * @see https://doc.wikimedia.org/mediawiki-core/master/php/classWikiPage.html#a1a69c99a33a08b923d5482254223cad5
	 * for definition of flags
	 */
	public static function onPageContentSaveComplete(
		WikiPage $article, User $user, Content $content, $summary, $isMinor,
		$isWatch, $section, &$flags, Revision $revision, Status $status,
		$baseRevId, $undidRevId
	) {
		if ( RelatedChangeWatcher::titleHasCategoryWatchers(
			$article->getTitle()
		) ) {
			global $wgContLang;
			EchoEvent::create( [
				'type' => 'periodicrelatedchanges-page-changed',
				'title' => $title,
				'extra' => [
					'revid' => $revision->getId(),
					'source' => $source,
					'excerpt' => EchoDiscussionParser::getEditExcerpt(
						$revision, $wgContLang
					),
				],
				'agent' => $user,
			] );
		}
	}

	/**
	 * Determine how our echo bundles are handled
	 *
	 * @param EchoEvent $event notification type
	 * @param string &$bundleString which bundle this goes into
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/EchoGetBundleRules
	 */
	public static function onEchoGetBundleRules(
		EchoEvent $event, &$bundleString
	) {
		switch ( $event->getType() ) {
		case 'periodicrelatedchanges-page-added':
		case 'periodicrelatedchanges-page-removed':
		case 'periodicrelatedchanges-page-changed':
			$bundleString = 'perodicrelatedchanges';
		}
	}
}
