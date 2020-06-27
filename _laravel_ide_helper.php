<?php

namespace Illuminate\Database\Eloquent
{
    if (false) {
        class Builder
        {
            /**
             * Convert has() and whereHas() constraints to join() ones for BelongsTo and HasOne relations.
             *
             * @param  string|string[]                       $relationMethod
             * @param  callable[]|null[]                     $constraints
             * @return \Illuminate\Database\Eloquent\Builder
             * @see \Mpyw\EloquentHasByJoin\HasByJoinMacro
             */
            public function hasByJoin($relationMethod, ?callable ...$constraints)
            {
                return $this;
            }
        }
    }
}
