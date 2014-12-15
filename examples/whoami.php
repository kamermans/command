<?php

use kamermans\Command\Command;

require_once __DIR__.'/../vendor/autoload.php';

$cmd = Command::factory("whoami")->run();

if ($cmd->getExitCode() === 0) {
	echo "STDOUT:\n";
    echo $cmd->getStdOut();
} else {
	echo "STDERR:\n";
    echo $cmd->getStdErr();
}

echo "Done.\n";
