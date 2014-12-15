<?php

use kamermans\Command\Command;

require_once __DIR__.'/../vendor/autoload.php';

// We don't want to escape this command since it would break it
$no_escape = true;

// This example probably only works in real BASH on Linux/UNIX
// It will output a sorted list of directories in the $PATH
$cmd = Command::factory('IFS=":"; (for DIR in $PATH; do echo $DIR; done) | sort', $no_escape)
    // Throw an exception if it fails
    ->useExceptions()
    ->run();

echo $cmd->getStdOut();

echo "Done.\n";
