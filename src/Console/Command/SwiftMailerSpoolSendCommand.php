<?php

namespace Console\Command;

use Silex\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SwiftMailerSpoolSendCommand extends ApplicationAwareCommand
{

    protected function configure()
    {
        $this->setName('swiftmailer:spool:send')
            ->setDescription('Send spool messages')
            ->addOption('limit', 'lim', InputOption::VALUE_OPTIONAL, 'Notifications limit', 99999);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $limit = $input->getOption('limit');

        if($limit === 0)
        {
            $limit = 99999;
        }


        if (! is_int($limit)) {
            $output->writeln(sprintf('Limit must be an integer, %s given.', gettype($limit)));
            return;
        }

        if ($this->app['mailer.initialized']) {
            $this->app['swiftmailer.spooltransport']->getSpool()->flushQueue($this->app['swiftmailer.transport']);
        }

        $output->writeln('Spool sent.');

    }

}
