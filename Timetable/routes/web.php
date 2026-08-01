<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
});

Route::get('/admin/login', function () {
    return view('admin.login');
});

Route::post('/admin/login', function (Request $request) {
    $email = $request->input('email');
    $password = $request->input('password');

    if ($email === 'admin@example.com' && $password === 'admin') {
        session(['admin.auth' => [
            'name' => 'Admin User',
            'email' => $email,
        ]]);

        return redirect('/admin/dashboard');
    }

    return back()->withErrors(['email' => 'Invalid admin credentials.']);
});

Route::get('/admin/dashboard', function () {
    if (!session('admin.auth')) {
        return redirect('/admin/login');
    }

    return view('admin.dashboard');
});

Route::get('/admin/profile', function () {
    if (!session('admin.auth')) {
        return redirect('/admin/login');
    }

    return view('admin.profile');
});

Route::post('/admin/logout', function () {
    session()->forget('admin.auth');

    return redirect('/');
});
