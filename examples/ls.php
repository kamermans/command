<?php

use kamermans\Command\Command;

require_once __DIR__.'/../vendor/autoload.php';

$cmd = Command::factory('ls')
    ->setCallback(function($pipe, $data) {
        echo $data;
    })
    ->setDirectory('/tmp')
    ->option('-l')
    ->run();

echo "\nCommand '$cmd' Finished.\n";