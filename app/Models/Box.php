<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Box extends Model
{
    protected $fillable = ['plant_id', 'code', 'part_number', 'serial_number', 'status', 'released_at', 'validation_rules'];

    protected function casts(): array
    {
        return ['released_at' => 'datetime', 'validation_rules' => 'array'];
    }

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }

    public function scans(): HasMany
    {
        return $this->hasMany(BoxScan::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(BoxEvent::class);
    }
}
