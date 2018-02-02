<?php

if ( PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg' ) {
	die( 'Not an entry point' );
}

error_reporting( E_ALL | E_STRICT );
date_default_timezone_set( 'UTC' );
ini_set( 'display_errors', 1 );

$version = null;
if (
	method_exists(
		MediaWiki\Extensions\PeriodicRelatedChanges\Hook::class, 'getVersion'
	) ) {
	$version = MediaWiki\Extensions\PeriodicRelatedChanges\Hook::getVersion();
}
if ( $version === null ) {
	die(
		"\nPeriodic Related Changes is not available, please check your Composer "
		. "or LocalSettings.\n"
	);
}

print sprintf( "\n%-20s%s\n", "Periodic Related Changes: ", $version );
