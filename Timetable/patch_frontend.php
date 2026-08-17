<?php

$file = 'resources/views/admin/classrooms/allocation.blade.php';
$content = file_get_contents($file);

// Replace Summary Grid
$summaryGridStart = strpos($content, '<div class="summary-grid">');
$summaryGridEnd = strpos($content, '</div>', strpos($content, '</div>', strpos($content, '</div>', strpos($content, '</div>', $summaryGridStart) + 1) + 1) + 1) + 6;

$newSummaryGrid = <<<'HTML'
<div class="summary-grid">
    <div class="summary-card">
        <h3>Total Subjects</h3>
        <div class="val">{{ $totalLectures ?? 0 }}</div>
    </div>
    <div class="summary-card">
        <h3>Classroom Allocated</h3>
        <div class="val" style="color: #166534;">{{ $allocatedCount ?? 0 }}</div>
    </div>
    <div class="summary-card">
        <h3>Lab Allocated</h3>
        <div class="val" style="color: #0369a1;">{{ $allocatedLabCount ?? 0 }}</div>
    </div>
    <div class="summary-card">
        <h3>Unallocated</h3>
        <div class="val" style="color: #991b1b;">{{ $unallocatedCount ?? 0 }}</div>
    </div>
</div>
HTML;

$content = substr_replace($content, $newSummaryGrid, $summaryGridStart, $summaryGridEnd - $summaryGridStart);

// Remove timing-card
$timingCardStart = strpos($content, '<div class="timing-card">');
$timingCardEnd = strpos($content, '<div class="page-card">');
if ($timingCardStart !== false && $timingCardEnd !== false) {
    $content = substr_replace($content, '', $timingCardStart, $timingCardEnd - $timingCardStart);
}

// Update table header
$tableHeadStart = strpos($content, '<thead>');
$tableHeadEnd = strpos($content, '</thead>') + 8;
$newTableHead = <<<'HTML'
            <thead>
                <tr>
                    <th>Sr. No.</th>
                    <th>Class / Division</th>
                    <th>Subject</th>
                    <th>Subject Type</th>
                    <th>Allocated Classroom / Lab</th>
                    <th>Status</th>
                </tr>
            </thead>
HTML;
$content = substr_replace($content, $newTableHead, $tableHeadStart, $tableHeadEnd - $tableHeadStart);

// Update table body
$tableBodyStart = strpos($content, '<tbody>');
$tableBodyEnd = strpos($content, '</tbody>') + 8;

$newTableBody = <<<'HTML'
            <tbody>
                @forelse ($allocations as $index => $allocation)
                    <tr>
                        <td>{{ method_exists($allocations, 'firstItem') ? $allocations->firstItem() + $index : $index + 1 }}</td>
                        <td>{{ $allocation->class_name }}</td>
                        <td>{{ $allocation->subject?->name ?? '—' }}</td>
                        <td>
                            @php
                                $subType = $allocation->subject?->subject_type ?? 'Classroom';
                                $isLab = str_contains(strtolower($subType), 'lab') || str_contains(strtolower($subType), 'practical');
                            @endphp
                            <span class="badge {{ $isLab ? 'badge-lab' : 'badge-classroom' }}">
                                {{ $isLab ? 'Lab' : 'Classroom' }}
                            </span>
                        </td>
                        <td>{{ $allocation->classroom?->room_number ?? '—' }}</td>
                        <td>
                            @if($allocation->status == 'Allocated')
                                <span class="badge badge-allocated">Allocated</span>
                            @else
                                <span class="badge badge-unallocated">Unallocated</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="text-align: center; padding: 24px;">No allocation records found. Click Auto Generate to start.</td></tr>
                @endforelse
            </tbody>
HTML;
$content = substr_replace($content, $newTableBody, $tableBodyStart, $tableBodyEnd - $tableBodyStart);

// Remove note card
$noteCardStart = strpos($content, '<div class="note-card">');
$noteCardEnd = strpos($content, '@endsection');
if ($noteCardStart !== false && $noteCardEnd !== false) {
    $content = substr_replace($content, '', $noteCardStart, $noteCardEnd - $noteCardStart);
}

// Update page subtitle
$subtitleOld = "Auto allocate classrooms and labs based on timetable and subject type (Classroom / Lab)";
$subtitleNew = "Auto allocate classrooms and labs based on subject type.";
$content = str_replace($subtitleOld, $subtitleNew, $content);

file_put_contents($file, $content);
echo "Successfully patched $file\n";
