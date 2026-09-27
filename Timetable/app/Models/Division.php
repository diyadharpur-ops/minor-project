<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'semester', 'is_active'])]
class Division extends Model
{
    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class);
    }

    /**
     * Get active divisions for a specific semester, exactly matching Manage Subjects logic:
     * - Default divisions are 'A', 'B', 'C'
     * - If a division record exists in DB for this semester, use its is_active value
     * - If no DB record exists for this division in this semester, it defaults to active (true)
     */
    public static function getActiveDivisionsForSemester($semester): array
    {
        $divisions = static::where('semester', (string) $semester)->get();
        $defaultNames = ['A', 'B', 'C'];
        $existing = $divisions->keyBy('name');
        
        $active = [];
        foreach ($defaultNames as $name) {
            if ($existing->has($name)) {
                if ((bool) $existing->get($name)->is_active) {
                    $active[] = $name;
                }
            } else {
                $active[] = $name;
            }
        }
        
        foreach ($divisions as $div) {
            if (!in_array($div->name, $defaultNames) && (bool) $div->is_active) {
                $active[] = $div->name;
            }
        }

        if (empty($active)) {
            $allForSem = $divisions->pluck('name')->toArray();
            $active = !empty($allForSem) ? $allForSem : ['A', 'B'];
        }
        
        sort($active, SORT_NATURAL);
        return array_values($active);
    }

    /**
     * Get divisions map for all semesters that have subjects in the database.
     * Returns array like: ['3' => ['A', 'B'], '5' => ['A', 'B']]
     */
    public static function getDivisionsMap($semesters = null): array
    {
        if ($semesters === null) {
            $semesters = Subject::whereNotNull('semester')
                ->distinct()
                ->pluck('semester')
                ->filter()
                ->sort()
                ->values();
        }

        $allDbDivisions = static::all()->groupBy(fn($d) => (string) $d->semester);
        $defaultNames = ['A', 'B', 'C'];
        $map = [];

        foreach ($semesters as $sem) {
            $semStr = (string) $sem;
            $semDivs = $allDbDivisions->get($semStr, collect())->keyBy('name');
            $active = [];
            foreach ($defaultNames as $name) {
                if ($semDivs->has($name)) {
                    if ((bool) $semDivs->get($name)->is_active) {
                        $active[] = $name;
                    }
                } else {
                    $active[] = $name;
                }
            }
            foreach ($allDbDivisions->get($semStr, collect()) as $div) {
                if (!in_array($div->name, $defaultNames) && (bool) $div->is_active) {
                    $active[] = $div->name;
                }
            }
            if (empty($active)) {
                $allForSem = $allDbDivisions->get($semStr, collect())->pluck('name')->toArray();
                $active = !empty($allForSem) ? $allForSem : ['A', 'B'];
            }
            sort($active, SORT_NATURAL);
            $map[$semStr] = array_values($active);
        }

        return $map;
    }

    /**
     * Get all unique active divisions across the given (or all subject) semesters.
     */
    public static function getAllActiveDivisions($semesters = null): array
    {
        $map = static::getDivisionsMap($semesters);
        $all = collect($map)->flatten()->unique()->sort()->values()->toArray();
        return !empty($all) ? $all : ['A', 'B'];
    }

}
