<!doctype html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes" />
    <title>Forgot Password - JIMS Bank Jatim</title>

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

        <!-- Card -->
        <div class="card card-outline card-danger shadow-sm">
            <div class="card-body login-card-body">
                <p class="login-box-msg text-body-secondary">
                    You forgot your password? Here you can easily retrieve a new password.
                </p>

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

                <form action="{{ route('password.email') }}" method="post">
                    @csrf
                    <label class="visually-hidden" for="forgotEmail">Email</label>
                    <div class="input-group mb-3">
                        <input id="forgotEmail" type="email" name="email" value="{{ old('email') }}" required class="form-control" />
                        <div class="input-group-text">
                            <span class="bi bi-envelope"></span>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-danger fw-bold">Request new password</button>
                            </div>
                        </div>
                        <!-- /.col -->
                    </div>
                </form>

                <p class="mt-3 mb-1 text-center">
                    <a href="{{ route('login') }}" class="text-decoration-none fs-7">Login</a>
                </p>
                <p class="mb-0 text-center">
                    <a href="{{ route('register') }}" class="text-center text-decoration-none fs-7">Register a new membership</a>
                </p>
            </div>
            <!-- /.login-card-body -->
        </div>
        <!-- /.card -->

        <div class="text-center mt-3 text-secondary fs-8">
            &copy; {{ date('Y') }} PT Bank Pembangunan Daerah Jawa Timur Tbk
        </div>
    </main>
    <!-- /.login-box -->

    <!-- Required Bootstrap & AdminLTE Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@4.9.1/dist/js/adminlte.min.js" crossorigin="anonymous"></script>
</body>
</html>
