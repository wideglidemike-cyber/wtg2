<?php
/**
 * Fix file permissions after upload via Bluehost File Manager.
 *
 * Visit this URL after deploying:
 * https://winetoursgrapevine.com/wp-content/plugins/wtg2/fix-permissions.php
 *
 * Sets directories to 755 and files to 644.
 */

// Only allow if accessed directly (not through WordPress).
if ( php_sapi_name() === 'cli' ) {
	die( 'Run this from a browser.' );
}

$plugin_dir = __DIR__;

// Fix directory permissions.
$dirs = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $plugin_dir, RecursiveDirectoryIterator::SKIP_DOTS ),
	RecursiveIteratorIterator::SELF_FIRST
);

$dir_count  = 0;
$file_count = 0;

foreach ( $dirs as $item ) {
	if ( $item->isDir() ) {
		chmod( $item->getPathname(), 0755 );
		$dir_count++;
	} else {
		chmod( $item->getPathname(), 0644 );
		$file_count++;
	}
}

// Fix the plugin root directory itself.
chmod( $plugin_dir, 0755 );

echo "Permissions fixed: {$dir_count} directories (755), {$file_count} files (644).";
