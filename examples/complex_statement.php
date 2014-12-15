<?php

use kamermans\Command\Command;

require_once __DIR__.'/../vendor/autoload.php';

// We don't want to escape this command since it would break it
$escape = false;

// This example probably only works in real BASH on Linux/UNIX
// It will output a sorted list of directories in the $PATH
$cmd = Command::factory('IFS=":" sort <(for DIR in $PATH; do echo $DIR; done)', $escape)->run();

if ($cmd->getExitCode() === 0) {
	echo "STDOUT:\n";
    echo $cmd->getStdOut();
} else {
	echo "STDERR:\n";
    echo $cmd->getStdErr();
}

echo "Done.\n";