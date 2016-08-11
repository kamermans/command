<?php namespace kamermans\Command\Stream;

class StreamWriter implements Writer {

    protected $id;
    protected $stream;
    protected $dest_stream;
    protected $buffer;
    protected $buffer_size;

    public function __construct($source_stream, $dest_stream, $buffer_size=4096)
    {
        if (!is_resource($source_stream)) {
            throw new \InvalidArguentException("source_stream is not a valid resource");
        }

        if (!is_resource($dest_stream)) {
            throw new \InvalidArgumentException("dest_stream is not a valid resource");
        }

        $this->id = (string)$source_stream;
        $this->stream = $source_stream;
        $this->dest_stream = $dest_stream;
        $this->buffer =  '';
        $this->buffer_size = $buffer_size;
    }

    public function write()
    {
        // If stream is empty and the send buffer is empty, close the stream
        if (feof($this->stream) && strlen($this->buffer) === 0) {
            fclose($this->dest_stream);
            return false;
        }

        if (!feof($this->stream) && strlen($this->buffer) < $this->buffer_size) {
            // The stream buffer is running low
            $this->buffer .= stream_get_contents($this->stream, $this->buffer_size);
        }

        $bytes_written = fwrite($this->dest_stream, $this->buffer);

        if ($bytes_written === false) {
            continue;
        }

        $current_buffer_length = strlen($this->buffer);

        if ($bytes_written === $current_buffer_length) {
            $this->buffer = '';
        } else {
            // Only part of the buffer was written so we remove that part
            $this->buffer = substr($this->buffer, $bytes_written);
        }

        return $bytes_written;

    }

}
