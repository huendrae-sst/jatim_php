<!doctype html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes" />
    <title>Login - JIMS Bank Jatim</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/icon-bankjatim.png') }}">

    <!-- Theme Init (prevents flash of incorrect theme on load) -->
    <script>
      (() => {
        'use strict';
        const root = document.documentElement;
        if (root.getAttribute('data-lte-color-mode') === 'off') {
          return;
        }
        const STORAGE_KEY = 'lte-theme';
        let stored = null;
        try {
          stored = localStorage.getItem(STORAGE_KEY);
        } catch {}
        const authored = root.getAttribute('data-bs-theme');
        let resolved = 'light';
        if (stored === 'dark' || stored === 'light') {
          resolved = stored;
        } else if (authored === 'dark' || authored === 'light') {
          resolved = authored;
        } else if (globalThis.matchMedia('(prefers-color-scheme: dark)').matches) {
          resolved = 'dark';
        }
        root.setAttribute('data-bs-theme', resolved);
        root.style.colorScheme = resolved;
        if (resolved !== authored) {
          root.setAttribute('data-lte-theme-resolved', '');
        }
      })();
    </script>

    <!-- Accessibility Meta Tags -->
    <meta name="color-scheme" content="light dark" />

    <!-- Fonts: Poppins (Skote Standard) & JetBrains Mono -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/poppins@5.0.14/index.css" crossorigin="anonymous" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">

    <!-- Third Party Plugin (Bootstrap Icons) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" crossorigin="anonymous" />

    <!-- Required Plugin (AdminLTE v4.9.1 / Bootstrap 5) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.9.1/dist/css/adminlte.min.css" crossorigin="anonymous" />

    <style>
        :root {
            --bs-font-sans-serif: "Poppins", system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            --bs-body-font-family: var(--bs-font-sans-serif);
            --bs-body-font-size: 0.8125rem;
        }
        body {
            font-family: var(--bs-body-font-family) !important;
            font-size: var(--bs-body-font-size) !important;
        }
        .login-box {
            width: 420px;
        }
        @media (max-width: 576px) {
            .login-box {
                width: 92%;
                margin-top: 1rem;
            }
        }
        [data-bs-theme="dark"] .brand-logo-light { display: none !important; }
        [data-bs-theme="dark"] .brand-logo-dark { display: inline-block !important; }
        [data-bs-theme="light"] .brand-logo-light { display: inline-block !important; }
        [data-bs-theme="light"] .brand-logo-dark { display: none !important; }

        .fs-7 { font-size: 0.875rem !important; }
        .fs-8 { font-size: 0.75rem !important; }
        .fs-9 { font-size: 0.6875rem !important; }

        input[type="password"] {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        input[type="password"]::placeholder {
            font-family: var(--bs-font-sans-serif);
        }
    </style>
</head>
<body class="login-page bg-body-secondary">
    <main class="login-box">
        <!-- Login Logo -->
        <div class="login-logo text-center mb-3">
            <a href="{{ url('/') }}" class="text-decoration-none d-inline-flex flex-column align-items-center">
                <img src="{{ asset('images/logo-bankjatim.png') }}" alt="Bank Jatim" class="brand-logo-light" style="height: 48px; width: auto; max-width: 220px; object-fit: contain;">
                <img src="{{ asset('images/logo-bankjatim-white.png') }}" alt="Bank Jatim" class="brand-logo-dark" style="height: 48px; width: auto; max-width: 220px; object-fit: contain; display: none;">
                <div class="fs-6 fw-bold text-body-secondary mt-1">
                    Inventory Management System
                    <span class="badge text-bg-danger ms-1">JIMS</span>
                </div>
            </a>
        </div>
        <!-- /.login-logo -->

        <!-- Login Card -->
        <div class="card card-outline card-danger shadow-sm">
            <div class="card-body login-card-body">
                <p class="login-box-msg text-body-secondary">Login</p>

                @if($errors->any())
                    <div class="alert alert-danger py-2 px-3 fs-7 mb-3" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i> {{ $errors->first() }}
                    </div>
                @endif

                @if(session('status'))
                    <div class="alert alert-success py-2 px-3 fs-7 mb-3" role="alert">
                        <i class="bi bi-check-circle-fill me-1"></i> {{ session('status') }}
                    </div>
                @endif

                @if(session('success'))
                    <div class="alert alert-success py-2 px-3 fs-7 mb-3" role="alert">
                        <i class="bi bi-check-circle-fill me-1"></i> {{ session('success') }}
                    </div>
                @endif

                <form action="{{ route('login') }}" method="post">
                    @csrf
                    <label class="visually-hidden" for="loginEmail">Email</label>
                    <div class="input-group mb-3">
                        <input id="loginEmail" type="email" name="email" value="{{ old('email') }}" required class="form-control" placeholder="Email" autocomplete="username" />
                        <div class="input-group-text">
                            <span class="bi bi-envelope"></span>
                        </div>
                    </div>

                    <label class="visually-hidden" for="loginPassword">Password</label>
                    <div class="input-group mb-3">
                        <input id="loginPassword" type="password" name="password" required class="form-control" placeholder="Password" autocomplete="current-password" />
                        <div class="input-group-text">
                            <span class="bi bi-lock-fill"></span>
                        </div>
                    </div>

                    <!-- Row: Remember Me & Sign In Button -->
                    <div class="row align-items-center mb-3">
                        <div class="col-7">
                            <div class="form-check mb-0">
                                <input class="form-check-input" type="checkbox" name="remember" value="1" id="rememberMe" checked />
                                <label class="form-check-label fs-7" for="rememberMe"> Remember Me </label>
                            </div>
                        </div>
                        <!-- /.col -->
                        <div class="col-5">
                            <div class="d-grid">
                                <button type="submit" class="btn btn-danger fw-bold">Sign In</button>
                            </div>
                        </div>
                        <!-- /.col -->
                    </div>
                    <!--end::Row-->
                </form>

                <p class="mb-0 text-center">
                    <a href="{{ route('password.request') }}" class="text-decoration-none fs-7">I forgot my password</a>
                </p>
            </div>
            <!-- /.login-card-body -->
        </div>
        <!-- /.card -->

        <!-- Demo Quick Login Switcher -->
        @if(isset($sampleUsers) && $sampleUsers->count() > 0)
            <div class="card mt-3 shadow-xs border">
                <div class="card-header bg-body-tertiary py-2 px-3 d-flex align-items-center justify-content-between" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#demoRoleSwitcher">
                    <span class="fs-8 fw-bold text-secondary text-uppercase">
                        <i class="bi bi-lightning-charge-fill text-warning me-1"></i> Demo Quick Login (1-Klik)
                    </span>
                    <i class="bi bi-chevron-down fs-8 text-secondary"></i>
                </div>
                <div id="demoRoleSwitcher" class="collapse show">
                    <div class="card-body p-2" style="max-height: 240px; overflow-y: auto;">
                        <div class="d-grid gap-1">
                            @foreach($sampleUsers as $u)
                                <form action="{{ route('quick.switch') }}" method="POST" class="m-0">
                                    @csrf
                                    <input type="hidden" name="user_id" value="{{ $u->id }}">
                                    <button type="submit" class="btn btn-sm btn-outline-secondary d-flex align-items-center justify-content-between text-start px-2.5 py-1.5 border-0 w-100">
                                        <div class="text-truncate me-2">
                                            <div class="fw-bold fs-8 text-body text-truncate">{{ $u->name }}</div>
                                            <div class="fs-9 text-secondary text-truncate">{{ $u->role_display_name }} ({{ $u->organization->name ?? 'Kantor Pusat' }})</div>
                                        </div>
                                        <span class="badge text-bg-danger fs-9 flex-shrink-0">Login &rarr;</span>
                                    </button>
                                </form>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="text-center mt-3 text-secondary fs-8">
            &copy; {{ date('Y') }} PT Bank Pembangunan Daerah Jawa Timur Tbk
        </div>
    </main>
    <!-- /.login-box -->

    <!-- Required Bootstrap & AdminLTE Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@4.9.1/dist/js/adminlte.min.js" crossorigin="anonymous"></script>

    <script>
        function quickFill(email) {
            const emailInput = document.getElementById('loginEmail');
            const passInput = document.getElementById('loginPassword');
            if (emailInput && passInput) {
                emailInput.value = email;
                passInput.value = 'password123';
                emailInput.focus();
            }
        }
    </script>
</body>
</html>
