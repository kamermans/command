<?php namespace kamermans\Command;

/**
 * Command building and execution
 *
 * Most methods implement a "fluent interface" for easy method call chaining.
 * 
 * @see http://pollinimini.net/blog/php-command-runner/
 * @author IvÃ¡n -DrSlump- Montes <drslump@pollinimini.net>
 * @license BSD
 */
class Command
{
    const STDIN = 0;
    const STDOUT = 1;
    const STDERR = 2;

    // Maximum number of milliseconds to sleep while pooling for data
    const SLEEP_MAX = 200;
    // Number of milliseconds to sleep the first time
    const SLEEP_START = 1;
    // Multiplying rate to increase the number of milliseconds to sleep
    const SLEEP_FACTOR = 1.5;

    protected $_readbuffer = 16536;
    protected $_separator = ' ';
    protected $_cmd;
    protected $_args = [];
    protected $_exitcode;
    protected $_stdout;
    protected $_stderr;
    protected $_callback;
    protected $_callbacklines = false;
    protected $_timestart;
    protected $_timeend;
    protected $_cwd;
    protected $_env;
    protected $_conf = [];

    /**
     * Creates a new Command object
     *
     * @param string $cmd
     * @param bool $noescape    If true the $cmd string will be escaped
     * @return Command - Fluent interface
     */
    static public function factory($cmd = null, $noescape = false)
    {
        $obj = new self();
        if ($cmd !== null) {
            $obj->command($cmd, $noescape);
        }
        return $obj;
    }

    /**
     * Register a callback function to be triggered when there is data for stdout/stderr
     *
     * The first argument is the pipe identifier (Command::STDOUT or Command::STDERR)
     * The second argument is either a string with the available data or null to 
     * indicate the eof.
     *
     * @param callable $callback Callable like function($pipe, $data){}
     * @param bool $readlines If true, callback is called for each line instead of the buffer size
     * @return Command - Fluent
     */
    public function setCallback(callable $callback, $readlines=false)
    {
        $this->_callback = $callback;
        $this->_callbacklines = $readlines;
        return $this;
    }
    
    /**
     * Read no more than this many bytes at a time
     * 
     * @param int $bytes
     * @reutrn Command - Fluent
     */
    public function setReadBuffer($bytes)
    {
        $bytes = (int)$bytes;
        if ($bytes <= 0) {
            throw new \InvalidArgumentException("Read buffer must be greater than 0");
        }
        $this->_readbuffer = $bytes;
        return $this;
    }

    /**
     * Sets working directory for the execution
     *
     * @param string $cwd
     * @return Command - Fluent
     */
    public function setDirectory($cwd)
    {
        $this->_cwd = $cwd;
        return $this;
    }

    /**
     * Sets environment variables for the command execution
     * 
     * @param array $env
     * @return Command - Fluent
     */
    public function setEnv($env = [])
    {
        $this->_env = $env;
        return $this;
    }

    /** 
     * Flags proc_open() to use binary pipes
     *
     * @param bool $binary
     * @return Command - Fluent
     */
    public function setBinary($binary = true)
    {
        $this->_conf['binary_pipes'] = $binary;
        return $this;
    }

    /**
     * Sets the character to use to separate an option from its argument
     *
     * @param string $sep
     * @return Command - Fluent interface
     */
    public function setOptionSeparator($sep)
    {
        $this->_separator = $sep;
        return $this;
    }

    /**
     * Sets the command to run
     *
     * @param string $cmd
     * @param bool $noescape    If true the $cmd string will be escaped
     * @return Command - Fluent interface
     */
    public function command($cmd, $noescape = false)
    {
        $this->_cmd = $noescape ? $cmd : escapeshellcmd($cmd);
        return $this;
    }

    /**
     * Adds a new option to the command
     *
     * @param string $left  Option name
     * @param string $right Argument for the option (automatically escaped)
     * @param string $sep   Specific separator to use between the option and the argument
     * @return Command - Fluent interface
     */
    public function option($left, $right = null, $sep = null)
    {
        if ($right !== null) {
            $right = escapeshellarg($right);
            if (empty($right)) {
                $right = "''";
            }
            $left .= ($sep === null ? $this->_separator : $sep) . $right;
        }

        $this->_args[] = $left;

        return $this;
    }

