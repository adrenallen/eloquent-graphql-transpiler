<?php

namespace Adrenallen\EloquentGraphqlTranspiler\Services;

use Symfony\Component\Console\Command\Command;
use Adrenallen\EloquentGraphqlTranspiler\Metadata\MethodReturnTypeSignature;

use Illuminate\Support\Facades\Schema;
use Adrenallen\EloquentGraphqlTranspiler\DatabaseTypeHelpers\Citext;

class ModelMetadataService
{
    
    /**
     * command
     *
     * @var Command
     */
    private $command;
    public function __construct(Command $command)
    {
        Citext::registerSelf();
        $this->command = $command;
    }
    
    /**
     * findModel
     *
     * Finds the given model from the current app container
     *
     * @param  string $inputModelPath
     * @return void
     */
    public function findModel(string $inputModelPath)
    {
        $model = null;
        try {
            $model = app($inputModelPath);
            return $model;
        } catch (\Exception $e) {
            $this->command->warn(sprintf('Did not find model at path %s', $inputModelPath));
        }

        $modelPaths = config('lighthouse.namespaces.models');
        foreach ($modelPaths as $modelPath) {
            try {
                $tryModelPath = $modelPath . '\\' . $inputModelPath;
                $model = app($tryModelPath);
                $this->command->info("Found a matching model at " . $tryModelPath);
                return $model;
            } catch (\Exception $e) {
                $this->command->warn(sprintf('Did not find model at path %s', $tryModelPath));
            }
        }

        return $model;
    }
    
    /**
     * getClassName
     *
     * Returns the name of the class from a given
     * fully qualified name
     *
     * @param  string $fullNamespace
     * @return string
     */
    public function getClassName(string $fullNamespace) : string
    {
        $path = explode('\\', $fullNamespace);
        return array_pop($path);
    }
    
    /**
     * getDatabaseColumnTypes
     *
     * @param  mixed $tableName
     * @param  mixed $columns
     * @return array
     */
    public function getDatabaseColumnTypes(string $tableName, array $columns) : array
    {
        $colTypes = [];
        foreach ($columns as $col) {
            $colTypes[$col] = Schema::getColumnType($tableName, $col);
        }
        return $colTypes;
    }
    
    /**
     * getMethodReturnType
     *
     * Gets the given method's return type
     * Checks docblock first
     * Checks type hint second
     * Checks actual invoked result last
     * Returns null if nothing was found
     * 
     * @param  mixed $model
     * @param  mixed $method
     * @return MethodReturnTypeSignature|null
     */
    public function getMethodReturnType(object $model, \ReflectionMethod $method) : ?MethodReturnTypeSignature
    {
        if ($method->class != get_class($model) ||
            !empty($method->getParameters()) ||
            $method->getName() == __FUNCTION__) {
            throw new Exception("Method does not match model");
        }

        // Try to use docblock comment first
        $returnSig = $this->getMethodReturnTypeDocComment($model, $method);

        // If nothing found in doc block then try type hint
        if ($returnSig == null || count($returnSig->types) < 1) {
            $returnSig = $this->getMethodReturnTypeHint($model, $method);
        }

        // If still nothing found via type hint then
        // try to execute the method to get the actual return type
        if ($returnSig == null || count($returnSig->types) < 1) {
            $returnSig = $this->getMethodReturnTypeHint($model, $method);
        }

        return $returnSig;
    }
    
    /**
     * getMethodReturnTypeDocComment
     *
     * Try to get method return type by parsing the doc block if
     * one exists
     *
     * @param  mixed $model
     * @param  mixed $method
     * @return MethodReturnTypeSignature
     */
    private function getMethodReturnTypeDocComment(object $model, \ReflectionMethod $method) : ?MethodReturnTypeSignature
    {
        $returnSig = null;

        // Check docblock first for return type
        if (strlen($method->getDocComment()) > 0) {
            $returnSig = new MethodReturnTypeSignature();

            preg_match('/@return\s*(.*)$/m', $method->getDocComment(), $matchesArray);
            if (count($matchesArray) > 1) {
                $foundTypes = \explode('|', $matchesArray[1]);
                try {
                    foreach ($foundTypes as $foundType) {
                        
                        //If just null then mark as nullable type!
                        if ($foundType == 'null') {
                            $returnSig->nullable = true;
                            continue;
                        }

                        // see if we find an iterable type here
                        preg_match('/.*\[\]\s*$/', $input_line, $iterableKeyMatches);
                        $foundType = str_replace('[]', '', $foundType);

                        $returnSig->addType(new $foundType());

                        //Is type iterable?
                        //Set everytime so we can catch if we swap
                        //since that isn't supported
                        $returnSig->setIterable(count($iterableKeyMatches) > 0);
                    }
                } catch (\Exception $e) {
                    //TODO - handle error output gracefully
                    //$this->warn("Whoops we have iterable and non-iterable together so skipping this method!! ${e}");
                }
            }
        }

        return $returnSig;
    }
    
    /**
     * getMethodReturnTypeHint
     *
     * Try to get method return type by
     * looking at the typehinting on the method itself
     *
     * @param  mixed $model
     * @param  mixed $method
     * @return MethodReturnTypeSignature
     */
    private function getMethodReturnTypeHint(object $model, \ReflectionMethod $method) : ?MethodReturnTypeSignature
    {
        $returnSig = null;

        $returnTypeHint = $method->getReturnType();

        // if getName exists we know it's a real type
        // we wanna skip built-in types like Int
        // we're just looking for relationships
        //TODO - optionally include built-ins
        if (\method_exists($returnTypeHint, 'getName') && !$returnTypeHint->isBuiltin()) {
            $returnSig = new MethodReturnTypeSignature();

            $returnSig->nullable = $returnTypeHint->allowsNull();

            $returnTypeHintClass = $returnTypeHint->getName();
            $return = new $returnTypeHintClass();

            $returnSig->setIterable(is_iterable($return));

            if ($returnSig->iterable) {
                $returnSig->addType($return[0]);
            } else {
                $returnSig->addType($return);
            }
        }

        return $returnSig;
    }
    
    /**
     * getMethodReturnTypeInvoke
     *
     * Invokes a function to get the actual return type
     * 
     * @param  mixed $model
     * @param  mixed $method
     * @return MethodReturnTypeSignature
     */
    private function getMethodReturnTypeInvoke(object $model, \ReflectionMethod $method) : ?MethodReturnTypeSignature
    {
        $returnSig = null;

        $returnValue = $method->invoke($model);
        if ($returnValue) {
            $returnSig = new MethodReturnTypeSignature();
            $returnSig->iterable = is_iterable($returnValue);
            if ($returnSig->iterable) {
                $returnSig->addType($returnValue[0]);
            } else {
                $returnSig->addType($returnValue);
            }
        }

        return $returnSig;
    }
}
