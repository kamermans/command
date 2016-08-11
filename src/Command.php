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
        $stdin_position = 0;
        $stdin = $buffers[self::STDIN];
        $stdin_is_stream = is_resource($stdin);
        $use_stdin = $stdin_is_stream || !empty($stdin);
        $stdin_length = null;
        
        if (!$use_stdin) {
            fclose($pipes[self::STDIN]);
        } else if (!$stdin_is_stream) {
            $stdin_length = strlen($stdin);
        }

        // Setup all streams to non-blocking mode
        stream_set_blocking($pipes[self::STDIN], false);
        stream_set_blocking($pipes[self::STDOUT], false);
        stream_set_blocking($pipes[self::STDERR], false);

        $stream_select_timeout_sec = null;
        $stream_select_timeout_usec = null;

        $delay = 0;
        $code = null;

        $buffers[self::STDIN] = '';
        $buffers[self::STDOUT] = empty($buffers[self::STDOUT]) ? '' : $buffers[self::STDOUT];
        $buffers[self::STDERR] = empty($buffers[self::STDERR]) ? '' : $buffers[self::STDERR];

        // Read from the process' STDOUT and STDERR
        $reads = [
            $pipes[self::STDOUT],
            $pipes[self::STDERR],
        ];

        // Write to the process' STDIN
        $writes = [
            $pipes[self::STDIN],
        ];

        $stream_id_map = [
            self::STDIN => $pipes[self::STDIN],
            self::STDOUT => $pipes[self::STDOUT],
            self::STDERR => $pipes[self::STDERR],
        ];

        // Read/write loop
        while (true) {

            // Setup streams before each iteration since they are changed by stream_select()
            $streams = [
                'read'  => array_filter($reads, 'is_resource'),
                'write' => array_filter($writes, 'is_resource'),
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
            if ($code === null) {
                $status = proc_get_status($ph);
                if (!$status['running']) {
                    $code = $status['exitcode'];
                    break;
                }
            }

            if ($ready_streams === 0) {
                // Stream timeout; no streams ready, retry stream_select
                continue;
            }

            if ($ready_streams === false) {
                throw new \Exception("stream_select() failed while waiting for I/O on command");
            }

            // Read from all ready streams
            foreach ($streams['read'] as $stream) {

                $stream_id = array_search($stream, $stream_id_map, true);

                $str = stream_get_contents($stream, $readbuffer);
                if (strlen($str) !== 0) {
                    $buffers[$stream_id] .= $str;
                    
                    if ($callback) {
                        if ($callbacklines) {
                            // Note: \r will be left in the line in case of CRLF,
                            // and we will need to add \n to the end of each line
                            $lines = explode("\n", $buffers[$stream_id]);

                            // This is left over and does not end with the delimiter
                            $buffers[$stream_id] = array_pop($lines);

                            foreach ($lines as $line) {
                                $callback_return = call_user_func($callback, $stream_id, "$line\n");
                                if ($callback_return === false) {
                                    // We killed the proc early, set code to 0
                                    $code = 0;
                                    break 3;
                                }
                            }
                            
                        } else {
                            $callback_return = call_user_func($callback, $stream_id, $buffers[$stream_id]);
                            $buffers[$stream_id] = '';
                            if ($callback_return === false) {
                                // We killed the proc early, set code to 0
                                $code = 0;
                                break 2;
                            }
                        }
                    }

                    // Since we've got some data we don't need to sleep :)
                    $delay = 0;
                // Check if we have consumed all the data in the current pipe
                }
            }

            // Write to all write ready streams (STDIN of the process)
            foreach ($streams['write'] as $stream) {
                
                $stream_id = array_search($stream, $stream_id_map, true);

                if ($stdin_is_stream) {
                    // It seems this method is less memory-intensive that the stream copying builtin:
                    //   stream_copy_to_stream(resource $source, resource $dest)
                    if (feof($stdin)) {
                        fclose($stream);
                    } else {
                        if (strlen($buffers[$stream_id]) < $readbuffer) {
                            // The STDIN buffer is running low
                            $buffers[$stream_id] .= stream_get_contents($stdin, $readbuffer);
                        }

                        $bytes_written = fwrite($stream, $buffers[$stream_id]);

                        if ($bytes_written === false) {
                            continue;
                        }

                        $buffer_length = strlen($buffers[$stream_id]);

                        if ($bytes_written === $buffer_length) {
                            $buffers[$stream_id] = '';
                        } else {
                            // Only part of the buffer was written so we remove that part
                            $buffers[$stream_id] = substr($buffers[$stream_id], $bytes_written);
                        }
                    }

                } else {
                    if ($stdin_position >= $stdin_length) {
                        fclose($stream);
                    } else {
                        $chunk = substr($stdin, $stdin_position, $readbuffer);
                        $bytes_written = fwrite($stream, $chunk);
                        $stdin_position += $bytes_written;
                    }
                }
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
        if ($code === null) {
            $code = proc_close($ph);
        } else {
            proc_close($ph);
        }

        return $code;
    }
}
