<?php

function string_to_stream($value, $blocking=false)
{
    $stream = fopen("php://memory", "r+");
    fwrite($stream, $value);
    rewind($stream);
    stream_set_blocking($stream, $blocking);
    return $stream;
}

function get_test_string($iterations=1000)
{
    $line_data = str_repeat("0123456789", 10);
    $out = '';
    for ($i=0; $i<$iterations; $i++) {
        $out .= sprintf("[%010s]: %s\n", $i, $line_data);
    }
    return $out;
}