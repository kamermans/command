<?php namespace kamermans\Command\Stream;

/**
 * Manages an IO handle/resource and is able to provide stats about the bytes affected.
 * @package kamermans\Command\Stream
 */
interface Handler {

    public function getBytes();

}
