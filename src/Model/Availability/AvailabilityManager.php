<?php

namespace Model\Availability;

use Model\Neo4j\GraphManager;
use Model\Proposal\Proposal;

class AvailabilityManager
{
    protected $graphManager;

    /**
     * AvailabilityManager constructor.
     * @param $graphManager
     */
    public function __construct(GraphManager $graphManager)
    {
        $this->graphManager = $graphManager;
    }

    public function getByProposal(Proposal $proposal)
    {
        $qb = $this->graphManager->createQueryBuilder();

        $qb->match('(proposal)')
            ->where('id(proposal) = {proposalId}')
            ->setParameter('proposalId', $proposal->getId());

        $qb->match('(proposal)-[:HAS_AVAILABILITY]->(availability:Availability)')
            ->with('availability');

        $qb->match('(availability)-[:INCLUDES]-(day:Day)')
            ->returns('{id: id(availability)} AS availability', 'collect(id(day)) AS daysIds');

        $resultSet = $qb->getQuery()->getResultSet();

        if ($resultSet->count() == 0){
            return null;
        }
        $availabilityData = $qb->getData($resultSet->current());

        return $this->build($availabilityData);
    }

    public function create($daysIds)
    {
        $qb = $this->graphManager->createQueryBuilder();

        $qb->create('(availability:Availability)')
            ->with('availability');

        if (!empty($daysIds))
        {
            $qb->optionalMatch('(day:Day)')
                ->where('id(day) IN {days}')
                ->with('availability', 'day')
                ->setParameter('days', $daysIds);

            $qb->merge('(availability)-[:INCLUDES]->(day)');
        }

        $qb->returns('{id: id(availability)} AS availability');

        $resultSet = $qb->getQuery()->getResultSet();
        $data = $qb->getData($resultSet->current());

        return $this->build($data['availability']);
    }

    public function update($availabilityId, $data)
    {
        $qb = $this->graphManager->createQueryBuilder();

        $dates = $data['dates'];

        $qb->match('(availability:Availability)')
            ->where('id(availability) = {availabilityId}')
            ->with('availability')
            ->setParameter('availabilityId', $availabilityId);

        $qb->optionalMatch('(availability)-[includes:INCLUDES]-(:Day)')
            ->delete('includes')
            ->with('availability');

        $qb->match('(day:Day)')
            ->where('id(day) IN {days}')
            ->with('availability', 'day')
            ->setParameter('days', $dates);

        $qb->merge('(availability)-[:INCLUDES]->(day)');

        $qb->returns('{id: id(availability)} AS availability');

        $resultSet = $qb->getQuery()->getResultSet();

        $availabilityData = $qb->getData($resultSet->current());

        return $this->build($availabilityData);
    }

    public function delete($availabilityId)
    {
        $qb = $this->graphManager->createQueryBuilder();

        $qb->match('(availability:Availability)')
            ->where('id(availability) = {availabilityId}')
            ->with('availability')
            ->setParameter('availabilityId', $availabilityId);

        $qb->detachDelete('availability');

        $qb->getQuery()->getResultSet();

        return true;
    }

    protected function build(array $availabilityData)
    {
        $availability = new Availability();
        $availability->setId($availabilityData['id']);

        if (isset($availabilityData['daysIds'])){
            $availability->setDaysIds($availabilityData['daysIds']);
        }

        return $availability;
    }

    public function relateToProposal(Availability $availability, Proposal $proposal)
    {
        $qb = $this->graphManager->createQueryBuilder();

        $availabilityId = $availability->getId();
        $qb->match('(availability:Availability)')
            ->where('id(availability) = {availabilityId}')
            ->with('availability')
            ->setParameter('availabilityId', $availabilityId);

        $proposalId = $proposal->getId();
        $qb->match('(proposal:Proposal)')
            ->where('id(proposal) = {proposalId}')
            ->with('proposal')
            ->setParameter('proposalId', $proposalId);

        $qb->merge('(proposal)-[:HAS_AVAILABILITY]-(availability)');

        $result = $qb->getQuery()->getResultSet();

        return !!($result->count());
    }
}