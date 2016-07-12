<?php
/**
 * @package    xero-schemas
 * @author     Michael Calcinai <michael@calcin.ai>
 */

namespace Calcinai\XeroSchemaGenerator;


class Inflector {

    /**
     * This function is based on Wave\Inflector::singularize().
     * It only contains a fraction of the rules from its predecessor, so only good for a quick basic singularisation.
     *
     * @param $string
     * @return mixed
     */
    public static function singularize($string) {
        $singular = array(
            '/(vert|ind)ices$/i'    => "$1ex",
            '/(alias)es$/i'         => "$1",
            '/(x|ch|ss|sh)es$/i'    => "$1",
            '/(s)eries$/i'          => "$1eries",
            '/(s)tatus$/i'          => "$1tatus",
            '/([^aeiouy]|qu)ies$/i' => "$1y",
            '/([lr])ves$/i'         => "$1f",
            '/([ti])a$/i'           => "$1um",
            '/(us)es$/i'            => "$1",
            '/(basis)$/i'           => "$1",
            '/([^s])s$/i'           => "$1"
        );

        // check for matches using regular expressions
        foreach($singular as $pattern => $result) {
            if(preg_match($pattern, $string))
                return preg_replace($pattern, $result, $string);
        }

        //Else return
        return $string;
    }

    public static function pluralize($string) {

        $plural = array(
            '/(quiz)$/i'                     => "$1zes",
            '/(matr|vert|ind)ix|ex$/i'       => "$1ices",
            '/(x|ch|ss|sh)$/i'               => "$1es",
            '/([^aeiouy]|qu)y$/i'            => "$1ies",
            '/(hive)$/i'                     => "$1s",
            '/(?:([^f])fe|([lr])f)$/i'       => "$1$2ves",
            '/(shea|lea|loa|thie)f$/i'       => "$1ves",
            '/sis$/i'                        => "ses",
            '/([ti])um$/i'                   => "$1a",
            '/(tomat|potat|ech|her|vet)o$/i' => "$1oes",
            '/(bu)s$/i'                      => "$1ses",
            '/(alias)$/i'                    => "$1es",
            '/(ax|test)is$/i'                => "$1es",
            '/(us)$/i'                       => "$1es",
            '/s$/i'                          => "s",
            '/$/'                            => "s"
        );

        // check for matches using regular expressions
        foreach($plural as $pattern => $result) {
            if(preg_match($pattern, $string))
                return preg_replace($pattern, $result, $string);
        }

        return $string;
    }

}