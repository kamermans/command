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
    protected $_args = array();
    protected $_exitcode;
    protected $_stdout;
    protected $_stderr;
    protected $_callback;
    protected $_callbacklines = false;
    protected $_timestart;
    protected $_timeend;
    protected $_cwd;
    protected $_env;
    protected $_conf = array();

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
        if ($cmd !== NULL) {
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
    public function setEnv($env = array())
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
        if ($right !== NULL) {
            $right = escapeshellarg($right);
            if (empty($right)) {
                $right = "''";
            }
            $left .= ($sep === NULL ? $this->_separator : $sep) . $right;
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
     * @param string $stdin
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
        $buffers = array(
            0 => $stdin,
            1 => &$this->_stdout,
            2 => &$this->_stderr,
        );
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
        $parts = array_merge(array($this->_cmd), $this->_args);
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
            $buffers = array();
        }

        // Define the pipes to configure for the process
        $descriptors = array(
            self::STDIN  => array('pipe', 'r'),
            self::STDOUT => array('pipe', 'w'),
            self::STDERR => array('pipe', 'w'),
        );

        // Start the process
        $ph = proc_open($cmd, $descriptors, $pipes, $cwd, $env, $conf);
        if (!is_resource($ph)) {
            return null;
        }

        // Feed the process with the stdin if any and close it
        if (!empty($buffers[self::STDIN])) {
        fwrite($pipes[self::STDIN], $buffers[self::STDIN]);
        }
        fclose($pipes[self::STDIN]);

        // Setup non-blocking behaviour for stdout and stderr
        stream_set_blocking($pipes[self::STDOUT], 0);
        stream_set_blocking($pipes[self::STDERR], 0);

        $delay = 0;
        $code = null;
        $open = array(self::STDOUT, self::STDERR);
        $buffers[self::STDOUT] = empty($buffers[self::STDOUT]) ? '' : $buffers[self::STDOUT];
        $buffers[self::STDERR] = empty($buffers[self::STDERR]) ? '' : $buffers[self::STDERR];

        while (!empty($open)) {
            // Try to find the exit code of the command before buggy proc_close()
            if ($code === NULL) {
                $status = proc_get_status($ph);
                if (!$status['running']) {
                    $code = $status['exitcode'];
                }
            }

            // Go thru all open pipes and check for data
            foreach ($open as $i=>$pipe) {
                // Try to get some data
                $str = fread($pipes[$pipe], $readbuffer);
                if (strlen($str)) {
                    $buffers[$pipe] .= $str;
                    
                    if ($callback) {
                        if ($callbacklines) {
                            // Note: \r will be left in the line in case of CRLF,
                            // and we will need to add \n to the end of each line
                            $lines = explode("\n", $buffers[$pipe]);

                            // This is left over and does not end with the delimiter
                            $buffers[$pipe] = array_pop($lines);

                            foreach ($lines as $line) {
                                $callback_return = call_user_func($callback, $pipe, "$line\n");
                                if ($callback_return === false) {
                                    // We killed the proc early, set code to 0
                                    $code = 0;
                                    break 3;
                                }
                            }
                            
                        } else {
                            $callback_return = call_user_func($callback, $pipe, $buffers[$pipe]);
                            $buffers[$pipe] = '';
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
                } else if (feof($pipes[$pipe])) {
                    if ($callback) {
                        if (call_user_func($callback, $pipe, null) === false) {
                            break 2;
                        }
                    }
                    unset($open[$i]);
                    continue 2;
                }
            }

            // Check if we have to sleep for a bit to be nice on the CPU
            if ($delay) {
                usleep($delay * 1000);
                $delay = ceil(min(self::SLEEP_MAX, $delay*self::SLEEP_FACTOR));
            } else {
                $delay = self::SLEEP_START;
            }
        }

        // Make sure all pipes are closed
        foreach ($pipes as $pipe=>$desc) {
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
        if ($code === NULL) {
            $code = proc_close($ph);
        } else {
            proc_close($ph);
        }

        return $code;
    }
}
