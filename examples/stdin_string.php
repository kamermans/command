<?php

use kamermans\Command\Command;

require_once __DIR__.'/../vendor/autoload.php';

$stdin = "banana\norange\napple\npear\n";

$cmd = Command::factory("sort")
	->run($stdin);

echo $cmd->getStdOut();