<?php

use kamermans\Command\Command;

require_once __DIR__.'/../vendor/autoload.php';

$cmd = Command::factory('sleep 2 && echo "OK"', true)
    ->run();

echo $cmd->getStdOut();
echo "Finished in ".$cmd->getDuration()." second(s)\n";
echo " (with microseconds: ".$cmd->getDuration(true).")\n";
