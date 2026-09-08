<!doctype html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes" />
    <title>Register - JIMS Bank Jatim</title>

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
        .register-box {
            width: 440px;
        }
        @media (max-width: 576px) {
            .register-box {
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
<body class="register-page bg-body-secondary">
    <main class="register-box">
        <!-- Register Logo -->
        <div class="register-logo text-center mb-3">
            <a href="{{ url('/') }}" class="text-decoration-none d-inline-flex flex-column align-items-center">
                <img src="{{ asset('images/logo-bankjatim.png') }}" alt="Bank Jatim" class="brand-logo-light" style="height: 48px; width: auto; max-width: 220px; object-fit: contain;">
                <img src="{{ asset('images/logo-bankjatim-white.png') }}" alt="Bank Jatim" class="brand-logo-dark" style="height: 48px; width: auto; max-width: 220px; object-fit: contain; display: none;">
                <div class="fs-6 fw-bold text-body-secondary mt-1">
                    Inventory Management System
                    <span class="badge text-bg-danger ms-1">JIMS</span>
                </div>
            </a>
        </div>
        <!-- /.register-logo -->

        <!-- Register Card -->
        <div class="card card-outline card-danger shadow-sm">
            <div class="card-body register-card-body">
                <p class="register-box-msg text-body-secondary">Register a new membership</p>

                @if($errors->any())
                    <div class="alert alert-danger py-2 px-3 fs-7 mb-3" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i> {{ $errors->first() }}
                    </div>
                @endif

                <form action="{{ route('register') }}" method="post">
                    @csrf
                    
                    <!-- Full Name -->
                    <label class="visually-hidden" for="registerName">Full Name</label>
                    <div class="input-group mb-3">
                        <input id="registerName" type="text" name="name" value="{{ old('name') }}" required class="form-control" placeholder="Full Name" autocomplete="name" />
                        <div class="input-group-text">
                            <span class="bi bi-person"></span>
                        </div>
                    </div>

                    <!-- NIP / NIK -->
                    <label class="visually-hidden" for="registerNip">NIP / NIK Pegawai</label>
                    <div class="input-group mb-3">
                        <input id="registerNip" type="text" name="nip" value="{{ old('nip') }}" class="form-control" placeholder="NIP / NIK Pegawai" />
                        <div class="input-group-text">
                            <span class="bi bi-person-badge"></span>
                        </div>
                    </div>

                    <!-- Email -->
                    <label class="visually-hidden" for="registerEmail">Email</label>
                    <div class="input-group mb-3">
                        <input id="registerEmail" type="email" name="email" value="{{ old('email') }}" required class="form-control" placeholder="Email" autocomplete="email" />
                        <div class="input-group-text">
                            <span class="bi bi-envelope"></span>
                        </div>
                    </div>

                    <!-- Unit Kerja / Organization -->
                    <label class="visually-hidden" for="registerOrg">Unit Kerja / Cabang</label>
                    <div class="input-group mb-3">
                        <select id="registerOrg" name="organization_id" class="form-select fs-7">
                            <option value="">-- Pilih Unit Kerja / Cabang --</option>
                            @foreach($organizations as $org)
                                <option value="{{ $org->id }}" {{ old('organization_id') == $org->id ? 'selected' : '' }}>
                                    [{{ $org->code }}] {{ $org->name }}
                                </option>
                            @endforeach
                        </select>
                        <div class="input-group-text">
                            <span class="bi bi-building"></span>
                        </div>
                    </div>

                    <!-- Password -->
                    <label class="visually-hidden" for="registerPassword">Password</label>
                    <div class="input-group mb-3">
                        <input id="registerPassword" type="password" name="password" required class="form-control" placeholder="Password" autocomplete="new-password" />
                        <div class="input-group-text">
                            <span class="bi bi-lock-fill"></span>
                        </div>
                    </div>

                    <!-- Confirm Password -->
                    <label class="visually-hidden" for="registerPasswordConfirm">Confirm Password</label>
                    <div class="input-group mb-3">
                        <input id="registerPasswordConfirm" type="password" name="password_confirmation" required class="form-control" placeholder="Confirm Password" autocomplete="new-password" />
                        <div class="input-group-text">
                            <span class="bi bi-shield-lock"></span>
                        </div>
                    </div>

                    <!-- Row: Terms Agreement & Register Button -->
                    <div class="row align-items-center mb-3">
                        <div class="col-8">
                            <div class="form-check mb-0">
                                <input class="form-check-input" type="checkbox" name="agree" value="1" id="agreeTerms" required />
                                <label class="form-check-label fs-7" for="agreeTerms">
                                    I agree to the <a href="#" class="text-decoration-none" data-bs-toggle="modal" data-bs-target="#termsModal">terms</a>
                                </label>
                            </div>
                        </div>
                        <!-- /.col -->
                        <div class="col-4">
                            <div class="d-grid">
                                <button type="submit" class="btn btn-danger fw-bold">Register</button>
                            </div>
                        </div>
                        <!-- /.col -->
                    </div>
                    <!--end::Row-->
                </form>

                <p class="mb-0 text-center">
                    <a href="{{ route('login') }}" class="text-center text-decoration-none fs-7">I already have a membership</a>
                </p>
            </div>
            <!-- /.register-card-body -->
        </div>
        <!-- /.card -->

        <div class="text-center mt-3 text-secondary fs-8">
            &copy; {{ date('Y') }} PT Bank Pembangunan Daerah Jawa Timur Tbk
        </div>
    </main>
    <!-- /.register-box -->

    <!-- Terms Modal -->
    <div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fs-6 fw-bold" id="termsModalLabel">Syarat & Ketentuan Penggunaan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body fs-7 text-secondary">
                    <p>Sistem Informasi Manajemen Persediaan (JIMS) PT Bank Pembangunan Daerah Jawa Timur Tbk hanya dapat diakses oleh pegawai dan staf resmi Bank Jatim.</p>
                    <ul class="ps-3 mb-0">
                        <li>Pengguna bertanggung jawab penuh menjaga kerahasiaan kredensial akun.</li>
                        <li>Setiap transaksi pengadaan, persediaan, dan permohonan barang dicatat dalam audit trail log.</li>
                        <li>Penyalahgunaan hak akses persediaan dapat dikenakan sanksi sesuai kebijakan internal perbankan.</li>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-danger fw-bold" data-bs-dismiss="modal">Saya Mengerti</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Required Bootstrap & AdminLTE Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@4.9.1/dist/js/adminlte.min.js" crossorigin="anonymous"></script>
</body>
</html>
