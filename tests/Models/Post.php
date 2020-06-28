<?php

namespace Mpyw\EloquentHasByJoin\Tests\Models;

use Awobaz\Compoships\Compoships;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use Compoships, SoftDeletes;

    public function category()
    {
        return $this->hasMany(Category::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function polymorphicPinnedComment()
    {
        return $this->morphOne(Comment::class, 'commentable')->where('comments.pinned', 1);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
