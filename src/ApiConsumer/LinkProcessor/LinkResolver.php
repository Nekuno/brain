<?php

namespace ApiConsumer\LinkProcessor;

use Goutte\Client;

/**
 * @author Juan Luis Martínez <juanlu@comakai.com>
 */
class LinkResolver
{

    /**
     * @var Client
     */
    protected $client;

    /**
     * @param Client $client
     */
    public function __construct(Client $client)
    {

        $this->client = $client;
    }

    /**
     * @param $preprocessedLink PreprocessedLink
     * @return PreprocessedLink
     */
    public function resolve($preprocessedLink)
    {

        try {
            if(!parse_url($preprocessedLink->getFetched(), PHP_URL_HOST)){
                $preprocessedLink->setFetched('http://'.$preprocessedLink->getFetched());
            };

            /* TODO: Remove this quick fix, put here because of Curl not firing error 52 (empty response) */
            $host = parse_url($preprocessedLink->getFetched(), PHP_URL_HOST);
            $firstLetter = substr($host, 0, 1);
            if (strtoupper($firstLetter) == $firstLetter || $host == 'imprint.printmag.com'){
                throw new \Exception('This url would not return data');
            }
            /* End of quick fix */

            $this->client->getHistory()->clear();
            $crawler = $this->client->request('GET', $preprocessedLink->getFetched());

        } catch (\Exception $e) {
            $preprocessedLink->addException($e);
            return $preprocessedLink;
        }

        $preprocessedLink->setStatusCode($this->client->getResponse()->getStatus());
        $preprocessedLink->setHistory($this->client->getHistory());

        $uri = $this->client->getRequest()->getUri();

        if ($preprocessedLink->getStatusCode() == 200 && isset($crawler)) {

            try {
                $canonical = $crawler->filterXPath('//link[@rel="canonical"]')->attr('href');
            } catch (\InvalidArgumentException $e) {
                $canonical = $uri;
            }

            if ($canonical && $uri !== $canonical) {

                $canonical = $this->verifyCanonical($canonical, $uri);
            }

            $preprocessedLink->setCanonical($canonical);
        }

        return $preprocessedLink;

    }

    protected function verifyCanonical($canonical, $uri)
    {
        $parsedCanonical = parse_url($canonical);

        if (!isset($parsedCanonical['scheme']) && !isset($parsedCanonical['host'])) {
            $parsedUri = parse_url($uri);
            $parsedCanonical['scheme'] = $parsedUri['scheme'];
            $parsedCanonical['host'] = $parsedUri['host'];
            $canonical = http_build_url($parsedCanonical);
        }

        return $canonical;
    }
} 