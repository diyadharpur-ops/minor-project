<?php

use App\Services\TimetableGenerator;

it('generates conflict-free weekly sessions for a selected semester', function () {
    $generator = new TimetableGenerator();

    $subjects = [
        [
            'id' => 1,
            'name' => 'Java Programming',
            'subject_code' => 'JAVA101',
            'semester' => '5',
            'credit' => 3,
            'theory_hours' => 3,
            'practical_hours' => 0,
            'faculty_name' => 'Prof. Shah',
        ],
        [
            'id' => 2,
            'name' => 'DBMS Lab',
            'subject_code' => 'DBMS201',
            'semester' => '5',
            'credit' => 1,
            'theory_hours' => 0,
            'practical_hours' => 2,
            'faculty_name' => 'Prof. Mehta',
        ],
    ];

    $faculties = [
        ['id' => 1, 'name' => 'Prof. Shah', 'availability' => 'Available', 'subjects' => ['Java Programming']],
        ['id' => 2, 'name' => 'Prof. Mehta', 'availability' => 'Available', 'subjects' => ['DBMS Lab']],
    ];

    $classrooms = [
        ['id' => 1, 'room_number' => 'A101', 'room_type' => 'Theory', 'availability' => 'Available'],
        ['id' => 2, 'room_number' => 'LAB1', 'room_type' => 'Lab', 'availability' => 'Available'],
    ];

    $result = $generator->generate(
        '5',
        ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
        ['09:00-10:00', '10:00-11:00', '11:00-12:00', '01:00-02:00', '02:00-03:00'],
        $subjects,
        $faculties,
        $classrooms
    );

    expect($result['sessions'])->toHaveCount(4);

    $seen = [];
    $labBlocks = array_values(array_filter($result['sessions'], fn ($session) => $session['type'] === 'lab'));
    expect($labBlocks)->toHaveCount(1);
    expect($labBlocks[0]['day'])->toBeString();
    expect($labBlocks[0]['time_slot'])->toBeString();

    foreach ($result['sessions'] as $session) {
        $facultyKey = $session['faculty'] . '|' . $session['day'] . '|' . $session['time_slot'];
        $roomKey = $session['room'] . '|' . $session['day'] . '|' . $session['time_slot'];
        $classKey = $session['subject'] . '|' . $session['day'] . '|' . $session['time_slot'];

        expect($seen)->not->toHaveKey($facultyKey);
        expect($seen)->not->toHaveKey($roomKey);
        expect($seen)->not->toHaveKey($classKey);

        $seen[$facultyKey] = true;
        $seen[$roomKey] = true;
        $seen[$classKey] = true;
    }
});
