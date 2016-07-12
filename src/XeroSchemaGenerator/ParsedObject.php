<?php

namespace Calcinai\XeroSchemaGenerator;

abstract class ParsedObject {

    /**
     * @var string
     */
    private $name;

    /**
     * @return string
     */
    public function getName() {
        return $this->name;
    }

    /**
     * @param string $name
     * @return static
     */
    public function setName($name) {
        $this->name = $name;
        return $this;
    }
}