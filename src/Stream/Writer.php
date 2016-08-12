<?php namespace kamermans\Command\Stream;

interface Writer {

    public function write($auto_close=true);
    public function getBytes();

}
