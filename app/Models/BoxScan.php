<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoxScan extends Model
{
    public $timestamps = false;

    protected $fillable = ['box_id', 'user_id', 'scan_type', 'scanned_value', 'accepted', 'reason', 'created_at'];

    protected function casts(): array
    {
        return ['accepted' => 'boolean', 'created_at' => 'datetime'];
    }

    public function box(): BelongsTo
    {
        return $this->belongsTo(Box::class);
    }
}
