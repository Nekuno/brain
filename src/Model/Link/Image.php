<?php

namespace Model\Link;

class Image extends Link
{
    const IMAGE_LABEL = 'Image';

    public function isComplete() {
        return !!$this->getUrl();
    }

    public function toArray()
    {
        $array = parent::toArray();
        if (!in_array(self::IMAGE_LABEL, $array['additionalLabels'])) {
            $array['additionalLabels'][] = self::IMAGE_LABEL;
        }

        return $array;
    }
}