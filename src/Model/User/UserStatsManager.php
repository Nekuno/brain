<?php
/**
 * Created by PhpStorm.
 * User: yawmoght
 * Date: 22/10/15
 * Time: 14:44
 */

namespace Model\User;


use Doctrine\ORM\EntityManager;
use Everyman\Neo4j\Node;
use Everyman\Neo4j\Query\Row;
use Model\Neo4j\GraphManager;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class UserStatsManager
{
    /**
     * @var EntityManager
     */
    protected $entityManagerBrain;

    /**
     * @var TokensModel
     */
    protected $tokensModel;

    /**
     * @var GraphManager
     */
    protected $graphManager;
    /**
     * @var RelationsModel
     */
    protected $relationsModel;

    function __construct(GraphManager $graphManager,
                         EntityManager $entityManagerBrain,
                         TokensModel $tokensModel,
                         RelationsModel $relationsModel)
    {
        $this->entityManagerBrain = $entityManagerBrain;
        $this->tokensModel = $tokensModel;
        $this->graphManager = $graphManager;
        $this->relationsModel = $relationsModel;
    }

    public function getStats($id)
    {

        $qb = $this->graphManager->createQueryBuilder();

        $qb->match('(u:User {qnoow_id: { id }})')
            ->setParameter('id', (integer)$id)
            ->with('u')
            ->optionalMatch('(u)-[r:LIKES]->(:Link)')
            ->with('u,count(r) AS contentLikes')
            ->optionalMatch('(u)-[r:LIKES]->(:Video)')
            ->with('u,contentLikes,count(r) AS videoLikes')
            ->optionalMatch('(u)-[r:LIKES]->(:Audio)')
            ->with('u,contentLikes,videoLikes,count(r) AS audioLikes')
            ->optionalMatch('(u)-[r:LIKES]->(:Image)')
            ->with('u,contentLikes,videoLikes,audioLikes,count(r) AS imageLikes')
            ->optionalMatch('(u)-[:BELONGS_TO]->(g:Group)')
            ->with('u,contentLikes, videoLikes, audioLikes, imageLikes, collect(g) AS groupsBelonged')
            ->optionalMatch('(u)-[r:ANSWERS]->(:Answer)')
            ->returns('contentLikes', 'videoLikes', 'audioLikes', 'imageLikes', 'groupsBelonged', 'count(r) AS questionsAnswered', 'u.available_invitations AS available_invitations');

        $query = $qb->getQuery();

        $result = $query->getResultSet();

        if ($result->count() < 1) {
            throw new NotFoundHttpException('User not found');
        }

        /* @var $row Row */
        $row = $result->current();

        $groups = array();
        foreach ($row->offsetGet('groupsBelonged') as $group) {
            /* @var $group Node */
            $groups[] = array(
                'id' => $group->getId(),
                'name' => $group->getProperty('name'),
                'html' => $group->getProperty('html'),
            );
        }

        $numberOfReceivedLikes = $this->relationsModel->countTo($id, RelationsModel::LIKES);
        $numberOfUserLikes = $this->relationsModel->countFrom($id, RelationsModel::LIKES);

        $dataStatusRepository = $this->entityManagerBrain->getRepository('\Model\Entity\DataStatus');

        $twitterStatus = $dataStatusRepository->findOneBy(array('userId' => (int)$id, 'resourceOwner' => 'twitter'));
        $facebookStatus = $dataStatusRepository->findOneBy(array('userId' => (int)$id, 'resourceOwner' => 'facebook'));
        $googleStatus = $dataStatusRepository->findOneBy(array('userId' => (int)$id, 'resourceOwner' => 'google'));
        $spotifyStatus = $dataStatusRepository->findOneBy(array('userId' => (int)$id, 'resourceOwner' => 'spotify'));

        $networks = $this->tokensModel->getConnectedNetworks($id);

        $userStats = new UserStatsModel(
            $row->offsetGet('contentLikes'),
            $row->offsetGet('videoLikes'),
            $row->offsetGet('audioLikes'),
            $row->offsetGet('imageLikes'),
            (integer)$numberOfReceivedLikes,
            (integer)$numberOfUserLikes,
            $groups,
            $row->offsetGet('questionsAnswered'),
            !empty($twitterStatus) ? (boolean)$twitterStatus->getFetched() && in_array(TokensModel::TWITTER, $networks) : false,
            !empty($twitterStatus) ? (boolean)$twitterStatus->getProcessed() && in_array(TokensModel::TWITTER, $networks) : false,
            !empty($facebookStatus) ? (boolean)$facebookStatus->getFetched() && in_array(TokensModel::FACEBOOK, $networks) : false,
            !empty($facebookStatus) ? (boolean)$facebookStatus->getProcessed() && in_array(TokensModel::FACEBOOK, $networks) : false,
            !empty($googleStatus) ? (boolean)$googleStatus->getFetched() && in_array(TokensModel::GOOGLE, $networks) : false,
            !empty($googleStatus) ? (boolean)$googleStatus->getProcessed() && in_array(TokensModel::GOOGLE, $networks) : false,
            !empty($spotifyStatus) ? (boolean)$spotifyStatus->getFetched() && in_array(TokensModel::SPOTIFY, $networks) : false,
            !empty($spotifyStatus) ? (boolean)$spotifyStatus->getProcessed() && in_array(TokensModel::SPOTIFY, $networks) : false,
            $row->offsetGet('available_invitations')
        );

        return $userStats;

    }


}