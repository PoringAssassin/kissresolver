<?php

$libRoot = '../lib';
$srcRoot = '../src';
$buildRoot = '../dist';
$outputName = 'kissresolver.phar';
 
$phar = new Phar
(
	$buildRoot . '/' . $outputName,
	FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::KEY_AS_FILENAME, 
	$outputName
);

$phar->buildFromDirectory($srcRoot, '#.php$#i');
$phar->setStub($phar->createDefaultStub('bootstrap.php'));

copy($libRoot . '/httpful.phar', $buildRoot . '/httpful.phar');

?>