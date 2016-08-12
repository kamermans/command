<?php namespace kamermans\Command\Stream;

use kamermans\Command\TerminateException;

class CallbackLinesReader extends CallbackReader {

    public function read()
    {
        $buffer = $this->getBytesFromStream();
        $bytes = strlen($buffer);
        if ($bytes === 0) {
            return 0;
        }

        $this->buffer .= $buffer;
        unset($buffer);

        // Note: \r will be left in the line in case of CRLF,
        // and we will need to add \n to the end of each line
        $lines = explode("\n", $this->buffer);

        // This is left over and does not end with the delimiter
        $this->buffer = array_pop($lines);

        foreach ($lines as $line) {
            $callback_return = call_user_func($this->callback, $this->stream_id, "$line\n");
            if ($callback_return === false) {
                throw new TerminateException();
            }
        }

        $this->bytes += $bytes;
        return $bytes;
    }
}
