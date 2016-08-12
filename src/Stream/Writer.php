<?php namespace kamermans\Command\Stream;

interface Writer {

    public function write();
    public function getBytes();

}
