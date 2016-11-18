<?php
/**
 * @package    xero-schemas
 * @author     Michael Calcinai <michael@calcin.ai>
 */

namespace Calcinai\XeroSchemaGenerator;

use Calcinai\Strut\Definitions\BodyParameter;
use Calcinai\Strut\Definitions\Definitions;
use Calcinai\Strut\Definitions\ExternalDocs;
use Calcinai\Strut\Definitions\Info;
use Calcinai\Strut\Definitions\Operation;
use Calcinai\Strut\Definitions\PathItem;
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

        $swagger = new Swagger();
        $swagger->setInfo(
            Info::create()
                ->setTitle($api->getName())
                ->setVersion($api->getVersion())
        )
            ->setHost('api.xero.com') //This needs to get overridden for partner APIs, but needs somehting as a base for valid swagger
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

            $definitions->set($model->getSingularName(),
                $schema_model = Schema::create()
                    ->setExternalDocs(ExternalDocs::create()
                        ->setUrl($model->getDocumentationURI())
                    )
            );


            if ($model->getResourceURI() !== null) {

                $paths->set($model->getResourceURI(),
                    $path_item_schema = PathItem::create()
                );

                //GET
                if ($model->supportsMethod(Model::METHOD_GET)) {

                    $path_item_schema->setGet(Operation::create()
                        ->setSummary($model->getDescriptionForMethod(Model::METHOD_GET))
//                        ->addParameter(QueryParameterSubSchema::create()
//                            ->setName('Limit')
//                            ->setDescription('How many items to return at one time (max 100)')
//                            ->setRequired(false)
//                            ->setType('integer')
//                            ->setFormat('int32')
//                        )
                        ->setResponses(Responses::create()
                            ->set('200', Response::create()
                                ->setDescription('')
                                ->setSchema(Schema::create()->setRef(sprintf('#/definitions/%s', $model->getSingularName())))
                            )
                        )
                    );

                }

                //POST - not correct yet
                if ($model->supportsMethod(Model::METHOD_POST)) {

                    $path_item_schema->setPost(Operation::create()
                        ->addParameter(BodyParameter::create()
                            ->setName($model->getCollectiveName())
                            ->setSchema(Schema::create()
                                ->setRef(sprintf('#/definitions/%s', $model->getSingularName())) //Not sure about this one yet
                            )
                        )
                        ->setResponses(Responses::create()
                            ->set('200', Response::create()
                                ->setDescription('')
                                ->setSchema(Schema::create()->setRef(sprintf('#/definitions/%s', $model->getSingularName())))
                            )
                        )
                    );

                }

                //PUT - not correct yet
                if ($model->supportsMethod(Model::METHOD_PUT)) {

                    $path_item_schema->setPut(Operation::create()
                        ->addParameter(BodyParameter::create()
                            ->setName($model->getCollectiveName())
                            ->setSchema(Schema::create()
                                ->setRef(sprintf('#/definitions/%s', $model->getSingularName())) //Not sure about this one yet
                            )
                        )
                        ->setResponses(Responses::create()
                            ->set('200', Response::create()
                                ->setDescription('')
                                ->setSchema(Schema::create()->setRef(sprintf('#/definitions/%s', $model->getSingularName())))
                            )
                        )
                    );

                }

                //DELETE - not correct yet
                if ($model->supportsMethod(Model::METHOD_DELETE)) {

                    $path_item_schema->setDelete(Operation::create()
                        ->setResponses(Responses::create()
                            ->set('200', Response::create()
                                ->setDescription('')
                            )
                        )
                    );

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

}