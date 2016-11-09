<?php
/**
 * @package    xero-schemas
 * @author     Michael Calcinai <michael@calcin.ai>
 */

namespace Calcinai\XeroSchemaGenerator\ParsedObject;

use Calcinai\XeroSchemaGenerator\ParsedObject;
use Calcinai\XeroSchemaGenerator\ParsedObject\Model\Property;

class Model extends ParsedObject
{

    /**
     * @var Property[]
     */
    private $properties;

    /**
     * @var array
     */
    private $methods;

    /**
     * @var string
     */
    private $url;

    /**
     * @var Model
     */
    private $parent_model;

    public $is_pagable;
    public $supports_pdf;

    public function __construct($raw_name)
    {
        parent::__construct($raw_name);

        $this->url = null;
        $this->methods = [];
        $this->properties = [];

        $this->is_pagable = false;
        $this->supports_pdf = false;
    }

    public function addProperty(Property $property)
    {
        $this->properties[$property->getName()] = $property;
    }


    /**
     * @return mixed
     */
    public function getURL()
    {
        return $this->url;
    }

    /**
     * @param mixed $url
     */
    public function setURL($url)
    {
        $this->url = $url;
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
            preg_match_all('/(?<methods>GET|PUT|POST|DELETE)/', $methods, $matches);
            $this->methods = array_unique($matches['methods']);
        }
    }


    //https://api.xero.com/api.xro/2.0/Contacts
    public function getResourceURI()
    {

        if (preg_match('#/[a-z]+.xro/[0-9\.]+/(?<uri>.+)#', $this->url, $matches))
            return $matches['uri'];

        //Otherwise default to name of object
        return $this->getName();
    }


    //Compare a string and see if it's the same name
    public function matchName($model_name)
    {
        $parsed = self::parseRawName($model_name);
        return in_array($this->name, $parsed) || in_array($this->collective_name, $parsed);
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
        printf("| %-" . ($total_row_width - 4) . "s |\n", $this->getName());
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

}