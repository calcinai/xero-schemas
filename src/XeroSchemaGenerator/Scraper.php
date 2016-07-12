<?php

/**
 * @package    xero-schemas
 * @author     Michael Calcinai <michael@calcin.ai>
 */

namespace Calcinai\XeroSchemaGenerator;

use Calcinai\XeroSchemaGenerator\ParsedObject\Enum;
use Calcinai\XeroSchemaGenerator\ParsedObject\Model;
use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;

class Scraper {

    const USER_AGENT = 'XeroSchemaGenerator/1.0 (https://github.com/calcinai/xero-schemas)';

    private $documentation_base;
    private $client;

    public function __construct($documentation_base) {
        $this->documentation_base = $documentation_base;

        $this->client = new Client();
        $this->client->setHeader('User-Agent', self::USER_AGENT);
    }

    public function scrapeEnums(API $api, $types_uri) {

        $crawler = $this->client->request('GET', sprintf('%s/%s', $this->documentation_base, $types_uri));

        $crawler->filter('.apidoc-table')->each(
            function (Crawler $table_node) use ($api) {

            //Unfortunately I couldn't get consistent results with xpath.
            $section_name = $table_node->previousAll()->filter('h3,h4')->first()->text();
            $subsection_node = $table_node->previousAll()->filter('p')->first();

            if(strlen($subsection_node->text()) > 100){
                //If the name is too long, it's probably a description, so go back tot he last strong (and hope).
                $subsection_node = $table_node->previousAll()->filter('strong')->first();
            }

            $subsection_name = $subsection_node->text();
            var_dump($subsection_name);

            //Save anchors for search keys
            $subsection_anchors = [];
            $subsection_node->filter('a[name]')->each(function(Crawler $node) use(&$subsection_anchors){
                $subsection_anchors[] = $node->attr('name');
            });

            //Skip empty sections
            if(empty($subsection_name)){
                //Equivalent of continue; from closure context.
                return true;
            }

            //This is all we have to go by at the moment!
            if($section_name === $subsection_name){
                $found_object = new Model();
                $api->addModel($found_object);
                $this->parseModelTable($found_object, $table_node);
            } else {
                $found_object = new Enum();
                $api->addEnum($found_object);
                $this->parseEnumTable($found_object, $table_node);
            }

            $found_object->setName($section_name);


            return true;
        });
    }


    private function parseEnumTable(Enum $enum, Crawler $table_node){


        $table_node->filter('tr')->each(function(Crawler $table_row_node, $row_index) use($enum){

            $table_column_nodes = $table_row_node->filter('td');

            if($table_column_nodes->eq(0)->filter('em')->count()){
                return true;
            }

            if($table_row_node->filter('> [style]')->count()){
                echo $enum->getName();
                echo "SKIPPINT CAUSE SYLR: \n";

                return true;
            }

//            //Why is this table different to every other one in the docs?
//            $swap_name_description = $current_object->getName() == 'SystemAccounts';
//            $skip_first_row = $current_object->getName() == 'SystemAccounts';
//
//            if($skip_first_row && $row_index == 0)
//                return false;
//
//            $children = $node->children();
//            $has_description = count($children) > 1;
//
//            $name = $children->eq(0)->text();
//            $description =  $has_description ? $children->eq(1)->text() : null;
//
//            if($swap_name_description)
//                list($name, $description) = array($description, $name);
//
//
//
//            $enum->addValue(new Enum\Value($name, $description));


            return true;
        });




//        if($children->eq(0)->filter('em')->count()){
//            //Skip if the first child has an em in it
//        } else {
//            $current_object->addValue($name, $description);
//        }


    }














    public function scrapeModels(API $api, $model_base_uri, $model_uris) {


    }




    private function parseModelTable(Model $model, Crawler $table_node){

    }


}