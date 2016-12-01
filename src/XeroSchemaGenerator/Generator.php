<?php
/**
 * @package    xero-schemas
 * @author     Michael Calcinai <michael@calcin.ai>
 */

namespace Calcinai\XeroSchemaGenerator;

use Calcinai\Strut\Definitions\BodyParameter;
use Calcinai\Strut\Definitions\Definitions;
use Calcinai\Strut\Definitions\ExternalDocs;
use Calcinai\Strut\Definitions\HeaderParameterSubSchema;
use Calcinai\Strut\Definitions\Info;
use Calcinai\Strut\Definitions\Operation;
use Calcinai\Strut\Definitions\PathItem;
use Calcinai\Strut\Definitions\PathParameterSubSchema;
use Calcinai\Strut\Definitions\Paths;
use Calcinai\Strut\Definitions\QueryParameterSubSchema;
use Calcinai\Strut\Definitions\Response;
use Calcinai\Strut\Definitions\Responses;
use Calcinai\Strut\Definitions\Schema;
use Calcinai\Strut\Definitions\Schema\Properties\Properties;
use Calcinai\Strut\Swagger;
use Calcinai\XeroSchemaGenerator\ParsedObject\Enum;
use Calcinai\XeroSchemaGenerator\ParsedObject\Model;
use Calcinai\XeroSchemaGenerator\ParsedObject\Model\Property;

class Generator
{

