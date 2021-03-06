<?php

namespace Model\Metadata;

use Symfony\Component\Translation\TranslatorInterface;

class ProposalMetadataManager extends MetadataManager
{
    /**
     * ProposalMetadataManager constructor.
     * @param TranslatorInterface $translator
     * @param MetadataUtilities $metadataUtilities
     * @param array $metadata
     * @param $defaultLocale
     */
    public function __construct(TranslatorInterface $translator, MetadataUtilities $metadataUtilities, array $metadata, $defaultLocale)
    {
        parent::__construct($translator, $metadataUtilities, $metadata, $defaultLocale);

        foreach ($this->metadata as &$metadatum) {
            $metadatum['title'] = array('type' => 'string');
            $metadatum['description'] = array('type' => 'string');
            $metadatum['participantLimit'] = array('type' => 'integer', 'min' => 1);
            $metadatum['availability'] = array('type' => 'availability');
            $metadatum['photo'] = array('type' => 'string');
        }
    }

    protected function modifyPublicField($publicField, $name, $values)
    {
        unset($publicField['label']);

        return $publicField;
    }

    protected function orderByLabel($metadata)
    {
        return $metadata;
    }

}