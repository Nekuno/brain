<?php
namespace ApiConsumer\Event;

final class TokenEvents
{
    /**
     * The token.refreshed event is thrown each time an oauth token is successfully
     * refreshed in the system.
     *
     * The event listener receives an
     * ApiConsumer\Event\FilterTokenEvent instance.
     *
     * @var string
     */
    const TOKEN_REFRESHED = 'token.refreshed';
}