    public function generate(API $api)
    {
        $related_model_tag = 'x-related-model';

        $swagger = new Swagger();
        $swagger->setInfo(
            Info::create()
                ->setTitle($api->getName())
                ->setVersion($api->getVersion())
        )
            ->setHost('api.xero.com')//This needs to get overridden for partner APIs, but needs somehting as a base for valid swagger
            ->setBasePath($api->getBasePath())
            ->addScheme('https')
            ->setConsumes(['text/xml', 'application/json'])
            ->setProduces(['text/xml', 'application/json']);


        $swagger->setPaths(
            $paths = Paths::create()
        );

        $swagger->setDefinitions(
            $definitions = Definitions::create()
        );

        foreach ($api->getModels() as $model) {

            //Make sure all property types are parsed first, as some have run-on effects.
            foreach ($model->getProperties() as $property) {
                $property->parseType();
            }

            $definitions->set($model->getSingularName(),
                $schema_model = Schema::create()
                    ->setExternalDocs(ExternalDocs::create()
                        ->setUrl($model->getDocumentationURI())
                    )
            );


            //TODO - de-nest some of these.
            if ($model->getResourceURI() !== null) {

                $paths->set($model->getResourceURI(),
                    $path_item_schema = PathItem::create()->set($related_model_tag, $model->getSingularName())
                );


                //GET /PrimaryModel
                if ($model->hasMethod(Model::METHOD_GET)) {
                    $path_item_schema->setGet($this->buildGetAllOperation($model));
                }

                //PUT /PrimaryModel
                if ($model->hasMethod(Model::METHOD_PUT)) {
                    $path_item_schema->setPut($this->buildPutOperation($model));
                }

                //POST /PrimaryModel
                if ($model->hasMethod(Model::METHOD_POST)) {
                    $path_item_schema->setPost($this->buildPostOperation($model));
                }


                if ($model->getIdentifyingProperty() !== null) {

                    $paths->set(sprintf('%s/{%s}', $model->getResourceURI(), $model->getIdentifyingProperty()->getSingularName()),
                        $path_item_specific_schema = PathItem::create()->set($related_model_tag, $model->getSingularName())
                    );

                    $model_path_item_parameter = PathParameterSubSchema::create()
                        ->setName($model->getIdentifyingProperty()->getSingularName())
                        ->setType('string')->setFormat('uuid')
                        ->setRequired(true);

                    //GET /PrimaryModel/{PrimaryModelID}
                    if ($model->hasMethod(Model::METHOD_GET)) {
                        $path_item_specific_schema->setGet(
                            $this->buildGetOperation($model)->addParameter($model_path_item_parameter)
                        );
                    }

                    //POST /PrimaryModel/{PrimaryModelID}
                    if ($model->hasMethod(Model::METHOD_POST)) {
                        $path_item_specific_schema->setPost(
                            $this->buildPostOperation($model)->addParameter($model_path_item_parameter)
                        );
                    }

                    //DELETE /PrimaryModel/{PrimaryModelID}
                    if ($model->hasMethod(Model::METHOD_DELETE)) {
                        $path_item_specific_schema->setDelete(
                            $this->buildDeleteOperation($model)->addParameter($model_path_item_parameter)
                        );
                    }


                    foreach ($model->getProperties() as $property) {
                        if (count($property->getSubResourceMethods()) === 0) {
                            continue;
                        }

                        $sub_model = $property->getChildObject();

                        $paths->set(sprintf('%s/{%s}/%s',
                            $model->getResourceURI(), $model->getIdentifyingProperty()->getSingularName(),
                            $property->getCollectiveName()),
                            $path_item_sub_schema = PathItem::create()->set($related_model_tag, $model->getSingularName())
                        );

                        //PUT /PrimaryModel/{PrimaryModelID}/SubModel
                        if ($property->hasSubResourceMethod(Model::METHOD_PUT)) {
                            $path_item_sub_schema->setPut(
                                $this->buildPutOperation($sub_model)->addParameter($model_path_item_parameter)
                            );
                        }

                        //DELETE /PrimaryModel/{PrimaryModelID}/SubModel
                        if ($property->hasSubResourceMethod(Model::METHOD_DELETE)) {
                            $path_item_sub_schema->setDelete(
                                $this->buildDeleteOperation($sub_model)->addParameter($model_path_item_parameter)
                            );
                        }


                        //Direct sub resource manipulation
                        if ($sub_model->getIdentifyingProperty() !== null &&
                            (
                                $property->hasSubResourceMethod(Model::METHOD_DELETE) ||
                                $property->hasSubResourceMethod(Model::METHOD_POST)
                            )
                        ) {
                            $paths->set(sprintf('%s/{%s}/%s/{%s}',
                                $model->getResourceURI(), $model->getIdentifyingProperty()->getSingularName(),
                                $property->getCollectiveName(), $sub_model->getIdentifyingProperty()->getSingularName()), //Maybe this will always work!!
                                $path_item_sub_specific_schema = PathItem::create()->set($related_model_tag, $model->getSingularName())
                            );

                            $sub_model_path_item_parameter = PathParameterSubSchema::create()
                                ->setName($sub_model->getIdentifyingProperty()->getSingularName())
                                ->setType('string')->setFormat('uuid')
                                ->setRequired(true);

                            //POST /PrimaryModel/{PrimaryModelID}/SubModel/{SubModelID}
                            if ($property->hasSubResourceMethod(Model::METHOD_POST)) {
                                $path_item_sub_specific_schema->setPost(
                                    $this->buildPostOperation($sub_model)
                                        ->addParameter($model_path_item_parameter)
                                        ->addParameter($sub_model_path_item_parameter)
                                );
                            }

                            //DELETE /PrimaryModel/{PrimaryModelID}/SubModel/{SubModelID}
                            if ($property->hasSubResourceMethod(Model::METHOD_DELETE)) {
                                $path_item_sub_specific_schema->setDelete(
                                    $this->buildDeleteOperation($sub_model)
                                        ->addParameter($model_path_item_parameter)
                                        ->addParameter($sub_model_path_item_parameter)
                                );
                            }
                        }
                    }
                }


            }


            //Add the property set to the model
            $schema_model->setProperties(
                $schema_properties = Properties::create()
            );

            foreach ($model->getProperties() as $property_name => $property) {

                //Container for the property of the model

                $schema_property = Schema::create();

                $schema_property->setDescription($property->getDescription());

                if ($property->isMandatory()) {
                    $schema_model->addRequired($property_name);
                }

                if ($property->isReadOnly()) {
                    $schema_property->setReadOnly(true);
                }

                if ($property->getMaxLength() !== null) {
                    $schema_property->setMaxLength($property->getMaxLength());
                }

                switch ($property->getType()) {

                    case Property::TYPE_BOOLEAN:
                        $schema_property->setType('boolean');
                        break;

                    case Property::TYPE_INT:
                        $schema_property->setType('number')->setFormat('double');
                        break;

                    case Property::TYPE_FLOAT:
                        $schema_property->setType('number')->setFormat('float');
                        break;

                    case Property::TYPE_STRING:
                        $schema_property->setType('string');
                        break;

                    case Property::TYPE_DATE:
                        $schema_property->setType('string')->setFormat('date');
                        break;

                    case Property::TYPE_DATETIME:
                        $schema_property->setType('string')->setFormat('date-time');
                        break;

                    case Property::TYPE_GUID:
                        $schema_property->setType('string')->setFormat('uuid');
                        break;

                    case Property::TYPE_ENUM:
                        $schema_property->setType('string');
                        /** @var Enum $enum */
                        $enum = $property->getChildObject();

                        //Means it belongs to this model
                        if ($enum->getTarget() === $model) {
                            foreach ($enum->getValues() as $enum) {
                                $schema_property->addEnum($enum->getName());
                            }
                        }

                        break;

                    case Property::TYPE_OBJECT:
                        $ref = sprintf('#/definitions/%s', $property->getChildObject()->getSingularName());

                        if ($property->isArray()) {
                            $schema_property
                                ->setType('array')
                                ->setItems(Schema::create()->setRef($ref));
                        } else {
                            //There should a better way of detecting this.
                            //If there's anything in the schema when the ref is added, make it a 'allOf'
                            if (count($schema_property->jsonSerialize())) {
                                $schema_property = Schema::create()
                                    ->addAllOf($schema_property)
                                    ->addAllOf(Schema::create()->setRef($ref));
                            } else {
                                $schema_property->setRef($ref);
                            }

                        }
                        break;
                }

                //Add the property to the property set
                $schema_properties->set($property_name, $schema_property);
            }
        }


        foreach ($api->getStrayEnums() as $enum) {
            //Put these somewhere appropriate?!
        }

        return $swagger;

    }


