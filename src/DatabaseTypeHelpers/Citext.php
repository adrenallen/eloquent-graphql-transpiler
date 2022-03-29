<?php

namespace Adrenallen\EloquentGraphqlTranspiler\DatabaseTypeHelpers;

// This is to handle PostgreSQL CITEXT column types
// We simply define some basic overrides extending a generic text
// column type and register it in our command constructor
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\TextType;

use Doctrine\DBAL\Types\Type;
use DB;




/**
 * Citext
 * Class to fill PostgreSQL Citext type
 */
final class Citext extends TextType
{
    const CITEXT = 'citext';

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return self::CITEXT;
    }

    /**
     * {@inheritdoc}
     */
    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
    {
        return $platform->getDoctrineTypeMapping(self::CITEXT);
    }
    
    /**
     * registerSelf
     *
     * Registers the citext fill for db usage
     * 
     * @return void
     */
    public static function registerSelf() {
        Type::addType(self::CITEXT, self::class);
        DB::connection()->getDoctrineConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('citext', self::CITEXT);
    }
}