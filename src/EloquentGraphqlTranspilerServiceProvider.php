<?php

namespace Adrenallen\EloquentGraphqlTranspiler;

use Adrenallen\EloquentGraphqlTranspiler\Console\Commands\EloquentGraphqlTranspile;

class EloquentGraphqlTranspilerServiceProvider extends \Illuminate\Support\ServiceProvider
{
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                EloquentGraphqlTranspile::class
            ]);
        }
    }
}
