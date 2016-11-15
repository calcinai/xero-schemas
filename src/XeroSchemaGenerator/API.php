<?php

/**
 * @package    xero-schemas
 * @author     Michael Calcinai <michael@calcin.ai>
 */

namespace Calcinai\XeroSchemaGenerator;

use Calcinai\XeroSchemaGenerator\ParsedObject\Enum;
use Calcinai\XeroSchemaGenerator\ParsedObject\Model;
use ICanBoogie\Inflector;

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


    private $search_keys;

    private $is_indexed;


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
        $model->setAPI($this);
        $this->models[$model->getSingularName()] = $model;
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


    /**
     * @return ParsedObject\Enum[]
     */
    public function getEnums()
    {
        return $this->enums;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    public function searchByName($search_name)
    {

        $inflector = Inflector::get();
        $search_arr = [
            $search_name,
            $inflector->singularize($search_name),
            $inflector->pluralize($search_name)
        ];

        foreach($this->models as $model){
            if(in_array($model->getName(), $search_arr)){
                return $model;
            }
        }

        foreach ($this->enums as $enum) {
            if(in_array($enum->getName(), $search_arr)){
                return $enum;
            }
        }

        return null;

    }

    public function searchByURL($url)
    {
        $parsed_search_url = parse_url($url);

        foreach($this->models as $model){
            $parsed_model_url = parse_url($model->getDocumentationURI());

            //Some real inconsistencies in the documentation
            if ($parsed_model_url['host'] === $parsed_search_url['host'] &&
                trim($parsed_model_url['path'], '/') === trim($parsed_search_url['path'], '/')){
                return $model;
            }
        }

        return null;

    }


}