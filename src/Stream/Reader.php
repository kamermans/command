<?php namespace kamermans\Command\Stream;

interface Reader {

    public function read();
    public function getBytes();

}
