# Eloquent GraphQL Transpiler
An project to automatically generate GraphQL schemas from Eloquent models.

This is an artisan command for Laravel and can be installed via composer using the repo URL.

```
graphql:transpile
    {model : Full path to a model, or a model name itself can be passed to default into looking at the graphql configured model folders}
    {--noOverwrite : Do not overwrite an existing schema file for this model if it exists already}
    {--noRelationships : Do not scaffold out any relationships for this model} 
```
