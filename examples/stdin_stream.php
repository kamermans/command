<?php

use kamermans\Command\Command;

require_once __DIR__.'/../vendor/autoload.php';

$filename = __DIR__.'/../README.md';
$stdin = fopen($filename, 'r');

// This will count the number of words in the README.md file
$cmd = Command::factory("wc")
	->option("--words")
	->run($stdin);

fclose($stdin);

$words = trim($cmd->getStdOut());
echo "File $filename contains $words words\n";
