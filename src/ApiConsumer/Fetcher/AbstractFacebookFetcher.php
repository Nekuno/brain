<?php

namespace ApiConsumer\Fetcher;

abstract class AbstractFacebookFetcher extends BasicPaginationFetcher
{
    protected $paginationField = 'after';

    protected $pageLength = 20;

    protected $paginationId = null;

    /**
     * @inheritdoc
     */
    public function getUrl()
    {
        return $this->user['facebookID'];
    }

    /**
     * @inheritdoc
     */
    protected function getQuery()
    {
        return array(
            'limit' => $this->pageLength,
        );
    }

    /**
     * @inheritdoc
     */
    protected function getItemsFromResponse($response)
    {
        return $response['data'] ?: array();
    }

    /**
     * @inheritdoc
     */
    protected function getPaginationIdFromResponse($response)
    {
        $paginationId = null;

        if (isset($response['paging']['cursors']['after'])) {
            $paginationId = $response['paging']['cursors']['after'];
        }

        if ($this->paginationId === $paginationId) {
            return null;
        }

        $this->paginationId = $paginationId;

        return $paginationId;
    }

    /**
     * @inheritdoc
     */
    protected function parseLinks(array $rawFeed)
    {
        $parsed = array();

        foreach ($rawFeed as $item) {
            $url = $item['link'];
            $id = $item['id'];
            $parsed[] = $this->getLinkArrayFromUrl($url, $id);

            if (isset($item['website'])) {
                $website = $item['website'];

                $website = str_replace('\n', ' ', $website);
                $website = str_replace(', ', ' ', $website);

                preg_match_all('/(https?\:\/\/[^\" ]+)|(www\.[a-z][-a-z0-9]+\.[a-z]+(\.[a-z]{2,2})?)/i', $website, $matches);
                $websiteUrlsArray = $matches[0];

                $counter = 1;
                foreach ($websiteUrlsArray as $websiteUrl) {
                    if (substr($websiteUrl,0,3) == 'www') {
                        $websiteUrl = 'http://' . $websiteUrl;
                    }
                    $parsed[] = $this->getLinkArrayFromUrl(trim($websiteUrl), $id.'-'.$counter);
                    $counter++;
                }
            }
        }

        return $parsed;
    }

    /**
     * Get the link array from a url and an id
     *
     * @param $url
     * @param $id
     * @return array
     */
    private function getLinkArrayFromUrl($url, $id)
    {
        $link = array();

        $parts = parse_url($url);
        $link['url'] = !isset($parts['host']) && isset($parts['path']) ? 'https://www.facebook.com' . $parts['path'] : $url;
        $link['title'] = null;
        $link['description'] = null;
        $link['resourceItemId'] = $id;
        $link['resource'] = 'facebook';

        return $link;
    }
}