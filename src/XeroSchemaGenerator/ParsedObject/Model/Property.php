<?php

/**
 * @package    xero-schemas
 * @author     Michael Calcinai <michael@calcin.ai>
 */

namespace Calcinai\XeroSchemaGenerator\ParsedObject\Model;

class Property
{

    private $name;
    private $description;
    private $links;
    private $type;

    public $saves_directly;

    public function __construct($name, $description)
    {
        $this->name = preg_replace('/[^a-z]+/i', '', ucwords($name));
        $this->description = $description;
        $this->links = [];

        $this->saves_directly = false;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function addLink($name, $href)
    {
        $this->links[$href] = $name;
    }

    public function getType(){

        return 'string';

        if($this->type === null){
            $this->parseType();
        }

        return $this->type;
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
            $type = self::PROPERTY_TYPE_BOOLEAN;

        //Spelling errors in the docs
        if (preg_match('/UTC$/', $this->getName()))
            $type = self::PROPERTY_TYPE_TIMESTAMP;

        if (preg_match('/^Has[A-Z]\w+/', $this->getName()))
            $type = self::PROPERTY_TYPE_BOOLEAN;

        if (preg_match('/(^sum\b|decimal|the\stotal|total\s(of|tax)|rate\b|amount\b)/i', $this->description)) {
            //If not the name of the field itself and not an 'amount type'
            if (stripos($this->name, 'name') === false && stripos($this->name, 'description') === false && stripos($this->description, 'amount type') === false) {
                $type = self::PROPERTY_TYPE_FLOAT;
            }
        }

        if (preg_match('/(alpha numeric)/i', $this->description))
            $type = self::PROPERTY_TYPE_STRING;

        if (preg_match('/(^int(eger)?\b)/i', $this->description))
            $type = self::PROPERTY_TYPE_INT;

        if (!isset($type) && preg_match('/(\bdate\b)/i', $this->description))
            $type = self::PROPERTY_TYPE_DATE;

        if (preg_match('/Xero (generated )?(unique )?identifier/i', $this->description))
            $type = self::PROPERTY_TYPE_GUID;

        if ($this->getModel()->getClassName() . 'ID' == $this->getName()) {
            $type = self::PROPERTY_TYPE_GUID;
            $this->getModel()->setGUIDProperty($this);
        }

        if (preg_match('/(Code|ID)$/', $this->getName()))
            $type = self::PROPERTY_TYPE_STRING;

        $result = null;

        if (!isset($type)) {
            //The ns hint for searching, look for subclasses of this first.
            $ns_hint = sprintf('%s\\%s', $this->getModel()->getNamespace(), $this->getModel()->getClassName());

            if (preg_match('/see\s(?<model>[^.]+)/i', $this->getDescription(), $matches)) {

                //Try NS'ing it with existing models... MNA htis is getting ugly.
                foreach ($this->getModel()->getAPI()->getModels() as $model) {
                    $class_name = $model->getClassName();
                    $model_name = $matches['model'];
                    if (strpos($model_name, $class_name) === 0) {
                        //this means it starts with the model name
                        $search_text = sprintf('%s\\%s', substr($model_name, 0, strlen($class_name)), substr($model_name, strlen($class_name)));
                        $result = $this->getModel()->getAPI()->searchByKey(str_replace(' ', '', ucwords($search_text)), $this->getModel()->getNamespace());

                    }
                }

            }

            if ($result === null && substr_count($ns_hint, '\\') > 1) {
                $parent_ns_hint = substr($ns_hint, 0, strrpos($ns_hint, '\\'));
                $result = $this->getModel()->getAPI()->searchByKey($this->getName(), $parent_ns_hint);
            }

            if ($result === null)
                $result = $this->getModel()->getAPI()->searchByKey($this->getName(), $ns_hint);

            if ($result === null) {
                foreach ($this->links as $link) {
                    $search_text = str_replace(' ', '', ucwords($link['text']));

                    $result = $this->getModel()->getAPI()->searchByKey($search_text, $ns_hint);

                    //then try anchor
                    if ($result === null) {
                        if (preg_match('/#(?<anchor>.+)/i', $link['href'], $matches)) {
                            $result = $this->getModel()->getAPI()->searchByKey($matches['anchor'], $ns_hint);
                        }
                    }
                }
            }

            //Otherwise, just have a stab again, this needs to be after other references
            if ($result === null) {
                if (preg_match('/see\s(?<model>[^.]+)/i', $this->getDescription(), $matches)) {
                    $result = $this->getModel()->getAPI()->searchByKey(str_replace(' ', '', ucwords($matches['model'])), $ns_hint);
                }
            }

            //Look for pointy bracketed references
            if ($result === null) {
                if (preg_match('/<(?<model>[^>]+)>/i', $this->getDescription(), $matches)) {
                    $result = $this->getModel()->getAPI()->searchByKey(str_replace(' ', '', ucwords($matches['model'])), $ns_hint);
                }
            }


            //I have tried very hard to avoid special cases!
            if ($result === null) {
                if (preg_match('/^(?<model>Purchase|Sale)s?Details/i', $this->getName(), $matches)) {
                    $result = $this->getModel()->getAPI()->searchByKey($matches['model'], $ns_hint);
                }
            }

        }


        if ($result instanceof Enum)
            $type = self::PROPERTY_TYPE_ENUM;
        elseif ($result instanceof Model) {
            $type = self::PROPERTY_TYPE_OBJECT;
            $this->related_object = $result;

            //If docs have case-typos in them, take the class name as authoritative.
            if (strcmp($this->getName(), $this->related_object->getName()) !== 0 && strcasecmp($this->getName(), $this->related_object->getName()) === 0)
                $this->name = $this->related_object->getName();


        }

        if (!isset($type))
            $type = self::PROPERTY_TYPE_STRING;

        return $type;
    }
    
}