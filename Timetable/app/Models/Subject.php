<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'name',
    'subject_code',
    'semester',
    'division_id',
    'department_id',
    'faculty_id',
    'subject_type',
    'credit',
    'lecture_credit',
    'lab_credit',
    'tutorial_credit',
    'folder_path',
    'class_selections',
    'division_selections',
    'tutorial_selections',
])]
class Subject extends Model
{
    protected function casts(): array
    {
        return [
            'class_selections' => 'array',
            'division_selections' => 'array',
            'tutorial_selections' => 'array',
        ];
    }

    public function getWeeklyHoursAttribute(): int
    {
        $hours = $this->lecture_weekly_hours
            + $this->lab_weekly_hours
            + $this->tutorial_weekly_hours;

        return $hours > 0 || ! str_contains(strtolower((string) $this->subject_type), 'project')
            ? $hours
            : (int) ($this->credit ?? 0) * count($this->effective_class_selections);
    }

    public function getEffectiveClassSelectionsAttribute(): array
    {
        if ($this->class_selections !== null) {
            return $this->class_selections;
        }

        $classes = collect([
            ...($this->division_selections ?? []),
            ...($this->tutorial_selections ?? []),
        ])
            ->map(fn (string $selection): string => $selection[0])
            ->filter(fn (string $class): bool => in_array($class, ['A', 'B', 'C'], true))
            ->unique()
            ->values();

        if ($classes->isNotEmpty()) {
            return $classes->all();
        }

        $legacyClass = $this->division?->name;

        return in_array($legacyClass, ['A', 'B', 'C'], true) ? [$legacyClass] : [];
    }

    public function getTotalLectureCreditsAttribute(): int
    {
        return (int) ($this->lecture_credit ?? 0) * count($this->effective_class_selections);
    }

    public function getTotalLabCreditsAttribute(): int
    {
        return (int) ($this->lab_credit ?? 0) * count($this->division_selections ?? []);
    }

    public function getTotalTutorialCreditsAttribute(): int
    {
        return (int) ($this->tutorial_credit ?? 0) * count($this->tutorial_selections ?? []);
    }

    public function getLectureWeeklyHoursAttribute(): int
    {
        return $this->total_lecture_credits;
    }

    public function getLabWeeklyHoursAttribute(): int
    {
        return $this->total_lab_credits * 2;
    }

    public function getTutorialWeeklyHoursAttribute(): int
    {
        return $this->total_tutorial_credits;
    }

    public function lectureCreditsForClass(string $class): int
    {
        return in_array($class, $this->effective_class_selections, true)
            ? (int) ($this->lecture_credit ?? 0)
            : 0;
    }

    public function labCreditsForClass(string $class): int
    {
        if (! in_array($class, $this->effective_class_selections, true)) {
            return 0;
        }

        $divisionCount = collect($this->division_selections ?? [])
            ->filter(fn (string $selection): bool => $selection[0] === $class)
            ->count();

        return (int) ($this->lab_credit ?? 0) * $divisionCount;
    }

    public function tutorialCreditsForClass(string $class): int
    {
        if (! in_array($class, $this->effective_class_selections, true)) {
            return 0;
        }

        $tutorialCount = collect($this->tutorial_selections ?? [])
            ->filter(fn (string $selection): bool => $selection[0] === $class)
            ->count();

        return (int) ($this->tutorial_credit ?? 0) * $tutorialCount;
    }

    public function weeklyHoursForClass(string $class): int
    {
        $hours = $this->lectureCreditsForClass($class)
            + ($this->labCreditsForClass($class) * 2)
            + $this->tutorialCreditsForClass($class);

        if ($hours > 0
            || ! str_contains(strtolower((string) $this->subject_type), 'project')
            || ! in_array($class, $this->effective_class_selections, true)) {
            return $hours;
        }

        return (int) ($this->credit ?? 0);
    }

    public function getClassSelectionLabelAttribute(): string
    {
        if ($this->effective_class_selections !== []) {
            return implode(', ', $this->effective_class_selections);
        }

        return $this->class_selections !== null ? 'No classes' : 'Not specified';
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }
}
