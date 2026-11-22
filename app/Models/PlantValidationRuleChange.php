<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlantValidationRuleChange extends Model
{
    public $timestamps = false;

    protected $fillable = ['plant_id', 'user_id', 'version', 'previous_rules', 'new_rules', 'created_at'];

    protected function casts(): array
    {
        return [
            'previous_rules' => 'array',
            'new_rules' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
