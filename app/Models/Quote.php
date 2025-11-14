<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Quote extends Model
{
    protected $fillable = [
        'text',
        'status',
        'sort_order',
    ];

    protected $casts = [
        'text' => 'array',
    ];

    public function getTextLocalizedAttribute(): string
    {
        $locale = app()->getLocale() ?? 'en';
        $t = $this->text ?? [];
        return $t[$locale] ?? ($t['en'] ?? reset($t) ?? '');
    }

    public function scopeActive($q)
    {
        return $q->where('status','active')
                 ->orderBy('sort_order')
                 ->orderByDesc('id');
    }
}
