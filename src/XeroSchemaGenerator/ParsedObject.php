<?php

namespace Calcinai\XeroSchemaGenerator;

abstract class ParsedObject {

    /**
     * @var string
     */
    protected $name;
    protected $raw_name;

    protected $aliases = [];

    public function __construct($raw_name) {
        $this->raw_name = $raw_name;

        $parsed = self::parseRawName($raw_name);
        $this->name = array_shift($parsed);
        $this->aliases += $parsed;
    }

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

    /**
     * Parse a raw anme into all possible names
     *
     * @param $raw_name
     * @return array
     */
    public static function parseRawName($raw_name){
        $names = [];

        foreach(preg_split('/\b(and|or)\b/', $raw_name) as $raw_name_part){
            //Name sure it's singular and title case
            $name = ucwords(Inflector::singularize($raw_name_part));
            //Remove spaces and non-a-z
            $names[] = preg_replace('/[^a-z]+/i', '', $name);
        }

        return $names;
    }
}