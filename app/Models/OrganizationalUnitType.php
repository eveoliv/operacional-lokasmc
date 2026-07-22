<?php

namespace App\Models;

use Database\Factories\OrganizationalUnitTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'hierarchy_order', 'is_active'])]
class OrganizationalUnitType extends Model
{
    /** @use HasFactory<OrganizationalUnitTypeFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'hierarchy_order' => 'integer',
        ];
    }

    /** @return HasMany<OrganizationalUnit, $this> */
    public function organizationalUnits(): HasMany
    {
        return $this->hasMany(OrganizationalUnit::class);
    }
}
