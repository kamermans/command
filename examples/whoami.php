<?php

use kamermans\Command\Command;

require_once __DIR__.'/../vendor/autoload.php';

$cmd = new Command("whoami");
$cmd->run();

if ($cmd->getExitCode() === 0) {
	echo "OK\n";
    echo $cmd->getStdOut();
} else {
	echo "Error\n";
    echo $cmd->getStdErr();
}

echo "Done.\n";