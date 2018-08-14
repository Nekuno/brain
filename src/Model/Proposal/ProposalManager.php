<?php

namespace Model\Proposal;

use Model\Neo4j\GraphManager;
use Model\Proposal\ProposalFields\ProposalBuilder;
use Model\User\User;

class ProposalManager
{
    protected $graphManager;

    //TODO: ProposalValidator

    protected $proposalBuilder;

    protected $locale = 'en';

    /**
     * ProposalManager constructor.
     * @param GraphManager $graphManager
     * @param ProposalBuilder $proposalBuilder
     */
    public function __construct(GraphManager $graphManager, ProposalBuilder $proposalBuilder)
    {
        $this->graphManager = $graphManager;
        $this->proposalBuilder = $proposalBuilder;
    }

    public function create()
    {
        $qb = $this->graphManager->createQueryBuilder();

        $qb->create('(proposal:Proposal)')
            ->returns('id(proposal) AS proposalId');

        $resultSet = $qb->getQuery()->getResultSet();
        $proposalId = $resultSet->current()->offsetGet('proposalId');

        return $proposalId;
    }

    public function update($proposalId, array $data)
    {
        $proposalName = $data['name'];
        $proposal = $this->proposalBuilder->buildFromData($proposalName, $data);
        $proposal->setId($proposalId);

        $qb = $this->graphManager->createQueryBuilder();

        $qb->match('(proposal:Proposal)')
            ->where('id(proposal) = {proposalId}')
            ->with('proposal')
            ->setParameter('proposalId', (integer)$proposalId);

        $proposalLabel = $proposal->getLabel();
        $qb->remove('proposal:Work:Sport:Videogame:Hobby:Show:Restaurant:Plan');
        $qb->set("proposal:$proposalLabel")
            ->with('proposal');

        $variables = array('proposal');
        foreach ($proposal->getFields() as $field) {
            $qb->add('', $field->getSaveQuery($variables));
        }
        $qb->setParameter('locale', $data['locale']);

        $qb->returns('proposal');
        $qb->getQuery()->getResultSet();

        return $proposal;
    }

    public function delete($proposalId)
    {
        $qb = $this->graphManager->createQueryBuilder();

        $qb->match('(proposal:Proposal)')
            ->where('id(proposal) = {proposalId}')
            ->with('proposal')
            ->setParameter('proposalId', $proposalId);

        $qb->optionalMatch('(proposal)-[rel:INCLUDES]->(:ProposalOption)')
            ->delete('rel')
            ->with('proposal');

        $qb->optionalMatch('(proposal)-[rel:INCLUDES]->(tag:ProposalTag)')
            ->delete('rel')
            ->with('proposal');

        $qb->detachDelete('proposal');

        $qb->getQuery()->getResultSet();
    }

    public function relateToUser(Proposal $proposal, User $user)
    {
        $qb = $this->graphManager->createQueryBuilder();

        $qb->match('(proposal:Proposal)')
            ->where('id(proposal) = {proposalId}')
            ->with('proposal')
            ->setParameter('proposalId', $proposal->getId());

        $qb->match('(user:User{qnoow_id: {userId}})')
            ->with('proposal', 'user')
            ->setParameter('userId', $user->getId());

        $qb->merge('(user)-[:PROPOSES]->(proposal)');

        $result = $qb->getQuery()->getResultSet();

        return !!($result->count());
    }

    public function getById($proposalId, $locale)
    {
        $this->locale = $locale;

        $qb = $this->graphManager->createQueryBuilder();

        $qb->match('(proposal:Proposal)')
            ->where('id(proposal) = {proposalId}')
            ->setParameter('proposalId', $proposalId);

        $qb->returns('{id: id(proposal), labels: labels(proposal)} AS proposal');

        $resultSet = $qb->getQuery()->getResultSet();

        $proposalData = $qb->getData($resultSet->current());

        $proposalName = $this->getProposalName($proposalData);
        $proposalId = $proposalData['proposal']['id'];

        $proposal = $this->getProposalData($proposalId, $proposalName);
        $proposal->setId($proposalId);

        return $proposal;
    }

    /**
     * @param User $user
     * @return array
     * @throws \Exception
     */
    public function getIdsByUser(User $user)
    {
        $userId = $user->getId();

        $qb = $this->graphManager->createQueryBuilder();

        $qb->match('(user:User{qnoow_id: {userId}})')
            ->setParameter('userId', $userId)
            ->with('user');

        $qb->match('(user)-[:PROPOSES]->(proposal:Proposal)')
            ->returns('{id: id(proposal), labels: labels(proposal)} AS proposal');

        $resultSet = $qb->getQuery()->getResultSet();

        $proposalIds = array();
        foreach ($resultSet as $row) {
            $data = $qb->getData($row);
            $proposalIds[] = (integer)$data['proposal']['id'];
        }

        return $proposalIds;
    }

    protected function getProposalData($proposalId, $proposalName)
    {
        $proposal = $this->proposalBuilder->buildEmpty($proposalName);

        $qb = $this->graphManager->createQueryBuilder();

        $qb->match('(proposal:Proposal)')
            ->where('id(proposal) = {proposalId}')
            ->with('proposal')
            ->setParameter('proposalId', $proposalId);

        $variables = array('proposal');
        foreach ($proposal->getFields() as $field) {
            $qb->add('', $field->addInformation($variables));
        }
        $qb->setParameter('locale', $this->locale);

        $variables[0] = '{id: id(proposal), labels: labels(proposal)} AS proposal';
        $qb->returns($variables);

        $resultSet = $qb->getQuery()->getResultSet();
        $data = $qb->getData($resultSet->current());

        return $this->proposalBuilder->buildFromData($proposalName, $data);
    }

    protected function getProposalName($proposalData)
    {
        $labels = $proposalData['proposal']['labels'];

        foreach ($labels as $label) {
            if ($label !== 'Proposal') {
                return lcfirst($label);
            }
        }

        return '';
    }

}