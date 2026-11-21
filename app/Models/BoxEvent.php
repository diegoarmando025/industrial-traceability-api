<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoxEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['box_id', 'user_id', 'event_type', 'details', 'created_at'];

    protected function casts(): array
    {
        return ['details' => 'array', 'created_at' => 'datetime'];
    }

    public function box(): BelongsTo
    {
        return $this->belongsTo(Box::class);
    }
}
