<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Journal extends Model
{
    protected $fillable = [
        'user_id',
        'category_id',
        'title',
        'slug',
        'abstract',
        'cover_image',
        'pdf_file',
        'pdf_url',
        'authors',
        'keywords',
        'status',
        'views',
        'downloads',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Auto-generate slug from title
    public static function boot()
    {
        parent::boot();
        
        static::creating(function ($journal) {
            if (empty($journal->slug)) {
                $journal->slug = Str::slug($journal->title);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    // Scope for approved journals only (fast query)
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    // Scope for search
    public function scopeSearch($query, $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('title', 'like', "%{$search}%")
              ->orWhere('authors', 'like', "%{$search}%")
              ->orWhere('abstract', 'like', "%{$search}%");
        });
    }
}
