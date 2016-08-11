<?php namespace kamermans\Command\Stream;

class StringWriter implements Writer {

    protected $data;
    protected $data_length;
    protected $dest_stream;
    protected $position;
    protected $buffer_size;

    public function __construct(&$data, $dest_stream, $buffer_size=4096)
    {
        if (!is_resource($dest_stream)) {
            throw new \InvalidArgumentException("dest_stream is not a valid resource: ".gettype($dest_stream));
        }

        $this->data = &$data;
        $this->data_length = strlen($this->data);
        $this->dest_stream = $dest_stream;
        $this->position = 0;
        $this->buffer_size = $buffer_size;
    }

    public function write()
    {
        // If we have written all the data, close the stream
        if ($this->position >= $this->data_length) {
            fclose($this->dest_stream);
            return false;
        } else {
            $chunk = substr($this->data, $this->position, $this->buffer_size);
            $bytes_written = fwrite($this->dest_stream, $chunk);
            $this->position += $bytes_written;
            return $bytes_written;
        }
    }

}
