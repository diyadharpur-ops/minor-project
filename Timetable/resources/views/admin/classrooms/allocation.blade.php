@extends('admin.layout')

@section('title', 'Classroom & Lab Allocation')

@section('content')
<style>
    .summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }
    .summary-card {
        background: white;
        padding: 16px;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        text-align: center;
    }
    .summary-card h3 {
        margin: 0;
        font-size: 0.9rem;
        color: #6b7280;
        font-weight: 500;
    }
    .summary-card .val {
        font-size: 1.8rem;
        font-weight: 700;
        color: #111827;
        margin-top: 8px;
    }
    .timing-card {
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        margin-bottom: 24px;
    }
    .timing-card h2 {
        margin-top: 0;
        font-size: 1.2rem;
        margin-bottom: 16px;
    }
    .timing-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        margin-bottom: 20px;
    }
    .timing-box {
        background: #f9fafb;
        padding: 12px;
        border-radius: 6px;
        border: 1px solid #e5e7eb;
    }
    .timing-box h4 {
        margin: 0 0 4px 0;
        font-size: 0.85rem;
        color: #4b5563;
    }
    .timing-box p {
        margin: 0;
        font-size: 0.95rem;
        font-weight: 600;
    }
    .slots-container {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }
    .slot-badge {
        background: #e0f2fe;
        color: #0369a1;
        padding: 6px 12px;
        border-radius: 4px;
        font-size: 0.85rem;
        font-weight: 500;
    }
    .slot-badge.theory {
        background: #dcfce7;
        color: #166534;
    }
    .badge {
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 0.85em;
        font-weight: 600;
        display: inline-block;
    }
    .badge-allocated { background: #dcfce7; color: #166534; }
    .badge-unallocated { background: #fee2e2; color: #991b1b; }
    .badge-classroom { background: #dcfce7; color: #166534; }
    .badge-lab { background: #e0f2fe; color: #0369a1; }
    
    .allocation-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
    }
    .note-card {
        background: #fffbeb;
        border: 1px solid #fef3c7;
        color: #92400e;
        padding: 16px;
        border-radius: 8px;
        margin-top: 24px;
        font-size: 0.9rem;
    }
</style>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start;">
    <div>
        <h1>Classroom & Lab Allocation</h1>
        <p style="color: #6b7280; font-size: 0.9rem; margin-top: 4px;">Auto allocate classrooms and labs based on subject type.</p>
    </div>
    <div style="text-align: right;">
        <div style="font-weight: 600; font-size: 0.95rem; margin-bottom: 4px;">Academic Year : {{ \App\Models\TimetableEntry::max('academic_year') ?? '2024-25' }}</div>
    </div>
</div>

@if (session('allocation_status'))
    <div class="alert" style="background:#dcfce7;color:#166534;">{{ session('allocation_status') }}</div>
@endif

@if ($errors->any())
    <div class="alert" style="background:#fee2e2;color:#991b1b;">
        <ul style="margin:0;padding-left:18px;">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

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

<div class="page-card">
    <div class="allocation-header">
        <h2 style="margin: 0;">Allocation Results</h2>
        <div class="page-actions">
            <form method="POST" action="/admin/classroom-allocation" style="display:inline;" id="autoGenForm">
                @csrf
                <input type="hidden" name="form_type" value="auto-allocate">
                <button type="submit" class="btn" onclick="this.innerHTML='Generating...'; this.disabled=true; document.getElementById('autoGenForm').submit();">
                    ⚙️ Auto Generate
                </button>
            </form>
            <form method="POST" action="/admin/classroom-allocation" style="display:inline;" onsubmit="return confirm('This will clear current allocations and re-generate. Continue?');" id="regenForm">
                @csrf
                <input type="hidden" name="form_type" value="re-generate">
                <button type="submit" class="btn btn-danger" onclick="this.innerHTML='Re-Generating...'; this.disabled=true; document.getElementById('regenForm').submit();">
                    🔄 Re-Generate
                </button>
            </form>
        </div>
    </div>

    <div class="table-wrap">
        <table style="min-width: 900px;">
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
                        <td>{{ $allocation->notes ?: ($allocation->classroom?->room_number ?? '—') }}</td>
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
        </table>
    </div>

    @if(method_exists($allocations, 'links'))
        <div style="margin-top: 16px;">
            {{ $allocations->links() }}
        </div>
    @endif
</div>

@endsection
