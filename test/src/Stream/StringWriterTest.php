<?php namespace kamermans\Command\Stream;

class StringWriterTest extends \PHPUnit_Framework_TestCase {

    public function testConstructor()
    {
        $stream = string_to_stream("");

        $value = 'foobar';
        $buffer_size = 1000;

        $writer = new StringWriter($value, $stream, $buffer_size);
    }

    public function testWrite()
    {
        $value = get_test_string();
        $value_size = strlen($value);

        $stream = string_to_stream("");

        $buffer_size = 1000;

        $writer = new StringWriter($value, $stream, $buffer_size);

        $this->assertSame(0, ftell($stream));

        $iterations = 0;
        $bytes = 0;
        while ($ret = $writer->write(false)) {
            $bytes += $ret;
            $iterations++;
        }
        
        // Make sure we read the same number of bytes
        $this->assertSame($value_size, $bytes);
        $this->assertSame($value_size, $writer->getBytes());
        $this->assertSame($value_size, ftell($stream));

        // Read the data that was written to the stream back
        // to the buffer so we can inspect it
        rewind($stream);
        stream_set_blocking($stream, true);
        $buffer = stream_get_contents($stream);

        // Make sure the data is copied to the buffer and is identical
        $this->assertSame(md5($value), md5($buffer), "I/O itegrity check failed");

        $min_iterations = ceil($value_size / $buffer_size);
        $this->assertGreaterThanOrEqual($min_iterations, $iterations, "Finished reading the stream in $iterations iterations, but with a buffer_size of $buffer_size and $value_size bytes of data, it must have taken at least $min_iterations iterations");
    }

    public function testWriteAutoCloseOff()
    {
        $value = get_test_string();
        $value_size = strlen($value);

        $stream = string_to_stream("");

        $buffer_size = 1000;

        $writer = new StringWriter($value, $stream, $buffer_size);

        $this->assertTrue(is_resource($stream));

        while ($ret = $writer->write(false));

        $this->assertTrue(is_resource($stream));
    }


    public function testWriteAutoCloseOn()
    {
        $value = get_test_string();
        $value_size = strlen($value);

        $stream = string_to_stream("");

        $buffer_size = 1000;

        $writer = new StringWriter($value, $stream, $buffer_size);

        $this->assertTrue(is_resource($stream));

        while ($ret = $writer->write(true));

        $this->assertFalse(is_resource($stream));
    }

}
