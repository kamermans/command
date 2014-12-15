<?php

use kamermans\Command\Command;
use kamermans\Command\CommandException;

require_once __DIR__.'/../vendor/autoload.php';

try {

    // The command "foo" doesn't exist
    $cmd = Command::factory('ls -la | foo', true)
        // Throw exceptions for failed commands
        ->useExceptions(true)
        ->run();

    echo $cmd->getStdOut();

} catch (CommandException $e) {
    // We should see something like "foo: not found"
	echo $e->getMessage();
}

echo "\n";
