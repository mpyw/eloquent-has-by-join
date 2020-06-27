<?php

namespace Mpyw\EloquentHasByJoin\Tests;

use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Mpyw\EloquentHasByJoin\EloquentHasByJoinServiceProvider;
use Mpyw\EloquentHasByJoin\Tests\Models\Comment;
use Mpyw\EloquentHasByJoin\Tests\Models\Post;
use Mpyw\EloquentHasByJoin\Tests\Models\User;
use NilPortugues\Sql\QueryFormatter\Formatter;
use Orchestra\Testbench\TestCase as BaseTestCase;

class Test extends BaseTestCase
{
    /**
     * @param  \Illuminate\Foundation\Application $app
     * @return array
     */
    protected function getPackageProviders($app): array
    {
        return [
            EloquentHasByJoinServiceProvider::class,
        ];
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     */
    protected function getEnvironmentSetUp($app): void
    {
        config(['database.default' => 'testing']);
    }

    /**
     * @param string                                $expectedSql
     * @param \Illuminate\Database\Eloquent\Builder $actualQuery
     */
    protected function assertQueryEquals(string $expectedSql, $actualQuery): void
    {
        $formatter = new Formatter();

        $this->assertSame(
            $formatter->format($expectedSql),
            $formatter->format(Str::replaceArray(
                '?',
                array_map(
                    function ($v) {
                        return is_string($v)
                            ? sprintf("'%s'", addcslashes($v, "\\'"))
                            : (int)$v;
                    },
                    (clone $actualQuery)->getBindings()
                ),
                (clone $actualQuery)->toSql()
            ))
        );
    }

    public function testCommentsHavingPost(): void
    {
        $this->assertQueryEquals(
            <<<'EOD'
            select
                "comments".*
            from
                "comments"
            inner join "posts"
                on "comments"."post_id" = "posts"."id"
                and "posts"."deleted_at" is null
            where
                "comments"."deleted_at" is null
EOD
            ,
            Comment::query()->hasByJoin('post')
        );
    }

    public function testCommentsOnlyTrashedHavingPostWithTrashed(): void
    {
        $this->assertQueryEquals(
            <<<'EOD'
            select
                "comments".*
            from
                "comments"
            inner join "posts"
                on "comments"."post_id" = "posts"."id"
                and "posts"."deleted_at" is not null
EOD
            ,
            Comment::query()->hasByJoin(
                'post',
                function (Builder $query) {
                    $query->onlyTrashed();
                }
            )->withTrashed()
        );
    }

    public function testCommentsHavingPostWithCustomSelect(): void
    {
        $this->assertQueryEquals(
            <<<'EOD'
            select
                "comments"."id"
            from
                "comments"
            inner join "posts"
                on "comments"."post_id" = "posts"."id"
                and "posts"."deleted_at" is null
            where
                "comments"."deleted_at" is null
EOD
            ,
            Comment::query()->hasByJoin('post')->select('comments.id')
        );
    }

    public function testCommentsHavingAuthorFromPostInstance(): void
    {
        $post = new Post();
        $post->forceFill([
            'id' => 123,
            'author_id' => 456,
            'deleted_at' => null,
        ])->syncOriginal()->exists = true;

        $this->assertQueryEquals(
            <<<'EOD'
            select
                "comments".*
            from
                "comments"
            inner join "users"
                on "comments"."author_id" = "users"."id"
            where
                "comments"."post_id" = 123
                and "comments"."post_id" is not null
                and "comments"."deleted_at" is null
EOD
            ,
            $post->comments()->hasByJoin('author')
        );
    }

    public function testCommentsHavingAuthor(): void
    {
        $this->assertQueryEquals(
            <<<'EOD'
            select
                "comments".*
            from
                "comments"
            inner join "users"
                on "comments"."author_id" = "users"."id"
            where
                "comments"."deleted_at" is null
EOD
            ,
            Comment::query()->hasByJoin('author')
        );
    }

    public function testCommentsHavingSameAuthorPost(): void
    {
        $this->assertQueryEquals(
            <<<'EOD'
            select
                "comments".*
            from
                "comments"
            inner join "posts"
                on "comments"."post_id" = "posts"."id"
                and "comments"."author_id" = "posts"."author_id"
                and "posts"."deleted_at" is null
            where
                "comments"."deleted_at" is null
EOD
            ,
            Comment::query()->hasByJoin('sameAuthorPost')
        );
    }

    public function testCommentsHavingPostAuthor(): void
    {
        $this->assertQueryEquals(
            <<<'EOD'
            select
                "comments".*
            from
                "comments"
            inner join "posts"
                on "comments"."post_id" = "posts"."id"
                and "posts"."deleted_at" is null
            inner join "users"
                on "posts"."author_id" = "users"."id"
            where
                "comments"."deleted_at" is null
EOD
            ,
            Comment::query()->hasByJoin('post.author')
        );
    }

    public function testCommentsHavingPostAuthorUsingCustomConstraints(): void
    {
        $this->assertQueryEquals(
            <<<'EOD'
            select
                "comments".*
            from
                "comments"
            inner join "posts"
                on "comments"."post_id" = "posts"."id"
            inner join "users"
                on "posts"."author_id" = "users"."id"
                and "users"."id" = 999
            where
                "comments"."deleted_at" is null
EOD
            ,
            Comment::query()->hasByJoin(
                'post.author',
                function (Builder $query) {
                    $query->withTrashed();
                },
                function (Builder $query) {
                    $query->whereKey(999);
                }
            )
        );
    }

    public function testCommentsHavingPostAuthorAndHavingCommentAuthorUsingTableAliases(): void
    {
        $this->assertQueryEquals(
            <<<'EOD'
            select
                "comments".*
            from
                "comments"
            inner join "posts"
                on "comments"."post_id" = "posts"."id"
                and "posts"."deleted_at" is null
            inner join "users" as "post_authors"
                on "posts"."author_id" = "post_authors"."id"
            inner join "users" as "comment_authors"
                on "comments"."author_id" = "comment_authors"."id"
            where
                "comments"."deleted_at" is null
EOD
            ,
            Comment::query()
                ->hasByJoin(['post', 'author as post_authors'])
                ->hasByJoin('author as comment_authors')
        );
    }

    public function testUsersHavingPinnedPost(): void
    {
        $this->assertQueryEquals(
            <<<'EOD'
            select
                "users".*
            from
                "users"
            inner join "posts"
                on "users"."id" = "posts"."user_id"
                and "posts"."pinned" = 1
                and "posts"."deleted_at" is null
EOD
            ,
            User::query()->hasByJoin('pinnedPost')
        );
    }

    public function testUsersHavingPinnedPostInGeneralCategory(): void
    {
        $this->assertQueryEquals(
            <<<'EOD'
            select
                "users".*
            from
                "users"
            inner join "posts"
                on "users"."id" = "posts"."user_id"
                and "posts"."pinned" = 1
                and "posts"."deleted_at" is null
            inner join "categories"
                on "posts"."category_id" = "categories"."id"
                and "categories"."slug" = 'general'
EOD
            ,
            User::query()->hasByJoin('pinnedPostInGeneralCategory')
        );
    }

    public function testUsersHavingPinnedPostInvalidAliasWithWhere(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('You cannot use table alias when your relation has extra joins or wheres.');

        User::query()->hasByJoin('pinnedPost as pinned_posts');
    }

    public function testUsersHavingPinnedPostInGeneralCategoryInvalidAliasWithJoin(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('You cannot use table alias when your relation has extra joins or wheres.');

        User::query()->hasByJoin('pinnedPostInGeneralCategory as general_pinned_posts');
    }

    public function testPostHavingComments(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Unsupported relation. Currently supported: BelongsTo and HasOne');

        Post::query()->hasByJoin('comments');
    }
}
