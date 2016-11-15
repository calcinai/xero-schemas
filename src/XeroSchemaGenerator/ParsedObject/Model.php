<?php
/**
 * @package    xero-schemas
 * @author     Michael Calcinai <michael@calcin.ai>
 */

namespace Calcinai\XeroSchemaGenerator\ParsedObject;

use Calcinai\XeroSchemaGenerator\API;
use Calcinai\XeroSchemaGenerator\ParsedObject;
use Calcinai\XeroSchemaGenerator\ParsedObject\Model\Property;

class Model extends ParsedObject
{

    /**
     * @var API
     */
    private $api;

    /**
     * @var Property[]
     */
    private $properties = [];

    /**
     * @var
     */
    private $guid_property;

    /**
     * @var array
     */
    private $methods = [];

    /**
     * @var string
     */
    private $full_url;

    /**
     * @var string
     */
    private $base_path;

    /**
     * @var string
     */
    private $version;

    /**
     * @var string
     */
    private $resource_uri;

    /**
     * @var Model
     */
    private $parent_model;

    /**
     * @var bool
     */
    public $is_pagable;

    /**
     * @var bool
     */
    public $supports_pdf;


    const METHOD_GET    = 'GET';
    const METHOD_PUT    = 'PUT';
    const METHOD_POST   = 'POST';
    const METHOD_DELETE = 'DELETE';

    /**
     * @var array
     */
    static $ALL_METHODS = [
        self::METHOD_GET,
        self::METHOD_PUT,
        self::METHOD_POST,
        self::METHOD_DELETE
    ];

    public function __construct($raw_name)
    {
        parent::__construct($raw_name);

        $this->is_pagable = false;
        $this->supports_pdf = false;
    }

    public function addProperty(Property $property)
    {
        $property->setParentModel($this);
        $this->properties[$property->getName()] = $property;
    }


    /**
     * @return mixed
     */
    public function getFullURL()
    {
        return $this->full_url;
    }

    /**
     * @param mixed $full_url
     */
    public function setFullURL($full_url)
    {
        $this->full_url = $full_url;

        if (preg_match('#(?<base_path>/[a-z]+.xro)/(?<version>[0-9\.]+)(?<uri>/.+)#', $this->full_url, $matches)){
            $this->base_path = $matches['base_path'];
            $this->version = $matches['version'];
            $this->resource_uri = $matches['uri'];
        }

    }


    /**
     * @return mixed
     */
    public function getMethods()
    {
        return $this->methods;
    }


    /**
     * Set accepted methods for API call.  Only going to be set on models that can be referenced directly
     *
     * @param mixed $methods
     */
    public function setMethods($methods)
    {

        if (is_array($methods)) {
            $this->methods = $methods;
        } else {
            $all_methods = implode('|', self::$ALL_METHODS);
            preg_match_all("/(?<methods>$all_methods)/", $methods, $matches);
            $this->methods = array_unique($matches['methods']);
        }
    }


    //https://api.xero.com/api.xro/2.0/Contacts
    public function getResourceURI()
    {
        return $this->resource_uri;
    }


    //Compare a string and see if it's the same name
    public function matchName($model_name)
    {
        $parsed = self::parseRawName($model_name);
        return in_array($this->singular_name, $parsed) || in_array($this->collective_name, $parsed);
    }

    /**
     * @param $property_name
     * @return bool
     */
    public function hasProperty($property_name)
    {
        return isset($this->properties[$property_name]);
    }

    /**
     * @param $property_name
     * @return Property
     */
    public function getProperty($property_name)
    {
        return $this->properties[$property_name];
    }

    /**
     * @return array|Model\Property[]
     */
    public function getProperties()
    {
        return $this->properties;
    }

    /**
     * @param Model $parent_model
     */
    public function setParentModel(Model $parent_model)
    {
        $this->parent_model = $parent_model;
    }


    /**
     * @param Property $property
     */
    public function setGUIDProperty(Property $property)
    {
        $this->guid_property = $property;
    }

    /**
     * @return Property
     */
    public function getGUIDProperty()
    {
        return $this->guid_property;
    }

    /**
     * @return API
     */
    public function getAPI()
    {
        return $this->api;
    }

    /**
     * @param API $api
     * @return $this
     */
    public function setAPI(API $api)
    {
        $this->api = $api;
        return $this;
    }



    public function getDescriptionForMethod($method)
    {
        switch($method){
            case self::METHOD_GET:
                preg_match('/.*(retrieve|get).*/i', $this->description, $matches);
                break;
            case self::METHOD_PUT:
                preg_match('/.*create.*/i', $this->description, $matches);
                break;
            case self::METHOD_POST:
                preg_match('/.*update.*/i', $this->description, $matches);
                break;
            case self::METHOD_DELETE:
                preg_match('/.*delete.*/i', $this->description, $matches);
                break;
        }

        //If a keyword is found, return that line
        if(isset($matches[0])){
            return $matches[0];
        }

        //Generic description
        return sprintf('%s a %s', ucfirst($method), $this->getSingularName());
    }


    /**
     * Pretty ugly eh!
     * For debugging
     *
     */
    public function printPropertyTable()
    {
        $rows = array();
        $column_sizes = array();

        foreach ($this->properties as $key => $property) {
            $rows[$key] = array($property->getName(), $string = substr(preg_replace('/[^\w\s.\-\(\)]|\n/', '', $property->getDescription()), 0, 100));

            foreach ($rows[$key] as $column_index => $column) {
                $column_sizes[$column_index] = max(isset($column_sizes[$column_index]) ? $column_sizes[$column_index] : 0, iconv_strlen($column));
            }
        }
        //Cannot echo the data types here.  They are lazily calculated after all the models are aware of each other.
        $total_row_width = array_sum($column_sizes) + count($column_sizes) * 3 + 1;
        echo str_repeat('-', $total_row_width) . "\n";
        printf("| %-" . ($total_row_width - 4) . "s |\n", $this->getSingularName());
        echo str_repeat('-', $total_row_width) . "\n";
        foreach ($rows as $row) {
            echo '|';
            foreach ($row as $column_index => $column) {
                printf(' %-' . $column_sizes[$column_index] . 's |', $column);
            }
            echo "\n";
        }
        echo str_repeat('-', $total_row_width) . "\n\n";


    }

    public function supportsMethod($method)
    {
        return in_array($method, $this->methods);
    }

}