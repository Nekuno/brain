<?php

namespace Service\LookUp;

use GuzzleHttp\Client;
use Entity\LookUpData;
use Model\Exception\ErrorList;
use Model\Exception\ValidationException;
use Service\LookUp\LookUpInterface\LookUpInterface;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Class LookUp
 * @package Service
 */
abstract class LookUp implements LookUpInterface
{
    const TWITTER_BASE_URL = 'https://twitter.com/';
    const FACEBOOK_BASE_URL = 'https://facebook.com/';

    protected $client;
    protected $apiKey;
    protected $apiUrl;
    protected $urlGenerator;

    function __construct($apiUrl, $apiKey, UrlGeneratorInterface $urlGenerator)
    {
        $this->client = new Client();
        $this->apiKey = $apiKey;
        $this->apiUrl = $apiUrl;
        $this->urlGenerator = $urlGenerator;
    }

    public function get($value, $lookUpType, $id = null)
    {
        $this->validateType($lookUpType);
        $customType = $this->getType($lookUpType);

        $customValue = $this->getValue($lookUpType, $value);
        $this->validateValue($customType, $customValue);

        $response = $this->getFromClient($this->client, $customType, $customValue, $this->apiKey, $id);

        return $this->processData($response);
    }

    public function getProcessedResponse($response)
    {
        return $this->processData($response);
    }

    protected function validateType($lookUpType)
    {
        if (!in_array($lookUpType, $this->getBaseTypes())) {
            $errorList = new ErrorList();
            $errorList->addError('lookUp', $lookUpType . ' type is not valid');
            throw new ValidationException($errorList);
        }
    }

    abstract protected function getType($lookUpType);

    abstract protected function validateValue($lookUpType, $value);

    abstract protected function getValue($lookUpType, $value);

    protected function getFromClient(Client $client, $lookUpType, $value, $apiKey, $webHookId)
    {
        try {
            if ($webHookId) {
                $route = $this->urlGenerator->generate('setLookUpFromWebHook', array(), UrlGenerator::ABSOLUTE_URL);
                $query = array(
                    $lookUpType => $value,
                    'apiKey' => $apiKey,
                    'webHookUrl' => urlencode($route),
                    'webHookId' => $webHookId,
                );
            } else {
                $query = array(
                    $lookUpType => $value,
                    'apiKey' => $apiKey,
                );
            }

            $response = $client->request('GET', $this->apiUrl, array('query' => $query));
            if ($response->getStatusCode() == 202) {
                // TODO: Should get data from web hook
                //throw new Exception('Resource not available yet. Wait 2 minutes and execute the command again.', 202);
            }
            if ($response->getStatusCode() == 200) {
                return json_decode($response->getBody()->getContents(), true);
            }
        } catch (\Exception $e) {
            // TODO: Refuse exceptions by now
            return array();
            //throw new Exception($e->getMessage(), 404);
        }

        return array();
    }

    abstract protected function processData($response);

    protected function getBaseTypes()
    {
        return array(
            LookUpData::LOOKED_UP_BY_EMAIL,
            LookUpData::LOOKED_UP_BY_TWITTER_USERNAME,
            LookUpData::LOOKED_UP_BY_FACEBOOK_USERNAME,
        );
    }

    abstract protected function processSocialData($response);

    abstract protected function getTypes();
}