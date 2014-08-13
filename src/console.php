<?php

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputOption;

$console = new Application('Qnoow Brain', '0.1');
$console->getDefinition()->addOption(new InputOption('--env', '-e', InputOption::VALUE_REQUIRED, 'The Environment name.', 'dev'));
$console->setDispatcher($app['dispatcher']);

$console->addCommands(array(
    new \Console\Command\FetchLinksCommand($app),
    new \Console\Command\FetchLinksQueueCommand($app),
    new \Console\Command\ScrapLinksMetadataCommand($app),
    new \Console\Command\Neo4jConstraintsCommand($app),
));

return $console;
