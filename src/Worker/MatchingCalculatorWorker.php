<?php


namespace Worker;

use Doctrine\DBAL\Connection;
use Event\UserStatusChangedEvent;
use Model\Neo4j\Neo4jException;
use Model\User\Matching\MatchingModel;
use Model\User\Similarity\SimilarityModel;
use Model\UserModel;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Class MatchingCalculatorWorker
 * @package Worker
 */
class MatchingCalculatorWorker extends LoggerAwareWorker implements RabbitMQConsumerInterface
{

    const TRIGGER_PERIODIC = 'periodic';
    /**
     * @var AMQPChannel
     */
    protected $channel;
    /**
     * @var UserModel
     */
    protected $userModel;
    /**
     * @var MatchingModel
     */
    protected $matchingModel;
    /**
     * @var SimilarityModel
     */
    protected $similarityModel;
    /**
     * @var Connection
     */
    protected $connectionSocial;
    /**
     * @var Connection
     */
    protected $connectionBrain;
    /**
     * @var EventDispatcher
     */
    protected $dispatcher;

    public function __construct(AMQPChannel $channel, UserModel $userModel, MatchingModel $matchingModel, SimilarityModel $similarityModel, Connection $connectionSocial, Connection $connectionBrain, EventDispatcher $dispatcher)
    {

        $this->channel = $channel;
        $this->userModel = $userModel;
        $this->matchingModel = $matchingModel;
        $this->similarityModel = $similarityModel;
        $this->connectionSocial = $connectionSocial;
        $this->connectionBrain = $connectionBrain;
        $this->dispatcher = $dispatcher;
    }

    /**
     * { @inheritdoc }
     */
    public function consume()
    {

        $exchangeName = 'brain.topic';
        $exchangeType = 'topic';
        $topic = 'brain.matching.*';
        $queueName = 'brain.matching';

        $this->channel->exchange_declare($exchangeName, $exchangeType, false, true, false);
        $this->channel->queue_declare($queueName, false, true, false, false);
        $this->channel->queue_bind($queueName, $exchangeName, $topic);
        $this->channel->basic_qos(null, 1, null);
        $this->channel->basic_consume($queueName, '', false, false, false, false, array($this, 'callback'));

        while (count($this->channel->callbacks)) {
            $this->channel->wait();
        }
    }

