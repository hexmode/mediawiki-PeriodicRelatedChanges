<?php
namespace MediaWiki\Extension\PeriodicRelatedChanges\Test;

use MediaWiki\Extension\PeriodicRelatedChanges;
use Title;

/**
 * @author
 *
 * @covers WatchedItem
 */
class UserTest extends \MediaWikiTestCase {

	public function setUp() {
		parent::setUp();
<<<<<<< HEAD
		$user = \User::newFromId( 1 );
=======
		$user = new \TestUser( 'PRCTestUser' );
		$user->save();
>>>>>>> d28a0005a2039a01bfc5087a0a95939f48bea8cf
		self::$users['PRCTestUser'] = $user;

		// $this->hideDeprecated( 'WatchedItem::fromUserTitle' );
	}

	private function getUser() {
		return self::$users['PRCTestUser'];
	}

	private function getManager() {
		return PeriodicRelatedChanges\Manager::getManager();
	}

	public function testWatchAndUnWatchItem() {
		$user = $this->getUser();
		$title = Title::newFromDBkey( "Category:Test" );
		$mgr = $this->getManager();

		var_dump($mgr->isValidUserTitle( $user, $title ));exit;
		$this->assertTrue(
			$mgr->isValidUserTitle( $user, $title )->isGood(),
			"User is valid"
		);

		// Cleanup after previous tests
		$this->assertTrue(
			get_class( $mgr->removeWatch( $user, $title ) ) === 'Status',
			"Got a status object"
		);
		$this->assertFalse(
			$mgr->isWatched( $user, $title ),
			'Page should not initially be watched'
		);
		$mgr->addWatch( $user, $title );
		$this->assertTrue(
			$mgr->isWatched($user, $title ),
			'Page should be watched'
		);
		$mgr->removeWatch( $user, $title );
		$this->assertFalse(
			$mgr->isWatched( $user, $title ),
			'Page should be unwatched'
		);
	}

	public function testUpdateAndResetNotificationTimestamp() {
		$user = $this->getUser();
		$otherUser = ( new TestUser( 'WatchedItemIntegrationTestUser_otherUser' ) )->getUser();
		$title = Title::newFromText( 'WatchedItemIntegrationTestPage' );
		WatchedItem::fromUserTitle( $user, $title )->addWatch();
		$this->assertNull( WatchedItem::fromUserTitle( $user, $title )->getNotificationTimestamp() );

		EmailNotification::updateWatchlistTimestamp( $otherUser, $title, '20150202010101' );
		$this->assertEquals(
			'20150202010101',
			WatchedItem::fromUserTitle( $user, $title )->getNotificationTimestamp()
		);

		MediaWikiServices::getInstance()->getWatchedItemStore()->resetNotificationTimestamp(
			$user, $title
		);
		$this->assertNull( WatchedItem::fromUserTitle( $user, $title )->getNotificationTimestamp() );
	}

	public function testDuplicateAllAssociatedEntries() {
		$user = $this->getUser();
		$titleOld = Title::newFromText( 'WatchedItemIntegrationTestPageOld' );
		$titleNew = Title::newFromText( 'WatchedItemIntegrationTestPageNew' );
		WatchedItem::fromUserTitle( $user, $titleOld->getSubjectPage() )->addWatch();
		WatchedItem::fromUserTitle( $user, $titleOld->getTalkPage() )->addWatch();
		// Cleanup after previous tests
		WatchedItem::fromUserTitle( $user, $titleNew->getSubjectPage() )->removeWatch();
		WatchedItem::fromUserTitle( $user, $titleNew->getTalkPage() )->removeWatch();

		WatchedItem::duplicateEntries( $titleOld, $titleNew );

		$this->assertTrue(
			WatchedItem::fromUserTitle( $user, $titleOld->getSubjectPage() )->isWatched()
		);
		$this->assertTrue(
			WatchedItem::fromUserTitle( $user, $titleOld->getTalkPage() )->isWatched()
		);
		$this->assertTrue(
			WatchedItem::fromUserTitle( $user, $titleNew->getSubjectPage() )->isWatched()
		);
		$this->assertTrue(
			WatchedItem::fromUserTitle( $user, $titleNew->getTalkPage() )->isWatched()
		);
	}

	public function testIsWatched_falseOnNotAllowed() {
		$user = $this->getUser();
		$title = Title::newFromText( 'WatchedItemIntegrationTestPage' );
		WatchedItem::fromUserTitle( $user, $title )->addWatch();

		$this->assertTrue( WatchedItem::fromUserTitle( $user, $title )->isWatched() );
		$user->mRights = [];
		$this->assertFalse( WatchedItem::fromUserTitle( $user, $title )->isWatched() );
	}

	public function testGetNotificationTimestamp_falseOnNotAllowed() {
		$user = $this->getUser();
		$title = Title::newFromText( 'WatchedItemIntegrationTestPage' );
		WatchedItem::fromUserTitle( $user, $title )->addWatch();
		MediaWikiServices::getInstance()->getWatchedItemStore()->resetNotificationTimestamp(
			$user, $title
		);

		$this->assertEquals(
			null,
			WatchedItem::fromUserTitle( $user, $title )->getNotificationTimestamp()
		);
		$user->mRights = [];
		$this->assertFalse( WatchedItem::fromUserTitle( $user, $title )->getNotificationTimestamp() );
	}

	public function testRemoveWatch_falseOnNotAllowed() {
		$user = $this->getUser();
		$title = Title::newFromText( 'WatchedItemIntegrationTestPage' );
		WatchedItem::fromUserTitle( $user, $title )->addWatch();

		$previousRights = $user->mRights;
		$user->mRights = [];
		$this->assertFalse( WatchedItem::fromUserTitle( $user, $title )->removeWatch() );
		$user->mRights = $previousRights;
		$this->assertTrue( WatchedItem::fromUserTitle( $user, $title )->removeWatch() );
	}

	public function testGetNotificationTimestamp_falseOnNotWatched() {
		$user = $this->getUser();
		$title = Title::newFromText( 'WatchedItemIntegrationTestPage' );

		WatchedItem::fromUserTitle( $user, $title )->removeWatch();
		$this->assertFalse( WatchedItem::fromUserTitle( $user, $title )->isWatched() );

		$this->assertFalse( WatchedItem::fromUserTitle( $user, $title )->getNotificationTimestamp() );
	}

}
