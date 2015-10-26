<?php

namespace Console\Command;

use ApiConsumer\LinkProcessor\LinkProcessor;
use Console\ApplicationAwareCommand;
use Model\LinkModel;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class LinksProcessCommand extends ApplicationAwareCommand
{

    protected function configure()
    {

        $this->setName('links:process')
            ->setDescription('Process links')
            ->setDefinition(
                array(
                    new InputArgument('limit', InputArgument::OPTIONAL, 'Items limit', 100)

                )
            )
            ->addOption('all', null, InputOption::VALUE_NONE, 'Process again all links, not only unprocessed ones');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        /* @var $linksModel LinkModel */
        $linksModel = $this->app['links.model'];

        $limit = $input->getArgument('limit');
        $all = $input->getOption('all');

        if ($all){
            $links = $linksModel->findAllLinks();
            foreach ($links as &$link){
                if (!isset($link['url'])){
                    continue;
                }
                $link['tempId'] = $link['url'];
            }
        } else {
            $links = $linksModel->getUnprocessedLinks($limit);
        }

        $output->writeln('Got '.count($links).' links to process');

        foreach ($links as $link) {

            try {
                /* @var LinkProcessor $processor */
                $processor = $this->app['api_consumer.link_processor'];
                $processedLink = $processor->process($link, $all);

                $processed = array_key_exists('processed', $link)? $link['processed'] : 1;
                if ($processed){
                    $output->writeln(sprintf('Success: Link %s processed', $link['url']));
                } else {
                    $output->writeln(sprintf('Failed request: Link %s not processed', $link['url']));
                }

            } catch (\Exception $e) {
                $output->writeln(sprintf('Error: %s', $e->getMessage()));
                $output->writeln(sprintf('Error: Link %s not processed', $link['url']));
                $linksModel->updateLink($link, true);
                continue;
            }

            try {
                $linksModel->updateLink($processedLink, $processed);

                if (isset($processedLink['tags'])) {
                    foreach ($processedLink['tags'] as $tag) {
                        $linksModel->createTag($tag);
                        $linksModel->addTag($processedLink, $tag);
                    }
                }

                $output->writeln(sprintf('Success: Link %s saved', $processedLink['url']));

            } catch (\Exception $e) {
                $output->writeln(sprintf('Error: Link %s not saved', $processedLink['url']));
                $output->writeln($e->getMessage());
            }
        }
    }
}
