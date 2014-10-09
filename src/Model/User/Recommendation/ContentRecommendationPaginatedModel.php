<?php

namespace Model\User\Recommendation;

use Paginator\PaginatedInterface;
use Model\User\MatchingModelOld;

use Everyman\Neo4j\Client;
use Everyman\Neo4j\Cypher\Query;

class ContentRecommendationPaginatedModel implements PaginatedInterface
{
    /**
     * @var \Everyman\Neo4j\Client
     */
    protected $client;

    /**
     * @var \Model\User\MatchingModel
     */
    protected $matchingModel;

    /**
     * @param \Everyman\Neo4j\Client $client
     * @param \Model\User\MatchingModel $matchingModel
     */
    public function __construct(Client $client, MatchingModelOld $matchingModel)
    {
        $this->client = $client;
        $this->matchingModel = $matchingModel;
    }

    /**
     * Hook point for validating the query.
     * @param array $filters
     * @return boolean
     */
    public function validateFilters(array $filters)
    {
        return isset($filters['id']);
    }

    /**
     * Slices the query according to $offset, and $limit.
     * @param array $filters
     * @param int $offset
     * @param int $limit
     * @throws \Exception
     * @return array
     */
    public function slice(array $filters, $offset, $limit)
    {
        $id = $filters['id'];
        $response = array();

        $params = array(
            'UserId' => (integer)$id,
            'offset' => (integer)$offset,
            'limit' => (integer)$limit
        );

        $tagQuery = '';
        if (isset($filters['tag'])) {
            $tagQuery = "
                MATCH
                (content)-[:TAGGED]->(filterTag:Tag)
                WHERE filterTag.name = {tag}
            ";
            $params['tag'] = $filters['tag'];
        }

        $typeQuery = 'has(match.matching_questions)';
        if($this->matchingModel->getPreferredMatchingType($id) == MatchingModelOld::PREFERRED_MATCHING_CONTENT) {
            $typeQuery = "has(match.matching_content)";
        }

        $query = "
            MATCH
            (user:User {qnoow_id: {UserId}})-[match:MATCHES]-(matching_users:User)
            WHERE
        ";
        $query .= $typeQuery;
        $query .= "
            MATCH
            (matching_users)-[:LIKES]->(content:Link)
            WHERE
            NOT (user)-[:LIKES]->(content)
        ";
        $query .= $tagQuery;
        $query .= "
            OPTIONAL MATCH
            (content)-[:TAGGED]->(tag:Tag)
            RETURN
            content,
            match.matching_content AS match,
            matching_users AS via,
            collect(distinct tag.name) as tags
            ORDER BY
            match
            SKIP {offset}
            LIMIT {limit};
        ";

        //Create the Neo4j query object
        $contentQuery = new Query(
            $this->client,
            $query,
            $params
        );

        //Execute query
        try {
            $result = $contentQuery->getResultSet();

            foreach ($result as $row) {
                $content = array();
                $content['url'] = $row['content']->getProperty('url');
                $content['title'] = $row['content']->getProperty('title');
                $content['description'] = $row['content']->getProperty('description');
                foreach ($row['tags'] as $tag) {
                    $content['tags'][] = $tag;
                }
                $content['match'] = $row['match'];
                $content['via']['qnoow_id'] = $row['via']->getProperty('qnoow_id');
                $content['via']['name'] = $row['via']->getProperty('username');

                $response[] = $content;
            }

        } catch (\Exception $e) {
            throw $e;
        }

        return $response;
    }

    /**
     * Counts the total results from queryset.
     * @param array $filters
     * @throws \Exception
     * @return int
     */
    public function countTotal(array $filters)
    {
        $id = $filters['id'];
        $count = 0;

        $params = array(
            'UserId' => (integer)$id,
        );

        $tagQuery = '';
        if (isset($filters['tag'])) {
            $tagQuery = "
                MATCH
                (content)-[:TAGGED]->(filterTag:Tag)
                WHERE filterTag.name = {tag}
            ";
            $params['tag'] = $filters['tag'];
        }

        $typeQuery = 'has(match.matching_questions)';
        if($this->matchingModel->getPreferredMatchingType($id) == MatchingModelOld::PREFERRED_MATCHING_CONTENT) {
            $typeQuery = "has(match.matching_content)";
        }

        $query = "
            MATCH
            (user:User {qnoow_id: {UserId}})-[match:MATCHES]-(matching_users:User)
            WHERE
        ";
        $query .= $typeQuery;
        $query .= "
            MATCH
            (matching_users)-[r:LIKES]->(content:Link)
            WHERE
            NOT (user)-[:LIKES]->(content)
        ";
        $query .= $tagQuery;
        $query .= "
            RETURN
            count(distinct r) as total;
        ";

        //Create the Neo4j query object
        $contentQuery = new Query(
            $this->client,
            $query,
            $params
        );

        //Execute query
        try {
            $result = $contentQuery->getResultSet();

            foreach ($result as $row) {
                $count = $row['total'];
            }

        } catch (\Exception $e) {
            throw $e;
        }

        return $count;
    }
} 