<?php

namespace Provider;

use Paginator\Paginator;
use Silex\Application;
use Silex\ServiceProviderInterface;

class PaginatorServiceProvider implements ServiceProviderInterface
{

    /**
     * { @inheritdoc }
     */
    public function register(Application $app)
    {
        $app['paginator'] = $app->share(
            function ($app) {
                $paginator = new Paginator();

                return $paginator;
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