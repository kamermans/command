<?php namespace kamermans\Command\Stream;

/**
 * Reads a stream into a string
 * @package kamermans\Command\Stream
 */
class StringReader implements Handler, Reader {

    protected $id;
    protected $stream;
    protected $stream_id;
    protected $buffer_size;
    protected $buffer;
    protected $bytes = 0;

    /**
     * StringReader constructor.
     * @param resource $stream The source stream that will be read from
     * @param int $stream_id The ID of the stream (0=STDIN, 1=STDOUT, 2=STDERR)
     * @param string $buffer The destination string that will be written to
     * @param int $buffer_size The max bytes that can be copied at one time
     */
    public function __construct($stream, $stream_id, &$buffer, $buffer_size=4096)
    {
        if (!is_resource($stream)) {
            throw new \InvalidArgumentException("stream is not a valid resource");
        }

        $this->id = (string)$stream;
        $this->stream = $stream;
        $this->stream_id = $stream_id;
        $this->buffer = &$buffer;
        $this->buffer_size = $buffer_size;
    }

    public function read()
    {
        $before = strlen($this->buffer);
        $this->buffer .= $this->getBytesFromStream();
        $bytes = strlen($this->buffer) - $before;
        $this->bytes += $bytes;

        return $bytes;
    }

    public function getBytes()
    {
        return $this->bytes;
    }

    protected function getBytesFromStream()
    {
        return stream_get_contents($this->stream, $this->buffer_size);
    }

}
