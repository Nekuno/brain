<?php

namespace Model\Metadata;

class ProfileMetadataManager extends MetadataManager
{
    protected function modifyPublicField($publicField, $name, $values)
    {
        $publicField = parent::modifyPublicField($publicField, $name, $values);

        $publicField = $this->modifyCommonAttributes($publicField, $values);

        return $publicField;
    }

    protected function modifyCommonAttributes(array $publicField, $values)
    {
        $locale = $this->translator->getLocale();
        $publicField['labelEdit'] = isset($values['labelEdit']) ? $this->metadataUtilities->getLocaleString($values['labelEdit'], $locale) : $publicField['label'];
        $publicField['required'] = isset($values['required']) ? $values['required'] : false;
        $publicField['editable'] = isset($values['editable']) ? $values['editable'] : true;
        $publicField['hidden'] = isset($values['hidden']) ? $values['hidden'] : false;

        if ($publicField['type'] === 'multiple_fields') {
            foreach ($publicField['metadata'] as $key => $value) {
                $publicField['metadata'][$key]['label'] = $this->metadataUtilities->getLocaleString($value['label'], $locale);
                $publicField['metadata'][$key]['labelEdit'] = $this->metadataUtilities->getLocaleString($value['labelEdit'], $locale);
            }
        }

        $publicField['natural'] = isset($values['natural']) ? $this->buildNatural($values['natural'], $locale) : null;

        return $publicField;
    }

    protected function buildNatural($values, $locale)
    {
        $natural = array();
        
        $natural['prefix'] = isset($values['prefix']) ? $this->metadataUtilities->getLocaleString($values['prefix'], $locale) : null;
        $natural['interfix'] = isset($values['interfix']) ? $this->metadataUtilities->getLocaleString($values['interfix'], $locale) : null;
        $natural['suffix'] = isset($values['suffix']) ? $this->metadataUtilities->getLocaleString($values['suffix'], $locale) : null;
        
        return $natural;
    }
}