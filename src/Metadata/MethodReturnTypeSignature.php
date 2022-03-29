<?php

namespace Adrenallen\EloquentGraphqlTranspiler\Metadata;

class MethodReturnTypeSignature
{
    protected $iterable;
    protected $types;
    protected $nullable;

    private $iterableSet;
    
    /**
     * setIterable
     *
     * We must either be iterable or not for all types
     * So if we set to iterable then not iterable later
     * we want to throw an error because that will break!
     *
     * @param  mixed $iterable
     * @return void
     */
    public function setIterable(bool $iterable) : void
    {
        if ($this->iterableSet && $iterable != $this->iterable) {
            throw new Exception(
                sprintf("Iterable has been set already to %s!", $this->iterable ?? 'true')
            );
        }
    }

    public function addType(mixed $type) : void
    {
        if (!in_array($type, $this->types)) {
            $this->types[] = $type;
        }
    }
}
