<?php
/**
 * @package    xero-schemas
 * @author     Michael Calcinai <michael@calcin.ai>
 */

namespace Calcinai\XeroSchemaGenerator\ParsedObject;

use Calcinai\XeroSchemaGenerator\ParsedObject;
use Calcinai\XeroSchemaGenerator\ParsedObject\Enum\Value;

/**
 * Class Enum
 * @package Calcinai\XeroSchemaGenerator\ParsedObject
 */
class Enum extends ParsedObject
{


    /**
     * @var array
     */
    private $values;

    /**
     * @var string
     */
    private $target_name;

    /**
     * Enum constructor.
     * @param $raw_name
     */
    public function __construct($raw_name)
    {
        //Stop enum names being split up
        $raw_name = str_replace(' ', '', $raw_name);
        parent::__construct($raw_name);

        $this->values = [];
    }

    /**
     * @param $target_name
     */
    public function setTargetName($target_name)
    {
        $parsed = self::parseRawName($target_name);
        $this->target_name = array_shift($parsed);
    }

    /**
     * @return string
     */
    public function getTargetName()
    {
        return $this->target_name;
    }

    /**
     * @param Value $value
     */
    public function addValue(Value $value)
    {
        $this->values[$value->getName()] = $value;
    }

    /**
     * @return Value[]
     */
    public function getValues()
    {
        return $this->values;
    }

    /**
     * @return Enum|Model|null
     */
    public function getTarget()
    {
        if(null !== $target_search = $this->getAPI()->searchByName($this->target_name)){
            return $target_search;
        }

        return $this->getAPI()->searchByName($this->getName());
    }
}