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
		$user = \User::newFromId( 1 );
		self::$users['PRCTestUser'] = $user;
		$user = \User::newFromId( 2 );
		self::$users['PRCOtherTestUser'] = $user;

		// $this->hideDeprecated( 'WatchedItem::fromUserTitle' );
	}

	private function getUser() {
		return self::$users['PRCTestUser'];
	}

	private function getOtherUser() {
		return self::$users['PRCTestUser'];
	}

	private function getManager() {
		return PeriodicRelatedChanges\Manager::getManager();
	}

	public function testUserIsValid() {
		$user = $this->getUser();
		$title = Title::newFromDBkey( "Category:Test" );
		$mgr = $this->getManager();

		$valid = $mgr->isValidUserTitle( $user, $title );
		$this->assertTrue( $valid->isGood(), "User is valid" );
	}

	public function testWatchAndUnWatchItem() {
		$user = $this->getUser();
		$title = Title::newFromDBkey( "Category:Test" );
		$mgr = $this->getManager();

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
			$mgr->isWatched( $user, $title ),
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
		$otherUser = $this->getOtherUser();
		$title = Title::newFromText( 'WatchedItemIntegrationTestPage' );
		$mgr = $this->getManager();
		$mgr->addWatch( $user, $title );

		$this->assertNull(
			$mgr->getNotificationTimestamp( $user, $title ), "Null timetamp"
		);

		$this->assertTrue(
			$mgr->updateNotificationTimestamp( $otherUser, $title, '20150202010101' ),
			"Updated timestamp"
		);

		$this->assertEquals(
			'20150202010101',
			$mgr->getNotificationTimestamp( $user, $title ),
			"Timestamp was updated"
		);

		$this->assertTrue(
			$mgr->resetNotificationTimestamp( $user, $title ), "Reset timestamp"
		);
		$this->assertNull( $mgr->getNotificationTimestamp( $user, $title ) );
	}

	public function testDuplicateAllAssociatedEntries() {
		$user = $this->getUser();
		$titleOld = Title::newFromText( 'WatchedItemIntegrationTestPageOld' );
		$titleNew = Title::newFromText( 'WatchedItemIntegrationTestPageNew' );
		$mgr = $this->getManager();

		$mgr->addWatch( $user, $titleOld->getSubjectPage() );
		$mgr->addWatch( $user, $titleOld->getTalkPage() );
		// Cleanup after previous tests
		$mgr->removeWatch( $user, $titleNew->getSubjectPage() );
		$mgr->removeWatch( $user, $titleNew->getTalkPage() );

		$this->assertTrue(
			$mgr->duplicateEntries( $titleOld, $titleNew ),
			"Duplicate watches"
		);

		$this->assertTrue(
			$mgr->isWatched( $user, $titleOld->getSubjectPage() ),
			"Old page is still watched"
		);
		$this->assertTrue(
			$mgr->isWatched( $user, $titleNew->getSubjectPage() ),
			"New page is watched, too"
		);
	}

	public function testIsWatched_falseOnNotAllowed() {
		$user = $this->getUser();
		$title = Title::newFromText( 'WatchedItemIntegrationTestPage' );
		$mgr = $this->getManager();
		$mgr->addWatch( $user, $title );

		$this->assertTrue(
			$mgr->isWatched( $user, $title ), "Permissions unchanged"
		);
		$user->mRights = [];
		$this->assertFalse(
			$mgr->isWatched( $user, $title ), "Cannot watch"
		);
	}

	public function testGetNotificationTimestamp_falseOnNotAllowed() {
		$user = $this->getUser();
		$title = Title::newFromText( 'WatchedItemIntegrationTestPage' );
		$mgr = $this->getManager();
		$mgr->addWatch( $user, $title );
		$mgr->resetNotificationTimestamp( $user, $title );

		$this->assertEquals(
			null,
			$mgr->getNotificationTimestamp( $user, $title )
		);
		$user->mRights = [];
		$this->assertFalse( $mgr->getNotificationTimestamp( $user, $title )->isOk() );
	}

	public function testRemoveWatch_falseOnNotAllowed() {
		$user = $this->getUser();
		$title = Title::newFromText( 'WatchedItemIntegrationTestPage' );
		$mgr = $this->getManager();
		$mgr->addWatch( $user, $title );

		$previousRights = $user->mRights;
		$user->mRights = [];
		$this->assertFalse( $mgr->removeWatch( $user, $title )->isOk() );
		$user->mRights = $previousRights;
		$this->assertTrue( $mgr->removeWatch( $user, $title )->isOk() );
	}

	public function testGetNotificationTimestamp_falseOnNotWatched() {
		$user = $this->getUser();
		$title = Title::newFromText( 'WatchedItemIntegrationTestPage' );
		$mgr = $this->getManager();
		$mgr->removeWatch( $user, $title );

		$this->assertFalse( $mgr->isWatched( $user, $title ) );

		$this->assertFalse( $mgr->getNotificationTimestamp( $user, $title )->isOk() );
	}

}
