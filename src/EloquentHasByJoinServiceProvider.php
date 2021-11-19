<?php

namespace Mpyw\EloquentHasByJoin;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\ServiceProvider;

class EloquentHasByJoinServiceProvider extends ServiceProvider
{
    /**
     * Register Eloquent\Builder::hasByJoin() macro.
     */
    public function boot(): void
    {
        Builder::macro('hasByJoin', function ($relationMethod, ?callable ...$constraints): Builder {
            /** @var \Illuminate\Database\Eloquent\Builder $query */
            $query = $this;
            return (new HasByJoinMacro($query))($relationMethod, ...$constraints);
        });
    }
}
