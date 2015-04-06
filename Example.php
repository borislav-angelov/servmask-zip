<?php

// define('WP_CONTENT_DIR', 'wp');

// // Iterate over WP_CONTENT_DIR directory
// $iterator = new RecursiveIteratorIterator(
// 	new RecursiveDirectoryIterator(
// 		WP_CONTENT_DIR
// 	),
// 	RecursiveIteratorIterator::SELF_FIRST
// );

// foreach ( $iterator as $item ) {
// 	if ( $item->isFile() ) {
// 		echo $iterator->getSubPathName();
// 		echo "\n";
// 	}
// }
// exit;

// require_once 'ServMaskZip.php';
// require_once 'ServMaskZipExtractor.php';

// $zip = new ServMaskZipExtractor;
// $zip->open('test.zip');

// $a = $zip->readCentralDirectory();

// //var_dump($a);
// exit;
// // //0x06054b50

// // exit;

require_once 'ServMaskZipCompressor.php';
require_once 'ServMaskZipExtractor.php';

@unlink('test.zip');
@unlink('test.zipx');

$zip = new ServMaskZipCompressor;

$zip->open('test.zip');

$zip->add('wp/file1.txt', 'file1.txt');
$zip->add('mmm.txt');

$zip->flush();

$zip->close();