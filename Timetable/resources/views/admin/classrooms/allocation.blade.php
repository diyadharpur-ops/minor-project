@extends('admin.layout')

@section('title', 'Classroom & Lab Allocation')

@section('content')
    <div class="page-header">
        <div>
            <h1>Classroom & Lab Allocation</h1>
            <p>Manage rooms, labs, and subject allocations from the database.</p>
        </div>
        <div class="page-actions">
            <a href="/admin/classrooms" class="btn btn-muted">View Rooms</a>
        </div>
    </div>

    @if (session('room_status'))
        <div class="alert" style="background:#dcfce7;color:#166534;">{{ session('room_status') }}</div>
    @endif

    @if (session('allocation_status'))
        <div class="alert" style="background:#dcfce7;color:#166534;">{{ session('allocation_status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert">
            <ul style="margin:0;padding-left:18px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="page-card">
        <h2>Room / Lab Details</h2>
        <form method="POST" action="/admin/classroom-allocation">
            @csrf
            <input type="hidden" name="form_type" value="save-room">
            <div class="form-row" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
                <div>
                    <label>Room Number</label>
                    <input type="text" name="room_number" value="{{ old('room_number') }}" placeholder="Enter room number" required>
                </div>
                <div>
                    <label>Room Name</label>
                    <input type="text" name="room_name" value="{{ old('room_name') }}" placeholder="Enter room name" required>
                </div>
                <div>
                    <label>Room Type</label>
                    <select name="room_type" required>
                        <option value="">Select room type</option>
                        <option value="Classroom" {{ old('room_type') === 'Classroom' ? 'selected' : '' }}>Classroom</option>
                        <option value="Computer Lab" {{ old('room_type') === 'Computer Lab' ? 'selected' : '' }}>Computer Lab</option>
                        <option value="Electrical Lab" {{ old('room_type') === 'Electrical Lab' ? 'selected' : '' }}>Electrical Lab</option>
                        <option value="Mechanical Lab" {{ old('room_type') === 'Mechanical Lab' ? 'selected' : '' }}>Mechanical Lab</option>
                        <option value="Civil Lab" {{ old('room_type') === 'Civil Lab' ? 'selected' : '' }}>Civil Lab</option>
                        <option value="Other Lab" {{ old('room_type') === 'Other Lab' ? 'selected' : '' }}>Other Lab</option>
                    </select>
                </div>
                <div>
                    <label>Room Capacity</label>
                    <input type="number" name="room_capacity" min="1" value="{{ old('room_capacity') }}" placeholder="Enter capacity" required>
                </div>
                <div>
                    <label>Facilities</label>
                    <input type="text" name="facilities" value="{{ old('facilities') }}" placeholder="e.g. Projector, Wi-Fi">
                </div>
                <div>
                    <label>Department</label>
                    <select name="department_id" required>
                        <option value="">Select department</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" {{ old('department_id') == $department->id ? 'selected' : '' }}>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label>Room Status</label>
                    <select name="availability" required>
                        <option value="">Select status</option>
                        <option value="Available" {{ old('availability') === 'Available' ? 'selected' : '' }}>Available</option>
                        <option value="Occupied" {{ old('availability') === 'Occupied' ? 'selected' : '' }}>Occupied</option>
                        <option value="Maintenance" {{ old('availability') === 'Maintenance' ? 'selected' : '' }}>Maintenance</option>
                    </select>
                </div>
            </div>
            <div class="page-actions">
                <button type="submit" class="btn">Save Room</button>
            </div>
        </form>
    </div>

    <div class="page-card">
        <h2>Room List</h2>

        <form method="GET" action="/admin/classroom-allocation" class="search">
            <input type="text" name="room_search" value="{{ request('room_search') }}" placeholder="Search room number, name, type or facilities">
            <select name="room_type_filter">
                <option value="">All room types</option>
                <option value="Classroom" {{ request('room_type_filter') === 'Classroom' ? 'selected' : '' }}>Classroom</option>
                <option value="Computer Lab" {{ request('room_type_filter') === 'Computer Lab' ? 'selected' : '' }}>Computer Lab</option>
                <option value="Electrical Lab" {{ request('room_type_filter') === 'Electrical Lab' ? 'selected' : '' }}>Electrical Lab</option>
                <option value="Mechanical Lab" {{ request('room_type_filter') === 'Mechanical Lab' ? 'selected' : '' }}>Mechanical Lab</option>
                <option value="Civil Lab" {{ request('room_type_filter') === 'Civil Lab' ? 'selected' : '' }}>Civil Lab</option>
                <option value="Other Lab" {{ request('room_type_filter') === 'Other Lab' ? 'selected' : '' }}>Other Lab</option>
            </select>
            <select name="room_department_filter">
                <option value="">All departments</option>
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}" {{ request('room_department_filter') == $department->id ? 'selected' : '' }}>{{ $department->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn">Filter</button>
            <a href="/admin/classroom-allocation" class="btn btn-muted">Clear</a>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Room Number</th>
                        <th>Room Name</th>
                        <th>Type</th>
                        <th>Capacity</th>
                        <th>Facilities</th>
                        <th>Department</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rooms as $room)
                        <tr>
                            <td>{{ $room->room_number }}</td>
                            <td>{{ $room->room_name }}</td>
                            <td>{{ $room->room_type }}</td>
                            <td>{{ $room->room_capacity }}</td>
                            <td>{{ $room->facilities ?: '—' }}</td>
                            <td>{{ $room->department?->name ?? '—' }}</td>
                            <td>{{ $room->availability }}</td>
                            <td class="actions">
                                <form method="POST" action="/admin/classroom-allocation/{{ $room->id }}/delete-room" onsubmit="return confirm('Delete this room?');">
                                    @csrf
                                    <button type="submit" class="btn btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8">No rooms added yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="page-card">
        <h2>Allocate Room to Subject</h2>
        <form method="POST" action="/admin/classroom-allocation">
            @csrf
            <input type="hidden" name="form_type" value="find-room">
            <div class="form-row" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
                <div>
                    <label>Department</label>
                    <select name="department_id" required>
                        <option value="">Select department</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" {{ old('department_id') == $department->id ? 'selected' : '' }}>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label>Semester</label>
                    <input type="text" name="semester" value="{{ old('semester') }}" placeholder="Enter semester" required>
                </div>
                <div>
                    <label>Subject</label>
                    <select name="subject_id" required>
                        <option value="">Select subject</option>
                        @foreach ($subjects as $subject)
                            <option value="{{ $subject->id }}" {{ old('subject_id') == $subject->id ? 'selected' : '' }}>{{ $subject->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label>Faculty</label>
                    <select name="faculty_id" required>
                        <option value="">Select faculty</option>
                        @foreach ($faculties as $faculty)
                            <option value="{{ $faculty->id }}" {{ old('faculty_id') == $faculty->id ? 'selected' : '' }}>{{ $faculty->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label>Class / Division</label>
                    <input type="text" name="class_name" value="{{ old('class_name') }}" placeholder="e.g. CE-5A" required>
                </div>
                <div>
                    <label>Number of Students</label>
                    <input type="number" name="student_count" value="{{ old('student_count') }}" min="1" placeholder="Enter number" required>
                </div>
                <div>
                    <label>Day</label>
                    <select name="day" required>
                        <option value="">Select day</option>
                        @foreach (['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] as $day)
                            <option value="{{ $day }}" {{ old('day') === $day ? 'selected' : '' }}>{{ $day }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label>Start Time</label>
                    <input type="time" name="start_time" value="{{ old('start_time') }}" required>
                </div>
                <div>
                    <label>End Time</label>
                    <input type="time" name="end_time" value="{{ old('end_time') }}" required>
                </div>
            </div>
            <div class="page-actions">
                <button type="submit" class="btn">Find Suitable Room</button>
            </div>
        </form>
    </div>

    @if ($suitableRooms->isNotEmpty())
        <div class="page-card">
            <h2>Suitable Rooms</h2>
            <table>
                <thead>
                    <tr>
                        <th>Room Number</th>
                        <th>Room Name</th>
                        <th>Type</th>
                        <th>Capacity</th>
                        <th>Facilities</th>
                        <th>Department</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($suitableRooms as $room)
                        <tr>
                            <td>{{ $room->room_number }}</td>
                            <td>{{ $room->room_name }}</td>
                            <td>{{ $room->room_type }}</td>
                            <td>{{ $room->room_capacity }}</td>
                            <td>{{ $room->facilities ?: '—' }}</td>
                            <td>{{ $room->department?->name ?? '—' }}</td>
                            <td>
                                <form method="POST" action="/admin/classroom-allocation">
                                    @csrf
                                    <input type="hidden" name="form_type" value="save-allocation">
                                    <input type="hidden" name="department_id" value="{{ old('department_id') }}">
                                    <input type="hidden" name="semester" value="{{ old('semester') }}">
                                    <input type="hidden" name="subject_id" value="{{ old('subject_id') }}">
                                    <input type="hidden" name="faculty_id" value="{{ old('faculty_id') }}">
                                    <input type="hidden" name="class_name" value="{{ old('class_name') }}">
                                    <input type="hidden" name="student_count" value="{{ old('student_count') }}">
                                    <input type="hidden" name="day" value="{{ old('day') }}">
                                    <input type="hidden" name="start_time" value="{{ old('start_time') }}">
                                    <input type="hidden" name="end_time" value="{{ old('end_time') }}">
                                    <input type="hidden" name="room_id" value="{{ $room->id }}">
                                    <button type="submit" class="btn">Allocate</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="page-card">
        <h2>Allocations</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Class</th>
                        <th>Subject</th>
                        <th>Faculty</th>
                        <th>Room</th>
                        <th>Day</th>
                        <th>Time</th>
                        <th>Students</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($allocations as $allocation)
                        <tr>
                            <td>{{ $allocation->class_name }}</td>
                            <td>{{ $allocation->subject?->name ?? '—' }}</td>
                            <td>{{ $allocation->faculty?->name ?? '—' }}</td>
                            <td>{{ $allocation->classroom?->room_number ?? '—' }}</td>
                            <td>{{ $allocation->day }}</td>
                            <td>{{ $allocation->start_time }} - {{ $allocation->end_time }}</td>
                            <td>{{ $allocation->student_count ?? '—' }}</td>
                            <td class="actions">
                                <form method="POST" action="/admin/classroom-allocation/{{ $allocation->id }}/delete-allocation" onsubmit="return confirm('Delete this allocation?');">
                                    @csrf
                                    <button type="submit" class="btn btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8">No allocations found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
