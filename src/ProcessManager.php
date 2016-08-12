<?php namespace kamermans\Command;

class ProcessManager {
    
    protected $cmd;
    protected $buffers;
    protected $handle;
    protected $io_handles = [];
    protected $io_reads = [];
    protected $io_writes = [];
    protected $streams = [];

    public function __construct($cmd, &$buffers)
    {
        if (!is_array($buffers)) {
            $buffers = [];
        }

        $this->cmd = $cmd;
        $this->buffers = &$buffers;
    }

    /**
     * Executes a command returning the exitcode and capturing the stdout and stderr
     *
     * @param string $cmd
     * @param array &$buffers
     *  0 - StdIn contents to be passed to the command (optional)
     *  1 - StdOut contents returned by the command execution
     *  2 - StdOut contents returned by the command execution
     * @param callable $callback  A callback function for stdout/stderr data
     * @param bool $callbacklines Call callback for each line
     * @param int $buffer_size Read this many bytes at a time
     * @param string $cwd Set working directory
     * @param array $env Environment variables for the process
     * @param array $conf Additional options for proc_open()
     * @return int
     */
    public function exec($callback, $callbacklines, $buffer_size, $cwd, $env, $conf)
    {
        $this->open($cwd, $env, $conf);
        $this->setupStreams($callback, $callbacklines, $buffer_size);

        $exit_code = null;

        try {

            while (true) {

                $handles = $this->waitForReadyHandles();

                // Try to find the exit code of the command before buggy proc_close()
                if ($exit_code === null) {

                    $status = proc_get_status($this->handle);

                    if (!$status['running']) {
                        // Process exited, get the exit code while it's still available
                        if ($exit_code === null) {
                            $exit_code = $status['exitcode'];
                        }

                        // Close STDIN
                        array_map('fclose', array_filter($this->io_writes, 'is_resource'));
                    }
                }

                $finished_handles = count(array_filter($this->io_reads, 'feof'));
                if ($finished_handles === count($this->io_reads)) {
                    break;
                }

                $this->doReadWrite($handles);

            }

        } catch (TerminateException $e) {
            // We killed the proc early, set code to 0
            $exit_code = 0;
        }

        $this->close($exit_code, $callback);

        return $exit_code;
    }

    protected function doReadWrite($handles)
    {
        if ($handles['ready'] === 0) {
            // Stream timeout; no streams ready
            return false;
        } else if ($handles['ready'] === false) {
            throw new \Exception("stream_select() failed while waiting for I/O on command");
        }

        // Read from all ready streams
        foreach ($handles['read'] as $handle) {
            $this->getStreamFromIoHandle($handle)->read();
        }

        // Write to all write ready streams (STDIN of the process)
        if (!empty($handles['write'])) {
            $this->streams[Command::STDIN]->write();
        }

        /* Stream debugging code:
        $in  = $this->streams[Command::STDIN]? $this->streams[Command::STDIN]->getBytes(): 0;
        $out = $this->streams[Command::STDOUT]->getBytes();
        $err = $this->streams[Command::STDERR]->getBytes();
        Command::echoStdErr("[To STDIN: $in, From STDOUT: $out, From STDERR: $err]\n");
        */

        return true;
    }

    protected function open($cwd, $env, $conf)
    {
        // Define the streams to configure for the process
        $descriptors = [
            Command::STDIN  => ['pipe', 'r'],
            Command::STDOUT => ['pipe', 'w'],
            Command::STDERR => ['pipe', 'w'],
        ];

        // Start the process
        $this->handle = proc_open($this->cmd, $descriptors, $this->io_handles, $cwd, $env, $conf);
        if (!is_resource($this->handle)) {
            throw new \Exception("Failed to open process handle");
        }

        // Set all IO handles to non-blocking mode
        foreach ($this->io_handles as $io_handle) {
            if (is_resource($io_handle)) {
                stream_set_blocking($io_handle, false);
            }
        }

        // Read from the process' STDOUT and STDERR
        $this->io_reads = [
            $this->io_handles[Command::STDOUT],
            $this->io_handles[Command::STDERR],
        ];

        // Write to the process' STDIN
        $this->io_writes = [
            $this->io_handles[Command::STDIN],
        ];
    }

