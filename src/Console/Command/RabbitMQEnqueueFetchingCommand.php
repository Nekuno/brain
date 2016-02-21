<?php

namespace Console\Command;

use Console\ApplicationAwareCommand;
use Manager\UserManager;
use Model\User;
use Service\AMQPManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RabbitMQEnqueueFetchingCommand extends ApplicationAwareCommand
{

    protected function configure()
    {

        $this->setName('rabbitmq:enqueue:fetching')
            ->setDescription('Enqueues a fetching task for all users')
            ->addOption(
                'user',
                null,
                InputOption::VALUE_OPTIONAL,
                'If set, only will enqueue fetching process for given user'
            )->addOption(
                'resource',
                null,
                InputOption::VALUE_OPTIONAL,
                'If set, only will enqueue fetching process for given resource owner'
            )->addOption(
                'public',
                null,
                InputOption::VALUE_NONE,
                'Fetch as Nekuno instead of as the user'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $userId = $input->getOption('user');
        $resourceOwner = $input->getOption('resource');
        $public = $input->getOption('public', false);

        $availableResourceOwners = $this->app['api_consumer.config']['resource_owner'];
        if ($resourceOwner && !array_key_exists($resourceOwner, $availableResourceOwners)) {
            $output->writeln(sprintf('%s is not an valid resource owner', $resourceOwner));
            exit;
        }

        /* @var $usersModel UserManager */
        $usersModel = $this->app['users.manager'];

        if ($userId == null) {
            $users = $usersModel->getAll();
        } else {
            $users = array($usersModel->getById($userId, true));
        }

        if (empty($users)) {
            $output->writeln(sprintf('Not user found with %d and resource %s connected', $userId, $resourceOwner));
            exit;
        }

        if ($resourceOwner == null) {
            $resourceOwners = array();
            foreach ($availableResourceOwners as $name => $config) {
                $resourceOwners[] = $name;
            }
        } else {
            $resourceOwners[] = $resourceOwner;
        }

        /* @var $amqpManager AMQPManager */
        $amqpManager = $this->app['amqpManager.service'];

        foreach ($users as $user) {
            /* @var $user User */
            foreach ($resourceOwners as $name) {
                $data = array(
                    'userId' => $user->getId(),
                    'resourceOwner' => $name,
                    'public' => $public,
                );
                $amqpManager->enqueueMessage($data, 'brain.fetching.links');
            }
        }
    }

}