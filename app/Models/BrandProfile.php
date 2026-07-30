<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrandProfile extends Model
{
    /** @use HasFactory<\Database\Factories\BrandProfileFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * Get the team that owns the brand profile.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'topics' => 'array',
            'banned_terms' => 'array',
            'examples' => 'array',
        ];
    }
}
