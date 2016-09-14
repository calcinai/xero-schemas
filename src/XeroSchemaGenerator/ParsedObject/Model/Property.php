<?php

/**
 * @package    xero-schemas
 * @author     Michael Calcinai <michael@calcin.ai>
 */

namespace Calcinai\XeroSchemaGenerator\ParsedObject\Model;

class Property {

    private $name;
    private $description;
    private $links;

    public $saves_directly;

    public function __construct($name, $description) {
        $this->name = preg_replace('/[^a-z]+/i', '', ucwords($name));
        $this->description = $description;
        $this->links = [];

        $this->saves_directly = false;
    }

    public function getName() {
        return $this->name;
    }


    public function addLink($name, $href){
        $this->links[$href] = $name;
    }

}