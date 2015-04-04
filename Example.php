<?php

require_once 'ServMaskZip.php';

@unlink('test.zip');
@unlink('test.zip.cd');

$zip = new ServMaskZip;

$zip->open('test.zip');

$zip->addFile('README.md');

$zip->close();