<?php
/**
 * @package    xero-schemas
 * @author     Michael Calcinai <michael@calcin.ai>
 */

namespace Calcinai\XeroSchemaGenerator\ParsedObject;

use Calcinai\XeroSchemaGenerator\ParsedObject;

class Enum extends ParsedObject {

    /**
     * @var array
     */
    private $values;

    public function __construct() {
        $this->values = [];
    }

    public function addValue(ParsedObject\Enum\Value $value) {
        $this->values[$value->getName()] = $value;
    }
}