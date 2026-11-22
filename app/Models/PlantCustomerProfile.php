<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlantCustomerProfile extends Model
{
    protected $fillable = ['plant_id', 'code', 'name', 'validation_rules', 'active'];

    protected function casts(): array
    {
        return ['validation_rules' => 'array', 'active' => 'boolean'];
    }

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }

    public function boxes(): HasMany
    {
        return $this->hasMany(Box::class, 'customer_profile_id');
    }
}
