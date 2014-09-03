<?php


namespace EventListener;

use Doctrine\ORM\EntityManager;
use Event\StatusEvent;
use Model\Entity\UserDataStatus;
use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class StatusSubscriber implements EventSubscriberInterface
{

    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var AMQPConnection
     */
    protected $connection;

    public function __construct(EntityManager $entityManager, AMQPConnection $connection)
    {

        $this->entityManager = $entityManager;
        $this->connection = $connection;
    }

    /**
     * { @inheritdoc }
     */
    public static function getSubscribedEvents()
    {

        return array(
            \StatusEvents::USER_DATA_FETCHING_START => array('onUserDataFetchStart'),
            \StatusEvents::USER_DATA_FETCHING_FINISH => array('onUserDataFetchFinish'),
            \StatusEvents::USER_DATA_PROCESS_START => array('onUserDataProcessStart'),
            \StatusEvents::USER_DATA_PROCESS_FINISH => array('onUserDataProcessFinish'),
        );
    }

    public function onUserDataFetchStart(StatusEvent $event)
    {

        $status = $this->getStatus($event);

        $status->setFetched(false);

        $this->saveStatus($status);
    }

    /**
     * @param StatusEvent $event
     * @return \Model\Entity\UserDataStatus
     */
    public function getStatus(StatusEvent $event)
    {

        $user = $event->getUser();
        $resourceOwner = $event->getResourceOwner();

        $repository = $this->entityManager->getRepository('\Model\Entity\UserDataStatus');
        $status = $repository->findOneBy(array('userId' => $user['id'], 'resourceOwner' => $resourceOwner));

        if (!$status) {
            $status = new UserDataStatus();
            $status->setUser($user['id']);
            $status->setResourceOwner($resourceOwner);
        }

        return $status;
    }

    /**
     * @param $status
     */
    public function saveStatus($status)
    {

        $this->entityManager->persist($status);
        $this->entityManager->flush();
    }

    public function onUserDataFetchFinish(StatusEvent $event)
    {

        $status = $this->getStatus($event);

        $status->setFetched(true);

        $this->saveStatus($status);

        $this->enqueueMatchingCalculation($event);
    }

    /**
     * @param StatusEvent $event
     */
    private function enqueueMatchingCalculation(StatusEvent $event)
    {

        $user = $event->getUser();
        $resourceOwner = $event->getResourceOwner();

        $data = array(
            'userId' => $user['id'],
            'resourceOwner' => $resourceOwner,
            'type' => 'process_finished',
        );

        $message = new AMQPMessage(json_encode($data, JSON_UNESCAPED_UNICODE));

        $exchangeName = 'brain.topic';
        $exchangeType = 'topic';
        $routingKey = 'brain.matching.process';
        $topic = 'brain.matching.*';
        $queueName = 'brain.matching';

        $channel = $this->connection->channel();
        $channel->exchange_declare($exchangeName, $exchangeType, false, true, false);
        $channel->queue_declare($queueName, false, true, false, false);
        $channel->queue_bind($queueName, $exchangeName, $topic);
        $channel->basic_publish($message, $exchangeName, $routingKey);
    }

    public function onUserDataProcessStart(StatusEvent $event)
    {

        $status = $this->getStatus($event);

        $status->setProcessed(false);

        $this->saveStatus($status);
    }

    public function onUserDataProcessFinish(StatusEvent $event)
    {

        $status = $this->getStatus($event);

        $status->setProcessed(true);

        $this->saveStatus($status);
    }

}
