command
=======

External command runner / executor for PHP

Taken from http://pollinimini.net/blog/php-command-runner/ and hosted here for maintenance, improvement and use with Packagist

At its simplest form, you can execute commands like this:

```php
$cmd = Command::factory('ls')->run();
```

Here we are safely adding arguments:

```php
use kamermans\Command\Command;

$cmd = Command::factory('/usr/bin/svn');
$cmd->option('--username', 'drslump')
    ->option('-r', 'HEAD')
    ->option('log')
    ->argument('http://code.google.com/drslump/trunk');
    ->run();
if ($cmd->getExitCode() === 0) {
    echo $cmd->getStdOut();
} else {
    echo $cmd->getStdErr();
}
```

Incremental updates can be accomplished with a callback function, like in the following example (PHP 5.3+):

```php
use kamermans\Command\Command;

$cmd = Command::factory('ls');
$cmd->setCallback(function($pipe, $data){
        if ($pipe === Command::STDOUT) echo 'STDOUT: ';
        if ($pipe === Command::STDERR) echo 'STDERR: ';
        echo $data === NULL ? "EOF\n" : "$data\n";
        // If we return "false" all pipes will be closed
        // return false;
    })
    ->setDirectory('/tmp')
    ->option('-l')
    ->run();
if ($cmd->getExitCode() === 0) {
    echo $cmd->getStdOut();
} else {
    echo $cmd->getStdErr();
}
```

Some more features:
StdIn data can be provided to the process as a parameter to run()
Set environment variables for the process with setEnv()
Second argument to option() and argument to argument() are automatically escaped.
Options separator is white space by default, it can be changed by manually setting it as third argument to option() or setting a new default with setOptionSeparator().
The proc_open wrapper is exposed as a static method for your convenience Command::exec()
And finally the class which makes all that possible :)