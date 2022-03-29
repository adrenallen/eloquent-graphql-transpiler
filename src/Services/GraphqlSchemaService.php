<?php

namespace Adrenallen\EloquentGraphqlTranspiler\Services;

use Symfony\Component\Console\Command\Command;

class GraphqlSchemaService
{
    
    /**
     * command
     *
     * @var Command
     */
    private $command;
    public function __construct(Command $command)
    {
        $this->command = $command;
    }

        
    /**
     * getGraphqlSchemaFilePath
     *
     * Generates the graphql schema file path for the given model
     * TODO - Make this a config setting!
     *
     * @param  mixed $modelName
     * @return string
     */
    public function getGraphqlSchemaFilePath($modelName)
    {
        return "/graphql/models/${modelName}.graphql";
    }
    
    /**
     * columnTypesToGraphTypes
     *
     *  Takes a given array of column names => types
     *  and converts them to names => graphql type
     *
     * @param  mixed $columnTypes
     * @param  mixed $primaryKey
     * @return array
     */
    public function columnTypesToGraphTypes(array $columnTypes, string $primaryKey = null) : array
    {
        $graphTypes = [];
        foreach ($columnTypes as $columnName => $columnType) {
            $graphTypes[$columnName] = $this->columnTypeToGraphType($columnName, $columnType, $primaryKey);
        }
        return $graphTypes;
    }
    
    /**
     * columnTypeToGraphType
     *
     * @param  mixed $columnName
     * @param  mixed $columnType
     * @param  mixed $primaryKey
     * @return string
     */
    public function columnTypeToGraphType(string $columnName, string $columnType, string $primaryKey = null)
    {
        if ($columnName == $primaryKey) {
            return 'ID';
        }
        
        switch ($columnType) {
            case 'boolean':
                return 'Boolean';
            case 'decimal':
            case 'float':
                return 'Float';
            case 'datetime':
                return 'DateTime';
            case 'integer':
            case 'smallint':
                return 'Int';
            case 'guid':
            case 'string':
            case 'text':
            case 'citext':
                return 'String';
            case 'datetimetz':
                return 'DateTimeTz';
            case 'date':
                return 'Date';
            case 'json':
                return 'JSON';
            default:
                $this->command->error(
                    sprintf(
                        "Column %s has type of %s which could not be mapped, defaulting to String (something is probably wrong)",
                        $columnName,
                        $columnType
                    )
                );
                return 'String';
        }
    }
    
    /**
     * getModelPropertyDefString
     *
     * Takes an array of graphql column definitions
     * and turns it into a string for a schema file
     * to fill out basic property mappings
     * 
     * @param  array $colDefinitions
     * @return string
     */
    public function getModelPropertyDefString(array $colDefinitions) : string
    {
        $fieldDefs = [];
        foreach ($colDefinitions as $col => $colType) {
            $fieldDefs[] = sprintf('%s: %s', $col, $colType);
        }
        return implode("\n    ", $fieldDefs);
    }
    
    /**
     * getModelColumnEnums
     *
     * Takes an array of graphql column definitions
     * and turns it into a string for a schema file
     * to fill out column enum types
     *
     * @param  array $colDefinitions
     * @return string
     */
    public function getModelColumnEnums(array $colDefinitions) : string
    {
        $modelColumns = [];
        foreach ($colDefinitions as $col => $colType) {
            $modelColumns[] = sprintf('%s @enum(value: "%s")', strtoupper($col), $col);
        }
        return implode("\n    ", $modelColumns);
    }
}
