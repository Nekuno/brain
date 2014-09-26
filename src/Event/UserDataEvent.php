<?php


namespace Event;

use Symfony\Component\EventDispatcher\Event;

class UserDataEvent extends Event
{

    protected $user;

    protected $resourceOwner;

    public function __construct($user, $resourceOwner = null)
    {

        $this->user = $user;
        $this->resourceOwner = $resourceOwner;
    }

    /**
     * @return mixed
     */
    public function getResourceOwner()
    {

        return $this->resourceOwner;
    }

    /**
     * @return mixed
     */
    public function getUser()
    {

        return $this->user;
    }
}