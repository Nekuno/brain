<?php

namespace Model\Availability;

class Availability
{
    protected $id;

    protected $dayPeriods = array();

    protected $periodIds = array();

    //TODO: Dynamic ranges
    /**
     * @return mixed
     */
    public function getDayPeriods()
    {
        return $this->dayPeriods;
    }

    /**
     * @param mixed $dayPeriods
     */
    public function setDayPeriods($dayPeriods): void
    {
        $this->dayPeriods = $dayPeriods;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param mixed $id
     */
    public function setId($id): void
    {
        $this->id = $id;
    }

    /**
     * @return mixed
     */
    public function getPeriodIds()
    {
        return $this->periodIds;
    }

    /**
     * @param mixed $periodIds
     */
    public function setPeriodIds($periodIds): void
    {
        $this->periodIds = $periodIds;
    }

}