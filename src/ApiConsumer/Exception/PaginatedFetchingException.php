<?php
/**
 * Created by PhpStorm.
 * User: yawmoght
 * Date: 03/02/2016
 * Time: 14:58
 */

namespace ApiConsumer\Exception;


class PaginatedFetchingException extends \Exception
{
    protected $links;

    protected $originalException;

    /**
     * PaginatedFetchingException constructor.
     * @param array $links
     * @param $originalException
     */
    public function __construct(array $links, \Exception $originalException)
    {
        $this->links = $links;
        $this->originalException = $originalException;
    }

    /**
     * @return string
     */
    public function getLinks()
    {
        return $this->links;
    }

    /**
     * @param string $links
     */
    public function setLinks($links)
    {
        $this->links = $links;
    }

    /**
     * @return \Exception
     */
    public function getOriginalException()
    {
        return $this->originalException;
    }

    /**
     * @param \Exception $originalException
     */
    public function setOriginalException($originalException)
    {
        $this->originalException = $originalException;
    }

}