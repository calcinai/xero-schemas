<?php

/**
 * @package    xero-schemas
 * @author     Michael Calcinai <michael@calcin.ai>
 */

namespace Calcinai\XeroSchemaGenerator\ParsedObject\Model;

use Calcinai\XeroSchemaGenerator\ParsedObject;
use Calcinai\XeroSchemaGenerator\ParsedObject\Model;

class Property extends ParsedObject
{

    const TYPE_STRING    = 'string';
    const TYPE_INT       = 'int';
    const TYPE_FLOAT     = 'float';
    const TYPE_BOOLEAN   = 'bool';
    const TYPE_DATE      = 'date';
    const TYPE_DATETIME  = 'datetime';
    const TYPE_GUID      = 'guid';

    private $links;
    private $type;

    /**
     * @var Model
     */
    private $parent_model;

    /**
     * @var Model
     */
    private $child_model;

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


    private $max_length;

    public function __construct($name, $description, $mandatory = false, $read_only = false, $deprecated = false)
    {

        parent::__construct($name);

        if (strpos($name, '(deprecated)')) {
            $name = str_replace('(deprecated)', '', $name);
            $deprecated = true;
        }

        $this->name = preg_replace('/[^a-z]+/i', '', ucwords($name));
        $this->description = $description;
        $this->links = [];

        $this->is_deprecated = $deprecated;
        $this->is_read_only = $read_only;
        $this->is_mandatory = $mandatory;

        $this->parseDescription();
    }

    public function addLink($name, $href)
    {
        $this->links[$href] = $name;
    }

    public function getType(){

        if($this->type === null){
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
     * @param Model $child_model
     * @return Property
     */
    public function setChildModel($child_model)
    {
        $this->child_model = $child_model;
        return $this;
    }

    /**
     * @return Model
     */
    public function getChildModel()
    {
        return $this->child_model;
    }


    private function parseDescription(){

        if (strpos($this->description, 'read only')) {
            $this->is_read_only = true;
        }

        if(preg_match('/max length\s?=\s?(?<length>\d+)/', $this->description, $matches)){
            $this->max_length = (int) $matches['length'];
        }

    }


    /**
     * A very ugly function to parse the property type based on a massive arbitrary set of rules.
     *
     * @return string
     */
    private function parseType()
    {

        //Spelling errors in the docs
        if (preg_match('/^((a\s)?bool|true\b|booelan)/i', $this->description))
            $type = self::TYPE_BOOLEAN;

        //Spelling errors in the docs
        if (preg_match('/UTC$/', $this->getName()))
            $type = self::TYPE_DATETIME;

        if (preg_match('/^Has[A-Z]\w+/', $this->getName()))
            $type = self::TYPE_BOOLEAN;

        if (preg_match('/(^sum\b|decimal|the\stotal|total\s(of|tax)|rate\b|amount\b)/i', $this->description)) {
            //If not the name of the field itself and not an 'amount type'
            if (stripos($this->name, 'name') === false &&
                stripos($this->name, 'description') === false &&
                stripos($this->description, 'amount type') === false) {
                    $type = self::TYPE_FLOAT;
            }
        }

        if (preg_match('/(alpha numeric)/i', $this->description))
            $type = self::TYPE_STRING;

        if (preg_match('/(^int(eger)?\b)/i', $this->description)){
            $type = self::TYPE_INT;
        }

        if (!isset($type) && preg_match('/(\bdate\b)/i', $this->description)){
            $type = self::TYPE_DATE;
        }

        if (preg_match('/Xero (generated )?(unique )?identifier/i', $this->description)){
            $type = self::TYPE_GUID;
        }

        if ($this->getParentModel()->getSingularName() . 'ID' == $this->getName()) {
            $type = self::TYPE_GUID;
            $this->getParentModel()->setGUIDProperty($this);
        }

        $result = null;

        if (!isset($type)) {
//            //The ns hint for searching, look for subclasses of this first.
//            $ns_hint = sprintf('%s\\%s', $this->getParentModel()->getNamespace(), $this->getParentModel()->getClassName());
//
//            if (preg_match('/see\s(?<model>[^.]+)/i', $this->getDescription(), $matches)) {
//
//                //Try NS'ing it with existing models... MNA htis is getting ugly.
//                foreach ($this->getParentModel()->getAPI()->getModels() as $model) {
//                    $class_name = $model->getClassName();
//                    $model_name = $matches['model'];
//                    if (strpos($model_name, $class_name) === 0) {
//                        //this means it starts with the model name
//                        $search_text = sprintf('%s\\%s', substr($model_name, 0, strlen($class_name)), substr($model_name, strlen($class_name)));
//                        $result = $this->getParentModel()->getAPI()->searchByKey(str_replace(' ', '', ucwords($search_text)), $this->getParentModel()->getNamespace());
//
//                    }
//                }
//
//            }
//
//            if ($result === null && substr_count($ns_hint, '\\') > 1) {
//                $parent_ns_hint = substr($ns_hint, 0, strrpos($ns_hint, '\\'));
//                $result = $this->getParentModel()->getAPI()->searchByKey($this->getName(), $parent_ns_hint);
//            }
//
//            if ($result === null)
//                $result = $this->getParentModel()->getAPI()->searchByKey($this->getName(), $ns_hint);
//
//            if ($result === null) {
//                foreach ($this->links as $link) {
//                    $search_text = str_replace(' ', '', ucwords($link['text']));
//
//                    $result = $this->getParentModel()->getAPI()->searchByKey($search_text, $ns_hint);
//
//                    //then try anchor
//                    if ($result === null) {
//                        if (preg_match('/#(?<anchor>.+)/i', $link['href'], $matches)) {
//                            $result = $this->getParentModel()->getAPI()->searchByKey($matches['anchor'], $ns_hint);
//                        }
//                    }
//                }
//            }
//
//            //Otherwise, just have a stab again, this needs to be after other references
//            if ($result === null) {
//                if (preg_match('/see\s(?<model>[^.]+)/i', $this->getDescription(), $matches)) {
//                    $result = $this->getParentModel()->getAPI()->searchByKey(str_replace(' ', '', ucwords($matches['model'])), $ns_hint);
//                }
//            }
//
//            //Look for pointy bracketed references
//            if ($result === null) {
//                if (preg_match('/<(?<model>[^>]+)>/i', $this->getDescription(), $matches)) {
//                    $result = $this->getParentModel()->getAPI()->searchByKey(str_replace(' ', '', ucwords($matches['model'])), $ns_hint);
//                }
//            }
//
//
//            //I have tried very hard to avoid special cases!
//            if ($result === null) {
//                if (preg_match('/^(?<model>Purchase|Sale)s?Details/i', $this->getName(), $matches)) {
//                    $result = $this->getParentModel()->getAPI()->searchByKey($matches['model'], $ns_hint);
//                }
//            }

        }


//        if ($result instanceof Enum)
//            $type = self::TYPE_ENUM;
//        elseif ($result instanceof Model) {
//            $type = self::TYPE_OBJECT;
//            $this->related_object = $result;
//
//            //If docs have case-typos in them, take the class name as authoritative.
//            if (strcmp($this->getName(), $this->related_object->getSingularName()) !== 0 && strcasecmp($this->getName(), $this->related_object->getSingularName()) === 0)
//                $this->name = $this->related_object->getSingularName();
//
//
//        }

        if (!isset($type)){
            $type = self::TYPE_STRING;
        }

        return $type;
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

}