<?php

namespace Console\Command;

use Everyman\Neo4j\Query\ResultSet;
use Model\Entity\EmailNotification;
use Model\LinkModel;
use Model\User\Affinity\AffinityModel;
use Model\UserModel;
use Service\AffinityRecalculations;
use Service\EmailNotifications;
use Silex\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PredictionCommand extends ApplicationAwareCommand
{

    protected function configure()
    {
        $this->setName('prediction:calculate')
            ->setDescription('Calculate the predicted high affinity links for a user.')
            ->addOption('user', null, InputOption::VALUE_OPTIONAL, 'The id of the user')
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Max links to calculate per user')
            ->addOption('recalculate', null, InputOption::VALUE_NONE, 'Include already calculated affinities (Updates those links)')
            ->addOption('notify', null, InputOption::VALUE_OPTIONAL, 'Email users who get links with more affinity than this value');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /* @var $userModel UserModel */
        $userModel = $this->app['users.model'];
        /* @var $linkModel LinkModel */
        $linkModel = $this->app['links.model'];
        /* @var $affinityModel AffinityModel */
        $affinityModel = $this->app['users.affinity.model'];

        $user = $input->getOption('user');
        $limit = $input->getOption('limit');
        $recalculate = $input->getOption('recalculate');
        $notify = $input->getOption('notify');

        try {

            $users = null === $user ? $userModel->getAll() : array($userModel->getById($user));

            $limit = $limit ?: 40;

            $recalculate = $recalculate ? true : false;

            $notify = $notify ?: 99999;

            if (!$recalculate) {
                foreach ($users as $user) {

                    $linkIds = $linkModel->getPredictedContentForAUser($user['qnoow_id'], $limit, false);
                    foreach ($linkIds as $linkId) {

                        $affinity = $affinityModel->getAffinity($user['qnoow_id'], $linkId);
                        if (OutputInterface::VERBOSITY_NORMAL <= $output->getVerbosity()) {
                            $output->writeln(sprintf('User: %d --> Link: %d (Affinity: %f)', $user['qnoow_id'], $linkId, $affinity['affinity']));
                        }
                    }
                }
            } else {
                /* @var $affinityRecalculations AffinityRecalculations */
                $affinityRecalculations = $this->app['affinityRecalculations.service'];
                foreach ($users as $user) {

                    $result = $affinityRecalculations->recalculateAffinities($user['qnoow_id'], $limit, $notify);
                    foreach ($result['affinities'] as $linkId => $affinity) {
                        $output->writeln(sprintf('User: %d --> Link: %d (Affinity: %f)', $user['qnoow_id'], $linkId, $affinity));
                    }
                    if(!empty($result['emailInfo'])){
                        $emailInfo=$result['emailInfo'];
                        $linkIds=array();
                        foreach($emailInfo['links'] as $link ){
                            $linkIds[]=$link['id'];
                        }
                        $output->writeln(sprintf('Email sent to user: %s with links: %s', $user['qnoow_id'], implode(', ',$linkIds)));
                    }
                }
            }

        } catch (\Exception $e) {
            $output->writeln('Error trying to recalculate predicted links with message: ' . $e->getMessage());
            return;
        }

        $output->writeln('Done.');

    }
}
