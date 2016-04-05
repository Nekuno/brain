<?php


namespace ApiConsumer\Fetcher\GetOldTweets;

use ApiConsumer\LinkProcessor\UrlParser\TwitterUrlParser;
use Manager\TweetManager;
use Model\TweetCriteria;
use Model\User\TokensModel;

class GetOldTweets
{
    const MAX_TWEETS = 1000;

    protected $parser;
    protected $tweetManager;

    public function __construct(TwitterUrlParser $parser, TweetManager $tweetManager)
    {
        $this->parser = $parser;
        $this->tweetManager = $tweetManager;
    }

    /**
     * @param $username
     * @param int $maxtweets
     * @param null $since
     * @param null $until
     * @param null $querysearch
     * @return \Model\Tweet[]
     */
    public function fetchTweets($username, $maxtweets = GetOldTweets::MAX_TWEETS, $since = null, $until = null, $querysearch = null)
    {
        $criteria = new TweetCriteria();
        $criteria->setUsername($username);
        $criteria->setMaxTweets($maxtweets);
        $criteria->setSince($since);
        $criteria->setUntil($until);
        $criteria->setQuerySearch($querysearch);

        return $this->tweetManager->getTweets($criteria);
    }

    /**
     * @param array $tweets
     * @return array
     */
    public function getLinksFromTweets(array $tweets)
    {
        $links = array();
        $resource = TokensModel::TWITTER;
        
        foreach ($tweets as $tweet) {
            $text = utf8_encode($tweet['text']);

            $newUrls = $this->parser->extractURLsFromText($text);
            $timestamp = $this->getDate($tweet);

            $errorCharacters = array('&Acirc;', '&acirc;', '&brvbar;', '&nbsp;');
            foreach ($newUrls as $newUrl) {
                
                $newUrl = str_replace($errorCharacters, '', htmlentities($newUrl));
                $newUrl = html_entity_decode($newUrl);
                $links[] = array(
                    'url' => $newUrl,
                    'timestamp' => $timestamp,
                    'resource' => $resource);
            }
        }
        return $links;
    }

    public function needMore(array $tweets)
    {
        if (count($tweets) >= $this::MAX_TWEETS) {
            return true;
        }
        return false;
    }

    public function getMinDate(array $tweets)
    {
        return min(array_map(array($this, 'getDate'), $tweets));
    }

    public function getDate(array $tweet)
    {
        return $tweet['date'];
    }
}