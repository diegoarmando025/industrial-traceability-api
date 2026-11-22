<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plant extends Model
{
    protected $fillable = ['code', 'name', 'validation_rules'];

    protected function casts(): array
    {
        return ['validation_rules' => 'array', 'validation_rules_version' => 'integer'];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role');
    }

    public function customerProfiles(): HasMany
    {
        return $this->hasMany(PlantCustomerProfile::class);
    }

    public function boxes(): HasMany
    {
        return $this->hasMany(Box::class);
    }
}
