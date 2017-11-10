<?php
namespace MediaWiki\Extension\PeriodicRelatedChanges\Test;

use MediaWiki\Extension\PeriodicRelatedChanges;
use Wikimedia\Timestamp\ConvertibleTimestamp;
use EditPageTest;
use Title;
use User;
use WikiTextContent;
use WikiPage;

/**
 * @group Echo
 * @group DataBase
 * @group medium
 */
class RelatedWatchTest extends \MediaWikiTestCase {

	protected $dbr;
	protected $mgr;
	protected static $page;
	protected static $title;
	protected static $ts;
	protected static $user;

	public function setUp() {
		parent::setUp();
		$this->dbr = \MWEchoDbFactory::getDB( DB_SLAVE );

		self::$ts['start'] = new ConvertibleTimestamp;
		self::$user['PRCTestUser'] = User::newFromId( 1 );
		self::$user['PRCOtherTestUser'] = User::newFromId( 2 );

		self::$page['rel'] = "Rel";
		self::$page['anotherRel'] = "anotherRel";
		self::$page['mainPage'] = "MainPageTest";

		self::$title['rel'] = Title::newFromDBkey( self::$page['rel'] );
		self::$title['anotherRel'] = Title::newFromDBkey(
			self::$page['anotherRel']
		);
		self::$title['mainPage'] = Title::newFromDBkey(
			self::$page['mainPage']
		);
		$this->mgr = $this->getManager();
		// $this->hideDeprecated( 'WatchedItem::fromUserTitle' );
	}

	private function getManager() {
		return PeriodicRelatedChanges\Manager::getManager();
	}

	public function testArticleDoesNotExist() {
		$rel = self::$title['rel'];

		if ( $rel->exists() ) {
			WikiPage::factory( $rel )->doDeleteArticle( "unittest" );
		}

		$this->assertFalse(
			$rel->exists(),
			"Ensure Page does not exist"
		);
	}

	protected function getListOfLinks( array $titleArray ) {
		return new WikitextContent(
			"* " . implode( "\n* ",
							array_map(
								function ( $title ) {
									return
										"[[" . $title->getPrefixedText() . "]]";
								}, $titleArray
							) )
		);
	}

	public function testAddRedlink() {
		$mainPage = self::$title['mainPage'];
		$page = WikiPage::factory( $mainPage );
		$rel = self::$title['rel'];
		$page->doEditContent(
			$this->getListOfLinks( [ $rel ] ),
			'Create a page with a redLink',
			0,
			false,
			self::$user['PRCTestUser']
		);
		$pages = $this->mgr->getRelatedPages( $mainPage );

		$this->assertEquals(
			0,
			$pages->numRows(),
			'Verify that there are no related pages yet since'
			. 'we only have a redlink.'
		);

		$this->assertEquals(
			[],
			$this->mgr->getRelatedChangesSince( $mainPage, self::$ts['start'] ),
			'Verify that the doesn\'t contain the page since it '
			. 'isn\'t created yet.'
		);
	}

	/**
	 * Creates a category that has changed members
	 */
	public function testCreateRedlinkedPage() {
		$mainPage = self::$title['mainPage'];
		$rel = self::$title['rel'];
		$user = self::$user['PRCTestUser'];
		$this->mgr->addWatch( $user, $mainPage );

		$page = WikiPage::factory( $rel );
		self::$ts['edit1'] = new ConvertibleTimestamp;
		$page->doEditContent(
			$this->getListOfLinks( [ $mainPage ] ),
			'Create the redlinked page',
			0,
			false,
			$user
		);

		$pages = $this->mgr->getRelatedPages( $mainPage );
		$this->assertEquals(
			1,
			$pages->numRows(),
			'Verify that the changed page shows up.'
		);

		$pages = $this->mgr->getRelatedPages( $mainPage );
		$title = $pages->currentTitle();
		$this->assertEquals(
			[ $rel->getNamespace(), $rel->getDBkey() ],
			[ $title->getNamespace(), $title->getDBkey() ],
			'Verify that the changed page shows up.'
		);
	}

	public function testNewPageRelatedChanges() {
		$mainPage = self::$title['mainPage'];
		$rel = self::$title['rel'];
		$pages = $this->mgr->getRelatedPages( $mainPage );

		$this->assertEquals(
			[ self::$page['rel'] => [
				'old' => '0',
				'new' => '3',
				'ts' => [
					ConvertibleTimestamp::convert( TS_MW, self::$ts['edit1'] )
				]
			] ],
			$this->mgr->getRelatedChangesSince(
				$mainPage, self::$ts['start'], "to"
			),
			'Verify linked change list for the changed page is empty.'
		);

		$this->assertEquals(
			[ self::$page['rel'] => [
				'old' => '0',
				'new' => '3',
				'ts' => [
					ConvertibleTimestamp::convert( TS_MW, self::$ts['edit1'] )
				]
			] ],
			$this->mgr->getRelatedChangesSince(
				$mainPage, self::$ts['start'], "from"
			),
			'Verify that the changes for the changed page shows up.'
		);
	}

	public function testNewPageReverseRelatedChanges() {
		$mainPage = self::$title['mainPage'];
		$rel = self::$title['rel'];
		$user = self::$user['PRCTestUser'];
		$this->mgr->addWatch( $user, $mainPage );

		$page = WikiPage::factory( $mainPage );
		self::$ts['edit2'] = new ConvertibleTimestamp;
		$page->doEditContent(
			$this->getListOfLinks( [ $rel ] ),
			'Create the redlinked page',
			0,
			false,
			$user
		);

		$pages = $this->mgr->getRelatedPages( $mainPage );
		$this->assertEquals(
			[ self::$page['mainPage'] => [
				'old' => '0',
				'new' => '2',
				'ts' => [
					ConvertibleTimestamp::convert( TS_MW, self::$ts['edit2'] )
				]
			] ],
			$this->mgr->getRelatedChangesSince(
				$rel, self::$ts['start'], "from"
			),
			'Verify that page shows up on backlink.'
		);
	}

	public function testAnotherRelatedChanges() {
		$mainPage = self::$title['mainPage'];
		$anotherRel = self::$title['anotherRel'];
		$rel = self::$title['rel'];
		$user = self::$user['PRCTestUser'];
		$this->mgr->addWatch( $user, $mainPage );

		$page = WikiPage::factory( $mainPage );
		self::$ts['edit3'] = new ConvertibleTimestamp;
		$page->doEditContent(
			$this->getListOfLinks( [ $rel, $anotherRel ] ),
			'Create another redlink',
			0,
			false,
			$user
		);
		$anotherPage = WikiPage::factory( $anotherRel );
		$anotherPage->doEditContent(
			$this->getListOfLinks( [ $mainPage, $rel ] ),
			'Moar backlinks',
			0,
			false,
			$user
		);
		$this->assertEquals(
			[ self::$page['mainPage'] => [
				'old' => '0',
				'new' => (string)$mainPage->getLatestRevID(),
				'ts' => [
					ConvertibleTimestamp::convert( TS_MW, self::$ts['edit1'] ),
					ConvertibleTimestamp::convert( TS_MW, self::$ts['edit2'] )
				] ],
			  self::$page['rel'] => [
				'old' => '0',
				'new' => (string)$rel->getLatestRevID(),
				'ts' => [
					ConvertibleTimestamp::convert( TS_MW, self::$ts['edit2'] )
				] ] ],
			$this->mgr->getRelatedChangesSince(
				$anotherRel, self::$ts['start'], "from"
			),
			'Verify that page shows up on backlink.'
		);
	}

	public function testNotifyRelatedChanges() {
	}
}
