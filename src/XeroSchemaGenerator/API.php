<?php

/**
 * @package    xero-schemas
 * @author     Michael Calcinai <michael@calcin.ai>
 */

namespace Calcinai\XeroSchemaGenerator;

use Calcinai\XeroSchemaGenerator\ParsedObject\Enum;
use Calcinai\XeroSchemaGenerator\ParsedObject\Model;

class API
{

    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $namespace;


    /**
     * @var Model[]
     */
    private $models = [];

    /**
     * @var Enum[]
     */
    private $enums = [];


    public function __construct($name, $namespace)
    {
        $this->name = $name;
        $this->namespace = $namespace;
    }


    /**
     * @param Model $model
     */
    public function addModel(Model $model)
    {
        $this->models[$model->getSingularName()] = $model;

        //For debugging
        $model->printPropertyTable();
    }

    /**
     * @param Enum $enum
     */
    public function addEnum(Enum $enum)
    {
        $this->enums[$enum->getSingularName()] = $enum;
    }

    /**
     * @return ParsedObject\Model[]
     */
    public function getModels()
    {
        return $this->models;
    }

    public function getName()
    {
        return $this->name;
    }
}