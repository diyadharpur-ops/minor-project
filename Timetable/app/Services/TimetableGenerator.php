<?php

namespace App\Services;

class TimetableGenerator
{
    public function generate(string $semester, array $days, array $timeSlots, array $subjects, array $faculties, array $classrooms): array
    {
        $facultyMap = [];
        foreach ($faculties as $faculty) {
            $facultyMap[$faculty['name']] = $faculty;
        }

        $theoryRooms = array_values(array_filter($classrooms, fn ($room) => $this->isTheoryRoom($room)));
        $labRooms = array_values(array_filter($classrooms, fn ($room) => $this->isLabRoom($room)));

        $sessions = [];
        $used = [];
        $subjectLoad = [];

        $subjects = array_values(array_filter($subjects, fn ($subject) => (string) ($subject['semester'] ?? '') === (string) $semester));
        usort($subjects, function ($a, $b) {
            $aHours = (int) (($a['theory_hours'] ?? 0) + ($a['practical_hours'] ?? 0));
            $bHours = (int) (($b['theory_hours'] ?? 0) + ($b['practical_hours'] ?? 0));

            return $bHours <=> $aHours;
        });

        foreach ($subjects as $subject) {
            $type = $this->resolveType($subject);
            $hours = $type === 'lab' ? (int) ($subject['practical_hours'] ?? 0) : (int) ($subject['theory_hours'] ?? 0);
            $faculty = $facultyMap[$subject['faculty_name']] ?? null;

            if (! $faculty) {
                continue;
            }

            if ($type === 'lab') {
                $this->addLabSessions($sessions, $used, $subject, $faculty, $labRooms, $days, $timeSlots);
                continue;
            }

            $this->addTheorySessions($sessions, $used, $subject, $faculty, $theoryRooms, $days, $timeSlots, $hours);
        }

        return [
            'semester' => $semester,
            'days' => $days,
            'time_slots' => $timeSlots,
            'sessions' => $sessions,
        ];
    }

    private function addTheorySessions(array &$sessions, array &$used, array $subject, array $faculty, array $rooms, array $days, array $timeSlots, int $hours): void
    {
        $availableRooms = array_values(array_filter($rooms, function (array $room): bool {
            return $this->isRoomAvailable($room);
        }));

        if ($availableRooms === []) {
            return;
        }

        for ($i = 0; $i < $hours; $i++) {
            $room = $this->pickBestRoom($availableRooms, $used, $days, $timeSlots);
            if ($room === null) {
                break;
            }

            [$day, $slot] = $this->findNextAvailableSlot($used, $days, $timeSlots, $faculty['name'], $room['room_number']);

            if ($day === null || $slot === null) {
                break;
            }

            $sessions[] = [
                'subject' => $subject['name'],
                'faculty' => $faculty['name'],
                'room' => $room['room_number'],
                'day' => $day,
                'time_slot' => $slot,
                'type' => 'theory',
            ];

            $used[$this->makeKey($faculty['name'], $day, $slot)] = true;
            $used[$this->makeKey($room['room_number'], $day, $slot)] = true;
            $used[$this->makeKey($subject['name'], $day, $slot)] = true;
        }
    }

    private function addLabSessions(array &$sessions, array &$used, array $subject, array $faculty, array $rooms, array $days, array $timeSlots): void
    {
        $availableRooms = array_values(array_filter($rooms, function (array $room): bool {
            return $this->isRoomAvailable($room);
        }));

        if ($availableRooms === []) {
            return;
        }

        $room = $this->pickBestRoom($availableRooms, $used, $days, $timeSlots);
        if ($room === null) {
            return;
        }

        [$day, $slot] = $this->findNextAvailableLabSlot($used, $days, $timeSlots, $faculty['name'], $room['room_number']);

        if ($day === null || $slot === null) {
            return;
        }

        $nextSlot = $this->nextSlot($timeSlots, $slot);
        if ($nextSlot === null) {
            return;
        }

        $sessions[] = [
            'subject' => $subject['name'],
            'faculty' => $faculty['name'],
            'room' => $room['room_number'],
            'day' => $day,
            'time_slot' => $slot,
            'end_time_slot' => $nextSlot,
            'type' => 'lab',
        ];

        $used[$this->makeKey($faculty['name'], $day, $slot)] = true;
        $used[$this->makeKey($faculty['name'], $day, $nextSlot)] = true;
        $used[$this->makeKey($room['room_number'], $day, $slot)] = true;
        $used[$this->makeKey($room['room_number'], $day, $nextSlot)] = true;
        $used[$this->makeKey($subject['name'], $day, $slot)] = true;
        $used[$this->makeKey($subject['name'], $day, $nextSlot)] = true;
    }

