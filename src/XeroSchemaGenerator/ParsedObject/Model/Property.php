<?php

/**
 * @package    xero-schemas
 * @author     Michael Calcinai <michael@calcin.ai>
 */

namespace Calcinai\XeroSchemaGenerator\ParsedObject\Model;

use Calcinai\XeroSchemaGenerator\ParsedObject;
use Calcinai\XeroSchemaGenerator\ParsedObject\Enum;
use Calcinai\XeroSchemaGenerator\ParsedObject\Model;

class Property extends ParsedObject
{

    const TYPE_STRING = 'string';
    const TYPE_INT = 'int';
    const TYPE_FLOAT = 'float';
    const TYPE_BOOLEAN = 'bool';
    const TYPE_DATE = 'date';
    const TYPE_DATETIME = 'datetime';
    const TYPE_GUID = 'guid';

    const TYPE_OBJECT = 'object';
    const TYPE_ENUM = 'enum';

    private $links;
    private $type;

    /**
     * @var Model
     */
    private $parent_model;

    /**
     * @var Model
     */
    private $child_object;

    /**
     * @var bool
     */
    private $is_deprecated;

    /**
     * @var bool
     */
    private $is_mandatory;

    /**
     * @var bool
     */
    private $is_read_only;

    /**
     * @var string[]
     */
    private $sub_resource_methods;

    private $max_length;

    public function __construct($name, $description, $mandatory = false, $read_only = false, $deprecated = false)
    {

        parent::__construct($name);

        if (strpos($name, '(deprecated)')) {
            $name = str_replace('(deprecated)', '', $name);
            $deprecated = true;
        }

        $this->name = preg_replace('/[^a-z\d]+/i', '', ucwords($name));
        $this->description = trim($description);
        $this->links = [];
        $this->sub_resource_methods = [];

        $this->is_deprecated = $deprecated;
        $this->is_read_only = $read_only;
        $this->is_mandatory = $mandatory;

        $this->parseDescription();
    }

    public function addLink($name, $href)
    {
        $this->links[$href] = $name;
    }

    public function getType()
    {

        if ($this->type === null) {
            $this->type = $this->parseType();
        }

        return $this->type;
    }


    /**
     * @param Model $parent_model
     * @return Property
     */
    public function setParentModel($parent_model)
    {
        $this->parent_model = $parent_model;
        return $this;
    }

    /**
     * @return Model
     */
    public function getParentModel()
    {
        return $this->parent_model;
    }

    /**
     * @param Model $child_object
     * @return Property
     */
    public function setChildObject($child_object)
    {
        $this->child_object = $child_object;
        return $this;
    }

    /**
     * @return Model|Enum
     */
    public function getChildObject()
    {
        return $this->child_object;
    }


    private function parseDescription()
    {

        if (strpos($this->description, 'read only')) {
            $this->is_read_only = true;
        }

        if (preg_match('/max length\s?=\s?(?<length>\d+)/', $this->description, $matches)) {
            $this->max_length = (int)$matches['length'];
        }

    }


    /**
     * @return bool
     */
    public function isArray()
    {

        switch (true) {
            case stripos($this->getName(), 'status') !== false:
            case $this->getType() === self::TYPE_BOOLEAN:
            case $this->getType() === self::TYPE_ENUM:
            case $this->getType() === self::TYPE_STRING:
                return false;
            //This to to improve detection of names that are the same plural/sing
            case preg_match('/maximum of [2-9] <(?<model>[a-z]+)> elements/i', $this->getDescription()):
                return true;
            case in_array($this->name, ['PaymentTerms', 'Bills', 'Sales']):
                return false;
            default:
                return $this->getSingularName() !== $this->getName();
        }

    }

