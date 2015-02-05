<?php

namespace Provider;

use Everyman\Neo4j\Client;
use Model\Neo4j\GraphManager;
use Psr\Log\LoggerAwareInterface;
use Silex\Application;
use Silex\ServiceProviderInterface;

class Neo4jPHPServiceProvider implements ServiceProviderInterface
{

    /**
     * { @inheritdoc }
     */
    public function register(Application $app)
    {

        // Initialize neo4j
        $app['neo4j.client'] = $app->share(
            function ($app) {

                $client = new Client($app['neo4j.options']['host'], $app['neo4j.options']['port']);

                if (isset($app['neo4j.options']['auth']) && $app['neo4j.options']['auth']) {
                    $client
                        ->getTransport()
                        ->setAuth($app['neo4j.options']['user'], $app['neo4j.options']['pass']);
                }

                return $client;
            }
        );

        $app['neo4j.graph_manager'] = $app->share(
            function ($app) {

                $manager = new GraphManager($app['neo4j.client']);

                if ($app['env'] == 'dev' && $manager instanceof LoggerAwareInterface) {
                    $manager->setLogger($app['monolog']);
                }

                return $manager;
            }
        );
    }

    /**
     * { @inheritdoc }
     */
    public function boot(Application $app)
    {

    }

}
