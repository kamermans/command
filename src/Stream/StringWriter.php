<?php namespace kamermans\Command\Stream;

use kamermans\Command\Exception;

/**
 * Writes a string to a stream
 * @package kamermans\Command\Stream
 */
class StringWriter implements Handler, Writer {

    protected $data;
    protected $data_length;
    protected $dest_stream;
    protected $position;
    protected $buffer_size;

    /**
     * StringWriter constructor.
     *
     * @param string $data The string data to be read from
     * @param resource $dest_stream The destination stream to be written to
     * @param int $buffer_size The max bytes that can be copied at one time
     * @throws Exception The destination stream is invalid
     */
    public function __construct(&$data, $dest_stream, $buffer_size=4096)
    {
        if (!is_resource($dest_stream)) {
            throw new Exception("dest_stream is not a valid resource: ".gettype($dest_stream));
        }

        $this->data = &$data;
        $this->data_length = strlen($this->data);
        $this->dest_stream = $dest_stream;
        $this->position = 0;
        $this->buffer_size = $buffer_size;
    }

    public function write($auto_close=true)
    {
        // If we have written all the data, close the stream
        if ($this->position >= $this->data_length) {
            if ($auto_close && is_resource($this->dest_stream)) {
                fclose($this->dest_stream);
            }
            return false;
        } else {
            $chunk = substr($this->data, $this->position, $this->buffer_size);
            $bytes_written = @fwrite($this->dest_stream, $chunk);

            if ($bytes_written === false) {
                $bytes_written = 0;
            }

            $this->position += $bytes_written;
            return $bytes_written;
        }
    }

    public function getBytes()
    {
        return $this->position;
    }

}
