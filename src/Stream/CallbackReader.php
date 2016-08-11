<?php namespace kamermans\Command\Stream;

use kamermans\Command\TerminateException;

class CallbackReader extends StringReader {

    protected $callback;

    public function __construct($stream, $stream_id, $callback, $buffer_size=4096)
    {
        if (!is_resource($stream)) {
            throw new \InvalidArguentException("stream is not a valid resource");
        }

        $this->callback = $callback;
        $psuedo_buffer = '';

        parent::__construct($stream, $stream_id, $psuedo_buffer, $buffer_size);
    }

    public function read()
    {
        $buffer = $this->getBytesFromStream();
        if (strlen($buffer) === 0) {
            return;
        }

        $callback_return = call_user_func($this->callback, $this->stream_id, $buffer);

        if ($callback_return === false) {
            throw new TerminateException();
        }
    }
}
