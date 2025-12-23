<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    protected $fillable = [
        'image_path',
        'status',
        'sort_order',
    ];

    public function scopeActive($q)
    {
        return $q->where('status', 'active')
                 ->orderBy('sort_order')
                 ->orderByDesc('id');
    }
}
