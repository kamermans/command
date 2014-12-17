<?php

use kamermans\Command\Command;
use kamermans\Command\CommandException;

require_once __DIR__.'/../vendor/autoload.php';

echo "Using Exception handlers:\n\n";

try {
	// The command "foo" doesn't exist
	$cmd = Command::factory('ls -la | foo', true)
    	->run();

    // This will not run since run() will throw an exception
    echo "STDOUT: ".$cmd->getStdOut()."\n";

} catch (CommandException $e) {
	$cmd = $e->getCommand();
	echo "Exit Code: ".$cmd->getExitCode()."\n";
	echo "STDERR: ".$cmd->getStdErr()."\n";
    // We should see something like "foo: not found"
	echo $e->getMessage();
}

echo "\n======\n";
echo "Disabling Exception handlers:\n\n";

// Disabling exceptions
$cmd = Command::factory('ls -la | foo', true)
	// Second argument (false) disables exceptions
    ->run(null, false);

echo "Exit Code: ".$cmd->getExitCode()."\n";
echo "STDOUT: ".$cmd->getStdOut()."\n";
echo "STDERR: ".$cmd->getStdErr()."\n";
