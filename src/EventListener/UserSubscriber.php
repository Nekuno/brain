<?php

namespace EventListener;

use Event\GroupEvent;
use Event\UserEvent;
use Model\User\Thread\ThreadManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class UserSubscriber implements EventSubscriberInterface
{
    /**
     * @var ThreadManager
     */
    protected $threadManager;

    public function __construct(ThreadManager $threadManager)
    {
        $this->threadManager = $threadManager;
    }

    public static function getSubscribedEvents()
    {
        return array(
            \AppEvents::USER_CREATED => array('onUserCreated'),
            \AppEvents::GROUP_ADDED => array('onGroupAdded'),
        );
    }

    public function onUserCreated(UserEvent $event)
    {
        $user = $event->getUser();
        //$threads = $this->threadManager->getDefaultThreads($user);

        //$createdThreads = $this->threadManager->createBatchForUser($user->getId(), $threads);
        // TODO: Enqueue thread recommendation
    }

    public function onGroupAdded(GroupEvent $groupEvent)
    {
        $user = $groupEvent->getUser();
        $group = $groupEvent->getGroup();

        $this->threadManager->create($user->getId(), $this->threadManager->getGroupThreadData($group, $user->getId()));
    }
}