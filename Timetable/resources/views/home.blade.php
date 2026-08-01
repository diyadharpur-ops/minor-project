<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>K. D. Polytechnic | Portal</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #f4f7fb;
            --panel: #ffffff;
            --sidebar: #0f3d5e;
            --sidebar-accent: #1f6f9f;
            --text: #1f2937;
            --muted: #6b7280;
            --border: #dce4ee;
            --accent: #2563eb;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: linear-gradient(135deg, #eef4ff 0%, #f8fbff 100%);
            color: var(--text);
        }

        .page {
            min-height: 100vh;
            display: flex;
            padding: 24px;
        }

        .sidebar {
            width: 320px;
            background: var(--sidebar);
            color: white;
            border-radius: 20px 0 0 20px;
            padding: 28px 20px;
            box-shadow: 0 12px 35px rgba(15, 61, 94, 0.2);
        }

        .sidebar h1 {
            margin: 0 0 20px;
            font-size: 1.5rem;
            line-height: 1.4;
        }

        .sidebar-nav {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .sidebar-nav button {
            text-align: left;
            padding: 12px 14px;
            border-radius: 10px;
            border: 1px solid transparent;
            background: rgba(255,255,255,0.12);
            color: white;
            cursor: pointer;
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }

        .sidebar-nav button:hover,
        .sidebar-nav button.active {
            background: var(--sidebar-accent);
            border-color: rgba(255,255,255,0.28);
        }

        .content {
            flex: 1;
            background: var(--panel);
            border-radius: 0 20px 20px 0;
            padding: 30px;
            box-shadow: 0 12px 35px rgba(15, 23, 42, 0.08);
        }

        .card {
            display: none;
            max-width: 620px;
        }

        .card.active {
            display: block;
        }

        .card h2 {
            margin: 0 0 8px;
            font-size: 1.4rem;
        }

        .card p {
            margin: 0 0 20px;
            color: var(--muted);
        }

        .form-grid {
            display: grid;
            gap: 14px;
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .field label {
            font-weight: 600;
            color: var(--text);
        }

        .field input {
            padding: 11px 12px;
            border: 1px solid var(--border);
            border-radius: 10px;
            font-size: 0.95rem;
        }

        .field input:focus {
            outline: 2px solid rgba(37, 99, 235, 0.18);
            border-color: var(--accent);
        }

        .actions {
            margin-top: 8px;
        }

        .actions button {
            padding: 10px 16px;
            border: none;
            border-radius: 10px;
            background: var(--accent);
            color: white;
            cursor: pointer;
            font-weight: 600;
        }

        @media (max-width: 900px) {
            .page { flex-direction: column; padding: 16px; }
            .sidebar, .content { border-radius: 20px; }
            .sidebar { width: 100%; margin-bottom: 12px; }
            .content { padding: 20px; }
        }
    </style>
</head>
<body>
    <div class="page">
        <aside class="sidebar">
            <h1>WELCOME TO K. D. POLYTECHNIC, PATAN</h1>
            <nav class="sidebar-nav" aria-label="Portal options">
                <button class="active" data-target="admin">Admin Login</button>
                <button data-target="faculty">Faculty Login</button>
                <button data-target="student-login">Student Login</button>
                <button data-target="student-register">Student Register</button>
            </nav>
        </aside>

        <main class="content">
            <section id="admin" class="card active">
                <h2>Admin Login</h2>
                <p>Enter your admin credentials to continue.</p>
                <form class="form-grid" method="POST" action="/admin/login">
                    @csrf
                    <div class="field">
                        <label for="admin-email">Admin Email</label>
                        <input id="admin-email" name="email" type="email" value="admin@example.com" placeholder="admin@example.com" required>
                    </div>
                    <div class="field">
                        <label for="admin-password">Password</label>
                        <input id="admin-password" name="password" type="password" value="admin" placeholder="Enter password" required>
                    </div>
                    <div class="actions">
                        <button type="submit">Login</button>
                    </div>
                </form>
            </section>

            <section id="faculty" class="card">
                <h2>Faculty Login</h2>
                <p>Enter your faculty email and password.</p>
                <form class="form-grid">
                    <div class="field">
                        <label for="faculty-email">Faculty Email</label>
                        <input id="faculty-email" type="email" placeholder="faculty@example.com">
                    </div>
                    <div class="field">
                        <label for="faculty-password">Password</label>
                        <input id="faculty-password" type="password" placeholder="Enter password">
                    </div>
                    <div class="actions">
                        <button type="submit">Login</button>
                    </div>
                </form>
            </section>

            <section id="student-login" class="card">
                <h2>Student Login</h2>
                <p>Use your enrollment number and password to sign in.</p>
                <form class="form-grid">
                    <div class="field">
                        <label for="student-enrollment">Student Enrollment Number</label>
                        <input id="student-enrollment" type="text" placeholder="Enrollment Number">
                    </div>
                    <div class="field">
                        <label for="student-password">Password</label>
                        <input id="student-password" type="password" placeholder="Enter password">
                    </div>
                    <div class="actions">
                        <button type="submit">Login</button>
                    </div>
                </form>
            </section>

            <section id="student-register" class="card">
                <h2>Student Register</h2>
                <p>Create a new student account with your details.</p>
                <form class="form-grid">
                    <div class="field">
                        <label for="reg-enrollment">Student Enrollment Number</label>
                        <input id="reg-enrollment" type="text" placeholder="Enrollment Number">
                    </div>
                    <div class="field">
                        <label for="reg-name">Student Name</label>
                        <input id="reg-name" type="text" placeholder="Student Name">
                    </div>
                    <div class="field">
                        <label for="reg-department">Department</label>
                        <input id="reg-department" type="text" placeholder="Department">
                    </div>
                    <div class="field">
                        <label for="reg-semester">Semester</label>
                        <input id="reg-semester" type="text" placeholder="Semester">
                    </div>
                    <div class="field">
                        <label for="reg-class">Class</label>
                        <input id="reg-class" type="text" placeholder="Class">
                    </div>
                    <div class="field">
                        <label for="reg-divcon">DivCon</label>
                        <input id="reg-divcon" type="text" placeholder="DivCon">
                    </div>
                    <div class="actions">
                        <button type="submit">Register</button>
                    </div>
                </form>
            </section>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const buttons = document.querySelectorAll('.sidebar-nav button');
            const cards = document.querySelectorAll('.card');

            buttons.forEach(function (button) {
                button.addEventListener('click', function () {
                    buttons.forEach(function (btn) { btn.classList.remove('active'); });
                    this.classList.add('active');

                    const target = this.getAttribute('data-target');
                    cards.forEach(function (card) {
                        card.classList.toggle('active', card.id === target);
                    });
                });
            });
        });
    </script>
</body>
</html>
