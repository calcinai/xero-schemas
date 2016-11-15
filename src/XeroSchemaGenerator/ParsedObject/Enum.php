<?php
/**
 * @package    xero-schemas
 * @author     Michael Calcinai <michael@calcin.ai>
 */

namespace Calcinai\XeroSchemaGenerator\ParsedObject;

use Calcinai\XeroSchemaGenerator\ParsedObject;
use Calcinai\XeroSchemaGenerator\ParsedObject\Enum\Value;

class Enum extends ParsedObject
{

    /**
     * @var array
     */
    private $values;

    public function __construct($raw_name)
    {
        //Stop enum names being split up
        $raw_name = str_replace(' ', '', $raw_name);
        parent::__construct($raw_name);

        $this->values = [];
    }

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
}