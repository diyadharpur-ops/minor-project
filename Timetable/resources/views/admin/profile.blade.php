@extends('admin.layout')

@section('title', 'Admin Profile')

@section('content')
    <div class="page-header">
        <div>
            <h1>Admin Profile</h1>
            <p>Administrator account details</p>
        </div>
        <a href="/admin/dashboard" class="btn">Back to Dashboard</a>
    </div>

    <div class="page-card">
        <div class="row"><span class="label">Name</span><span>{{ session('admin.auth.name') }}</span></div>
        <div class="row"><span class="label">Email</span><span>{{ session('admin.auth.email') }}</span></div>
        <div class="row"><span class="label">Role</span><span>Administrator</span></div>
    </div>
@endsection
