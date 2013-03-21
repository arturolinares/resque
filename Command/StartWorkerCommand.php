<?php

namespace BCC\ResqueBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Process\Process;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class StartWorkerCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('bcc:resque:worker-start')
            ->setDescription('Start a bcc resque worker')
            ->addArgument('queues', InputArgument::REQUIRED, 'Queue names (separate using comma)')
            ->addOption('foreground', 'f', InputOption::VALUE_NONE, 'Should the worker run in foreground')
            ->addOption('memory-limit', 'm', InputOption::VALUE_REQUIRED, 'Force cli memory_limit (expressed in Mbytes)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $env = array(
            'APP_INCLUDE' => $this->getContainer()->getParameter('bcc_resque.resque.vendor_dir').'/autoload.php',
            'QUEUE'       => $input->getArgument('queues'),
            'VERBOSE'     => 1,
        );
        if ($input->getOption('verbose')) {
            $env['VVERBOSE'] = 1;
        }
        if ($input->getOption('quiet')) {
            unset($env['VERBOSE']);
        }
        $opt = '';
        if (0 !== $m = (int) $input->getOption('memory-limit')) {
            $opt = sprintf('-d memory_limit=%dM', $m);
        }
        $workerCommand = strtr('php %opt% %dir%/chrisboulton/php-resque/resque.php', array(
            '%opt%' => $opt,
            '%dir%' => $this->getContainer()->getParameter('bcc_resque.resque.vendor_dir'),
        ));

        if (!$input->getOption('foreground')) {
            $workerCommand = strtr('nohup %cmd% > %logs_dir%/resque.log 2>&1 & echo $!', array(
                '%cmd%'      => $workerCommand,
                '%logs_dir%' => $this->getContainer()->getParameter('kernel.logs_dir'),
            ));
        }

        $process = new Process($workerCommand, null, $env, null, null);

        if (!$input->getOption('quiet')) {
            $output->writeln(\sprintf('Starting worker <info>%s</info>', $process->getCommandLine()));
        }

        // if foreground, we redirect output
        if ($input->getOption('foreground')) {
            $process->run(function ($type, $buffer) use ($output) {
                $output->write($buffer);
            });
        }
        // else we recompose and display the worker id
        else {
            $process->run();
            $pid = \trim($process->getOutput());
            if (function_exists('gethostname')) {
                $hostname = gethostname();
            } else {
                $hostname = php_uname('n');
            }
            if (!$input->getOption('quiet')) {
                $output->writeln(\sprintf('<info>Worker started</info> %s:%s:%s', $hostname, $pid, $input->getArgument('queues')));
            }
        }
    }
}
