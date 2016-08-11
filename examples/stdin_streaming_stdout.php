<?php

use kamermans\Command\Command;

require_once __DIR__.'/../vendor/autoload.php';

$filename = __DIR__.'/../README.md';
$stdin = fopen($filename, 'r');

// This will read README.md and grep for lines containing 'the'
$cmd = Command::factory("grep 'the'")
    ->setCallback(function($pipe, $data) {
        // Change the text to uppercase
        $data = strtoupper($data);

        if ($pipe === Command::STDERR) {
            Command::echoStdErr($data);
        } else {
            echo $data;
        }
    })
    ->run($stdin);

fclose($stdin);
