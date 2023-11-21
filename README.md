# Eloquent Has By Join [![Build Status](https://github.com/mpyw/eloquent-has-by-join/actions/workflows/ci.yml/badge.svg?branch=master)](https://github.com/mpyw/eloquent-has-by-join/actions) [![Coverage Status](https://coveralls.io/repos/github/mpyw/eloquent-has-by-join/badge.svg?branch=master)](https://coveralls.io/github/mpyw/eloquent-has-by-join?branch=master) [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/mpyw/eloquent-has-by-join/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/mpyw/eloquent-has-by-join/?branch=master)

Convert `has()` and `whereHas()` constraints to `join()` ones for single-result relations.

> [!IMPORTANT]
> **NOTICE: Postgres' optimizer is very smart and covers JOIN optimization for dependent (correlated) subqueries. Therefore, this library is mainly targeted at MySQL which has a poor optimizer.**

> [!CAUTION]
> **UPDATE: [MySQL's optimizer has also been updated in version `8.0.16`](https://zenn.dev/yumemi_inc/articles/e8ca9535dba0b6) to include optimizations similar to PostgreSQL. This library is no longer maintained.**

## Requirements

- PHP: `^7.3 || ^8.0`
- Laravel: `^6.0 || ^7.0 || ^8.0 || ^9.0 || ^10.0`

## Installing

```bash
composer require mpyw/eloquent-has-by-join
```

## Motivation

Suppose you have the following relationship:

```php
class Post extends Model
{
    use SoftDeletes;
}
```


```php
class Comment extends Model
{
    use SoftDeletes;

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
```

If you use `has()` constraints, your actual query would have **dependent subqueries**.

```php
$comments = Comment::has('post')->get();
```

```sql
select * from `comments` where exists (
  select * from `posts`
  where `comments`.`post_id` = `posts`.`id`
    and `posts`.`deleted_at` is null
) and `comments`.`deleted_at` is null
``` 

These subqueries may cause performance degradations.
This package provides **`Illuminate\Database\Eloquent\Builder::hasByJoin()`** macro to solve this problem:
you can easily transform dependent subqueries into simple JOIN queries.

```php
$comments = Comment::hasByJoin('post')->get();
```

```sql
select `comments`.* from `comments`
inner join `posts`
        on `comments`.`post_id` = `posts`.`id`
       and `posts`.`deleted_at` is null
where `comments`.`deleted_at` is null
```

## API

### Signature

```php
Illuminate\Database\Eloquent\Builder::hasByJoin(string|string[] $relationMethod, ?callable ...$constraints): $this
```

### Arguments

#### `$relationMethod`

A relation method name that returns a **`BelongsTo`**, **`HasOne`** or **`MorphOne`** instance.

```php
Builder::hasByJoin('post')
```

You can pass nested relations as an array or a string with dot-chain syntax. 

```php
Builder::hasByJoin(['post', 'author'])
```

```php
Builder::hasByJoin('post.author')
```

You can provide table aliases with **`"as"`** syntax.

```php
Builder::hasByJoin(['post as messages', 'author as message_authors'])
```

#### `$constraints`

Additional `callable` constraints for relations that take **`Illuminate\Database\Eloquent\Builder`** as the first argument.

```php
Builder::hasByJoin('post', fn (Builder $query) => $query->withTrashed())
```

The first closure corresponds to `post` and the second one corresponds to `author`.

```php
Builder::hasByJoin(
    'post.author',
    fn (Builder $query) => $query->withTrashed(),
    fn (Builder $query) => $query->whereKey(123)
)
```

## Feature Comparison

| Feature | `mpyw/eloquent-has-by-join` | [`mpyw/eloquent-has-by-non-dependent-subquery`](https://github.com/mpyw/eloquent-has-by-non-dependent-subquery) |
|:----|:---:|:---:|
| Minimum Laravel version | 5.6 | 5.8 |
| Argument of optional constraints | `Illuminate\Database\Eloquent\Builder` | `Illuminate\Database\Eloquent\Relations\*`<br>(`Builder` can be also accepted by specifying argument type) |
| [Compoships](https://github.com/topclaudy/compoships) support | ✅ | ❌ |
| No subqueries | ✅ | ❌<br>(Performance depends on database optimizers) |
| No table collisions | ❌<br>(Sometimes you need to give aliases) | ✅ |
| No column collisions | ❌<br>(Sometimes you need to use qualified column names) | ✅ |
| OR conditions | ❌ | ✅ |
| Negative conditions | ❌ | ✅ |
| Counting conditions | ❌ | ❌ |
| `HasOne` | ✅ | ✅ |
| `HasMany` | ❌ | ✅ |
| `BelongsTo` | ✅ | ✅ |
| `BelongsToMany` | ❌ | ✅ |
| `MorphOne` | ✅ | ✅ |
| `MorphMany` | ❌ | ✅ |
| `MorphTo` | ❌ | ❌ |
| `MorphMany` | ❌ | ✅ |
| `MorphToMany` | ❌ | ✅ |
| `HasOneThrough` | ❌ | ✅ |
| `HasManyThrough` | ❌ | ✅ |
