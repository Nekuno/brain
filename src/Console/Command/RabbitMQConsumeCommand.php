<?php

namespace Console\Command;

use ApiConsumer\Auth\UserProviderInterface;
use ApiConsumer\EventListener\FetchLinksInstantSubscriber;
use ApiConsumer\EventListener\FetchLinksSubscriber;
use ApiConsumer\Fetcher\FetcherService;
use Console\ApplicationAwareCommand;
use EventListener\UserStatusSubscriber;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Silex\Application;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Worker\LinkProcessorWorker;
use Worker\MatchingCalculatorWorker;

/**
 * Class RabbitMQConsumeCommand
 *
 * @package Console\Command
 */
class RabbitMQConsumeCommand extends ApplicationAwareCommand
{

    protected $validConsumers = array(
        'fetching',
        'matching',
    );

    protected function configure()
    {

        $this->setName('rabbitmq:consume')
            ->setDescription(sprintf('Starts a RabbitMQ consumer by name ("%s")', implode('", "', $this->validConsumers)))
            ->addArgument('consumer', InputArgument::OPTIONAL, 'Consumer to start up', 'fetching');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $consumer = $input->getArgument('consumer');

        if (!in_array($consumer, $this->validConsumers)) {
            throw new \Exception(sprintf('Invalid "%s" consumer name, valid consumers "%s".', $consumer, implode('", "', $this->validConsumers)));
        }

        /* @var $logger LoggerInterface */
        $logger = $this->app['monolog'];
        /* @var $fetcher FetcherService */
        $fetcher = $this->app['api_consumer.fetcher'];

        if (OutputInterface::VERBOSITY_NORMAL < $output->getVerbosity()) {
            $logger = new ConsoleLogger($output, array(LogLevel::NOTICE => OutputInterface::VERBOSITY_NORMAL));
        }

        $fetchLinksSubscriber = new FetchLinksSubscriber($output);
        $dispatcher = $this->app['dispatcher'];
        /* @var $dispatcher EventDispatcher */
        $dispatcher->addSubscriber($fetchLinksSubscriber);

        $fetcher->setLogger($logger);

        /* @var $connection AMQPStreamConnection */
        $connection = $this->app['amqp'];

        $output->writeln(sprintf('Starting %s consumer', $consumer));
        switch ($consumer) {
            case 'fetching':
                $fetchLinksInstantSubscriber = new FetchLinksInstantSubscriber($this->app['guzzle.client'], $this->app['instant.host']);
                $dispatcher->addSubscriber($fetchLinksInstantSubscriber);
                /* @var $channel AMQPChannel */
                $channel = $connection->channel();
                $worker = new LinkProcessorWorker($channel, $fetcher, $this->app['users.tokens.model'], $this->app['dbs']['mysql_social'], $this->app['dbs']['mysql_brain']);
                $worker->setLogger($logger);
                $logger->notice('Processing fetching queue');
                $worker->consume();
                $channel->close();
                break;
            case 'matching':
                /* @var $channel AMQPChannel */
                $channel = $connection->channel();
                $userStatusSubscriber = new UserStatusSubscriber($this->app['instant.client']);
                $dispatcher->addSubscriber($userStatusSubscriber);
                $worker = new MatchingCalculatorWorker($channel, $this->app['users.model'], $this->app['users.matching.model'], $this->app['users.similarity.model'], $this->app['dbs']['mysql_social'], $this->app['dbs']['mysql_brain'], $dispatcher);
                $worker->setLogger($logger);
                $logger->notice('Processing matching queue');
                $worker->consume();
                $channel->close();
                break;
        }

        $connection->close();
    }
}
