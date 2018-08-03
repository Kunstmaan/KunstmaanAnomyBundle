<?php

namespace Kunstmaan\AnomyBundle\Command;

use Kunstmaan\AnomyBundle\Exceptions\AnomyException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * Class AbstractCommand
 */
abstract class AbstractCommand extends Command
{
    /** @var ConsoleLogger */
    private $logger;

    /** @var ProgressBar */
    protected $progress;

    /** @var OutputInterface */
    protected $output;

    /** @var InputInterface */
    protected $input;

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->logger = new ConsoleLogger($output);
        $this->output = $output;
        $this->input = $input;

        $this->doExecute();
    }

    /**
     * @return void
     */
    abstract protected function doExecute();

    /**
     * @param string    $command The command
     * @param bool      $silent  Be silent or not
     *
     * @param  \Closure $callback
     *
     * @return bool|string
     */
    public function executeCommand($command, $silent = false, \Closure $callback = null, $env = [])
    {
        return $this->performCommand($command, $silent, $callback, $env);
    }

    /**
     * @param string        $command The command
     * @param bool          $silent  Be silent or not
     * @param string        $sudoAs  Sudo as a different user then the root user
     * @param \Closure|null $callback
     * @param array         $env
     *
     * @return bool|string
     */
    public function executeSudoCommand($command, $silent = false, $sudoAs = null, \Closure $callback = null, $env = [])
    {
        if (null === $sudoAs) {
            $command = 'sudo -s -p "Please enter your sudo password:" '.$command;
        } else {
            $command = 'sudo -s -p "Please enter your sudo password:" -u '.$sudoAs.' '.$command;
        }

        return $this->performCommand($command, $silent, $callback, $env);
    }

    /**
     * @param string        $command
     * @param bool          $silent
     * @param \Closure|null $callback
     * @param array         $env
     *
     * @return bool|string
     * @throws AnomyException
     */
    private function performCommand($command, $silent = false, \Closure $callback = null, $env = [])
    {
        $startTime = microtime(true);

        if (!$silent) {
            $this->logCommand($command);
        }

        $env = array_replace($_ENV, $_SERVER, $env);
        $process = new Process($command, null, $env);
        $process->setTty(true);
        $process->mustRun();
        $process->setTimeout(14400 * 100);
        $process->run($callback);
        if (!$silent) {
            $this->logCommandTime($startTime);
        }
        if (!$process->isSuccessful()) {
            if ($process->getExitCode() === 23) {
                return $process->getOutput();
            } else {
                if (!$silent) {
                    $this->logError($process->getErrorOutput());
                }

                return false;
            }
        }

        return $process->getOutput();
    }

    /**
     * @param string $message
     */
    public function logCommand($message)
    {
        if ($this->output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
            $this->logger->notice('<info>   $</info> <comment>'.$message.'</comment> ');
        } else {
            if ($this->progress !== null) {
                $this->progress->advance();
            }
        }
    }

    /**
     * @param $startTime
     */
    public function logCommandTime($startTime)
    {
        if ($this->output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
            $this->logger->notice('<fg=yellow;options=bold>'.round(microtime(true) - $startTime, 2).'s</fg=yellow;options=bold>');
        } else {
            if ($this->progress !== null) {
                $this->progress->advance();
            }
        }
    }

    /**
     * @param           $message
     * @param bool|true $report
     *
     * @throws AnomyException
     */
    public function logError($message, $report = true)
    {
        if ($report) {
            throw new AnomyException($message);
        } else {
            $this->logger->error("\n\n<error>  ".$message."</error>\n\n");
            exit(1);
        }
    }

    /**
     * @param string $message
     */
    public function logTask($message)
    {
        $this->clearLine();
        $this->output->writeln('<fg=blue;options=bold>   > '.$message." </fg=blue;options=bold>");
        if ($this->output->getVerbosity() <= OutputInterface::VERBOSITY_NORMAL) {
            $this->progress = new ProgressBar($this->output);
            $this->progress->setEmptyBarCharacter(' ');
            $this->progress->setBarCharacter('-');
            $this->progress->start();
        }
    }

    /**
     * @param string $message
     */
    public function logNotice($message)
    {
        if ($this->output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
            $this->output->writeln('<info>   !</info> <comment>'.$message.'</comment> ');
        } else {
            if ($this->progress !== null) {
                $this->progress->advance();
            }
        }
    }

    /**
     *
     */
    public function clearLine()
    {
        $message = str_pad("", 100, "\x20");
        $this->output->write("\x0D");
        $this->output->write($message);
        $this->output->write("\x0D");
    }

    /**
     * @param $path
     * @param $content
     * @param $append
     */
    public function writeProtectedFile($path, $content, $append = false)
    {
        $tmpfname = tempnam(sys_get_temp_dir(), "anomy");
        file_put_contents($tmpfname, $content);
        $this->executeCommand("cat ".$tmpfname." | sudo tee ".($append ? "-a " : "").$path.' > /dev/null');
        $this->executeCommand("rm -f ".$tmpfname);
    }

    /**
     * @param  string   $message
     * @param  string[] $extra
     *
     * @return string
     */
    public function logQuery($message, $extra = null)
    {
        if ($this->output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
            $this->output->writeln(
                '<info>   ~</info> <comment>'.
                $message.
                ($extra ?
                    ' ('.
                    implode(
                        ', ',
                        array_map(
                            function ($v, $k) {
                                return $k.'='.$v;
                            },
                            $extra,
                            array_keys($extra)
                        )
                    ).
                    ')' : '').
                '</comment> '
            );
        } else {
            if ($this->progress !== null) {
                $this->progress->advance();
            }
        }

        return $message;
    }
}
