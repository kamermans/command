<?php

use kamermans\Command\Command;

require_once __DIR__.'/../vendor/autoload.php';

// After this many callbacks, stop the command
$max_lines = 20;
// This will be incremented on each callback
$lines = 0;
// This is used in setCallback() to break on each line
$use_lines = true;

// This callback will be called for every line of output
$output_callback = function($pipe, $data) use ($max_lines, &$lines) {
    if ($pipe === Command::STDERR) {
        if ($data === null) {
            // We've hit EOF on STDERR
            return;
        }
        // send everything from STDERR to our STDERR
        Command::echoStdErr("STDERR: ".strlen($data)."\n");
        return;
    }

    // We've reached EOF on STDOUT
    if ($data === null) {
        return;
    }

    echo $data;

    $lines++;

    // If we return "false" all pipes will be closed
    if ($lines >= $max_lines) {
        return false;
    }
};

// Create the command, assign the callback and run it
$cmd = Command::factory('cat /var/log/syslog')
    ->setCallback($output_callback, $use_lines)
    ->run();