    /**
     * Adds a new argument to the command
     *
     * @param string $arg   the argument (automatically escaped)
     * @return Command - Fluent interface
     */
    public function argument($arg) {
        $this->_args[] = escapeshellarg($arg);
        return $this;
    }

    /**
     * Runs the command
     *
     * @param string|resource $stdin The string contents for STDIN or a stream resource to be consumed
     * @param bool $throw_exceptions If true (default), an exception will be thrown if the command fails
     * @return Command - Fluent interface
     */
    public function run($stdin = null, $throw_exceptions = true)
    {
        // Clear previous run
        $this->_exitcode = null;
        $this->_stdout = null;
        $this->_stderr = null;
        $this->_timestart = microtime(true);

        // Prepare the buffers structure
        $buffers = [
            0 => $stdin,
            1 => &$this->_stdout,
            2 => &$this->_stderr,
        ];
        $this->_exitcode = self::exec($this->getFullCommand(), $buffers, $this->_callback, $this->_callbacklines, $this->_readbuffer, $this->_cwd, $this->_env, $this->_conf);
        $this->_timeend = microtime(true);

        if ($throw_exceptions && $this->_exitcode !== 0) {
            throw new CommandException($this, "Command failed '$this':\n".trim($this->getStdErr()));
        }

        return $this;
    }

    public function __toString()
    {
        return $this->getFullCommand();
    }

    /**
     * Get the full configured command as it would be run
     *
     * @return string
     */
    public function getFullCommand()
    {
        $parts = array_merge([$this->_cmd], $this->_args);
        return implode($this->_separator, $parts);
    }

    /**
     * Gets the exit code from the last run
     *
     * @return int
     */
    public function getExitCode()
    {
        return $this->_exitcode;
    }

    /**
     * Gets the generated stdout from the last run
     *
     * @return string
     */
    public function getStdOut()
    {
        return $this->_stdout;
    }

    /**
     * Gets the generated stderr from the last run
     *
     * @return string
     */
    public function getStdErr()
    {
        return $this->_stderr;
    }

    /**
     * Gets the duration of the command execution
     *
     * @param bool $microseconds If true, return microseconds (float), otherwise seconds (int)
     * @return int|float
     */
    public function getDuration($microseconds=false)
    {
        $duration = $this->_timeend - $this->_timestart;
        return $microseconds? $duration: round($duration);
    }

    public static function echoStdErr($content)
    {
        fputs(STDERR, $content);
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
     * @param int $readbuffer Read this many bytes at a time
     * @param string $cwd Set working directory
     * @param array $env Environment variables for the process
     * @param array $conf Additional options for proc_open()
     * @return int
     */
    public static function exec($cmd, &$buffers, $callback = null, $callbacklines = false, $readbuffer = 16536, $cwd = null, $env = null, $conf = null)
    {
        if (!is_array($buffers)) {
            $buffers = [];
        }

        // Define the pipes to configure for the process
        $descriptors = [
            self::STDIN  => ['pipe', 'r'],
            self::STDOUT => ['pipe', 'w'],
            self::STDERR => ['pipe', 'w'],
        ];

        // Start the process
        $ph = proc_open($cmd, $descriptors, $pipes, $cwd, $env, $conf);
        if (!is_resource($ph)) {
            return null;
        }

        // Prepare STDIN
        $stdin = $buffers[self::STDIN];
        $stdin_is_stream = is_resource($stdin);
        $use_stdin = $stdin_is_stream || !empty($stdin);
        
        if (!$use_stdin) {
            fclose($pipes[self::STDIN]);
        } else if (is_resource($buffers[self::STDIN])) {
            $input_stream = new Stream\StreamWriter($buffers[self::STDIN], $pipes[self::STDIN], $readbuffer);
        } else {
            $input_stream = new Stream\StringWriter($buffers[self::STDIN], $pipes[self::STDIN], $readbuffer);
        }

        // Prepare STDOUT and STDERR
        if ($callback === null) {
            $stdout_stream = new Stream\StringReader($pipes[self::STDOUT], self::STDOUT, $buffers[self::STDOUT], $readbuffer);
            $stderr_stream = new Stream\StringReader($pipes[self::STDERR], self::STDERR, $buffers[self::STDERR], $readbuffer);
        } else if ($callbacklines) {
            $stdout_stream = new Stream\CallbackLinesReader($pipes[self::STDOUT], self::STDOUT, $callback, $readbuffer);
            $stderr_stream = new Stream\CallbackLinesReader($pipes[self::STDERR], self::STDERR, $callback, $readbuffer);
        } else {
            $stdout_stream = new Stream\CallbackReader($pipes[self::STDOUT], self::STDOUT, $callback, $readbuffer);
            $stderr_stream = new Stream\CallbackReader($pipes[self::STDERR], self::STDERR, $callback, $readbuffer);
        }

        // Setup all streams to non-blocking mode
        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                stream_set_blocking($pipe, false);
            }
        }

