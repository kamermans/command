<?php

use kamermans\Command\Command;

require_once __DIR__.'/../vendor/autoload.php';

echo "Type some words, one per line, then press CTRL-D and they will be sorted:\n";

$cmd = Command::factory("sort")
	// This causes Command to use the real STDIN
	->run(STDIN);

echo "\n";
echo $cmd->getStdOut();