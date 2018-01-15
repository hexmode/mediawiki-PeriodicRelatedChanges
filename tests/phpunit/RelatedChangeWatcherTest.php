<?php
namespace MediaWiki\Extensions\PeriodicRelatedChanges\Test;

use MediaWiki\Extensions\PeriodicRelatedChanges\RelatedChangeWatcher;
use Title;
use User;

/**
 * @group medium
 */
class RelatedChangeWatcherTest extends \EchoTalkPageFunctionalTest {

	/**
	 * Creates and updates a page that has related watches
	 */
	public function testGetRelatedChangeWatchers() {
		$wok = Title::newFromText( "randomBit" );
		$wlTable = 'periodic_related_change';
		$conditions = [ 'wc_user' => 1,
						'wc_namespace' => NS_MAIN,
						'wc_title' => 'China' ];
		wfGetDB( DB_MASTER )->delete( $wlTable, $conditions, __METHOD__ );

		$content = <<<EOF
[[Image:Wok cooking.jpg|thumb|right|250px|Cooking in a wok]]
A '''wok''' is a [[China|Chinese]] pan used for [[cooking]] . It has a round bottom. The most common use for the wok is [[stir frying]], though it can also be used for [[deep frying]], [[smoking]], [[braising]], [[roasting]], [[grilling]], and [[steaming]].

In [[Indonesia]], the wok is known as a ''wadjang'', ''kuali'' in [[Malaysia]], and ''kawali'' (small wok)  and ''kawa'' (big wok) in the [[Philippines]].{{fact|date=April 2009}}

== Other websites ==
* [http://www.thaifoodandtravel.com/features/wokcare.html Wok Seasoning and Care] from thaifoodandtravel.com


{{tech-stub}}

[[Category:Cookware and bakeware]]
EOF;
		$this->editPage( $wok->getText(), $content, '', NS_MAIN );
		$watchers = RelatedChangeWatcher::getRelatedChangeWatchers( $wok );
		$this->assertEquals( [], $watchers );

		$this->assertEquals(
			wfGetDB( DB_MASTER )->insert( $wlTable, $conditions, __METHOD__ ),
			1
		);
		$watchers = RelatedChangeWatcher::getRelatedChangeWatchers( $wok );
		$this->assertEquals( [ User::newFromID( $conditions['wc_user'] ) ], $watchers );
	}
}
