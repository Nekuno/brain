<?php

namespace Model\Availability;

use Model\Date\DayPeriod;
use Model\Neo4j\GraphManager;
use Model\Proposal\Proposal;
use Model\User\User;

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

    public function getByUser(User $user)
    {
        $qb = $this->graphManager->createQueryBuilder();

        $qb->match('(user:UserEnabled)')
            ->where('user.qnoow_id = {userId}')
            ->setParameter('userId', $user->getId());

        $qb->match('(user)-[:HAS_AVAILABILITY]->(availability:Availability)')
            ->with('availability');

        $qb->optionalMatch('(availability)-[:INCLUDES{static:true}]->(period:DayPeriod)')
            ->returns('{id: id(availability), properties: properties(availability)} AS availability', 'collect(id(period)) AS periodIds');

        $resultSet = $qb->getQuery()->getResultSet();

        if ($resultSet->count() == 0) {
            return null;
        }
        $availabilityData = $qb->getData($resultSet->current());

        return $this->build($availabilityData);
    }

    public function getByProposal(Proposal $proposal)
    {
        $qb = $this->graphManager->createQueryBuilder();

        $qb->match('(proposal)')
            ->where('id(proposal) = {proposalId}')
            ->setParameter('proposalId', $proposal->getId());

        $qb->match('(proposal)-[:HAS_AVAILABILITY]->(availability:Availability)')
            ->with('availability');

        $qb->optionalMatch('(availability)-[:INCLUDES{static:true}]->(period:DayPeriod)')
            ->returns('{id: id(availability), properties: properties(availability)} AS availability', 'collect(id(period)) AS periodIds');

        $resultSet = $qb->getQuery()->getResultSet();

        if ($resultSet->count() == 0) {
            return null;
        }
        $availabilityData = $qb->getData($resultSet->current());

        return $this->build($availabilityData);
    }

    public function create()
    {
        $qb = $this->graphManager->createQueryBuilder();

        $qb->create('(availability:Availability)')
            ->with('availability');

        $qb->returns('{id: id(availability), properties: properties(availability)} AS availability');

        $resultSet = $qb->getQuery()->getResultSet();
        $data = $qb->getData($resultSet->current());

        return $this->build($data);
    }

    /**
     * @param Availability $availability
     * @param DayPeriod[] $staticData
     * @return Availability
     * @throws \Exception
     */
    public function addStatic(Availability $availability, array $staticData)
    {
        $qb = $this->graphManager->createQueryBuilder();
        $qb->match('(availability:Availability)')
            ->where('id(availability) = {availabilityId}')
            ->with('availability')
            ->setParameter('availabilityId', $availability->getId());

        foreach ($staticData as $index => $period) {

            $qb->optionalMatch("(period$index:DayPeriod)")
                ->where("id(period$index) = {periodId$index}")
                ->setParameter("periodId$index", $period->getId());

            $qb->merge("(availability)-[includes:INCLUDES{static:true}]->(period$index)");
            $qb->with('availability');
        }

        //TODO: Remove this necessity and build static data from relationships when retrieving data
        $staticDataString = $availability->getStatic();
        $qb->set("availability.static = {staticData}")
            ->with('availability')
            ->setParameter('staticData', $staticDataString);

        $qb->returns('{id: id(availability), properties: properties(availability)} AS availability');

        $resultSet = $qb->getQuery()->getResultSet();
        $data = $qb->getData($resultSet->current());

        return $this->build($data);
    }

    public function addDynamic(Availability $availability, array $dynamic)
    {
        if (empty($dynamic)) {
            return $availability;
        }

        foreach ($dynamic as $each) {

            $weekday = $each['weekday'];
            $range = $each['range'];

            foreach ($range as $index => $dayPeriod) {

                $qb = $this->graphManager->createQueryBuilder();

                $qb->match('(availability:Availability)')
                    ->where('id(availability) = {availabilityId}')
                    ->with('availability')
                    ->setParameter('availabilityId', $availability->getId());

                $qb->set("availability.$weekday = COALESCE(availability.$weekday, [])");
                $qb->with('availability');
                $qb->set("availability.$weekday = availability.$weekday + ['$dayPeriod']");
                $qb->with('availability');

                $qb->match("(day:$weekday)");
                $qb->match("(day)-[:PERIOD_OF]-(period:$dayPeriod)")
                    ->merge('(availability)-[:INCLUDES]->(period)');
                $qb->with('availability');

                $qb->returns('{id: id(availability), properties: properties(availability)} AS availability');

                $resultSet = $qb->getQuery()->getResultSet();
            }
        }

        $qb = $this->graphManager->createQueryBuilder();
        $data = $qb->getData($resultSet->current());

        return $this->build($data);
    }

    public function relateToUser(Availability $availability, User $user)
    {
        $qb = $this->graphManager->createQueryBuilder();

        $qb->match('(availability:Availability)')
            ->where('id(availability) = {availabilityId}')
            ->with('availability')
            ->setParameter('availabilityId', $availability->getId());

        $qb->match('(user:UserEnabled)')
            ->where('user.qnoow_id = {userId}')
            ->with('availability', 'user')
            ->setParameter('userId', $user->getId());

        $qb->merge('(user)-[:HAS_AVAILABILITY]->(availability)');

        $resultSet = $qb->getQuery()->getResultSet();
        $created = !!($resultSet->count());

        return $created;
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

    protected function build(array $data)
    {
        $availabilityData = $data['availability'];
        $availability = new Availability();
        $availability->setId($availabilityData['id']);

        if (isset($availabilityData['properties']))
        {
            $this->setDynamicData($availability, $availabilityData);
            $this->setStaticData($availability, $availabilityData);
        }

        if (isset($data['periodIds'])) {
            $availability->setPeriodIds($data['periodIds']);
        }

        return $availability;
    }

    protected function setDynamicData(Availability $availability, array $availabilityData)
    {
        $properties = $availabilityData['properties'];
        $dynamicData = array();
        foreach ($properties as $weekday => $range) {
            if ($weekday == 'static'){
                continue;
            }
            $dynamicData[] = ['weekday' => $weekday, 'range' => $range];
        }
        $availability->setDynamic($dynamicData);
    }

    protected function setStaticData(Availability $availability, array $availabilityData)
    {
        $properties = $availabilityData['properties'];
        $static = isset($properties['static']) ? $properties['static'] : '';
        $availability->setStatic($static);
    }

    public function relateToProposal(Availability $availability, Proposal $proposal)
    {
        $qb = $this->graphManager->createQueryBuilder();

        $availabilityId = $availability->getId();
        $qb->match('(availability:Availability)')
            ->where('id(availability) = {availabilityId}')
            ->with('availability')
            ->setParameter('availabilityId', (integer)$availabilityId);

        $proposalId = $proposal->getId();
        $qb->match('(proposal:Proposal)')
            ->where('id(proposal) = {proposalId}')
            ->with('proposal', 'availability')
            ->setParameter('proposalId', (integer)$proposalId);

        $qb->merge('(proposal)-[:HAS_AVAILABILITY]-(availability)');

        $result = $qb->getQuery()->getResultSet();

        return !!($result->count());
    }
}