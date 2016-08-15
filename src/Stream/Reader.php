<?php namespace kamermans\Command\Stream;

/**
 * Handles a stream and can read from it
 * @package kamermans\Command\Stream
 */
interface Reader {

    /**
     * Reads data from a stream.  This function should be called until the stream is closed (EOF).
     * @return int bytes read
     */
    public function read();

}
