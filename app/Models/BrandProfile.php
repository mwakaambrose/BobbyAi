<?php

namespace App\Models;

use Database\Factories\BrandProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $team_id
 * @property string|null $voice
 * @property string|null $audience
 * @property array<int, string> $topics
 * @property array<int, string> $banned_terms
 * @property array<int, string> $examples
 * @property-read Team $team
 */
class BrandProfile extends Model
{
    /** @use HasFactory<BrandProfileFactory> */
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
