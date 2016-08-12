<?php namespace kamermans\Command\Stream;

use stdClass as Storage;
use kamermans\Command\Command;

class CallbackLinesReaderTest extends \PHPUnit_Framework_TestCase {

    public function testConstructor()
    {
        $stream = string_to_stream("foobar\nfoobar\n");
        $buffer_size = 1000;

        $callback_data = new Storage();
        $callback_data->calls = 0;
        $callback = function($pipe, $data) use ($callback_data) {
            $callback_data->calls++;
        };

        $reader = new CallbackLinesReader($stream, 0, $callback, $buffer_size);

        while (!feof($stream)) {
            $reader->read();
        }

        $this->assertSame(2, $callback_data->calls);
    }

    public function testCallbackReceivesSameData()
    {
        $value = get_test_string();
        $value_size = strlen($value);
        $stream = string_to_stream($value);

        $stream_id = Command::STDOUT;
        $buffer_size = 1000;

        $callback_data = new Storage();
        $callback_data->bytes = 0;
        $callback_data->calls = 0;
        $callback_data->buffer = '';
        $callback_data->stream_id = $stream_id;

        $phpunit = $this;

        $callback = function($pipe, $data) use ($callback_data, $phpunit) {
            $callback_data->calls++;
            $callback_data->bytes += strlen($data);
            $callback_data->buffer .= $data;
            $phpunit->assertSame($callback_data->stream_id, $pipe);
        };

        $reader = new CallbackLinesReader($stream, $stream_id, $callback, $buffer_size);

        $this->assertEmpty($callback_data->buffer);
        $this->assertSame(0, $reader->getBytes());

        $iterations = 0;
        $bytes = 0;
        while (!feof($stream)) {
            $iterations++;
            $bytes += $reader->read();
        }

        // Make sure we read the same number of bytes
        $this->assertSame($value_size, $bytes);
        $this->assertSame($value_size, $reader->getBytes());
        $this->assertSame($value_size, $callback_data->bytes);

        $total_lines = substr_count($value, "\n");
        $this->assertSame($total_lines, $callback_data->calls);

        // Make sure the data is copied to the buffer and is identical
        $this->assertSame(md5($value), md5($callback_data->buffer), "I/O itegrity check failed");

        $min_iterations = ceil($value_size / $buffer_size);
        $this->assertGreaterThanOrEqual($min_iterations, $iterations, "Finished reading the stream in $iterations iterations, but with a buffer_size of $buffer_size and $value_size bytes of data, it must have taken at least $min_iterations iterations");
    }
}
