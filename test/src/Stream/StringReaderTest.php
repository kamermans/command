<?php namespace kamermans\Command\Stream;

class StringReaderTest extends \PHPUnit_Framework_TestCase {

    public function testConstructor()
    {
        $stream = string_to_stream("foobar");

        $buffer = '';
        $buffer_size = 1000;

        $reader = new StringReader($stream, 0, $buffer, $buffer_size);
    }

    public function testRead()
    {
        $value = get_test_string();
        $value_size = strlen($value);
        $stream = string_to_stream($value);

        $buffer = '';
        $buffer_size = 1000;

        $reader = new StringReader($stream, 0, $buffer, $buffer_size);

        $this->assertEmpty($buffer);
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

        // Make sure the data is copied to the buffer and is identical
        $this->assertSame(md5($value), md5($buffer), "I/O itegrity check failed");

        $min_iterations = ceil($value_size / $buffer_size);
        $this->assertGreaterThanOrEqual($min_iterations, $iterations, "Finished reading the stream in $iterations iterations, but with a buffer_size of $buffer_size and $value_size bytes of data, it must have taken at least $min_iterations iterations");
    }

}
