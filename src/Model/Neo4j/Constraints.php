<?php

namespace Model\Neo4j;

/**
 * Class Constraints
 *
 * @package Model\Neo4j
 */
class Constraints
{

    /**
     * @var GraphManager
     */
    protected $gm;

    /**
     * @param GraphManager $gm
     */
    public function __construct(GraphManager $gm)
    {

        $this->gm = $gm;
    }

    /**
     * Load the constraints
     *
     * @throws Neo4jException
     */
    public function load()
    {

        $constraints = array(
            'CREATE INDEX ON :Link(url)',
            'CREATE CONSTRAINT ON (inv:Invitation) ASSERT inv.token IS UNIQUE',
        );

        $fields = array('qnoow_id', 'usernameCanonical');
        foreach ($fields as $field) {
            $constraints[] = "CREATE CONSTRAINT ON (u:User) ASSERT u.$field IS UNIQUE";
        }

        foreach ($constraints as $query) {
            $this->gm->createQuery($query)->getResultSet();
        }
    }
}