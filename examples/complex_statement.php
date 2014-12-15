<?php

use kamermans\Command\Command;

require_once __DIR__.'/../vendor/autoload.php';

// We don't want to escape this command since it would break it
$no_escape = true;

// This example probably only works in real BASH on Linux/UNIX
// It will output a sorted list of directories in the $PATH
//$cmd = Command::factory('IFS=":"; (for DIR in $PATH; do echo $DIR; done) | sort', $escape)->run();
$cmd = Command::factory('IFS=":"; (for DIR in $PATH; do echo $DIR; done) | sort', $no_escape)->run();

if ($cmd->getExitCode() === 0) {
	echo "STDOUT:\n";
    echo $cmd->getStdOut();
} else {
	echo "STDERR:\n";
    echo $cmd->getStdErr();
}

echo "Done.\n";
