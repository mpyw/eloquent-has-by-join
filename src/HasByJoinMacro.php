<?php

namespace Mpyw\EloquentHasByJoin;

use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\JoinClause;

/**
 * Class HasByJoinMacro
 *
 * Convert has() and whereHas() constraints to join() ones for single-result relations.
 */
class HasByJoinMacro
{
    /**
     * @var \Illuminate\Database\Eloquent\Builder
     */
    protected $query;

    /**
     * HasByJoinMacro constructor.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     */
    public function __construct(Builder $query)
    {
        $this->query = $query;
    }

    /**
     * Parse nested constraints and iterate them to apply.
     *
     * @param  string|string[]                       $relationMethod
     * @param  callable[]|null[]                     $constraints
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function __invoke($relationMethod, ?callable ...$constraints): Builder
    {
        // Prepare a root Model
        $root = $current = $this->query->getModel();

        // Extract dot-chained expressions
        $relationMethods = is_string($relationMethod) ? explode('.', $relationMethod) : array_values($relationMethod);

        foreach ($relationMethods as $i => $currentRelationMethod) {
            // Extract an alias specified with "as" if exists
            [$currentRelationMethod, $currentTableAlias] = preg_split('/\s+as\s+/i', $currentRelationMethod, -1, PREG_SPLIT_NO_EMPTY) + [1 => null];

            // Create a Relation instance
            $relation = $current->newModelQuery()->getRelation($currentRelationMethod);

            // Convert Relation constraints to JOIN ones
            $this->applyRelationAsJoin($relation, $constraints[$i] ?? null, $currentTableAlias);

            // Prepare the next Model
            $current = $relation->getRelated();
        }

        // Prevent the original columns and JOIN target columns from being mixed
        if (($this->query->getQuery()->columns ?: ['*']) === ['*']) {
            $this->query->select("{$root->getTable()}.*");
        }

        return $this->query;
    }

    /**
     * Convert Relation constraints to JOIN ones.
     *
     * @param \Illuminate\Database\Eloquent\Relations\Relation $relation
     * @param null|callable                                    $constraints
     * @param null|string                                      $tableAlias
     */
    protected function applyRelationAsJoin(Relation $relation, ?callable $constraints, ?string $tableAlias): void
    {
        // Support BelongsTo and HasOne relations only
        if ((!$relation instanceof BelongsTo || $relation instanceof MorphTo) && !$relation instanceof HasOne) {
            throw new DomainException('Unsupported relation. Currently supported: BelongsTo and HasOne');
        }

        // Generate the same subquery as has() method does
        $relationExistenceQuery = $relation
            ->getRelationExistenceQuery($this->overrideTableWithAlias($relation->getRelated()->newQuery(), $tableAlias), $this->query)
            ->mergeConstraintsFrom($relationQuery = $this->overrideTableWithAlias($relation->getQuery(), $tableAlias));

        // Validate table alias availability
        $this->ensureTableAliasAvailability($relationQuery, $tableAlias);

        // Apply optional constraints
        if ($constraints) {
            $constraints($relationExistenceQuery);
        }

        // Convert Eloquent Builder to Query Builder and evaluate scope constraints
        $relationExistenceQuery = $relationExistenceQuery->toBase();

        // Migrate has() constraints to join()
        $this->query->join(
            $tableAlias ? "$relationExistenceQuery->from as $tableAlias" : $relationExistenceQuery->from,
            function (JoinClause $join) use ($relationExistenceQuery) {
                $join->mergeWheres($relationExistenceQuery->wheres, $relationExistenceQuery->bindings);
            }
        );

        // Migrate extra joins on has() constraints
        $this->mergeJoins($this->query, $relationQuery);
    }

    /**
     * Rewrite table name if its alias is provided.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @param  null|string                           $tableAlias
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function overrideTableWithAlias(Builder $query, ?string $tableAlias): Builder
    {
        if ($tableAlias) {
            $query->getModel()->setTable($tableAlias);
        }

        return $query;
    }

    /**
     * mergeJoins() is not provided by Laravel,
     * so we need to implement it by ourselves.
     *
     * @param \Illuminate\Database\Eloquent\Builder $dst
     * @param \Illuminate\Database\Eloquent\Builder $src
     */
    protected function mergeJoins(Builder $dst, Builder $src): void
    {
        [$dst, $src] = [$dst->getQuery(), $src->getQuery()];

        if ($src->joins) {
            $dst->joins = array_merge((array)$dst->joins, $src->joins);
        }
        if ($src->bindings) {
            $dst->bindings['join'] = array_merge((array)$dst->bindings['join'], $src->bindings['join']);
        }
    }

    /**
     * Extra where() or join() constraints in relation are evaluated early,
     * so we cannot apply table aliases to them.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param null|string                           $tableAlias
     */
    protected function ensureTableAliasAvailability(Builder $query, ?string $tableAlias): void
    {
        $query = $query->getQuery();

        if (($query->joins || $query->wheres) && $tableAlias) {
            throw new DomainException('You cannot use table alias when your relation has extra joins or wheres.');
        }
    }
}
