<?php
/**
 * Created by PhpStorm.
 * User: adridev
 * Date: 28/06/14
 * Time: 18:27
 */

namespace Social\API\Consumer\Storage;


interface StorageInterface {

    /**
     * @param array $links
     * @return mixed
     */
    function storeLinks(array $links);

} 