    protected function close($exit_code, $callback)
    {
        // Make sure all IO handles are closed
        foreach ($this->io_handles as $id => $handle) {
            if (is_resource($handle)) {
                if ($callback) {
                    // Notify the callback of each handle closure
                    call_user_func($callback, $id, null);
                }
                fclose($handle);
            }
        }

        // Make sure the process is terminated
        $status = proc_get_status($this->handle);
        if ($status['running']) {
            proc_terminate($this->handle);
        }

        // Find out the exit code
        if ($exit_code === null) {
            $exit_code = proc_close($this->handle);
        } else {
            proc_close($this->handle);
        }

        return $exit_code;
    }

    protected function isStdInAvailable()
    {
        return $this->isStdInStreaming() || !empty($this->buffers[Command::STDIN]);
    }

    protected function isStdInStreaming()
    {
        return is_resource($this->buffers[Command::STDIN]);
    }

    protected function setupStreams($callback, $callbacklines, $buffer_size)
    {
        // Prepare STDIN
        if (!$this->isStdInAvailable()) {
            // No STDIN is going to the process
            fclose($this->io_handles[Command::STDIN]);
            $stdin_stream = null;
        } else if ($this->isStdInStreaming()) {
            // STDIN is streaming to the process
            $stdin_stream = new Stream\StreamWriter(
                $this->buffers[Command::STDIN], 
                $this->io_handles[Command::STDIN], 
                $buffer_size
            );
        } else {
            // STDIN is provided as a string and fed to the process
            $stdin_stream = new Stream\StringWriter(
                $this->buffers[Command::STDIN],
                $this->io_handles[Command::STDIN],
                $buffer_size
            );
        }

        // Prepare STDOUT and STDERR
        if ($callback === null) {
            $stdout_stream = new Stream\StringReader(
                $this->io_handles[Command::STDOUT],
                Command::STDOUT,
                $this->buffers[Command::STDOUT],
                $buffer_size
            );
            $stderr_stream = new Stream\StringReader(
                $this->io_handles[Command::STDERR],
                Command::STDERR,
                $this->buffers[Command::STDERR],
                $buffer_size
            );
        } else if ($callbacklines) {
            $stdout_stream = new Stream\CallbackLinesReader(
                $this->io_handles[Command::STDOUT],
                Command::STDOUT,
                $callback,
                $buffer_size
            );
            $stderr_stream = new Stream\CallbackLinesReader(
                $this->io_handles[Command::STDERR],
                Command::STDERR,
                $callback,
                $buffer_size
            );
        } else {
            $stdout_stream = new Stream\CallbackReader(
                $this->io_handles[Command::STDOUT],
                Command::STDOUT,
                $callback,
                $buffer_size
            );
            $stderr_stream = new Stream\CallbackReader(
                $this->io_handles[Command::STDERR],
                Command::STDERR,
                $callback,
                $buffer_size
            );
        }

        $this->streams = [
            Command::STDIN  => $stdin_stream,
            Command::STDOUT => $stdout_stream,
            Command::STDERR => $stderr_stream,
        ];
    }


    protected function waitForReadyHandles()
    {
        // Setup streams before each iteration since they are changed by stream_select()
        $stream_select_timeout_sec = null;
        $stream_select_timeout_usec = 200000;

        $handles['read'] = array_filter($this->io_reads, 'is_resource');

        if ($this->isStdInAvailable() && is_resource($this->io_handles[Command::STDIN])) {
            $handles['write'] = $this->io_writes;
        } else {
            $handles['write'] = [];
        }

        $except = [];

        // This line will block until a stream is ready for input or output
        $num_ready = stream_select(
            $handles['read'],
            $handles['write'],
            $except,
            $stream_select_timeout_sec,
            $stream_select_timeout_usec
        );

        $handles['ready'] = $num_ready;

        return $handles;
    }

    protected function getStreamFromIoHandle($handle)
    {
        return $this->streams[array_search($handle, $this->io_handles)];
    }
}
