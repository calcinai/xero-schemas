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

    /**
     * @param API $api
     * @param $types_uri
     */
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

            //Save anchors for search keys
            $subsection_anchors = [];
            $subsection_node->filter('a[name]')->each(function(Crawler $node) use(&$subsection_anchors){
                $subsection_anchors[] = $node->attr('name');
            });

            //Skip empty sections
            if(empty($subsection_name)){
                //Equivalent of continue; from closure context.
                return;
            }

            //This is all we have to go by at the moment!
            if($section_name === $subsection_name){
                $model = new Model($section_name);
                $api->addModel($model);
                $this->parseModelTable($model, $table_node);
            } else {
                $enum = new Enum($section_name);
                $api->addEnum($enum);
                $this->parseEnumTable($enum, $table_node);
            }



            return;
        });
    }


    /**
     * @param Enum $enum
     * @param Crawler $table_node
     */
    private function parseEnumTable(Enum $enum, Crawler $table_node){

        $table_node->filter('tr')->each(function(Crawler $table_row_node, $row_index) use($enum){

            static $swap_columns = false;

            $table_column_nodes = $table_row_node->filter('td');

            //On the first row, check that it's not a header.  Docs don't use headers so this is crude.
            if($row_index === 0 && false !== strpos($table_column_nodes->eq(0)->attr('style'), 'background')){
                $swap_columns = true;
                return;
            }

            //If there's a description half way down the table, skip.
            if($table_column_nodes->eq(0)->filter('em')->count()){
                return;
            }

            //Get name from first column
            $name = $table_column_nodes->eq(0)->text();
            //If there's a second, use it as description
            $description =  $table_column_nodes->count() > 1 ? $table_column_nodes->eq(1)->text() : null;

            if($swap_columns){
                list($description, $name) = [$name, $description];
            }

            $enum->addValue(new Enum\Value($name, $description));

            return;
        });

    }



    public function scrapeModels(API $api, $model_base_uri, $model_uris) {

        foreach($model_uris as $model_uri){

            $full_uri = sprintf('%s/%s/%s/', $this->documentation_base, $model_base_uri, $model_uri);
            $crawler = $this->client->request('GET', $full_uri);

            //Take only the first line
            $page_heading = strtok($crawler->filter('.entry-content > h1')->first()->text(), "\n");

            $primary_model = new Model($page_heading);
            $current_model = $primary_model;

            $crawler->filter('.apidoc-table')->each(function (Crawler $table_node, $table_index) use ($api, $page_heading, $primary_model, &$current_model) {

                //This is the header table with meta inf
                if($table_index === 0){
                    $table_node->filter('tr')->each(function(Crawler $table_row) use($primary_model){
                        $table_columns = $table_row->children();
                        if($table_columns->count() == 0){
                            return;
                        }

                        switch(strtolower($table_columns->eq(0)->text())){
                            case 'url':
                                $primary_model->setUrl($table_columns->eq(1)->text());
                                break;
                            case 'methods supported':
                                $primary_model->setMethods($table_columns->eq(1)->text());
                                break;
                        }
                    });
                    return;
                }


                $section_name = $table_node->previousAll()->filter('h3,h4')->first()->text();
//                $subsection_name = $table_node->previousAll()->filter('p')->first()->text();

                if(stripos($section_name, 'example') === 0){
                    return;
                }

                if(preg_match('/(xml )?elements( returned)? for( adding)?( an| get| a)? (?<model_name>[\w\s]+)/i', $section_name, $matches)){

                    //too messy to add to the above regex - juse override model name.  This will pick off any lower case words preceding the actual name.
                    if(preg_match('/^[a-z\s]+(?<model_name>[A-Z][\w\s]+)/', $matches['model_name'], $uc_matches)){
                        $matches = $uc_matches;
                    }

                    //If the table that's being processed os for a different model, create a new one
                    if(false === $current_model->matchName($matches['model_name'])){
                        $current_model = new Model($matches['model_name']);
                    }

                    $this->parseModelTable($current_model, $table_node);
                }

                //Add (might happen more than once for the same model but doesn't matter).
                $api->addModel($current_model);

            });
        }
    }




    private function parseModelTable(Model $model, Crawler $table_node) {

        $table_node->filter('tr')->each(function (Crawler $table_row_node, $row_index) use ($model) {

            $table_column_nodes = $table_row_node->filter('td');

            //Get name from first column
            $name = $table_column_nodes->eq(0)->text();
            //If there's a second, use it as description
            $description = $table_column_nodes->count() > 1 ? $table_column_nodes->eq(1)->text() : null;


            //if there are commas in the name, it needs splitting.  eg. AddressLine 1,2,3,4
            if(false !== strpos($name, ',')) {
                list($name, $suffixes) = explode(' ', $name);
                foreach(explode(',', $suffixes) as $suffix) {
                    $model->addProperty(new Model\Property($name . $suffix, $description));
                }
            } else {
                //this is the normal case, where there's only one property
                $model->addProperty(new Model\Property($name, $description));
            }

        });
    }

}