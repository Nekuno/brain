<?php

namespace Console\Command;

use Console\ApplicationAwareCommand;
use Psr\Log\LoggerInterface;
use Service\ChatMessageNotifications;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

class SwiftMailerChatSendCommand extends ApplicationAwareCommand
{
    protected static $defaultName = 'swiftmailer:chat:send';

    protected $chatMessageNotifications;

    protected $mailerSpool;

    protected $mailerTransport;


    public function __construct(LoggerInterface $logger, ChatMessageNotifications $chatMessageNotifications, \Swift_Spool $mailerSpool, \Swift_Transport $mailerTransport)
    {
        parent::__construct($logger);
        $this->chatMessageNotifications = $chatMessageNotifications;
        $this->mailerSpool = $mailerSpool;
        $this->mailerTransport = $mailerTransport;
    }

    protected function configure()
    {
        $this
            ->setDescription('Send chat notifications (unread messages since last 24h)')
            ->addOption('limit', 'lim', InputOption::VALUE_OPTIONAL, 'Notifications limit', 99999);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $limit = $input->getOption('limit');

        if ($limit === 0) {
            $limit = 99999;
        }

        if (!is_int($limit)) {
            $output->writeln(sprintf('Limit must be an integer, %s given.', gettype($limit)));

            return;
        }

        try {

            $this->chatMessageNotifications->sendUnreadChatMessages($limit, $output, $this);

            $style = new OutputFormatterStyle('green', 'black', array('bold', 'blink'));
            $output->getFormatter()->setStyle('success', $style);
            $output->writeln('<success>SUCCESS</success>');

        } catch (\Exception $e) {

            $style = new OutputFormatterStyle('red', 'black', array('bold', 'blink'));
            $output->getFormatter()->setStyle('error', $style);
            $output->writeln('<error>Error trying to send emails: ' . $e->getMessage() . '</error>');
            $output->writeln('<error>FAIL</error>');
        }

        $this->mailerSpool->flushQueue($this->mailerTransport);
        $output->writeln('Spool sent.');
        $output->writeln('Done.');

    }

}
