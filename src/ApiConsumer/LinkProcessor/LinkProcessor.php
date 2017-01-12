<?php

namespace ApiConsumer\LinkProcessor;

use ApiConsumer\Factory\ProcessorFactory;
use ApiConsumer\Images\ImageAnalyzer;
use ApiConsumer\LinkProcessor\Processor\BatchProcessorInterface;
use ApiConsumer\LinkProcessor\Processor\ProcessorInterface;
use ApiConsumer\LinkProcessor\UrlParser\FacebookUrlParser;
use Model\Link;

class LinkProcessor
{
    private $processorFactory;
    private $imageAnalyzer;
    private $batch = array();

    public function __construct(ProcessorFactory $processorFactory, ImageAnalyzer $imageAnalyzer)
    {
        $this->processorFactory = $processorFactory;
        $this->imageAnalyzer = $imageAnalyzer;
    }

    public function scrape(PreprocessedLink $preprocessedLink)
    {
        $scrapper = $this->processorFactory->getScrapperProcessor();

        $this->executeProcessing($preprocessedLink, $scrapper);

        $this->checkSecureSites($preprocessedLink);

        return $preprocessedLink->getLinks();
    }

    private function checkSecureSites(PreprocessedLink $preprocessedLink)
    {
        $fb_security_titles = array('Vérification de sécurité', 'Security Check', 'Security Check Required', 'Control de seguridad');
        foreach ($preprocessedLink->getLinks() as $link) {
            if ($preprocessedLink->getType() == FacebookUrlParser::FACEBOOK_PAGE && in_array($link->getTitle(), $fb_security_titles)) {
                $link->setProcessed(false);
            }
        }
    }

    public function process(PreprocessedLink $preprocessedLink)
    {
        $processor = $this->selectProcessor($preprocessedLink);

        if ($processor instanceof BatchProcessorInterface) {
            $links = $this->executeBatchProcessing($preprocessedLink, $processor);
        } else {
            $this->executeProcessing($preprocessedLink, $processor);

            if (!$preprocessedLink->getFirstLink()->isComplete()) {
                $this->scrape($preprocessedLink);
            }
            $links = $preprocessedLink->getLinks();
        }

        return $links;
    }

    /**
     * @return Link[]
     */
    public function processLastLinks()
    {
        $links = array();
        foreach ($this->batch as $name => $batch) {
            /** @var BatchProcessorInterface $processor */
            $processor = $this->processorFactory->build($name);
            $links = array_merge($links, $processor->requestBatchLinks($batch));
        }

        return $links;
    }

    protected function selectProcessor(PreprocessedLink $link)
    {
        $processorName = LinkAnalyzer::getProcessorName($link);

        return $this->processorFactory->build($processorName);
    }

    protected function getThumbnail(PreprocessedLink $preprocessedLink, ProcessorInterface $processor, array $response)
    {
        $images = $processor->getImages($preprocessedLink, $response);

        return $this->imageAnalyzer->selectImage($images);
    }

    protected function executeProcessing(PreprocessedLink $preprocessedLink, ProcessorInterface $processor)
    {
        $preprocessedLink->getFirstLink()->setUrl($preprocessedLink->getUrl());

        $response = $processor->getResponse($preprocessedLink);

        $processor->hydrateLink($preprocessedLink, $response);
        $processor->addTags($preprocessedLink, $response);
        $processor->getSynonymousParameters($preprocessedLink, $response);

        if (!$preprocessedLink->getFirstLink()->getThumbnail()) {
            $image = $this->getThumbnail($preprocessedLink, $processor, $response);
            $preprocessedLink->getFirstLink()->setThumbnail($image);
        }
    }

    protected function executeBatchProcessing(PreprocessedLink $preprocessedLink, BatchProcessorInterface $processor)
    {
        $processorName = LinkAnalyzer::getProcessorName($preprocessedLink);

        $this->batch[$processorName] = $this->batch[$processorName] ?: array();
        $this->batch[$processorName][] = $preprocessedLink;

        $links = array(new Link());
        if ($processor->needToRequest($this->batch[$processorName])) {
            $links = $processor->requestBatchLinks($this->batch[$processorName]);
            $this->batch[$processorName] = array();
        }

        return $links;
    }
}
