<?php namespace kamermans\Command\Stream;

use kamermans\Command\Exception;
use kamermans\Command\TerminateException;

/**
 * Reads a stream into a callback in chunks
 * @package kamermans\Command\Stream
 */
class CallbackReader extends StringReader {

    protected $callback;

    /**
     * CallbackReader constructor.
     *
     * Note that the $callback function will be called with two arguments:
     *   int $stream_id The stream_id that the data was read from
     *   string $data The data that was read
     *
     * When the stream is finally closed, it will be called one last time with `$data = null`.
     *
     * @param resource $stream The source stream to be read from
     * @param int $stream_id The ID of the stream (0=STDIN, 1=STDOUT, 2=STDERR)
     * @param callable $callback The function that will be called for each chunk of data
     * @param int $buffer_size The max bytes that can be copied at one time
     * @throws Exception The source stream is invalid
     */
    public function __construct($stream, $stream_id, callable $callback, $buffer_size=4096)
    {
        if (!is_resource($stream)) {
            throw new Exception("stream is not a valid resource");
        }

        $this->callback = $callback;
        $psuedo_buffer = '';

        parent::__construct($stream, $stream_id, $psuedo_buffer, $buffer_size);
    }

    public function read()
    {
        $buffer = $this->getBytesFromStream();
        $bytes = strlen($buffer);
        if ($bytes === 0) {
            return 0;
        }

        $callback_return = call_user_func($this->callback, $this->stream_id, $buffer);

        if ($callback_return === false) {
            throw new TerminateException();
        }

        $this->bytes += $bytes;
        return $bytes;
    }
}
