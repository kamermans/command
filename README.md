command
=======

External command runner / executor for PHP.  This is an object oriented, robust replacement for `exec`, `shell_exec`, the backtick operator and the like.

> This package is inspired by http://pollinimini.net/blog/php-command-runner/.

## Running Commands

At its simplest form, you can execute commands like this:

```php
$cmd = Command::factory('ls')->run();
```

### Adding Arguments and Options

Here we are safely adding arguments:

```php
use kamermans\Command\Command;

$cmd = Command::factory('/usr/bin/svn')
    ->option('--username', 'drslump')
    ->option('-r', 'HEAD')
    ->option('log')
    ->argument('http://code.google.com/drslump/trunk')
    ->run();

echo $cmd->getStdOut();
```

### Using a Callback for Incremental Updates
Normally all command output is buffered and once the command completes you can access it.  By using a callback, the output is buffered until the desired number of bytes is received (see `Command::setReadBuffer(int $bytes)`), then it is passed to your callback function:

```php
use kamermans\Command\Command;

$cmd = Command::factory('ls')
    ->setCallback(function($pipe, $data) {
        // Gets run for every 4096 bytes
        echo $data;
    })
    ->setReadBuffer(4096)
    ->setDirectory('/tmp')
    ->option('-l')
    ->run();
```

Alternately, you can set the second argument for `Command::run(string $stdin, bool $lines)` to `true` to execute your callback once for every line of output:

```php
use kamermans\Command\Command;

$cmd = Command::factory('ls')
    ->setCallback(function($pipe, $data){
        // Gets run for each line of output
        echo $data;
    })
    ->setDirectory('/tmp')
    ->option('-l')
    ->run(null, true);
```

### Streaming large command output
The STDOUT and STDERR is collected inside PHP by default.  If you have a large amount of data to pass into the command, you should stream it in (see STDIN from a stream below).  If you have a large amount of output from the command, you should stream it out using a callback:

```php
use kamermans\Command\Command;

require_once __DIR__.'/../vendor/autoload.php';

$filename = __DIR__.'/../README.md';
$stdin = fopen($filename, 'r');

// This will read README.md and grep for lines containing 'the'
$cmd = Command::factory("grep 'the'")
    ->setCallback(function($pipe, $data) {
        // Change the text to uppercase
        $data = strtoupper($data);

        if ($pipe === Command::STDERR) {
            Command::echoStdErr($data);
        } else {
            echo $data;
        }
    })
    ->run($stdin);

fclose($stdin);

```

### Running a Command without Escaping
By default, the command passed to `Command::factory(string $command, bool $escape)` is escaped, so characters like `|` and `>` will replaced with `\|` and `\>` respectively.  To prevent the command factory from escaping your command, you can pass `true` as the second argument:

```php
use kamermans\Command\Command;

$cmd = Command::factory('grep CRON < /var/log/syslog | head', true)->run();

echo $cmd->getStdOut();
```

### Outputting to STDERR
To output content to your `STDERR` there is a helper function `Command::echoStdErr(string $content)`:

```php
use kamermans\Command\Command;

$cmd = Command::factory('grep CRON < /var/log/syslog | head', true)
    ->setCallback(function($pipe,$data) {
        if ($pipe === Command::STDERR) {
            Command::echoStdErr($data);
        } else {
            echo $data;
        }
    })
    ->run();
```

## Using STDIN
You can provide data for STDIN using a string or a stream resource (like a file handle)

### STDIN from a String

```php
use kamermans\Command\Command;

$stdin = "banana
orange
apple
pear
";

$cmd = Command::factory("sort")
    ->run($stdin);

echo $cmd->getStdOut();
```

### STDIN from a Stream

```php
use kamermans\Command\Command;

$filename = __DIR__.'/../README.md';
$stdin = fopen($filename, 'r');

// This will count the number of words in the README.md file
$cmd = Command::factory("wc")
    ->option("--words")
    ->run($stdin);

fclose($stdin);

$words = trim($cmd->getStdOut());
echo "File $filename contains $words words\n";
```

Your system's `STDIN` is also a stream, so you can accept input that is typed on the command line or piped into your script as well:

```php
use kamermans\Command\Command;

echo "Type some words, one per line, then press CTRL-D and they will be sorted:\n";

$cmd = Command::factory("sort")
    // This causes Command to use the real STDIN
    ->run(STDIN);

echo "\n";
echo $cmd->getStdOut();
```

Some more features:
 - `StdIn` data can be provided to the process as a parameter to `run()`
 - Set environment variables for the process with `setEnv()`
 - Second argument to `option()` and argument to `argument()` are automatically escaped.
 - Options separator is white space by default, it can be changed by manually setting it as third argument to `option()` or setting a new default with `setOptionSeparator()`.
 - The `proc_open` wrapper is exposed as a static method for your convenience `Command::exec()`
