<?php

/**
 * @package    xero-schemas
 * @author     Michael Calcinai <michael@calcin.ai>
 */

namespace Calcinai\XeroSchemaGenerator;

use Calcinai\XeroSchemaGenerator\ParsedObject\Enum;
use Calcinai\XeroSchemaGenerator\ParsedObject\Model;

class API {

    /**
     * @var Model[]
     */
    private $models;

    /**
     * @var Enum[]
     */
    private $enums;


    public function __construct() {
        $this->models = [];
        $this->enums = [];
    }


    /**
     * @param Model $model
     */
    public function addModel(Model $model) {
        $this->models[$model->getName()] = $model;
    }

    /**
     * @param Enum $enum
     */
    public function addEnum(Enum $enum) {
        $this->enums[$enum->getName()] = $enum;
    }

    /**
     * @return ParsedObject\Model[]
     */
    public function getModels() {
        return $this->models;
    }
}