    /**
     * @param Model $model
     * @return Operation
     */
    public function buildGetAllOperation(Model $model)
    {

        $get_operation = Operation::create()
            ->setSummary($model->getDescriptionForMethod(Model::METHOD_GET))
            ->setResponses(Responses::create()
                ->set('200', Response::create()
                    ->setDescription('A successful response')
                    ->setSchema(Schema::create()
                        ->setType('array')
                        ->setItems(
                            Schema::create()->setRef(sprintf('#/definitions/%s', $model->getSingularName()))
                        )
                    )
                )
            );

        if ($model->hasParameter('if-modified-since')) {
            $get_operation->addParameter(HeaderParameterSubSchema::create()
                ->setName('If-Modified-Since')
                ->setDescription('Only records created or modified since this timestamp will be returned')
                ->setType('string')->setFormat('date-time')
            );
        }

        if ($model->hasParameter('page')) {
            $get_operation->addParameter(QueryParameterSubSchema::create()
                ->setName('page')
                ->setDescription('e.g. page=1 – Up to 100 records will be returned in a single API call')
                ->setType('number')
            );
        }

        if ($model->hasParameter('where')) {
            $get_operation->addParameter(QueryParameterSubSchema::create()
                ->setName('where')
                ->setDescription('Filter by an any element')
                ->setType('string')
            );
        }

        if ($model->hasParameter('order')) {
            $get_operation->addParameter(QueryParameterSubSchema::create()
                ->setName('order')
                ->setDescription('Order by an any element')
                ->setType('string')
            );
        }

        return $get_operation;
    }


    /**
     * @param Model $model
     * @return Operation
     */
    public function buildGetOperation(Model $model)
    {

        return Operation::create()
            ->setSummary($model->getDescriptionForMethod(Model::METHOD_GET))
            ->setResponses(Responses::create()
                ->set('200', Response::create()
                    ->setDescription('A successful request')
                    ->setSchema(
                        Schema::create()->setRef(sprintf('#/definitions/%s', $model->getSingularName()))
                    )
                )
            );

    }


    /**
     * @param Model $model
     * @return Operation
     */
    public function buildPostOperation(Model $model)
    {
        return Operation::create()
            ->addParameter(BodyParameter::create()
                ->setName($model->getCollectiveName())
                ->setSchema(Schema::create()
                    ->setRef(sprintf('#/definitions/%s', $model->getSingularName()))
                )
                ->setRequired(true)
            )
            ->setResponses(Responses::create()
                ->set('200', Response::create()
                    ->setDescription('A successful request')
                    ->setSchema(Schema::create()->setRef(sprintf('#/definitions/%s', $model->getSingularName())))
                )
            );
    }

    /**
     * @param Model $model
     * @return Operation
     */
    private function buildDeleteOperation(Model $model)
    {
        return Operation::create()
            ->setResponses(Responses::create()
                ->set('200', Response::create()
                    ->setDescription('A successful request')
                )
            );
    }

    /**
     * @param Model $model
     * @return Operation
     */
    private function buildPutOperation(Model $model)
    {
        return Operation::create()
            ->addParameter(BodyParameter::create()
                ->setName($model->getCollectiveName())
                ->setSchema(Schema::create()
                    ->setRef(sprintf('#/definitions/%s', $model->getSingularName())) //Not sure about this one yet
                )
                ->setRequired(true)
            )
            ->setResponses(Responses::create()
                ->set('200', Response::create()
                    ->setDescription('A successful request')
                    ->setSchema(Schema::create()->setRef(sprintf('#/definitions/%s', $model->getSingularName())))
                )
            );
    }

}