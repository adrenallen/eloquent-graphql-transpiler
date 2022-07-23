# Eloquent GraphQL Transpiler
A project to automatically generate GraphQL schemas from Eloquent models.

This is a [Laravel artisan command](https://laravel.com/docs/master/artisan) and can be [installed via composer using the repo URL.](https://getcomposer.org/doc/05-repositories.md)

This is intended for use with [Lightouse PHP](https://lighthouse-php.com/) but can likely be modified to work elsewhere.

## Example usage
```
graphql:transpile
    {model : Full path to a model, or a model name itself can be passed to default into looking at the graphql configured model folders}
    {--noOverwrite : Do not overwrite an existing schema file for this model if it exists already}
    {--noRelationships : Do not scaffold out any relationships for this model} 
```


