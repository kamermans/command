<?php namespace kamermans\Command;

class Command
{
    const STDIN  = 0;
    const STDOUT = 1;
    const STDERR = 2;

    protected $readbuffer = 16536;
    protected $separator = ' ';
    protected $cmd;
    protected $args = [];
    protected $exitcode;
    protected $stdout;
    protected $stderr;
    protected $callback;
    protected $callbacklines = false;
    protected $timestart;
    protected $timeend;
    protected $cwd;
    protected $env;
    protected $conf = [];

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
        $this->callback = $callback;
        $this->callbacklines = $readlines;
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
        $this->readbuffer = $bytes;
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
        $this->cwd = $cwd;
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
        $this->env = $env;
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
        $this->conf['binary_pipes'] = $binary;
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
        $this->separator = $sep;
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
        $this->cmd = $noescape ? $cmd : escapeshellcmd($cmd);
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
            $left .= ($sep === null ? $this->separator : $sep) . $right;
        }

        $this->args[] = $left;

        return $this;
    }

    /**
     * Adds a new argument to the command
     *
     * @param string $arg   the argument (automatically escaped)
     * @return Command - Fluent interface
     */
    public function argument($arg) {
        $this->args[] = escapeshellarg($arg);
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
        $this->exitcode = null;
        $this->stdout = null;
        $this->stderr = null;
        $this->timestart = microtime(true);

        $process = new ProcessManager($this->getFullCommand(), $buffers);

        // Prepare the buffers structure
        $buffers = [
            self::STDIN  => $stdin,
            self::STDOUT => &$this->stdout,
            self::STDERR => &$this->stderr,
        ];

        $this->exitcode = $process->exec($this->callback, $this->callbacklines, $this->readbuffer, $this->cwd, $this->env, $this->conf);
        $this->timeend = microtime(true);

        if ($throw_exceptions && $this->exitcode !== 0) {
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
        $parts = array_merge([$this->cmd], $this->args);
        return implode($this->separator, $parts);
    }

    /**
     * Gets the exit code from the last run
     *
     * @return int
     */
    public function getExitCode()
    {
        return $this->exitcode;
    }

    /**
     * Gets the generated stdout from the last run
     *
     * @return string
     */
    public function getStdOut()
    {
        return $this->stdout;
    }

    /**
     * Gets the generated stderr from the last run
     *
     * @return string
     */
    public function getStdErr()
    {
        return $this->stderr;
    }

    /**
     * Gets the duration of the command execution
     *
     * @param bool $microseconds If true, return microseconds (float), otherwise seconds (int)
     * @return int|float
     */
    public function getDuration($microseconds=false)
    {
        $duration = $this->timeend - $this->timestart;
        return $microseconds? $duration: (int)round($duration);
    }

    public static function echoStdErr($content)
    {
        fputs(STDERR, $content);
    }
}
