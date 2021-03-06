<?php

namespace Model\Recommendation\Proposal;

use Model\Recommendation\AbstractUserRecommendator;

class CandidateRecommendator extends AbstractUserRecommendator
{
    /**
     * Hook point for validating the $filters.
     * @param array $filters
     * @return boolean
     */
    public function validateFilters(array $filters)
    {
        return isset($filters['proposalId']);
    }

    /**
     * Slices the results according to $filters, $offset, and $limit.
     * @param array $filtersArray
     * @param int $offset
     * @param int $limit
     * @return array
     * @throws \Exception
     */
    public function slice(array $filtersArray, $offset, $limit)
    {
        $limitInterested = ceil($limit*0.3);
        $limitUninterested = floor($limit*0.7);

        $proposalId = $filtersArray['proposalId'];
        $order = isset($filtersArray['userFilters']['order'])? $filtersArray['userFilters']['order'] : 'id DESC';
        $previousCondition = $this->buildPreviousCondition($filtersArray);

        $filters = $this->applyFilters($filtersArray);

        $qb = $this->gm->createQueryBuilder();

        $qb->match('(proposal:Proposal)')
            ->where('id(proposal) = {proposalId}')
            ->with('proposal')
            ->setParameter('proposalId', $proposalId);

        $qb->match('(user)-[:PROPOSES]->(proposal:Proposal)')
            ->with('proposal', 'user');

        $qb->optionalMatch('(proposal)<-[:INTERESTED_IN]-(anyUser:UserEnabled)')
            ->where($previousCondition)
            ->with('proposal', 'user', '{user: anyUser, interested: true} AS anyUser')
            ->limit($limitInterested)
            ->with('proposal', 'user', 'collect(anyUser) AS interested');

        $previousCondition[] = 'NOT (proposal)<-[:INTERESTED_IN]-(anyUser:UserEnabled)';
        $qb->optionalMatch('(anyUser:UserEnabled)')
            ->where($previousCondition)
            ->with('proposal', 'user', 'interested', '{user: anyUser, interested: false} AS anyUser')
            ->limit($limitUninterested)
            ->with('proposal', 'user', 'interested', 'collect(anyUser) AS uninterested')
            ->with('proposal', 'user', 'interested + uninterested AS candidates')
            ->unwind('candidates AS candidate')
            ->with('proposal', 'user', 'candidate', 'candidate.user AS anyUser');

        $qb->match('(anyUser)-[:PROFILE_OF]-(p)')
            ->with('candidate', 'anyUser', 'proposal', 'user', 'p');

        $qb->optionalMatch('(p)-[:LOCATION]-(l:Location)')
            ->with('candidate', 'anyUser', 'proposal', 'user', 'p', 'l');

        foreach ($filters['matches'] as $match) {
            $qb->match($match);
        }

        $qb->with('candidate', 'anyUser', 'proposal', 'user', 'p', 'l');

        $qb->where($filters['conditions']);

        $qb->with('candidate', 'anyUser', 'proposal', 'user', 'p');
        
        $qb->optionalMatch('(anyUser)-[similarity:SIMILARITY]-(user)')
            ->with('candidate', 'anyUser', 'proposal', 'user', 'p', 'similarity.similarity AS similarity');
        $qb->optionalMatch('(anyUser)-[matching:MATCHING]-(user)')
            ->with('candidate', 'proposal', 'anyUser', 'user', 'p', 'similarity', 'matching');

        $qb->optionalMatch('(p)-[:LOCATION]-(l:Location)')
            ->with('proposal', 'candidate', 'anyUser', 'p', 'l', 'similarity', 'matching')
            ->optionalMatch('(p)<-[optionOf:OPTION_OF]-(option:ProfileOption)')
            ->with('proposal', 'candidate', 'anyUser', 'p', 'l', 'similarity', 'matching',
                'collect(distinct {option: option, detail: (CASE WHEN EXISTS(optionOf.detail) THEN optionOf.detail ELSE null END)}) AS options')
            ->optionalMatch('(p)-[tagged:TAGGED]-(tag:ProfileTag)')
            ->with('proposal', 'candidate', 'anyUser', 'p', 'l', 'similarity', 'matching', 'options',
                'collect(distinct {tag: tag, tagged: tagged}) AS tags');

        $qb->where($filters['conditions']);

        $qb->returns(
            'anyUser.qnoow_id AS id, 
            anyUser.username AS username, 
            anyUser.slug AS slug,
            anyUser.photo AS photo,
            anyUser.createdAt AS createdAt,
            p.birthday AS birthday,
            p AS profile,
            l AS location,
            id(proposal) AS proposalId,
            matching AS matching_questions,
            similarity,
            options,
            tags,
            candidate.interested AS interested'
        )
            ->orderBy($order)
            ->skip('{offset}')
            ->limit('{limit}')
            ->setParameter('offset', $offset)
            ->setParameter('limit', $limit);

        $resultSet = $qb->getQuery()->getResultSet();

        return $qb->getData($resultSet);
    }

    public function countTotal(array $filtersArray)
    {
        $proposalId = $filtersArray['proposalId'];
        $filters = $this->applyFilters($filtersArray);

        $qb = $this->gm->createQueryBuilder();

        $qb->match('(proposal:Proposal)')
            ->where('id(proposal) = {proposalId}')
            ->with('proposal')
            ->setParameter('proposalId', $proposalId);

        $qb->match('(user)-[:PROPOSES]->(proposal:Proposal)')
            ->with('proposal', 'user');

        $qb->match('(anyUser:UserEnabled)')
            ->with('anyUser', 'proposal', 'user')
            ->where('NOT (proposal)-[:ACCEPTED|SKIPPED]->(anyUser)');

        $qb->match('(anyUser)-[:PROFILE_OF]-(p:Profile)');
        $qb->match('(p)-[:LOCATION]-(l:Location)');
        $qb->with('anyUser', 'proposal', 'user', 'p', 'l');

        foreach ($filters['matches'] as $match) {
            $qb->match($match);
        }

        $qb->with('anyUser', 'proposal', 'user');

        $qb->where($filters['conditions']);

        $qb->returns('count(distinct anyUser) AS amount');

        $result = $qb->getQuery()->getResultSet();

        return $result->current()->offsetGet('amount');
    }

    /**
     * @param array $filtersArray
     * @return array
     */
    protected function buildPreviousCondition(array $filtersArray)
    {
        $includeSkipped = isset($filtersArray['includeSkipped']) ? $filtersArray['includeSkipped'] : false;
        $excluded = json_encode($filtersArray['excluded']);

        $previousCondition = array('NOT (proposal)-[:ACCEPTED]->(anyUser)', 'NOT anyUser.qnoow_id = user.qnoow_id', "NOT anyUser.username IN $excluded");
        if (!$includeSkipped){
            $previousCondition[] = 'NOT (proposal)-[:SKIPPED]->(anyUser)';
        }

        return $previousCondition;
    }
}