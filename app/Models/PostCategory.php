<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PostCategory extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'description'
    ];

    protected $casts = [
        'description' => 'array',
    ];
    /**
     * Get the user that owns the category.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all posts in this category (many-to-many via pivot table).
     * If you use a pivot table 'post_category_posts'.
     */
    public function posts()
    {
        return $this->hasMany(Post::class, 'post_category_id');
    }

    /**
     * Get all accounts in this category (many-to-many via pivot table).
     * If you use a pivot table 'post_accounts'.
     */
    public function postAccount()
    {
        return $this->belongsTo(PostAccount::class, 'post_account_id');
    }

    /**
     * Optionally, if a post can have only one primary category,
     * you can add a hasMany relationship from category to posts
     * using the foreign key 'post_category_id' on the posts table.
     */
    public function primaryPosts(): HasMany
    {
        return $this->hasMany(Post::class, 'post_category_id');
    }

    /**
     * Get published posts in this category.
     */
    public function publishedPosts(): BelongsToMany
    {
        return $this->posts()->where('status', 'published');
    }

}


