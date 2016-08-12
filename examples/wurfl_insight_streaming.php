<?php

use kamermans\Command\Command;

require_once __DIR__.'/../vendor/autoload.php';

/**
 * This example demonstrates streaming HTTP User-Agent strings in STDIN
 * and passing them through the ScientiaMobile InSight for Device Analytics
 * product, then outputting JSON objects representing the WURFL device.
 * 
 * For information on ScientiaMobile InSight for Device Analytics see
 *   http://www.scientiamobile.com/page/wurfl-insight
 */

$wurfld_server = "localhost:13827";
$wurfl_fields = [
    "device_id",
    "is_mobile",
    "brand_name",
    "model_name",
];

// Make sure that wurfld is running
try {
    $cmd = Command::factory("wurfld-cli")
        ->option("--ping")
        ->run();
} catch (Exception $e) {
    Command::echoStdErr(get_class($e).": ".$e->getMessage()."\n");
    exit(2);
}

$cmd = Command::factory("wurfld-cli")
    ->option("--host $wurfld_server")
    ->option("--fields ".implode(",", $wurfl_fields))
    ->setCallback(function($pipe, $data) use ($wurfl_fields) {

        if ($pipe === Command::STDERR) {
            Command::echoStdErr($data);
            return;
        }

        if ($data === null) {
            // End of stream reached
            return;
        }

        $data_fields = explode("\t", rtrim($data, "\n"));
        $fields_missing = count($wurfl_fields) - count($data_fields);
        if ($fields_missing !== 0) {
            for ($i=0; $i<$fields_missing; $i++) {
                $data_fields[] = '';
            }
        }

        $device = array_combine($wurfl_fields, $data_fields);
        echo json_encode($device)."\n";

    }, true)
    ->run(STDIN);
