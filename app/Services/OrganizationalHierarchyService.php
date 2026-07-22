<?php

namespace App\Services;

use App\Models\OrganizationalUnit;
use App\Models\OrganizationalUnitType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrganizationalHierarchyService
{
    /**
     * @param  array{organizational_unit_type_id: int, code: string, name: string, is_active?: bool}  $attributes
     */
    public function create(array $attributes, ?OrganizationalUnit $parent = null): OrganizationalUnit
    {
        return DB::transaction(function () use ($attributes, $parent): OrganizationalUnit {
            $type = OrganizationalUnitType::query()->lockForUpdate()->findOrFail($attributes['organizational_unit_type_id']);
            $lockedParent = $parent === null ? null : $this->lockUnit($parent);

            $this->validatePlacement($type, $lockedParent);

            $unit = new OrganizationalUnit($attributes);
            $unit->parent()->associate($lockedParent);
            $unit->save();

            $rows = [['ancestor_id' => $unit->getKey(), 'descendant_id' => $unit->getKey(), 'depth' => 0]];

            if ($lockedParent !== null) {
                /** @var list<object{ancestor_id: int, depth: int}> $ancestors */
                $ancestors = DB::table('organizational_unit_closure')
                    ->where('descendant_id', $lockedParent->getKey())
                    ->orderBy('depth')
                    ->lockForUpdate()
                    ->get(['ancestor_id', 'depth'])
                    ->all();

                foreach ($ancestors as $ancestor) {
                    $rows[] = [
                        'ancestor_id' => $ancestor->ancestor_id,
                        'descendant_id' => $unit->getKey(),
                        'depth' => $ancestor->depth + 1,
                    ];
                }
            }

            DB::table('organizational_unit_closure')->insert($rows);

            return $unit->refresh();
        });
    }

    /**
     * @param  array{organizational_unit_type_id?: int, code?: string, name?: string, is_active?: bool}  $attributes
     */
    public function update(OrganizationalUnit $unit, array $attributes): OrganizationalUnit
    {
        return DB::transaction(function () use ($unit, $attributes): OrganizationalUnit {
            $lockedUnit = $this->lockUnit($unit);

            if (isset($attributes['organizational_unit_type_id'])) {
                $type = OrganizationalUnitType::query()->lockForUpdate()->findOrFail($attributes['organizational_unit_type_id']);
                $parent = $lockedUnit->parent_id === null
                    ? null
                    : OrganizationalUnit::query()->lockForUpdate()->findOrFail($lockedUnit->parent_id);
                $this->validatePlacement($type, $parent);
                $this->validateChildrenForType($lockedUnit, $type);
            }

            $lockedUnit->fill($attributes)->save();

            return $lockedUnit->refresh();
        });
    }

    public function move(OrganizationalUnit $unit, ?OrganizationalUnit $newParent): OrganizationalUnit
    {
        return DB::transaction(function () use ($unit, $newParent): OrganizationalUnit {
            $lockedUnit = $this->lockUnit($unit);
            $lockedParent = $newParent === null ? null : $this->lockUnit($newParent);

            if ($lockedParent?->is($lockedUnit)) {
                throw ValidationException::withMessages(['parent_id' => 'Uma unidade não pode ser pai de si mesma.']);
            }

            if ($lockedParent !== null && DB::table('organizational_unit_closure')
                ->where('ancestor_id', $lockedUnit->getKey())
                ->where('descendant_id', $lockedParent->getKey())
                ->exists()) {
                throw ValidationException::withMessages(['parent_id' => 'Uma unidade não pode ser movida para um de seus descendentes.']);
            }

            $this->validatePlacement($lockedUnit->type()->firstOrFail(), $lockedParent);

            if ($lockedUnit->parent_id === $lockedParent?->getKey()) {
                return $lockedUnit;
            }

            /** @var list<object{descendant_id: int, depth: int}> $subtree */
            $subtree = DB::table('organizational_unit_closure')
                ->where('ancestor_id', $lockedUnit->getKey())
                ->orderBy('depth')
                ->lockForUpdate()
                ->get(['descendant_id', 'depth'])
                ->all();
            $descendantIds = array_map(static fn (object $row): int => $row->descendant_id, $subtree);

            DB::table('organizational_unit_closure')
                ->whereIn('descendant_id', $descendantIds)
                ->whereNotIn('ancestor_id', $descendantIds)
                ->delete();

            if ($lockedParent !== null) {
                /** @var list<object{ancestor_id: int, depth: int}> $newAncestors */
                $newAncestors = DB::table('organizational_unit_closure')
                    ->where('descendant_id', $lockedParent->getKey())
                    ->orderBy('depth')
                    ->lockForUpdate()
                    ->get(['ancestor_id', 'depth'])
                    ->all();
                $rows = [];

                foreach ($newAncestors as $ancestor) {
                    foreach ($subtree as $descendant) {
                        $rows[] = [
                            'ancestor_id' => $ancestor->ancestor_id,
                            'descendant_id' => $descendant->descendant_id,
                            'depth' => $ancestor->depth + $descendant->depth + 1,
                        ];
                    }
                }

                DB::table('organizational_unit_closure')->insert($rows);
            }

            $lockedUnit->parent()->associate($lockedParent);
            $lockedUnit->save();

            return $lockedUnit->refresh();
        });
    }

    public function archive(OrganizationalUnit $unit, ?Carbon $archivedAt = null): OrganizationalUnit
    {
        return DB::transaction(function () use ($unit, $archivedAt): OrganizationalUnit {
            $lockedUnit = $this->lockUnit($unit);
            $descendantIds = DB::table('organizational_unit_closure')
                ->where('ancestor_id', $lockedUnit->getKey())
                ->lockForUpdate()
                ->pluck('descendant_id');

            OrganizationalUnit::query()->whereKey($descendantIds)->update([
                'is_active' => false,
                'archived_at' => $archivedAt ?? now(),
                'updated_at' => now(),
            ]);

            return $lockedUnit->refresh();
        });
    }

    private function lockUnit(OrganizationalUnit $unit): OrganizationalUnit
    {
        return OrganizationalUnit::query()->lockForUpdate()->whereKey($unit->getKey())->firstOrFail();
    }

    private function validatePlacement(OrganizationalUnitType $type, ?OrganizationalUnit $parent): void
    {
        if (! $type->is_active) {
            throw ValidationException::withMessages(['organizational_unit_type_id' => 'O tipo de unidade está inativo.']);
        }

        if ($parent === null) {
            return;
        }

        if (! $parent->is_active || $parent->archived_at !== null) {
            throw ValidationException::withMessages(['parent_id' => 'A unidade pai deve estar ativa.']);
        }

        $parentType = $parent->type()->firstOrFail();

        if ($parentType->hierarchy_order >= $type->hierarchy_order) {
            throw ValidationException::withMessages(['parent_id' => 'A unidade pai deve estar em um nível hierárquico superior.']);
        }
    }

    private function validateChildrenForType(OrganizationalUnit $unit, OrganizationalUnitType $type): void
    {
        $hasInvalidChild = $unit->children()
            ->whereHas('type', fn ($query) => $query->where('hierarchy_order', '<=', $type->hierarchy_order))
            ->exists();

        if ($hasInvalidChild) {
            throw ValidationException::withMessages([
                'organizational_unit_type_id' => 'O tipo deve permanecer acima dos tipos das unidades filhas.',
            ]);
        }
    }
}
