<?php

namespace Model\Recommendation\Proposal;

use Model\Recommendation\AbstractUserRecommendator;

class CandidateUninterestedFreeRecommendator extends AbstractUserRecommendator
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
        $offset = ceil($offset / 2);
        $limit = ceil($limit / 2);

        $proposalId = $filtersArray['proposalId'];
        $order = isset($filtersArray['userFilters']['order'])? $filtersArray['userFilters']['order'] : 'id DESC';

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
            ->where('NOT (proposal)<-[:INTERESTED_IN]-(anyUser)',
                'NOT (proposal)-[:ACCEPTED|SKIPPED]->(anyUser)',
                'NOT (proposal)--(:Availability)--(:DayPeriod)--(:Availability)--(anyUser)',
                'NOT anyUser.qnoow_id = user.qnoow_id');
        //TODO: Include filter by weekday

        $qb->optionalMatch('(anyUser)-[similarity:SIMILARITY]-(user)')
            ->with('anyUser', 'proposal', 'user', 'similarity');
        $qb->optionalMatch('(anyUser)-[matching:MATCHING]-(user)')
            ->with('anyUser', 'proposal', 'user', 'similarity', 'matching');
        $qb->match('(anyUser)-[:PROFILE_OF]-(p:Profile)')
            ->with('proposal', 'anyUser', 'p', 'similarity', 'matching');

        foreach ($filters['matches'] as $match) {
            $qb->match($match);
        }

        $qb->optionalMatch('(p)-[:LOCATION]-(l:Location)')
            ->with('proposal, anyUser', 'p', 'l', 'similarity', 'matching')
            ->optionalMatch('(p)<-[optionOf:OPTION_OF]-(option:ProfileOption)')
            ->with('proposal', 'anyUser', 'p', 'l', 'similarity', 'matching',
                'collect(distinct {option: option, detail: (CASE WHEN EXISTS(optionOf.detail) THEN optionOf.detail ELSE null END)}) AS options')
            ->optionalMatch('(p)-[tagged:TAGGED]-(tag:ProfileTag)')
            ->with('proposal', 'anyUser', 'p', 'l', 'similarity', 'matching', 'options',
                'collect(distinct {tag: tag, tagged: tagged}) AS tags');

        $qb->where($filters['conditions']);

        $qb->returns(
            'anyUser.qnoow_id AS id, 
            anyUser.username AS username, 
            anyUser.photo AS photo,
            anyUser.createdAt AS createdAt,
            p.birthday AS birthday,
            p AS profile,
            l AS location,
            proposal, 
            similarity,
            options,
            tags'
        )
            ->orderBy($order)
            ->skip('{offset}')
            ->limit('{limit}')
            ->setParameter('offset', $offset)
            ->setParameter('limit', $limit);

        $resultSet = $qb->getQuery()->getResultSet();

        $userRecommendations = $this->userRecommendationBuilder->buildUserRecommendations($resultSet);

        return $userRecommendations;
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
            ->where('NOT (proposal)<-[:INTERESTED_IN]-(anyUser)', 'NOT (proposal)-[:ACCEPTED|SKIPPED]->(anyUser)', 'NOT (proposal)--(:Availability)--(:DayPeriod)--(:Availability)--(anyUser)');

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
}