    /**
     * A very ugly function to parse the property type based on a massive arbitrary set of rules.
     *
     * Basically, the certainty goes down the further through the function you get
     *
     * @return string
     */
    public function parseType()
    {

        if ($this->getParentModel()->getSingularName() . 'ID' == $this->getName()) {
            $this->getParentModel()->setIdentifyingProperty($this);
            return self::TYPE_GUID;
        }

        if (preg_match('/Xero (generated )?(unique )?identifier/i', $this->description)) {
            return self::TYPE_GUID;
        }

        if (preg_match(sprintf('/A unique identifier for.+(%s)/i', $this->getParentModel()->getSingularName()), $this->description)) {
            $this->getParentModel()->setIdentifyingProperty($this);
            return self::TYPE_STRING;
        }

        if (preg_match('/ID$/', $this->name)) {
            return self::TYPE_GUID;
        }

        if (preg_match('/Code$/i', $this->getName())) {
            return self::TYPE_STRING;
        }

        if (preg_match('/(^int(eger)?\b)/i', $this->description)) {
            return self::TYPE_INT;
        }

        if (preg_match('/alpha numeric/i', $this->description)) {
            return self::TYPE_STRING;
        }

        if (preg_match('/(^sum\b|decimal|the\stotal|total\s(of|tax)|rate\b|amount\b)/i', $this->description)) {
            //If not the name of the field itself and not an 'amount type'
            if (stripos($this->name, 'name') === false &&
                stripos($this->name, 'description') === false &&
                stripos($this->description, 'amount type') === false
            ) {
                return self::TYPE_FLOAT;
            }
        }

        //Spelling errors in the docs
        if (preg_match('/UTC$/', $this->getName())) {
            return self::TYPE_DATETIME;
        }

        //Spelling errors in the docs
        if (preg_match('/^((a\s)?bool|true\b|booelan)/i', $this->description)) {
            return self::TYPE_BOOLEAN;
        }

        if (preg_match('/^Has[A-Z]\w+/', $this->getName())) {
            return self::TYPE_BOOLEAN;
        }

        if (preg_match('/(\bdate\b)/i', $this->description)) {
            return self::TYPE_DATE;
        }


        //This point on searches for related models/enums
        //Then the property name itself (root ns then child)
        $search_names[] = $this->getName();

        if (preg_match('/see\s(?<model>[^.]+)/i', $this->getDescription(), $matches)) {
            $search_names[] = $matches['model'];
        }

        //Look for pointy bracketed references
        if (preg_match('/<(?<model>[^>]+)>/i', $this->getDescription(), $matches)) {
            $search_names[] = $matches['model'];
        }

        //Stupid exception
        if (preg_match('/^(?<model>Purchase|Sale)s?Details/i', $this->getName(), $matches)) {
            $search_names[] = $matches['model'];
        }


        //then links
        $search_links = [];

        foreach ($this->links as $href => $name) {
            //Catch anchors - they're likely to be refences to types and codes (subschemas)
            //Don't allow circular refs
            if (preg_match('/#(?<anchor>.+)/i', $href, $matches) && $matches['anchor'] !== $this->getParentModel()->getName()) {
                $search_names[] = $matches['anchor'];
            } else {
                $search_links[] = $href;
            }
        }

        foreach ($search_names as $search_name) {
            //search for it
            if(null !== $this->child_object = $this->getParentModel()->getAPI()->searchByName($search_name)){
                break;
            }
        }

        //If not found by name, try the url.  Can be multiple models ont he same page so not always safe
        if($this->child_object === null){
            foreach ($search_links as $search_link) {
                //search for it
                if(null !== $this->child_object = $this->getParentModel()->getAPI()->searchByURL($search_link)){
                    break;
                }
            }
        }



        //See what was returned
        if ($this->child_object instanceof Enum) {
            return self::TYPE_ENUM;

        } elseif ($this->child_object instanceof Model) {

            //If docs have case-typos in them, take the class name as authoritative.
            if (strcmp($this->getName(), $this->child_object->getSingularName()) !== 0 && strcasecmp($this->getName(), $this->child_object->getSingularName()) === 0)
                $this->name = $this->child_object->getSingularName();

            return self::TYPE_OBJECT;
        }


        return self::TYPE_STRING;
    }

    public function isMandatory()
    {
        return $this->is_mandatory;
    }

    public function isReadOnly()
    {
        return $this->is_read_only;
    }

    /**
     * @return int|null
     */
    public function getMaxLength()
    {
        return $this->max_length;
    }

    /**
     * @param $method
     * @return Property
     */
    public function addSubResourceMethod($method)
    {
        $method = strtoupper($method);
        if(in_array($method, Model::$ALL_METHODS) && !in_array($method, $this->sub_resource_methods)){
            $this->sub_resource_methods[] = $method;
        }
        return $this;
    }

    /**
     * @return string[]
     */
    public function getSubResourceMethods()
    {
        return $this->sub_resource_methods;
    }

    /**
     * @param $method
     * @return bool
     */
    public function hasSubResourceMethod($method){
        return in_array($method, $this->sub_resource_methods);
    }

}