<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    protected $fillable = [
        'name','description','sessions_count','session_duration_min',
        'price_cents','discount_percent','currency','validity_days',
        'applicability','is_active','created_by_therapist_id'
    ];

    protected $casts = [
        'name'            => 'array',
        'description'     => 'array',
        'is_active'       => 'boolean',
        'discount_percent'=> 'float',
    ];

    public function scopeOwnedByDoctor($q, int $therapistId)
    {
        return $q->where('applicability','therapist')
                 ->where('created_by_therapist_id',$therapistId);
    }

    public function scopePublic($q)
    {
        return $q->where('applicability','any');
    }

    // ✅ Accessor: name_localized
    public function getNameLocalizedAttribute(): string
    {
        $locale = app()->getLocale() ?? 'en';
        $raw    = $this->name ?? [];

        if (is_array($raw)) {
            return $raw[$locale] ?? ($raw['en'] ?? reset($raw) ?? '');
        }

        return (string) $raw;
    }

    // ✅ Accessor: description_localized
    public function getDescriptionLocalizedAttribute(): ?string
    {
        $locale = app()->getLocale() ?? 'en';
        $raw    = $this->description ?? [];

        if (is_array($raw)) {
            return $raw[$locale] ?? ($raw['en'] ?? reset($raw) ?? '');
        }

        return $raw ? (string)$raw : null;
    }
}

