<?php
/**
 * Implements Special:Recentchangeslinked
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @ingroup SpecialPage
 */

namespace MediaWiki\Extensions\PeriodicRelatedChanges;

use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * This special page is a stub to get some functionality we need
 *
 * @ingroup SpecialPage
 */

class MySpecialRelatedChanges extends \SpecialRecentChangesLinked {
	protected $target;
	protected $days;
	protected $linkedTo;

	/**
	 * Weird, not sure if I should invoke the parent's constructor
	 * @param string $target page to look at linkings
	 * @param ConvertibleTimestamp $startTime to look for history
	 */
	function __construct( $target, ConvertibleTimestamp $startTime ) {
		parent::__construct( 'myrelatedchanges' );
		$this->target = $target;
		$this->days = $startTime->diff( new ConvertibleTimestamp() )->days;
	}

	/**
	 * Don't let anyone execute
	 * @param mixed $subpage unused
	 */
	public function execute( $subpage ) {
		$this->displayRestrictionError();
		return;
	}

	/**
	 * Don't list this as a special page
	 * @return bool
	 */
	public function isListed() {
		return false;
	}

	/**
	 * Set the linking direction, to or from
	 * @param bool $linkedTo true pages this page links to, false for from
	 */
	public function linkedTo( $linkedTo ) {
		$this->linkedTo = $linkedTo;
	}

	/**
	 * Override the parental options with our needs
	 * @return FormOptions object
	 */
	public function getDefaultOptions() {
		$opts = parent::getDefaultOptions();
		$opts->add( 'target', $this->target );
		$opts->add( 'showlinkedto', $this->linkedTo );
		$opts->add( 'days', $this->days );

		return $opts;
	}
}