        $stream_select_timeout_sec = null;
        $stream_select_timeout_usec = 200000;

        $exit_code = null;

        // Read from the process' STDOUT and STDERR
        $reads = [
            $pipes[self::STDOUT],
            $pipes[self::STDERR],
        ];

        // Write to the process' STDIN
        $writes = [
            $pipes[self::STDIN],
        ];

        // We will do a final, blocking read on all streams after the process exists
        $last_read_loop = false;

        // Read/write loop
        while (true) {

            // Setup streams before each iteration since they are changed by stream_select()
            $streams = [
                'read'  => array_filter($reads, 'is_resource'),
                'write' => $use_stdin? array_filter($writes, 'is_resource'): [],
                'except' => [],
            ];

            // This line will block until a stream is ready for input or output
            $ready_streams = stream_select(
                $streams['read'],
                $streams['write'],
                $streams['except'],
                $stream_select_timeout_sec,
                $stream_select_timeout_usec
            );

            // Try to find the exit code of the command before buggy proc_close()
            if ($exit_code === null || $last_read_loop === true) {

                $status = proc_get_status($ph);

                if (!$status['running']) {

                    if ($exit_code === null) {
                        $exit_code = $status['exitcode'];
                    }

                    if ($last_read_loop) {
                        // Process exited, close write streams
                        array_map('fclose', array_filter($writes, 'is_resource'));

                        // Set read streams to blocking mode so we can get all the remaining data
                        foreach (array_filter($reads, 'is_resource') as $stream) {
                            stream_set_blocking($stream, true);
                        }

                        // Break out of the read/write loop
                        break;
                    } else {
                        $last_read_loop = true;
                    }
                }
            }

            if ($ready_streams === 0) {
                // Stream timeout; no streams ready, retry stream_select
                continue;
            } else if ($ready_streams === false) {
                throw new \Exception("stream_select() failed while waiting for I/O on command");
            }

            // Read from all ready streams
            foreach ($streams['read'] as $stream) {
                try {

                    if ($stream === $pipes[self::STDOUT]) {
                        $stdout_stream->read();
                    } else {
                        $stderr_stream->read();
                    }

                } catch (TerminateException $e) {
                    // We killed the proc early, set code to 0
                    $exit_code = 0;
                }
            }

            // Write to all write ready streams (STDIN of the process)
            if (!empty($streams['write'])) {
                $input_stream->write();
            }

        } // End read/write loop

        // Make sure all pipes are closed
        foreach ($pipes as $pipe => $desc) {
            if (is_resource($desc)) {
                if ($callback) {
                    call_user_func($callback, $pipe, null);
                }
                fclose($desc);
            }
        }

        // Make sure the process is terminated
        $status = proc_get_status($ph);
        if ($status['running']) {
            proc_terminate($ph);
        }

        // Find out the exit code
        if ($exit_code === null) {
            $exit_code = proc_close($ph);
        } else {
            proc_close($ph);
        }

        return $exit_code;
    }
}
