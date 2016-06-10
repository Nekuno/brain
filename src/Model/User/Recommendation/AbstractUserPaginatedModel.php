<?php

namespace Model\User\Recommendation;

use Model\Neo4j\GraphManager;
use Model\User\GhostUser\GhostUserManager;
use Model\User\ProfileFilterModel;
use Paginator\PaginatedInterface;

abstract class AbstractUserPaginatedModel implements PaginatedInterface
{
    /**
     * @var GraphManager
     */
    protected $gm;

    /**
     * @var ProfileFilterModel
     */
    protected $profileFilterModel;

    public function __construct(GraphManager $gm, ProfileFilterModel $profileFilterModel)
    {
        $this->gm = $gm;
        $this->profileFilterModel = $profileFilterModel;
    }

    /**
     * Hook point for validating the query.
     * @param array $filters
     * @return boolean
     */
    public function validateFilters(array $filters)
    {
        $hasId = isset($filters['id']);
        $hasProfileFilters = isset($filters['profileFilters']);

        return $hasId && $hasProfileFilters;
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

        $parameters = array(
            'offset' => (integer)$offset,
            'limit' => (integer)$limit,
            'userId' => (integer)$id
        );

        $orderQuery = '  similarity DESC, matching_questions DESC ';
        if (isset($filters['order']) && $filters['order'] == 'questions') {
            $orderQuery = ' matching_questions DESC, similarity DESC ';
        }

        $filters = $this->profileFilterModel->splitFilters($filters);

        $profileFilters = $this->getProfileFilters($filters['profileFilters']);

        $qb = $this->gm->createQueryBuilder();

        $qb->setParameters($parameters);

        $qb->match('(u:User {qnoow_id: {userId}})-[:MATCHES|SIMILARITY]-(anyUser:User)')
            ->where('u <> anyUser', 'NOT (anyUser:' . GhostUserManager::LABEL_GHOST_USER . ')')
            ->optionalMatch('(u)-[like:LIKES]-(anyUser)')
            ->optionalMatch('(u)-[m:MATCHES]-(anyUser)')
            ->optionalMatch('(u)-[s:SIMILARITY]-(anyUser)')
            ->with(
                'u, anyUser,
                (CASE WHEN like IS NOT NULL THEN 1 ELSE 0 END) AS like,
                (CASE WHEN EXISTS(m.matching_questions) THEN m.matching_questions ELSE 0 END) AS matching_questions,
                (CASE WHEN EXISTS(s.similarity) THEN s.similarity ELSE 0 END) AS similarity'
            )
            ->match('(anyUser)<-[:PROFILE_OF]-(p:Profile)');

        $qb->optionalMatch('(p)-[:LOCATION]->(l:Location)');

        $qb->with('u, anyUser, like, matching_questions, similarity, p, l');
        $qb->where(
            array_merge(
                array('(matching_questions > 0 OR similarity > 0)'),
                $profileFilters['conditions']
            )
        )
            ->with('u', 'anyUser', 'like', 'matching_questions', 'similarity', 'p', 'l');

        foreach ($profileFilters['matches'] as $match) {
            $qb->match($match);
        }

        $qb->returns(
            'DISTINCT anyUser.qnoow_id AS id,
                    anyUser.username AS username,
                    anyUser.picture AS picture,
                    p.birthday AS birthday,
                    l.locality + ", " + l.country AS location,
                    matching_questions,
                    similarity,
                    like'
        )
            ->orderBy($orderQuery)
            ->skip('{ offset }')
            ->limit('{ limit }');

        $query = $qb->getQuery();
        $result = $query->getResultSet();

        foreach ($result as $row) {

            $age = null;
            if ($row['birthday']) {
                $date = new \DateTime($row['birthday']);
                $now = new \DateTime();
                $interval = $now->diff($date);
                $age = $interval->y;
            }

            $user = array(
                'id' => $row['id'],
                'username' => $row['username'],
                'picture' => $row['picture'],
                'matching' => $row['matching_questions'],
                'similarity' => $row['similarity'],
                'age' => $age,
                'location' => $row['location'],
                'like' => $row['like'],
            );

            $response[] = $user;
        }

        return $response;
    }

