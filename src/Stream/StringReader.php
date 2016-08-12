<?php namespace kamermans\Command\Stream;

class StringReader implements Reader {

    protected $id;
    protected $stream;
    protected $stream_id;
    protected $buffer_size;
    protected $buffer;
    protected $bytes = 0;

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
