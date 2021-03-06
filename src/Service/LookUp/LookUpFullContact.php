<?php

namespace Service\LookUp;

use Entity\LookUpData;
use GuzzleHttp\Client;
use Model\Exception\ErrorList;
use Model\Exception\ValidationException;
use Symfony\Component\Routing\Generator\UrlGenerator;

class LookUpFullContact extends LookUp
{
    const EMAIL_TYPE = 'email';
    const TWITTER_TYPE = 'twitter';
    const FACEBOOK_TYPE = 'facebookUsername';

    protected function getTypes()
    {
        return array(
            self::EMAIL_TYPE,
            self::TWITTER_TYPE,
            self::FACEBOOK_TYPE,
        );
    }

    protected function getType($lookUpType)
    {
        switch ($lookUpType) {
            case LookUpData::LOOKED_UP_BY_EMAIL:
                $fullContactType = LookUpFullContact::EMAIL_TYPE;
                break;
            case LookUpData::LOOKED_UP_BY_TWITTER_USERNAME:
                $fullContactType = LookUpFullContact::TWITTER_TYPE;
                break;
            case LookUpData::LOOKED_UP_BY_FACEBOOK_USERNAME:
                $fullContactType = LookUpFullContact::FACEBOOK_TYPE;
                break;
            default:
                $fullContactType = LookUpFullContact::EMAIL_TYPE;
        }

        return $fullContactType;
    }

    protected function getValue($lookUpType, $value)
    {
        switch ($lookUpType) {
            case LookUpData::LOOKED_UP_BY_EMAIL:
                break;
            case LookUpData::LOOKED_UP_BY_TWITTER_USERNAME:
                break;
            case LookUpData::LOOKED_UP_BY_FACEBOOK_USERNAME:
                break;
        }

        return $value;
    }

    protected function processData($response)
    {
        $data = array();
        if (isset($response['status']) && $response['status'] === 200) {
            if (isset($response['contactInfo'])) {
                if (isset($response['contactInfo']) && is_array($response['contactInfo']) && !empty($response['contactInfo'])) {
                    if (isset($response['contactInfo']['givenName'])) {
                        $data['name'] = str_replace(' ', '', $response['contactInfo']['givenName']);
                    }
                }
                if (isset($response['demographics']) && is_array($response['demographics']) && !empty($response['demographics'])) {
                    if (isset($response['demographics']['gender'])) {
                        $data['gender'] = strtolower($response['demographics']['gender']);
                    }
                    if (isset($response['demographics']['locationDeduced']) && isset($response['demographics']['locationDeduced']['deducedLocation'])) {
                        $data['location'] = $response['demographics']['locationDeduced']['deducedLocation'];
                    }
                }
            }
            $data['response'] = $response;

            $data['socialProfiles'] = $this->processSocialData($response);
        }

        return $data;
    }

    protected function processSocialData($response)
    {
        $socialData = array();
        if (isset($response['status']) && $response['status'] === 200) {
            if (is_array($response) && isset($response['socialProfiles']) && !empty($response['socialProfiles'])) {
                foreach ($response['socialProfiles'] as $socialProfile) {
                    $socialData[strtolower($socialProfile['typeName'])] = $socialProfile['url'];
                }
            }
        }

        return $socialData;
    }

    protected function validateValue($lookUpType, $value)
    {
        $errorList = new ErrorList();
        if ($lookUpType === self::EMAIL_TYPE) {
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errorList->addError('lookUp', $value . ' is not a valid email');
            }
        } elseif ($lookUpType === self::TWITTER_TYPE || $lookUpType === self::FACEBOOK_TYPE) {
            /* TODO: Do not validate username
            if(! preg_match('/^[\w\.-]+$/', $value)) {
                $error = $value . ' is not a valid username';
            }*/
        } else {
            $errorList->addError('lookUp', $lookUpType . ' is not a valid type');
        }

        if ($errorList->hasErrors()) {
            throw new ValidationException($errorList);
        }

        return true;
    }

    protected function getFromClient(Client $client, $lookUpType, $value, $apiKey, $webHookId)
    {
        try {
            if ($webHookId) {
                $route = $this->urlGenerator->generate('setLookUpFromWebHook', array(), UrlGenerator::ABSOLUTE_URL);
                $query = array(
                    $lookUpType => $value,
                    'webHookUrl' => urlencode($route),
                    'webHookId' => $webHookId,
                );
            } else {
                $query = array(
                    $lookUpType => $value,
                );
            }

            $headers = array('X-FullContact-APIKey' => $apiKey);

            $response = $client->request('GET', $this->apiUrl, array('query' => $query, 'headers' => $headers));
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
}