    /**
     * { @inheritdoc }
     */
    public function callback(AMQPMessage $message)
    {

        // Verify mysql connections are alive
        if ($this->connectionSocial->ping() === false) {
            $this->connectionSocial->close();
            $this->connectionSocial->connect();
        }

        if ($this->connectionBrain->ping() === false) {
            $this->connectionBrain->close();
            $this->connectionBrain->connect();
        }

        $data = json_decode($message->body, true);

        $trigger = $this->getTrigger($message);

        switch ($trigger) {
            case 'content_rated':
            case 'process_finished':

                $userA = $data['userId'];
                $this->logger->notice(sprintf('[%s] Calculating matching by trigger "%s" for user "%s"', date('Y-m-d H:i:s'), $trigger, $userA));

                try {
                    $status = $this->userModel->calculateStatus($userA);
                    $this->logger->notice(sprintf('Calculating user "%s" new status: "%s"', $userA, $status->getStatus()));
                    if ($status->getStatusChanged()) {
                        $userStatusChangedEvent = new UserStatusChangedEvent($userA, $status->getStatus());
                        $this->dispatcher->dispatch(\AppEvents::USER_STATUS_CHANGED, $userStatusChangedEvent);
                    }
                    $usersWithSameContent = $this->userModel->getByCommonLinksWithUser($userA);

                    foreach ($usersWithSameContent as $currentUser) {
                        $userB = $currentUser['qnoow_id'];
                        $similarity = $this->similarityModel->calculateSimilarityByInterests($userA, $userB);
                        $this->logger->info(sprintf('   Similarity by interests between users %d - %d: %s', $userA, $userB, $similarity));
                    }
                } catch (\Exception $e) {
                    $this->logger->error(sprintf('Worker: Error calculating similarity for user %d with message %s on file %s, line %d', $userA, $e->getMessage(), $e->getFile(), $e->getLine()));
                    if ($e instanceof Neo4jException) {
                        $this->logger->error(sprintf('Query: %s' . "\n" . 'Data: %s', $e->getQuery(), print_r($e->getData(), true)));
                    }
                }
                break;
            case 'question_answered':

                $userA = $data['userId'];
                $questionId = $data['question_id'];
                $this->logger->notice(sprintf('[%s] Calculating matching by trigger "%s" for user "%s" and question "%s"', date('Y-m-d H:i:s'), $trigger, $userA, $questionId));

                try {
                    $status = $this->userModel->calculateStatus($userA);
                    $this->logger->notice(sprintf('Calculating user "%s" new status: "%s"', $userA, $status->getStatus()));
                    if ($status->getStatusChanged()) {
                        $userStatusChangedEvent = new UserStatusChangedEvent($userA, $status->getStatus());
                        $this->dispatcher->dispatch(\AppEvents::USER_STATUS_CHANGED, $userStatusChangedEvent);
                    }
                    $usersAnsweredQuestion = $this->userModel->getByQuestionAnswered($questionId);
                    foreach ($usersAnsweredQuestion as $currentUser) {

                        $userB = $currentUser['qnoow_id'];
                        if ($userA <> $userB) {
                            $similarity = $this->similarityModel->calculateSimilarityByQuestions($userA, $userB);
                            $matching = $this->matchingModel->calculateMatchingBetweenTwoUsersBasedOnAnswers($userA, $userB);
                            $this->logger->info(sprintf('   Similarity by questions between users %d - %d: %s', $userA, $userB, $similarity));
                            $this->logger->info(sprintf('   Matching by questions between users %d - %d: %s', $userA, $userB, $matching));
                        }
                    }

                } catch (\Exception $e) {
                    $this->logger->error(sprintf('Worker: Error calculating matching and similarity for user %d with message %s on file %s, line %d', $userA, $e->getMessage(), $e->getFile(), $e->getLine()));
                    if ($e instanceof Neo4jException) {
                        $this->logger->error(sprintf('Query: %s' . "\n" . 'Data: %s', $e->getQuery(), print_r($e->getData(), true)));
                    }
                }
                break;
            case 'matching_expired':

                $matchingType = $data['matching_type'];
                $user1 = $data['user_1_id'];
                $user2 = $data['user_2_id'];
                $this->logger->notice(sprintf('[%s] Calculating matching by trigger "%s" for users %d - %d', date('Y-m-d H:i:s'), $trigger, $user1, $user2));

                try {
                    switch ($matchingType) {
                        case 'content':
                            $similarity = $this->similarityModel->getSimilarity($user1, $user2);
                            $this->logger->info(sprintf('   Similarity between users %d - %d: %s', $user1, $user2, $similarity['similarity']));
                            break;
                        case 'answer':
                            $matching = $this->matchingModel->calculateMatchingBetweenTwoUsersBasedOnAnswers($user1, $user2);
                            $this->logger->info(sprintf('   Matching by questions between users %d - %d: %s', $user1, $user2, $matching));
                            break;
                    }
                } catch (\Exception $e) {
                    $this->logger->error(sprintf('Worker: Error calculating matching between user %d and user %d with message %s on file %s, line %d', $user1, $user2, $e->getMessage(), $e->getFile(), $e->getLine()));
                    if ($e instanceof Neo4jException) {
                        $this->logger->error(sprintf('Query: %s' . "\n" . 'Data: %s', $e->getQuery(), print_r($e->getData(), true)));
                    }
                }
                break;
            case $this:: TRIGGER_PERIODIC:
                $user1 = $data['user_1_id'];
                $user2 = $data['user_2_id'];
                $this->logger->notice(sprintf('[%s] Calculating matching by trigger "%s" for users %d - %d', date('Y-m-d H:i:s'), $trigger, $user1, $user2));

                try {
                    $similarity = $this->similarityModel->getSimilarity($user1, $user2);
                    $matching = $this->matchingModel->calculateMatchingBetweenTwoUsersBasedOnAnswers($user1, $user2);
                    $this->logger->info(sprintf('   Similarity between users %d - %d: %s', $user1, $user2, $similarity['similarity']));
                    $this->logger->info(sprintf('   Matching by questions between users %d - %d: %s', $user1, $user2, $matching));
                } catch (\Exception $e) {
                    $this->logger->error(sprintf('Worker: Error calculating similarity and matching between user %d and user %d with message %s on file %s, line %d', $user1, $user2, $e->getMessage(), $e->getFile(), $e->getLine()));
                    if ($e instanceof Neo4jException) {
                        $this->logger->error(sprintf('Query: %s' . "\n" . 'Data: %s', $e->getQuery(), print_r($e->getData(), true)));
                    }
                }
                break;
            default;
                throw new \Exception('Invalid matching calculation trigger');
        }

        $message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);

        $this->memory();
    }

}
