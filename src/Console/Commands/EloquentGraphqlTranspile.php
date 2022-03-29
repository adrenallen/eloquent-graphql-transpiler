<?php

namespace Adrenallen\EloquentGraphqlTranspiler\Console\Commands;

use Illuminate\Console\Command;
use DB;
use Illuminate\Support\Facades\Schema;
use ErrorException;
use Illuminate\Database\Eloquent\Relations\Relation;
use ReflectionClass;
use ReflectionMethod;

use Adrenallen\EloquentGraphqlTranspiler\DatabaseTypeHelpers\Citext;
use Adrenallen\EloquentGraphqlTranspiler\Services\ModelMetadataService;
use Adrenallen\EloquentGraphqlTranspiler\Services\GraphqlSchemaService;


use Doctrine\DBAL\Types\Type;

class EloquentGraphqlTranspile extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'graphql:transpile 
                            {model : Full path to a model, or a model name itself can be passed to default into looking at the graphql configured model folders}
                            {--noOverwrite : Do not overwrite an existing schema file for this model if it exists already}
                            {--noRelationships : Do not scaffold out any relationships for this model} ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Transpile an Eloquent model to a GraphQL schema for use with Lighthouse PHP.';


    protected $modelMetadataSvc;
    protected $graphqlSchemaSvc;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        // TODO - Do I need this in here or can it just live in metadataSvc?
        //Citext::registerSelf();

        $this->modelMetadataSvc = new ModelMetadataService($this);
        $this->graphqlSchemaSvc = new GraphqlSchemaService($this);
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $inputModelPath = $this->argument('model');

        // Find the class for the given model input
        $model = $this->modelMetadataSvc->findModel($inputModelPath);
        if (!$model) {
            $this->error("Failed to find a model matching " . $inputModelPath);
            return;
        }

        // Get the class name from the found model
        $modelName = $this->modelMetadataSvc->getClassName(get_class($model));

        // If noOverwrite is passed make sure this model doesn't
        // exist already
        // if it does then end early
        if ($this->option('noOverwrite')
            && file_exists(base_path($this->graphqlSchemaSvc->getGraphqlSchemaFilePath($modelName)))) {
            $this->warn("This GraphQL model file already exists at " . $this->graphqlSchemaSvc->getGraphqlSchemaFilePath($modelName));
            return;
        }

        $tableName = (new $model)->getTable();
        $primaryKey = $model->getKeyName();

        $cols = Schema::getColumnListing($tableName);

        $colTypes = $this->modelMetadataSvc->getDatabaseColumnTypes($tableName, $cols);

        $gqlColDefinitions = $this
                            ->graphqlSchemaSvc
                            ->columnTypesToGraphTypes($colTypes, $primaryKey);
        
        $gqlSchemaColumnEnums = $this->graphqlSchemaSvc->getModelColumnEnums($gqlColDefinitions);
        $gsqlSchemaPropertyDefs = $this->graphqlSchemaSvc->getModelPropertyDefString($gqlColDefinitions);

        // If we don't pass in the no relationships flag
        // then we want to grab and generate any possible relationships
        // that we can find
        $modelRelationshipDefs = "";
        if (!$this->option('noRelationships')) {
            //$modelRelationshipDefs = $this->getModelRelationshipDefString($model);
        }

        $modelQueryName = $this->camelCaseName($modelName);
        $modelQueryNamePlural = $this->namePlural($modelQueryName);

        $stub = str_replace(
            [
                '{{modelQueryNamePlural}}',
                '{{modelPrimaryKey}}',
                '{{modelQueryName}}',
                '{{modelName}}',
                '{{modelColumnEnums}}',
                '{{modelFieldDefinitions}}',
                '{{modelRelationshipDefinitions}}'
            ],
            [
                $modelQueryNamePlural,
                $primaryKey,
                $modelQueryName,
                $modelName,
                $gqlSchemaColumnEnums,
                $gsqlSchemaPropertyDefs,
                $modelRelationshipDefs
            ],
            file_get_contents(__DIR__ . "/../stubs/GraphQLSchema.stub")
        );

        $filePath = $this->graphqlSchemaSvc->getGraphqlSchemaFilePath($modelName);
        file_put_contents(base_path($filePath), $stub);
    }

    private function camelCaseName($string) : string
    {
        for ($i = 0; $i < strlen($string); $i++) {
            // First always lowercased
            // Last always lowercased if we get all the way there
            // because that means we have an acronym like EDI
            // otherwise check if we are at the end of first word
            if ($i == 0 || $i+1 == strlen($string) || ctype_upper($string[$i+1])) {
                $string[$i] = strtolower($string[$i]);
            } else {
                break;
            }
        }
        return $string;
    }

    private function namePlural($string) : string
    {
        return \Illuminate\Support\Str::plural($string);
    }
}
