<?php namespace kamermans\Command\Stream;

use kamermans\Command\Exception;

/**
 * Writes a stream into another stream
 * @package kamermans\Command\Stream
 */
class StreamWriter implements Handler, Writer {

    protected $id;
    protected $stream;
    protected $dest_stream;
    protected $buffer;
    protected $buffer_size;
    protected $bytes = 0;

    /**
     * StreamWriter constructor.
     *
     * @param resource $source_stream The source stream that will be read from
     * @param resource $dest_stream The destination stream that will be written to
     * @param int $buffer_size The max bytes that can be copied at one time
     * @throws Exception The source or destination streams are invalid
     */
    public function __construct($source_stream, $dest_stream, $buffer_size=4096)
    {
        if (!is_resource($source_stream)) {
            throw new Exception("source_stream is not a valid resource");
        }

        if (!is_resource($dest_stream)) {
            throw new Exception("dest_stream is not a valid resource");
        }

        $this->id = (string)$source_stream;
        $this->stream = $source_stream;
        $this->dest_stream = $dest_stream;
        $this->buffer =  '';
        $this->buffer_size = $buffer_size;
    }

    public function write($auto_close=true)
    {
        // If stream is empty and the send buffer is empty, close the stream
        if (feof($this->stream) && strlen($this->buffer) === 0) {
            if ($auto_close && is_resource($this->dest_stream)) {
                fclose($this->dest_stream);
            }
            return false;
        }

        if (!feof($this->stream) && strlen($this->buffer) < $this->buffer_size) {
            // The stream buffer is running low
            $this->buffer .= stream_get_contents($this->stream, $this->buffer_size);
        }

        $bytes_written = @fwrite($this->dest_stream, $this->buffer);

        if ($bytes_written === false) {
            return 0;
        }

        $current_buffer_length = strlen($this->buffer);

        if ($bytes_written === $current_buffer_length) {
            $this->buffer = '';
        } else {
            // Only part of the buffer was written so we remove that part
            $this->buffer = substr($this->buffer, $bytes_written);
        }

        $this->bytes += $bytes_written;
        return $bytes_written;

    }

    public function getBytes()
    {
        return $this->bytes;
    }

}
