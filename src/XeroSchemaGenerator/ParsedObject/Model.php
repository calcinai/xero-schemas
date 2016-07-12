<?php
/**
 * @package    xero-schemas
 * @author     Michael Calcinai <michael@calcin.ai>
 */

namespace Calcinai\XeroSchemaGenerator\ParsedObject;

use Calcinai\XeroSchemaGenerator\ParsedObject;

class Model extends ParsedObject {

    private $properties;
    private $methods;
    private $url;

    public function __construct($raw_name) {
        parent::__construct($raw_name);

        $this->url = null;
        $this->methods = [];
        $this->properties = [];
    }

    public function addProperty(ParsedObject\Model\Property $property) {
        $this->properties[$property->getName()] = $property;
    }


    /**
     * @return mixed
     */
    public function getURL() {
        return $this->url;
    }

    /**
     * @param mixed $url
     */
    public function setURL($url) {
        $this->url = $url;
    }


    /**
     * @return mixed
     */
    public function getMethods() {
        return $this->methods;
    }


    /**
     * Set accepted methods for API call.  Only going to be set on models that can be referenced directly
     *
     * @param mixed $methods
     */
    public function setMethods($methods) {

        if(is_array($methods)){
            $this->methods = $methods;
        } else {
            preg_match_all('/(?<methods>GET|PUT|POST|DELETE)/', $methods, $matches);
            $this->methods = array_unique($matches['methods']);
        }
    }


    //https://api.xero.com/api.xro/2.0/Contacts
    public function getResourceURI(){

        if(preg_match('#/[a-z]+.xro/[0-9\.]+/(?<uri>.+)#', $this->url, $matches))
            return $matches['uri'];

        //Otherwise default to name of object
        return $this->getName();
    }



    //Compare a string and see if it's the same name
    public function matchName($model_name) {
        return in_array($this->name, self::parseRawName($model_name));
    }

}