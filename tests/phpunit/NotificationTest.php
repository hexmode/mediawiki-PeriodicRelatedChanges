<?php
namespace MediaWiki\Extension\PeriodicRelatedChanges\Test;

use MediaWiki\Extension\PeriodicRelatedChanges;
use Title;
use User;

/**
 * @group Echo
 * @group DataBase
 * @group medium
 */
class NotificationTest extends \ApiTestCase {

	protected $dbr;

	protected function setUp() {
		parent::setUp();
		$this->dbr = \MWEchoDbFactory::getDB( DB_SLAVE );
	}

	/**
	 * Creates and updates a page that has related watches
	 */
	public function testAddRelatedWatches() {
		$editor = self::$users['sysop']->getUser()->getName();
		$talkPage = self::$users['uploader']->getUser()->getName();
		// A set of messages which will be inserted
		$messages = [
			'Moar Cowbell',
			"I can haz test\n\nplz?", // checks that the parser allows multi-line comments
			'blah blah',
		];

		$messageCount = 1;
		$this->assertCount( 1, $this->fetchAllEvents() );
		// Start a talkpage
		$content = "== Section 8 ==\n\n" . $this->signedMessage( $editor, $messages[$messageCount] );
		$this->editPage( $talkPage, $content, '', NS_USER_TALK );

		// Ensure the proper event was created
		$events = $this->fetchAllEvents();
		$this->assertCount( 1 + $messageCount, $events, 'After initial edit a single event must exist.' ); // +1 is due to 0 index
		$row = array_shift( $events );
		$this->assertEquals( 'edit-user-talk', $row->event_type );
		$this->assertEventSectionTitle( 'Section 8', $row );

		// Add another message to the talk page
		$messageCount++;
		$content .= $this->signedMessage( $editor, $messages[$messageCount] );
		$this->editPage( $talkPage, $content, '', NS_USER_TALK );

		// Ensure another event was created
		$events = $this->fetchAllEvents();
		$this->assertCount( 1 + $messageCount, $events );
		$row = array_shift( $events );
		$this->assertEquals( 'edit-user-talk', $row->event_type );
		$this->assertEventSectionTitle( 'Section 8', $row );

		// Add a new section and a message within it
		$messageCount++;
		$content .= "\n\n== EE ==\n\n" . $this->signedMessage( $editor, $messages[$messageCount] );
		$this->editPage( $talkPage, $content, '', NS_USER_TALK );

		// Ensure this event has the new section title
		$events = $this->fetchAllEvents();
		$this->assertCount( 1 + $messageCount, $events );
		$row = array_pop( $events );
		$this->assertEquals( 'edit-user-talk', $row->event_type );
		$this->assertEventSectionTitle( 'EE', $row );
	}

	/**
	 * @return array All events in db sorted from oldest to newest
	 */
	protected function fetchAllEvents() {
		$res = $this->dbr->select( 'echo_event', [ '*' ], [], __METHOD__, [ 'ORDER BY' => 'event_id ASC' ] );

		return iterator_to_array( $res );
	}
}