    private function findNextAvailableSlot(array $used, array $days, array $timeSlots, string $faculty, string $room): array
    {
        $bestDay = null;
        $bestSlot = null;
        $bestScore = null;

        foreach ($days as $day) {
            foreach ($timeSlots as $slot) {
                if (isset($used[$this->makeKey($faculty, $day, $slot)]) || isset($used[$this->makeKey($room, $day, $slot)])) {
                    continue;
                }

                $score = $this->dayLoadScore($used, $day);
                if ($bestScore === null || $score < $bestScore) {
                    $bestScore = $score;
                    $bestDay = $day;
                    $bestSlot = $slot;
                }
            }
        }

        return [$bestDay, $bestSlot];
    }

    private function pickBestRoom(array $rooms, array $used, array $days, array $timeSlots): ?array
    {
        $bestRoom = null;
        $bestScore = null;

        foreach ($rooms as $room) {
            $score = 0;
            foreach ($days as $day) {
                foreach ($timeSlots as $slot) {
                    if (isset($used[$this->makeKey($room['room_number'], $day, $slot)])) {
                        $score++;
                    }
                }
            }

            if ($bestScore === null || $score < $bestScore) {
                $bestScore = $score;
                $bestRoom = $room;
            }
        }

        return $bestRoom;
    }

    private function findNextAvailableLabSlot(array $used, array $days, array $timeSlots, string $faculty, string $room): array
    {
        $bestDay = null;
        $bestSlot = null;
        $bestScore = null;

        foreach ($days as $day) {
            foreach ($timeSlots as $index => $slot) {
                $nextSlot = $this->nextSlot($timeSlots, $slot);
                if ($nextSlot === null) {
                    continue;
                }

                $isFree = ! isset($used[$this->makeKey($faculty, $day, $slot)])
                    && ! isset($used[$this->makeKey($faculty, $day, $nextSlot)])
                    && ! isset($used[$this->makeKey($room, $day, $slot)])
                    && ! isset($used[$this->makeKey($room, $day, $nextSlot)]);

                if (! $isFree) {
                    continue;
                }

                $score = $this->dayLoadScore($used, $day);
                if ($bestScore === null || $score < $bestScore) {
                    $bestScore = $score;
                    $bestDay = $day;
                    $bestSlot = $slot;
                }
            }
        }

        return [$bestDay, $bestSlot];
    }

    private function nextSlot(array $timeSlots, string $current): ?string
    {
        $index = array_search($current, $timeSlots, true);
        if ($index === false || $index + 1 >= count($timeSlots)) {
            return null;
        }

        return $timeSlots[$index + 1];
    }

    private function dayLoadScore(array $used, string $day): int
    {
        $count = 0;
        foreach ($used as $key => $value) {
            if (str_contains($key, '|'.$day.'|')) {
                $count++;
            }
        }

        return $count;
    }

    private function isTheoryRoom(array $room): bool
    {
        $type = strtolower((string) ($room['room_type'] ?? ''));

        return $type === 'classroom' || $type === 'theory' || $type === 'lecture';
    }

    private function isLabRoom(array $room): bool
    {
        $type = strtolower((string) ($room['room_type'] ?? ''));

        return $type === 'lab' || $type === 'laboratory';
    }

    private function isRoomAvailable(array $room): bool
    {
        $availability = strtolower((string) ($room['availability'] ?? ''));

        return $availability !== 'booked' && $availability !== 'maintenance';
    }

    private function makeKey(string $value, string $day, string $timeSlot): string
    {
        return $value . '|' . $day . '|' . $timeSlot;
    }

    private function resolveType(array $subject): string
    {
        $subjectType = strtolower((string) ($subject['subject_type'] ?? $subject['type'] ?? ''));

        if ($subjectType === 'lab') {
            return 'lab';
        }

        if ($subjectType === 'tutorial') {
            return 'tutorial';
        }

        if ((int) ($subject['practical_hours'] ?? 0) > 0) {
            return 'lab';
        }

        return 'theory';
    }
}
