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

$files = glob($srcRoot . '/*.{php}', GLOB_BRACE);
foreach($files as $file) {
	if ($file == 'index.php'){
		contine;
	}
		
	$phar[$file] = preg_replace
	(
		"/require_once *\( *['\"]([a-zA-Z-_]+\.php)['\"] *\);/", 
		"require_once('phar://". $outputName ."/$1');", 
		file_get_contents($srcRoot . '/' . $file)
	);
}

$phar->createDefaultStub();

copy($libRoot . '/httpful.phar', $buildRoot . '/httpful.phar');

?>