<?php namespace kamermans\Command;

class CommandTest extends \PHPUnit_Framework_TestCase {

    public function testConstructor()
    {
        $command = new Command();
    }

    public function testFactoryEscape()
    {
        $cmd = "whoami > /dev/null";
        $command = Command::factory($cmd, false);

        $this->assertSame("whoami \> /dev/null", $command->getFullCommand());
    }

    public function testFactoryNoEscape()
    {
        $cmd = "whoami > /dev/null";
        $command = Command::factory($cmd, true);

        $this->assertSame($cmd, $command->getFullCommand());
    }

    public function testArgumentsAndOptions()
    {
        $command = Command::factory("whoami")
            ->option("-u", "steve")
            ->option("--foo", "bar")
            ->option("somethirdoption")
            ->argument("afourtharg");

        $expected_result = "whoami -u 'steve' --foo 'bar' somethirdoption 'afourtharg'";
        $this->assertSame($expected_result, $command->getFullCommand());
        $this->assertSame($expected_result, (string)$command);
    }

    public function testArgumentsAndOptionsAreEscaped()
    {
        $command = Command::factory("whoami")
            ->option("-u", "steve's")
            ->option("--foo", "some'thing\"nasty")
            ->argument('!@#$%^&*()[]{}\|-=,.<>/?;\':\\"');

        $expected_result =<<<EOF
whoami -u 'steve'\''s' --foo 'some'\''thing"nasty' '!@#$%^&*()[]{}\|-=,.<>/?;'\'':\"'
EOF;
        $this->assertSame($expected_result, $command->getFullCommand());
    }

    /**
     * @expectedException kamermans\Command\CommandException
     */
    public function testCommandNotFound()
    {
        $command = Command::factory("./foobar")
            ->setDirectory(__DIR__."/../resources")
            ->run();
    }

    /**
     * @expectedException kamermans\Command\CommandException
     * @expectedExceptionMessage Command failed './exit_code.sh '4'':
     */
    public function testCommandFailedThrows()
    {
        $exit_code = 4;
        $command = Command::factory("./exit_code.sh")
            ->setDirectory(__DIR__."/../resources")
            ->argument($exit_code)
            ->run();
    }

    public function testCommandSuccess()
    {
        $exit_code = 0;
        $command = Command::factory("./exit_code.sh")
            ->setDirectory(__DIR__."/../resources")
            ->argument($exit_code)
            ->run();
    }

    public function testCommandFailedWithoutExceptions()
    {
        $exit_code = 4;
        $throw_exceptions = false;
        $command = Command::factory("./exit_code.sh")
            ->setDirectory(__DIR__."/../resources")
            ->argument($exit_code)
            ->run(null, $throw_exceptions);

        $this->assertSame($exit_code, $command->getExitCode());
        $this->assertSame("Message on STDOUT\n", $command->getStdOut());
        $this->assertSame("Message on STDERR\n", $command->getStdErr());
    }

    public function testCommandStdInToStdOutString()
    {
        $value = get_test_string();

        $command = Command::factory("./stdin_stream.sh")
            ->setDirectory(__DIR__."/../resources")
            ->argument("stdout")
            ->run($value);

        $this->assertSame(md5($value), md5($command->getStdOut()));
        $this->assertEmpty($command->getStdErr());
    }

    public function testCommandStdInToStdErrString()
    {
        $value = get_test_string();

        $command = Command::factory("./stdin_stream.sh")
            ->setDirectory(__DIR__."/../resources")
            ->argument("stderr")
            ->run($value);

        $this->assertEmpty($command->getStdOut());
        $this->assertSame(md5($value), md5($command->getStdErr()));
    }

    public function testCommandStdInToStdOutStream()
    {
        $value = get_test_string();
        $stream = string_to_stream($value);

        $command = Command::factory("./stdin_stream.sh")
            ->setDirectory(__DIR__."/../resources")
            ->argument("stdout")
            ->run($stream);

        $this->assertSame(md5($value), md5($command->getStdOut()));
        $this->assertEmpty($command->getStdErr());
    }

    public function testCommandStdInToStdErrStream()
    {
        $value = get_test_string();
        $stream = string_to_stream($value);

        $command = Command::factory("./stdin_stream.sh")
            ->setDirectory(__DIR__."/../resources")
            ->argument("stderr")
            ->run($stream);

        $this->assertEmpty($command->getStdOut());
        $this->assertSame(md5($value), md5($command->getStdErr()));
    }

    public function testCommandBinaryStdInToStdOutStream()
    {
        // NOTE: setBinary() adds the 'binary_pipes' option to proc_open()
        // but this option is not documented, and this test passes either way.

        $value = gzencode(get_test_string());
        $stream = string_to_stream($value);

        $command = Command::factory("./stdin_stream.sh")
            ->setDirectory(__DIR__."/../resources")
            ->setBinary()
            ->argument("stdout")
            ->run($stream);

        $this->assertSame(md5($value), md5($command->getStdOut()));
        $this->assertEmpty($command->getStdErr());
    }

    public function testDurationInt()
    {
        $delay = 1;
        $command = Command::factory("sleep")
            ->argument($delay)
            ->run();

        $duration = $command->getDuration();
        $this->assertInternalType('int', $duration);
        $this->assertSame(1, $duration);
    }

    public function testDurationFloat()
    {
        $delay = 1;
        $command = Command::factory("sleep")
            ->argument($delay)
            ->run();

        $duration = $command->getDuration(true);
        $this->assertInternalType('float', $duration);
        $this->assertGreaterThanOrEqual(0.9, $duration);
    }

}
