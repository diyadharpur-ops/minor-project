<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>K. D. Polytechnic | Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --navy: #0f172a;
            --navy-light: #1e293b;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --bg-main: #f8fafc;
            --bg-card: #ffffff;
            --border: #e2e8f0;
            --error: #ef4444;
            --success: #10b981;
            --font-main: 'Inter', sans-serif;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-main);
            background-color: var(--bg-main);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
        }

        /* Split Layout */
        .layout-container {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }

        /* Left Panel */
        .left-panel {
            flex: 1;
            background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%);
            color: white;
            padding: 4rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .left-panel::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 20% 80%, rgba(37, 99, 235, 0.15) 0%, transparent 50%),
                        radial-gradient(circle at 80% 20%, rgba(37, 99, 235, 0.1) 0%, transparent 40%);
            z-index: 1;
        }

        .left-content {
            position: relative;
            z-index: 2;
            max-width: 600px;
            margin: 0 auto;
        }

        .college-name {
            font-size: 1.25rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #94a3b8;
            margin-bottom: 1rem;
        }

        .system-title {
            font-size: 3rem;
            font-weight: 700;
            line-height: 1.2;
            margin-bottom: 3rem;
            background: linear-gradient(to right, #ffffff, #cbd5e1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .features-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 1.25rem;
            font-size: 1.1rem;
            color: #e2e8f0;
            font-weight: 500;
        }

        .feature-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            color: #60a5fa;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .illustration {
            margin-top: 4rem;
            opacity: 0.8;
            max-width: 80%;
        }

        /* Right Panel */
        .right-panel {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 2rem;
            background: var(--bg-main);
            position: relative;
        }

        .login-wrapper {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 0;
        }

        .login-card {
            background: var(--bg-card);
            width: 100%;
            max-width: 480px;
            border-radius: 24px;
            padding: 3rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.04),
                        0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .login-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .sys-icon {
            width: 56px;
            height: 56px;
            background: var(--primary);
            color: white;
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            box-shadow: 0 10px 20px rgba(37, 99, 235, 0.2);
        }

        .login-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 0.5rem;
        }

        .login-subtitle {
            color: var(--text-muted);
            font-size: 0.95rem;
        }

        /* Tabs */
        .role-tabs {
            display: flex;
            background: #f1f5f9;
            padding: 4px;
            border-radius: 12px;
            margin-bottom: 2rem;
        }

        .role-tab {
            flex: 1;
            padding: 0.75rem;
            text-align: center;
            border: none;
            background: transparent;
            color: var(--text-muted);
            font-weight: 600;
            font-size: 0.9rem;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .role-tab:hover {
            color: var(--text-main);
        }

        .role-tab.active {
            background: var(--bg-card);
            color: var(--primary);
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        /* Forms */
        .form-section {
            display: none;
            animation: fadeIn 0.3s ease;
        }

        .form-section.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(5px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .input-group {
            margin-bottom: 1.25rem;
        }

        .input-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 0.5rem;
        }

        .input-wrapper {
            position: relative;
        }

        .input-field {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 1px solid var(--border);
            border-radius: 10px;
            font-size: 0.95rem;
            color: var(--text-main);
            transition: all 0.2s ease;
            background: #fafafa;
        }

        .input-field:focus {
            outline: none;
            border-color: var(--primary);
            background: #fff;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 4px;
        }

        .password-toggle:hover {
            color: var(--text-main);
        }

        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-muted);
            cursor: pointer;
        }

        .remember-me input {
            accent-color: var(--primary);
            width: 16px;
            height: 16px;
        }

        .forgot-password {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }

        .forgot-password:hover {
            text-decoration: underline;
        }

        .btn-primary {
            width: 100%;
            padding: 0.875rem;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
        }

        .btn-primary:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        }

        .btn-primary.loading {
            color: transparent;
        }

        .btn-primary.loading::after {
            content: "";
            position: absolute;
            width: 20px;
            height: 20px;
            top: 50%;
            left: 50%;
            margin: -10px 0 0 -10px;
            border: 3px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 1s infinite linear;
        }

        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 2rem 0;
            color: var(--text-muted);
            font-size: 0.875rem;
        }

        .divider::before, .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid var(--border);
        }

        .divider::before { margin-right: .75rem; }
        .divider::after { margin-left: .75rem; }

        .btn-google {
            width: 100%;
            padding: 0.875rem;
            background: white;
            color: var(--text-main);
            border: 1px solid var(--border);
            border-radius: 10px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            transition: all 0.2s ease;
        }

        .btn-google:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }

        .register-link {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.95rem;
            color: var(--text-muted);
        }

        .register-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }

        .register-link a:hover {
            text-decoration: underline;
        }

        /* Default Credentials Box */
        .default-credentials {
            margin-top: 2rem;
            padding: 1.25rem;
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 12px;
            font-size: 0.85rem;
        }

        .default-credentials-title {
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .cred-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: white;
            padding: 0.5rem 0.75rem;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            border: 1px solid var(--border);
        }

        .cred-item:last-child {
            margin-bottom: 0;
        }

        .cred-label {
            color: var(--text-muted);
            margin-right: 0.5rem;
        }

        .cred-value {
            font-family: monospace;
            font-weight: 600;
            color: var(--navy);
            font-size: 0.9rem;
        }

        .btn-copy {
            background: #f1f5f9;
            border: 1px solid transparent;
            color: var(--primary);
            cursor: pointer;
            padding: 6px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .btn-copy:hover {
            background: #e2e8f0;
            border-color: #cbd5e1;
        }

        /* Footer */
        .footer {
            text-align: center;
            padding: 1rem;
            color: var(--text-muted);
            font-size: 0.85rem;
        }

        /* Alerts */
        .alert {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .alert-success {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .left-panel {
                padding: 3rem;
            }
            .system-title {
                font-size: 2.5rem;
            }
        }

        @media (max-width: 768px) {
            .layout-container {
                flex-direction: column;
            }
            .left-panel {
                flex: none;
                padding: 3rem 2rem;
            }
            .system-title {
                font-size: 2rem;
                margin-bottom: 2rem;
            }
            .right-panel {
                padding: 1.5rem;
            }
            .login-card {
                padding: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="layout-container">
        <!-- Left Panel -->
        <div class="left-panel">
            <div class="left-content">
                <div class="college-name">K. D. Polytechnic, Patan</div>
                <h1 class="system-title">AI College Timetable Management System</h1>
                
                <ul class="features-list">
                    <li class="feature-item">
                        <div class="feature-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                        </div>
                        Automated Timetable Generation
                    </li>
                    <li class="feature-item">
                        <div class="feature-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                        </div>
                        Smart Faculty & Classroom Management
                    </li>
                    <li class="feature-item">
                        <div class="feature-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                        </div>
                        Real-time Notifications
                    </li>
                </ul>
            </div>
        </div>

        <!-- Right Panel -->
        <div class="right-panel">
            <div class="login-wrapper">
                <div class="login-card">
                    <div class="login-header">
                        <div class="sys-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
                        </div>
                        <h2 class="login-title">Welcome Back!</h2>
                        <p class="login-subtitle">Sign in to continue to your account</p>
                    </div>

                    @if(session('student_register_success'))
                        <div class="alert alert-success" id="student-success-banner">
                            <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 12px;">
                                <div style="display: flex; align-items: flex-start; gap: 10px; flex: 1;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                                    <div>
                                        <strong>Registration Successful!</strong>
                                        <div>{{ session('student_register_success') }}</div>
                                        <div style="margin-top: 12px;">
                                            <a href="#student-login" class="inline-link" onclick="document.querySelector('[data-target=\'student-login\']').click(); return false;">Go to Student Login</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @elseif(session('status'))
                        <div class="alert alert-success">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                            {{ session('status') }}
                        </div>
                    @endif
                    @if(session('error'))
                        <div class="alert alert-error">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                            {{ session('error') }}
                        </div>
                    @endif

                    <!-- Tabs -->
                    <div class="role-tabs">
                        <button class="role-tab active" data-target="admin-login">Admin</button>
                        <button class="role-tab" data-target="faculty-login">Faculty</button>
                        <button class="role-tab" data-target="student-login">Student</button>
                    </div>

                    <!-- Admin Login -->
                    <form id="admin-login" class="form-section active" method="POST" action="/admin/login" onsubmit="return showLoading(this)">
                        @csrf
                        <div class="input-group">
                            <label class="input-label" for="admin-email">Email Address</label>
                            <div class="input-wrapper">
                                <input id="admin-email" name="email" type="text" class="input-field" value="{{ old('email', 'admin@example.com') }}" placeholder="Enter your email" required>
                            </div>
                        </div>
                        <div class="input-group">
                            <label class="input-label" for="admin-password">Password</label>
                            <div class="input-wrapper">
                                <input id="admin-password" name="password" type="password" class="input-field" value="admin" placeholder="Enter your password" required>
                                <button type="button" class="password-toggle" onclick="togglePassword('admin-password')">
                                    <svg class="eye-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                    <svg class="eye-off-icon" style="display:none;" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                                </button>
                            </div>
                        </div>
                        <div class="form-options">
                            <label class="remember-me">
                                <input type="checkbox" name="remember"> Remember me
                            </label>
                            <a href="#" class="forgot-password">Forgot Password?</a>
                        </div>
                        <button type="submit" class="btn-primary">Login to Account</button>
                    </form>

                    <!-- Faculty Login -->
                    <form id="faculty-login" class="form-section" method="POST" action="/faculty/login" onsubmit="return showLoading(this)">
                        @csrf
                        <div class="input-group">
                            <label class="input-label" for="faculty-email">Email Address</label>
                            <div class="input-wrapper">
                                <input id="faculty-email" name="email" type="text" class="input-field" value="{{ old('email') }}" placeholder="Enter your email" required>
                            </div>
                        </div>
                        <div class="input-group">
                            <label class="input-label" for="faculty-password">Password</label>
                            <div class="input-wrapper">
                                <input id="faculty-password" name="password" type="password" class="input-field" placeholder="Enter your password" required>
                                <button type="button" class="password-toggle" onclick="togglePassword('faculty-password')">
                                    <svg class="eye-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                    <svg class="eye-off-icon" style="display:none;" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                                </button>
                            </div>
                        </div>
                        <div class="form-options">
                            <label class="remember-me">
                                <input type="checkbox" name="remember"> Remember me
                            </label>
                            <a href="#" class="forgot-password">Forgot Password?</a>
                        </div>
                        <button type="submit" class="btn-primary">Login to Account</button>
                    </form>

                    <!-- Student Login -->
                    <form id="student-login" class="form-section" method="POST" action="/student/login" onsubmit="return showLoading(this)">
                        @csrf
                        <div class="input-group">
                            <label class="input-label" for="student-enrollment">Enrollment Number</label>
                            <div class="input-wrapper">
                                <input id="student-enrollment" name="enrollment_number" type="text" class="input-field" value="{{ old('enrollment_number') }}" placeholder="Enter your enrollment number" required>
                            </div>
                        </div>
                        <div class="input-group">
                            <label class="input-label" for="student-password">Password</label>
                            <div class="input-wrapper">
                                <input id="student-password" name="password" type="password" class="input-field" placeholder="Enter your password" required>
                                <button type="button" class="password-toggle" onclick="togglePassword('student-password')">
                                    <svg class="eye-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                    <svg class="eye-off-icon" style="display:none;" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                                </button>
                            </div>
                        </div>
                        <div class="form-options">
                            <label class="remember-me">
                                <input type="checkbox" name="remember"> Remember me
                            </label>
                            <a href="#" class="forgot-password">Forgot Password?</a>
                        </div>
                        <button type="submit" class="btn-primary">Login to Account</button>
                    </form>

                    <!-- Student Register -->
                    <form id="student-register" class="form-section" method="POST" action="/student/register" onsubmit="return showLoading(this)">
                        @csrf
                        <div class="input-group">
                            <label class="input-label" for="reg-enrollment">Enrollment Number</label>
                            <input id="reg-enrollment" name="enrollment_number" type="text" class="input-field" value="{{ old('enrollment_number') }}" placeholder="Enrollment Number" required>
                        </div>
                        <div class="input-group">
                            <label class="input-label" for="reg-name">Student Name</label>
                            <input id="reg-name" name="name" type="text" class="input-field" value="{{ old('name') }}" placeholder="Full Name" required>
                        </div>

                        <div class="input-group">
                            <label class="input-label" for="reg-email">Email</label>
                            <input id="reg-email" name="email" type="email" class="input-field" value="{{ old('email') }}" placeholder="Enter your email" required>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
                            <div class="input-group" style="margin-bottom: 0;">
                                <label class="input-label" for="reg-department">Department</label>
                                <input id="reg-department" name="department" type="text" class="input-field" value="{{ old('department') }}" placeholder="e.g. CE">
                            </div>
                            <div class="input-group" style="margin-bottom: 0;">
                                <label class="input-label" for="reg-semester">Semester</label>
                                <input id="reg-semester" name="semester" type="text" class="input-field" value="{{ old('semester') }}" placeholder="e.g. 5">
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
                            <div class="input-group" style="margin-bottom: 0;">
                                <label class="input-label" for="reg-class">Class</label>
                                <input id="reg-class" name="student_class" type="text" class="input-field" value="{{ old('student_class') }}" placeholder="e.g. CE-A">
                            </div>
                            <div class="input-group" style="margin-bottom: 0;">
                                <label class="input-label" for="reg-divcon">DivCon</label>
                                <input id="reg-divcon" name="divcon" type="text" class="input-field" value="{{ old('divcon') }}" placeholder="e.g. 1">
                            </div>
                        </div>

                        <div class="input-group">
                            <label class="input-label" for="reg-password-confirm">Confirm Password</label>
                            <input id="reg-password-confirm" name="password_confirmation" type="password" class="input-field" placeholder="Confirm password" required>
                        </div>

                        <div class="input-group">
                            <label class="input-label" for="reg-password">Password</label>
                            <div class="input-wrapper">
                                <input id="reg-password" name="password" type="password" class="input-field" placeholder="Create a password" required>
                                <button type="button" class="password-toggle" onclick="togglePassword('reg-password')">
                                    <svg class="eye-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                    <svg class="eye-off-icon" style="display:none;" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                                </button>
                            </div>
                        </div>
                        <button type="submit" class="btn-primary">Register</button>
                    </form>

                    <div class="register-link" id="register-toggle-section">
                        New here? <a href="#" id="toggle-register">Register as Student</a>
                    </div>
                    
                    <div class="register-link" id="login-toggle-section" style="display: none;">
                        Already have an account? <a href="#" id="toggle-login">Back to Login</a>
                    </div>
                </div>
            </div>
            <div class="footer">
                &copy; 2026 K. D. Polytechnic, Patan. All rights reserved.
            </div>
        </div>
    </div>

    <script>
        // Tab switching logic
        const tabs = document.querySelectorAll('.role-tab');
        const forms = document.querySelectorAll('.form-section');
        
        // Sections to hide when registering
        const registerToggleSection = document.getElementById('register-toggle-section');
        const loginToggleSection = document.getElementById('login-toggle-section');
        const roleTabsContainer = document.querySelector('.role-tabs');
        const loginTitle = document.querySelector('.login-title');
        const loginSubtitle = document.querySelector('.login-subtitle');

        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                // Remove active from all tabs & forms
                tabs.forEach(t => t.classList.remove('active'));
                forms.forEach(f => f.classList.remove('active'));
                
                // Add active to clicked tab
                tab.classList.add('active');
                
                // Show corresponding form
                const targetId = tab.getAttribute('data-target');
                document.getElementById(targetId).classList.add('active');
            });
        });

        // Toggle Register / Login
        document.getElementById('toggle-register').addEventListener('click', (e) => {
            e.preventDefault();
            forms.forEach(f => f.classList.remove('active'));
            document.getElementById('student-register').classList.add('active');
            
            roleTabsContainer.style.display = 'none';
            registerToggleSection.style.display = 'none';
            loginToggleSection.style.display = 'block';
            
            loginTitle.textContent = "Create an Account";
            loginSubtitle.textContent = "Register as a new student";
        });

        document.getElementById('toggle-login').addEventListener('click', (e) => {
            e.preventDefault();
            
            forms.forEach(f => f.classList.remove('active'));
            document.querySelector('.role-tab.active').click();
            
            roleTabsContainer.style.display = 'flex';
            registerToggleSection.style.display = 'block';
            loginToggleSection.style.display = 'none';
            
            loginTitle.textContent = "Welcome Back!";
            loginSubtitle.textContent = "Sign in to continue to your account";
        });

        // Password Toggle
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const btn = input.nextElementSibling;
            const eyeIcon = btn.querySelector('.eye-icon');
            const eyeOffIcon = btn.querySelector('.eye-off-icon');

            if (input.type === 'password') {
                input.type = 'text';
                eyeIcon.style.display = 'none';
                eyeOffIcon.style.display = 'block';
            } else {
                input.type = 'password';
                eyeIcon.style.display = 'block';
                eyeOffIcon.style.display = 'none';
            }
        }

        // Copy Text
        function copyText(elementId) {
            const text = document.getElementById(elementId).innerText;
            navigator.clipboard.writeText(text).then(() => {
                const btn = document.querySelector(`[onclick="copyText('${elementId}')"]`);
                const originalHtml = btn.innerHTML;
                btn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>`;
                setTimeout(() => {
                    btn.innerHTML = originalHtml;
                }, 2000);
            });
        }

        // Loading State
        function showLoading(form) {
            const btn = form.querySelector('button[type="submit"]');
            btn.classList.add('loading');
            return true;
        }

        // Handle validation errors switching to register tab if needed
        @if(old('name') || $errors->has('name'))
            document.getElementById('toggle-register').click();
        @endif
        @if(old('enrollment_number') && !old('email') && !old('name'))
            document.querySelector('[data-target="student-login"]').click();
        @endif
    </script>
</body>
</html>
