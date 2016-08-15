<?php namespace kamermans\Command\Stream;

/**
 * Handles a stream and can write to it
 * @package kamermans\Command\Stream
 */
interface Writer {

    /**
     * Writes data into a stream.  This function should be called until the input data is empty/EOF.
     * @param bool $auto_close If true, the stream being written to will be closed when input is EOF.
     * @return int bytes written
     */
    public function write($auto_close=true);

}
