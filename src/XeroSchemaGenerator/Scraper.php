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

class Scraper
{

    const USER_AGENT = 'Mozilla/5.0 (compatible; XeroSchemaGenerator/1.0; +https://github.com/calcinai/xero-schemas)';

    private $documentation_base;
    private $client;

    public function __construct($documentation_base)
    {
        $this->documentation_base = $documentation_base;

        $this->client = new Client();
        $this->client->setHeader('User-Agent', self::USER_AGENT);
    }


    /**
     * @param API $api
     * @param $types_uri
     */
    public function scrapeEnums(API $api, $types_uri)
    {

        $crawler = $this->client->request('GET', sprintf('%s/%s', $this->documentation_base, $types_uri));

        $crawler->filter('.apidoc-table')->each(
            function (Crawler $table_node) use ($api) {

                //Unfortunately I couldn't get consistent results with xpath.
                $section_name = $table_node->previousAll()->filter('h3,h4')->first()->text();
                $subsection_node = $table_node->previousAll()->filter('p')->first();

                $section_name = str_replace("\xc2\xa0", ' ', $section_name); //remove &nbsp;

                if (strlen($subsection_node->text()) > 100) {
                    //If the name is too long, it's probably a description, so go back to the last strong (and hope).
                    $subsection_node = $table_node->previousAll()->filter('strong')->first();
                }

                $subsection_name = $subsection_node->text();

                //Save anchors for search keys
                $subsection_anchors = [];
                $subsection_node->filter('a[name]')->each(function (Crawler $node) use (&$subsection_anchors) {
                    $subsection_anchors[] = $node->attr('name');
                });

                //Skip empty sections
                if (empty($subsection_name)) {
                    //Equivalent of continue; from closure context.
                    return;
                }

                //This is all we have to go by at the moment!
                if ($section_name === $subsection_name) {
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
    private function parseEnumTable(Enum $enum, Crawler $table_node)
    {

        $table_node->filter('tr')->each(function (Crawler $table_row_node, $row_index) use ($enum) {

            static $swap_columns = false;

            $table_column_nodes = $table_row_node->filter('td');

            //On the first row, check that it's not a header.  Docs don't use headers so this is crude.
            if ($row_index === 0 && false !== strpos($table_column_nodes->eq(0)->attr('style'), 'background')) {
                $swap_columns = true;
                return;
            }

            //If there's a description half way down the table, skip.
            if ($table_column_nodes->eq(0)->filter('em')->count()) {
                return;
            }

            //Get name from first column
            $name = $table_column_nodes->eq(0)->text();
            //If there's a second, use it as description
            $description = $table_column_nodes->count() > 1 ? $table_column_nodes->eq(1)->text() : null;

            if ($swap_columns) {
                list($description, $name) = [$name, $description];
            }

            $enum->addValue(new Enum\Value($name, $description));

            return;
        });

    }


    public function scrapeModels(API $api, $model_base_uri, $model_uris)
    {

        foreach ($model_uris as $model_uri) {

            $full_uri = sprintf('%s/%s/%s/', $this->documentation_base, $model_base_uri, $model_uri);
            $crawler = $this->client->request('GET', $full_uri);

            //Take only the first line
            $page_heading = strtok($crawler->filter('.entry-content > h1')->first()->text(), "\n");

            $primary_model = new Model($page_heading);
            $current_model = $primary_model;

            $crawler->filter('.apidoc-table')->each(function (Crawler $table_node, $table_index) use ($api, $page_heading, $primary_model, &$current_model) {

                //This is the header table with meta inf
                if ($table_index === 0) {
                    $table_node->filter('tr')->each(function (Crawler $table_row) use ($primary_model) {
                        $table_columns = $table_row->children();
                        if ($table_columns->count() == 0) {
                            return;
                        }

                        switch (strtolower($table_columns->eq(0)->text())) {
                            case 'url':
                                $primary_model->setFullURL($table_columns->eq(1)->text());
                                break;
                            case 'methods supported':
                                $primary_model->setMethods($table_columns->eq(1)->text());
                                break;
                        }
                    });
                    return;
                }

                //Go backward to find headings
                $section_nodes = $table_node->previousAll()->filter('h3,h4');
                if ($section_nodes->count() === 0) {
                    return;
                }

                $section_name = str_replace("\xc2\xa0", ' ', $section_nodes->first()->text()); //remove &nbsp;
//              $subsection_name = $table_node->previousAll()->filter('p')->first()->text();

                if (stripos($section_name, 'example') === 0) {
                    return;
                }

                if (preg_match('/(xml )?elements( returned)? for( adding)?( an| get| a)? (?<model_name>[\w\s]+)/i', $section_name, $matches)) {

                    //too messy to add to the above regex - juse override model name.  This will pick off any lower case words preceding the actual name.
                    if (preg_match('/^[a-z\s]+(?<model_name>[A-Z][\w\s]+)/', $matches['model_name'], $uc_matches)) {
                        $matches = $uc_matches;
                    }

                    //If the table that's being processed os for a different model, create a new one
                    if (false === $current_model->matchName($matches['model_name'])) {
                        $current_model = new Model($matches['model_name']);
                        $current_model->setParentModel($primary_model);
                    }

                    $this->parseModelTable($current_model, $table_node);
                }

                //Add (might happen more than once for the same model but doesn't matter).
                $api->addModel($current_model);

            });

            //Have a look on the page for any hints that a sub object has a URI
            //PUT CreditNotes/{CreditNoteID}/Allocations
            $crawler->filter('p')->each(function (Crawler $p_node) use ($primary_model) {
                if (preg_match('#(?<method>GET|PUT|DELETE)\s?/?(?<primary_model>[a-z]+)/[^/]+/(?<secondary_model>[a-z]+)/?#i', $p_node->text(), $matches)) {
                    if ($primary_model->getCollectiveName() === $matches['primary_model'] && $primary_model->hasProperty($matches['secondary_model'])) {
                        $primary_model->getProperty($matches['secondary_model'])->saves_directly = true;
                    }
                }

                if (strpos($p_node->text(), 'returned as PDF') !== false) {
                    //Assume that it does support it.
                    $primary_model->supports_pdf = true;
                }
            });

        }
    }


    private function parseModelTable(Model $model, Crawler $table_node)
    {

        $mandatory = false;
        $read_only = false;

        $table_node->filter('tr')->each(function (Crawler $table_row_node) use ($model, &$mandatory, &$read_only) {

            $table_column_nodes = $table_row_node->filter('td');

            //Breaks in the table with colspans
            if ($table_column_nodes->count() === 0) {
                return;
            } elseif ($table_column_nodes->count() !== 2) {

                $row_text = $table_column_nodes->text();

                //best that can be done really..
                if (preg_match('/at least (one|two)/i', $row_text)) {
                    $mandatory = false;
                    $read_only = false;
                } elseif (preg_match('/^Either/i', $row_text)) {
                    $mandatory = false;
                    $read_only = false;
                } elseif (preg_match('/(required|mandatory)/i', $row_text)) {
                    $mandatory = true;
                    $read_only = false;
                }

                if (preg_match('/(optional|recommended)/i', $row_text)) {
                    $mandatory = false;
                    $read_only = false;
                } elseif (preg_match('/(updatable)/i', $row_text)) {
                    $read_only = false;
                    $mandatory = false;
                } elseif (preg_match('/(only )?returned on (a )?GET requests?( only)?\.?$/i', $row_text)) {
                    $read_only = true;
                    $mandatory = false;
                }

                if (preg_match('/(PUT|POST)/i', $row_text)) {
                    $read_only = false;
                }

                //Stop processing.
                return;
            }


            //Get name from first column
            $property_name = $table_column_nodes->eq(0)->text();
            //If there's a second, use it as description
            $description = $table_column_nodes->count() > 1 ? $table_column_nodes->eq(1)->text() : null;


            //@todo Here should handle making these methods available on the models
            if (preg_match('/^(?<special_function>where|order|sort|filter|page|offset|pagesize|modified( after)?|record filter|include ?archived)$/i', $property_name, $matches) !== 0) {

                if (isset($matches['special_function'])) {
                    switch ($matches['special_function']) {
                        case 'page':
                            $model->is_pagable = true;
                            break;
                    }
                }

                return;
            }


            //if there are commas in the name, it needs splitting.  eg. AddressLine 1,2,3,4
            if (false !== strpos($property_name, ',')) {
                list($property_name, $suffixes) = explode(' ', $property_name);
                foreach (explode(',', $suffixes) as $suffix) {
                    $model->addProperty(new Model\Property($property_name . $suffix, $description, $mandatory, $read_only));
                }
            } else {
                //this is the normal case, where there's only one property (or <X> or <Y>)
                foreach (preg_split('/(>\s*or\s*<|\s&\s)/', $property_name) as $column_name) {
                    //make it into another param
                    $property = new Model\Property($column_name, $description, $mandatory, $read_only);

                    //add links to property (for parsing types)
                    $table_column_nodes->filter('a')->each(function (Crawler $node) use ($property) {
                        $property->addLink($node->text(), $node->attr('href'));
                    });

                    $model->addProperty($property);
                }


            }

        });
    }

}