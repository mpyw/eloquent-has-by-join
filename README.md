# Eloquent Has By Join [![Build Status](https://travis-ci.com/mpyw/eloquent-has-by-join.svg?branch=master)](https://travis-ci.com/mpyw/eloquent-has-by-join) [![Coverage Status](https://coveralls.io/repos/github/mpyw/eloquent-has-by-join/badge.svg?branch=master)](https://coveralls.io/github/mpyw/eloquent-has-by-join?branch=master) [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/mpyw/eloquent-has-by-join/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/mpyw/eloquent-has-by-join/?branch=master)

Convert `has()` and `whereHas()` constraints into `join()` ones against singular relations.

## Requirements

- PHP: ^7.1
- Laravel: ^5.6 || ^6.0 || ^7.0

## Installing

```bash
composer require mpyw/eloquent-has-by-join
```

## Motivation

Suppose you have the following relationships:

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

If you use `has()` constraints, your actual query will have **dependent subqueries**.

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

But these subqueries may cause performance degradations.
This package provides **`\Illuminate\Database\Eloquent\Builder::hasByJoin()`** macro to resolve this problem;
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
\Illuminate\Database\Eloquent\Builder::hasByJoin(string|string[] $relationMethod, ?callable ...$constraints): $this
```

### Arguments

#### `$relationMethod`

A relation method name that returns **`BelongsTo`** or **`HasOne`** instance.

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

Additional `callable` constraints for relations that takes `\Illuminate\Database\Eloquent\Builder` as the first argument.

```php
Builder::hasByJoin('post', fn (Builder $query) => $query->withTrashed())
```

The first closure corresponds to `post`, then the second one corresponds to `author`.

```php
Builder::hasByJoin(
    'post.author',
    fn (Builder $query) => $query->withTrashed(),
    fn (Builder $query) => $query->whereKey(123)
)
```
