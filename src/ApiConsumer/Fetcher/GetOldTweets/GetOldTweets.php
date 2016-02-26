<?php


namespace ApiConsumer\Fetcher\GetOldTweets;


use ApiConsumer\LinkProcessor\UrlParser\TwitterUrlParser;
use Model\User\TokensModel;
use Service\AMQPManager;

class GetOldTweets
{
    const MAX_TWEETS = 1000;

    protected $path;
    protected $parser;
    protected $AMQPManager;

    public function __construct(TwitterUrlParser $parser, AMQPManager $AMQPManager)
    {
        $this->path = __DIR__ . '/got.jar';
        $this->parser = $parser;
        $this->AMQPManager = $AMQPManager;
    }


    public function execute($username, $maxtweets = GetOldTweets::MAX_TWEETS, $since = null, $until = null, $querysearch = null)
    {
        $options = [];

        if ($username) {
            $options[] = ' username=' . $username;
        }
        if ($maxtweets) {
            $options[] = ' maxtweets=' . $maxtweets;
        }
        if ($since) {
            $options[] = ' since=' . $since;
        }
        if ($until) {
            $options[] = ' until=' . $until;
        }
        if ($querysearch) {
            $options[] = ' querysearch="' . $querysearch . '"';
        }

        exec('java -jar ' . $this->path . implode(' ', $options));

    }

    public function loadTweets()
    {
        $tweets = array();
        $first = true;

        if (($handle = fopen($this->getOutputFilePath(), 'r')) !== false) {

            while (($data = fgetcsv($handle, 0, ';')) !== false) {

                if ($first) {
                    $first = false;
                    continue;
                }

                $tweets[] = array('text' => $data[4], 'date' => (new \DateTime($data[1]))->getTimestamp());

            }
            fclose($handle);
        }

        return $tweets;
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

    private function getOutputFilePath()
    {
        return 'output_got.csv';
    }
}