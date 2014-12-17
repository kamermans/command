<?php

use kamermans\Command\Command;

require_once __DIR__.'/../vendor/autoload.php';

$cmd = Command::factory("whoami")->run();

echo "Command as it was run: '$cmd'\n";
echo "Output: ".$cmd->getStdOut()."\n";

echo "Done.\n";