    /**
     * @param array $filters
     * @return array
     */
    protected function getProfileFilters(array $filters)
    {
        $conditions = array();
        $matches = array();

        $profileFilterMetadata = $this->getProfileFilterMetadata();
        foreach ($profileFilterMetadata as $name => $filter) {
            if (isset($filters[$name])) {
                $value = $filters[$name];
                switch ($filter['type']) {
                    case 'text':
                    case 'textarea':
                        $conditions[] = "p.$name =~ '(?i).*$value.*'";
                        break;
                    case 'integer_range':
                        $min = (integer)$value['min'];
                        $max = (integer)$value['max'];
                        $conditions[] = "($min <= p.$name AND p.$name <= $max)";
                        break;
                    case 'date':

                        break;
                    //To use from social
                    case 'birthday':
                        $min = $value['min'];
                        $max = $value['max'];
                        $conditions[] = "('$min' <= p.$name AND p.$name <= '$max')";
                        break;
                    case 'birthday_range':
                        $birthdayRange = $this->profileFilterModel->getBirthdayRangeFromAgeRange($value['min'], $value['max']);
                        $min = $birthdayRange['min'];
                        $max = $birthdayRange['max'];
                        $conditions[] = "('$min' <= p.$name AND p.$name <= '$max')";
                        break;
                    case 'location_distance':
                    case 'location':
                        $distance = (int)$value['distance'];
                        $latitude = (float)$value['location']['latitude'];
                        $longitude = (float)$value['location']['longitude'];
                        $conditions[] = "(NOT l IS NULL AND EXISTS(l.latitude) AND EXISTS(l.longitude) AND
                        " . $distance . " >= toInt(6371 * acos( cos( radians(" . $latitude . ") ) * cos( radians(l.latitude) ) * cos( radians(l.longitude) - radians(" . $longitude . ") ) + sin( radians(" . $latitude . ") ) * sin( radians(l.latitude) ) )))";
                        break;
                    case 'boolean':
                        $conditions[] = "p.$name = true";
                        break;
                    case 'choice':
                    case 'multiple_choices':
                        $profileLabelName = $this->profileFilterModel->typeToLabel($name);
                        $value = implode("', '", $value);
                        $matches[] = "(p)<-[:OPTION_OF]-(option$name:$profileLabelName) WHERE option$name.id IN ['$value']";
                        break;
                    case 'double_choice':
                        $profileLabelName = $this->profileFilterModel->typeToLabel($name);
                        $value = implode("', '", $value);
                        $matches[] = "(p)<-[:OPTION_OF]-(option$name:$profileLabelName) WHERE option$name.id IN ['$value']";
                        break;
                    case 'double_multiple_choices':
                        $profileLabelName = $this->profileFilterModel->typeToLabel($name);
                        $matchQuery = "(p)<-[rel$name:OPTION_OF]-(option$name:$profileLabelName)";
                        $whereQueries = array();
                        foreach ($value as $dataValue){
                            $choice = $dataValue['choice'];
                            $detail = $dataValue['detail'];
                            $whereQueries[] = "( option$name.id = '$choice' AND rel$name.detail = '$detail')";
                        }

                        $matches[] = $matchQuery.' WHERE ' . implode('OR', $whereQueries);
                        break;
                    case 'tags':
                        $tagLabelName = $this->profileFilterModel->typeToLabel($name);
                        $matches[] = "(p)<-[:TAGGED]-(tag$name:$tagLabelName) WHERE tag$name.name = '$value'";
                        break;
                    case 'tags_and_choice':
                        $tagLabelName = $this->profileFilterModel->typeToLabel($name);
                        $matchQuery = "(p)<-[rel$name:TAGGED]-(tag$name:ProfileTag:$tagLabelName)";
                        $whereQueries = array();
                        foreach ($value as $dataValue) {
                            $tagValue = $name === 'language' ?
                                $this->profileFilterModel->getLanguageFromTag($dataValue['tag']) :
                                $dataValue['tag'];
                            $choice = !is_null($dataValue['choice']) ? $dataValue['choice'] : '';

                            $whereQueries[] = "( tag$name.name = '$tagValue' AND rel$name.detail = '$choice')";
                        }
                        $matches[] = $matchQuery.' WHERE ' . implode('OR', $whereQueries);
                        break;
                    case 'tags_and_multiple_choices':
                        $tagLabelName = $this->profileFilterModel->typeToLabel($name);
                        $matchQuery = "(p)<-[rel$name:TAGGED]-(tag$name:ProfileTag:$tagLabelName)";
                        $whereQueries = array();
                        foreach ($value as $dataValue) {
                            $tagValue = $name === 'language' ?
                                $this->profileFilterModel->getLanguageFromTag($dataValue['tag']) :
                                $dataValue['tag'];
                            $choices = !is_null($dataValue['choices']) ? json_encode($dataValue['choices']) : json_encode(array());

                            $whereQueries[] = "( tag$name.name = '$tagValue' AND rel$name.detail IN $choices )";
                        }
                        $matches[] = $matchQuery.' WHERE ' . implode('OR', $whereQueries);
                        break;
                    default:
                        break;
                }
            }
        }

        return array(
            'conditions' => $conditions,
            'matches' => $matches
        );
    }

    protected function getProfileFilterMetadata(){
        return $this->profileFilterModel->getFilters();
    }
}