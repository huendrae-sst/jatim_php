<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes" />
    <meta name="color-scheme" content="light dark" />
    <title>@yield('title', 'Bank Jatim JIMS') - Sistem Informasi Manajemen Persediaan</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/icon-bankjatim.png') }}">

    <!-- Theme Init (Prevents flash of incorrect theme on load) -->
    <script>
      (() => {
        'use strict';
        const STORAGE_KEY = 'lte-theme';
        let stored = null;
        try {
          stored = localStorage.getItem(STORAGE_KEY);
        } catch {}
        const root = document.documentElement;
        let resolved = 'light';
        if (stored === 'dark' || stored === 'light') {
          resolved = stored;
        } else if (stored === 'auto' || !stored) {
          if (globalThis.matchMedia('(prefers-color-scheme: dark)').matches) {
            resolved = 'dark';
          }
        }
        root.setAttribute('data-bs-theme', resolved);
        root.style.colorScheme = resolved;
      })();
    </script>

    <!-- Fonts: Poppins (Skote Standard) & JetBrains Mono -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/poppins@5.0.14/index.css" crossorigin="anonymous" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">

    <!-- Third Party Plugins: OverlayScrollbars, Bootstrap Icons, FontAwesome -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/styles/overlayscrollbars.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" />

    <!-- Required Plugins: Bootstrap 5.3 + AdminLTE v4.9.1 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.9.1/dist/css/adminlte.min.css" crossorigin="anonymous" />

    <!-- Tailwind CSS (Preflight disabled so Bootstrap 5 & AdminLTE 4 styles take full control) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            corePlugins: {
                preflight: false,
            },
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Poppins"', 'system-ui', '-apple-system', 'BlinkMacSystemFont', '"Segoe UI"', 'Roboto', 'sans-serif'],
                        mono: ['"JetBrains Mono"', 'ui-monospace', 'SFMono-Regular', 'Menlo', 'Monaco', 'Consolas', 'monospace'],
                    },
                    colors: {
                        jatim: {
                            700: '#D9252A',
                            800: '#9f1239',
                        },
                        brand: {
                            50: '#fff7ed',
                            100: '#ffedd5',
                            500: '#f97316',
                            600: '#ea580c',
                            700: '#c2410c',
                            accent: '#D97706',
                        }
                    }
                }
            }
        }
    </script>

    <!-- Alpine.js & Chart.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        [x-cloak] { display: none !important; }

        /* Typography Configuration (Aligned with Skote: Poppins, 0.8125rem / 13px base) */
        :root {
            --bs-font-sans-serif: "Poppins", system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol";
            --bs-body-font-family: var(--bs-font-sans-serif);
            --bs-body-font-size: 0.8125rem;
            --bs-body-font-weight: 400;
            --bs-body-line-height: 1.5;
            --bs-body-color: #495057;
        }

        body {
            font-family: var(--bs-body-font-family) !important;
            font-size: var(--bs-body-font-size) !important;
            line-height: var(--bs-body-line-height);
        }

        /* Headings Typography */
        h1, h2, h3, h4, h5, h6,
        .h1, .h2, .h3, .h4, .h5, .h6 {
            font-family: var(--bs-font-sans-serif);
            font-weight: 600;
        }

        /* Font Size Utilities */
        .fs-7 {
            font-size: 0.8125rem !important;
        }
        .fs-8 {
            font-size: 0.75rem !important;
        }
        .fs-9 {
            font-size: 0.6875rem !important;
        }
        .fs-7 .dropdown-menu {
            font-size: 0.8125rem !important;
        }
        .fs-7 .dropdown-toggle::after {
            vertical-align: 0.2rem;
        }

        .searchable-item-option {
            cursor: pointer;
            transition: background-color 0.15s ease-in-out;
        }
        .searchable-item-option:hover {
            background-color: var(--bs-tertiary-bg);
        }

        /* Component Typography (Skote standard) */
        .app-content-header h1,
        .app-content-header .h1 {
            font-size: 1.125rem;
            font-weight: 600;
        }
        .card-title {
            font-size: 0.9375rem;
            font-weight: 600;
        }
        .sidebar-menu .nav-link {
            font-size: 0.8125rem;
        }
        .sidebar-menu .nav-header {
            font-size: 0.6875rem;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }
        .nav-treeview .nav-link {
            font-size: 0.8125rem !important;
            padding-left: 2rem !important;
        }

        /* Skote Table Typography */
        .table {
            font-family: var(--bs-font-sans-serif);
            font-size: 0.8125rem;
        }
        .table th,
        .table thead th {
            font-weight: 600;
            font-size: 0.8125rem;
            color: #495057;
        }
        .table td {
            font-size: 0.8125rem;
            vertical-align: middle;
        }

        /* Form Controls Typography */
        .form-control,
        .form-select,
        .input-group-text {
            font-family: var(--bs-font-sans-serif);
            font-size: 0.8125rem;
        }

        .btn {
            font-family: var(--bs-font-sans-serif);
        }

        /* Table Action Icon Buttons (Icon only, no box/border) */
        .btn-action-icon {
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
            padding: 0.25rem 0.35rem !important;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
            border-radius: 0.25rem;
            transition: color 0.15s ease-in-out, transform 0.15s ease-in-out, opacity 0.15s ease-in-out;
            cursor: pointer;
            text-decoration: none;
        }
        .btn-action-icon:hover {
            opacity: 0.85;
            transform: scale(1.15);
        }
        .btn-action-icon i {
            font-size: 1rem;
            line-height: 1;
        }
        .btn-action-icon.text-secondary {
            color: #74788d !important;
        }
        .btn-action-icon.text-secondary:hover {
            color: #343a40 !important;
        }
        .btn-action-icon.text-primary {
            color: #556ee6 !important;
        }
        .btn-action-icon.text-primary:hover {
            color: #3b50be !important;
        }
        .btn-action-icon.text-danger {
            color: #f46a6a !important;
        }
        .btn-action-icon.text-danger:hover {
            color: #d9252a !important;
        }
        [data-bs-theme="dark"] .btn-action-icon.text-secondary {
            color: #a6b0cf !important;
        }
        [data-bs-theme="dark"] .btn-action-icon.text-secondary:hover {
            color: #eff2f7 !important;
        }
        [data-bs-theme="dark"] .btn-action-icon.text-primary {
            color: #798ceb !important;
        }
        [data-bs-theme="dark"] .btn-action-icon.text-primary:hover {
            color: #9cb0ff !important;
        }
        [data-bs-theme="dark"] .btn-action-icon.text-danger {
            color: #f68383 !important;
        }
        [data-bs-theme="dark"] .btn-action-icon.text-danger:hover {
            color: #ff9e9e !important;
        }

        /* Dialog & Modal Close Button ("Tombol X Tutup Dialog: Pojok Kanan Atas & Berwarna") */
        .modal-header,
        .fixed.inset-0 .card-header,
        .fixed.inset-0 [class*="border-b"] {
            position: relative;
            padding-right: 3.75rem !important;
        }

        .modal .btn-close,
        .fixed.inset-0 .btn-close {
            position: absolute !important;
            top: 0.875rem !important;
            right: 1rem !important;
            z-index: 1055 !important;
            width: 2rem !important;
            height: 2rem !important;
            min-width: 2rem !important;
            min-height: 2rem !important;
            padding: 0 !important;
            margin: 0 !important;
            border-radius: 50% !important;
            background-color: #fee2e2 !important;
            border: 1px solid #fca5a5 !important;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23dc3545'%3e%3cpath d='M.293.293a1 1 0 0 1 1.414 0L8 6.586 14.293.293a1 1 0 1 1 1.414 1.414L9.414 8l6.293 6.293a1 1 0 0 1-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 0 1-1.414-1.414L6.586 8 .293 1.707a1 1 0 0 1 0-1.414z'/%3e%3c/svg%3e") !important;
            background-position: center !important;
            background-repeat: no-repeat !important;
            background-size: 0.8125rem !important;
            opacity: 1 !important;
            box-shadow: 0 1px 3px rgba(220, 53, 69, 0.15) !important;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
            cursor: pointer !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            flex-shrink: 0 !important;
        }

        .modal .btn-close:hover,
        .fixed.inset-0 .btn-close:hover {
            background-color: #dc3545 !important;
            border-color: #dc3545 !important;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23ffffff'%3e%3cpath d='M.293.293a1 1 0 0 1 1.414 0L8 6.586 14.293.293a1 1 0 1 1 1.414 1.414L9.414 8l6.293 6.293a1 1 0 0 1-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 0 1-1.414-1.414L6.586 8 .293 1.707a1 1 0 0 1 0-1.414z'/%3e%3c/svg%3e") !important;
            transform: scale(1.1) rotate(90deg) !important;
            box-shadow: 0 3px 8px rgba(220, 53, 69, 0.4) !important;
        }

        .modal .btn-close:active,
        .fixed.inset-0 .btn-close:active {
            transform: scale(0.92) !important;
        }

        .modal .btn-close:focus,
        .fixed.inset-0 .btn-close:focus {
            outline: none !important;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25) !important;
        }

        /* Dark Theme Support for Dialog Close Button */
        [data-bs-theme="dark"] .modal .btn-close,
        [data-bs-theme="dark"] .fixed.inset-0 .btn-close {
            background-color: rgba(220, 53, 69, 0.2) !important;
            border-color: rgba(220, 53, 69, 0.4) !important;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23f87171'%3e%3cpath d='M.293.293a1 1 0 0 1 1.414 0L8 6.586 14.293.293a1 1 0 1 1 1.414 1.414L9.414 8l6.293 6.293a1 1 0 0 1-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 0 1-1.414-1.414L6.586 8 .293 1.707a1 1 0 0 1 0-1.414z'/%3e%3c/svg%3e") !important;
        }

        [data-bs-theme="dark"] .modal .btn-close:hover,
        [data-bs-theme="dark"] .fixed.inset-0 .btn-close:hover {
            background-color: #dc3545 !important;
            border-color: #dc3545 !important;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23ffffff'%3e%3cpath d='M.293.293a1 1 0 0 1 1.414 0L8 6.586 14.293.293a1 1 0 1 1 1.414 1.414L9.414 8l6.293 6.293a1 1 0 0 1-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 0 1-1.414-1.414L6.586 8 .293 1.707a1 1 0 0 1 0-1.414z'/%3e%3c/svg%3e") !important;
        }

        /* Mobile-First Responsive Utilities */
        @media (max-width: 767.98px) {
            .app-content-header {
                padding: 0.5rem 0.75rem 0.25rem 0.75rem !important;
            }
            .dropdown-menu-end {
                max-width: 90vw !important;
            }
            .table-responsive {
                border-radius: 0.375rem;
                -webkit-overflow-scrolling: touch;
            }
        }

        /* Horizontal Scrollable Tabs on Mobile */
        .nav-tabs-scroll,
        .nav-pills-scroll {
            display: flex;
            flex-wrap: nowrap;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            padding-bottom: 2px;
        }
        .nav-tabs-scroll::-webkit-scrollbar,
        .nav-pills-scroll::-webkit-scrollbar {
            display: none;
        }
        .nav-tabs-scroll .nav-link,
        .nav-pills-scroll .nav-link {
            white-space: nowrap;
            flex-shrink: 0;
        }

        /* Safe Mobile Bottom Spacing */
        .app-main {
            padding-bottom: 1.5rem;
        }

        /* =========================================================
           Section & Component White Space Elimination / Compacting
           ========================================================= */
        /* Tighten Page Header (Judul Halaman & Breadcrumb) and Content spacing */
        .app-content-header {
            padding: 0.625rem 1rem 0.25rem 1rem !important;
            margin-bottom: 0 !important;
        }
        .app-content-header h3,
        .app-content-header .h3 {
            font-size: 1.125rem;
            line-height: 1.25;
        }
        .app-content {
            padding-top: 0.25rem !important;
        }

        /* Eliminate default excessive margins between cards, metrics & sections */
        .card {
            margin-bottom: 0.75rem !important;
        }
        .info-box {
            margin-bottom: 0 !important;
        }
        
        /* Tighten vertical rhythm between rows, cards, and sections */
        .row + .card,
        .row + .row,
        .card + .card,
        .card + .row {
            margin-top: 0.75rem !important;
        }

        /* Compact Tailwind space-y utility spacing across sections */
        .space-y-4 > :not([hidden]) ~ :not([hidden]) {
            --tw-space-y-reverse: 0;
            margin-top: calc(0.75rem * calc(1 - var(--tw-space-y-reverse))) !important;
            margin-bottom: calc(0.75rem * var(--tw-space-y-reverse)) !important;
        }
        .space-y-6 > :not([hidden]) ~ :not([hidden]) {
            --tw-space-y-reverse: 0;
            margin-top: calc(0.75rem * calc(1 - var(--tw-space-y-reverse))) !important;
            margin-bottom: calc(0.75rem * var(--tw-space-y-reverse)) !important;
        }

        /* Tighten Filter Toolbar & Card Header Spacing */
        .card > .card-header {
            padding-top: 0.625rem !important;
            padding-bottom: 0.625rem !important;
        }
        .card > .card-body.border-bottom {
            padding-top: 0.625rem !important;
            padding-bottom: 0.625rem !important;
        }

        /* Brand & Logo styling */
        .sidebar-brand {
            height: 3.75rem;
            display: flex;
            align-items: center;
            padding: 0 1rem;
        }
        .sidebar-brand .brand-link {
            text-decoration: none;
            display: flex;
            align-items: center;
            width: 100%;
            padding: 0;
        }
        .sidebar-brand .brand-image-collapsed {
            display: none !important;
        }
        .sidebar-brand .brand-expanded {
            display: flex !important;
            align-items: center;
            justify-content: space-between;
            width: 100%;
        }
        .sidebar-collapse .sidebar-brand {
            justify-content: center;
            padding: 0;
        }
        .sidebar-collapse .sidebar-brand .brand-link {
            justify-content: center;
        }
        .sidebar-collapse .sidebar-brand .brand-image-collapsed {
            display: block !important;
            margin: 0 auto;
        }
        .sidebar-collapse .sidebar-brand .brand-expanded {
            display: none !important;
        }

        /* Light & Dark Brand Logos */
        [data-bs-theme="light"] .brand-logo-light {
            display: block !important;
        }
        [data-bs-theme="light"] .brand-logo-dark {
            display: none !important;
        }
        [data-bs-theme="dark"] .brand-logo-light {
            display: none !important;
        }
        [data-bs-theme="dark"] .brand-logo-dark {
            display: block !important;
        }
        .app-header .navbar-nav .nav-link {
            display: flex;
            align-items: center;
        }
        .navbar-badge {
            position: absolute;
            top: 6px;
            right: 4px;
            font-size: 0.65rem;
            padding: 2px 4px;
            border-radius: 9999px;
        }
        .theme-icon-active {
            font-size: 1.1rem;
        }

        /* LIGHT MODE THEME RULES */
        [data-bs-theme="light"] {
            --bs-body-bg: #f4f6f9;
            --bs-body-bg-rgb: 244, 246, 249;
            --bs-tertiary-bg: #eef1f5;
        }
        [data-bs-theme="light"] .app-sidebar {
            background-color: #ffffff !important;
            border-right: 1px solid #e2e8f0;
        }
        [data-bs-theme="light"] .sidebar-brand {
            border-bottom: 1px solid #e2e8f0 !important;
        }
        [data-bs-theme="light"] .sidebar-menu > .nav-item > .nav-link {
            color: #334155;
        }
        [data-bs-theme="light"] .sidebar-menu > .nav-item > .nav-link:hover {
            background-color: #f1f5f9;
            color: #0f172a;
        }
        [data-bs-theme="light"] .sidebar-menu > .nav-item > .nav-link.active {
            background-color: #D9252A !important;
            color: #ffffff !important;
            font-weight: 700;
        }
        [data-bs-theme="light"] .nav-treeview .nav-link.active {
            background-color: rgba(217, 37, 42, 0.12) !important;
            color: #D9252A !important;
            font-weight: 700;
        }
        [data-bs-theme="light"] .nav-header {
            color: #64748b !important;
        }

        /* DARK MODE THEME RULES */
        [data-bs-theme="dark"] {
            --bs-body-bg: #121417;
            --bs-body-bg-rgb: 18, 20, 23;
            --bs-body-color: #e2e8f0;
            --bs-secondary-bg: #1a1d21;
            --bs-tertiary-bg: #22262b;
            --bs-border-color: #2e343b;
        }
        [data-bs-theme="dark"] .app-sidebar {
            background-color: #17191d !important;
            border-right: 1px solid #23272d;
        }
        [data-bs-theme="dark"] .sidebar-brand {
            border-bottom: 1px solid #23272d !important;
        }
        [data-bs-theme="dark"] .sidebar-menu > .nav-item > .nav-link {
            color: #94a3b8;
        }
        [data-bs-theme="dark"] .sidebar-menu > .nav-item > .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.06);
            color: #ffffff;
        }
        [data-bs-theme="dark"] .sidebar-menu > .nav-item > .nav-link.active {
            background-color: #D9252A !important;
            color: #ffffff !important;
            font-weight: 700;
        }
        [data-bs-theme="dark"] .nav-treeview .nav-link.active {
            background-color: rgba(217, 37, 42, 0.25) !important;
            color: #ff6b6e !important;
            font-weight: 700;
        }
        [data-bs-theme="dark"] .nav-header {
            color: #64748b !important;
        }

        /* Bridge for Tailwind components inside Views in Dark Mode */
        [data-bs-theme="dark"] .bg-white,
        [data-bs-theme="dark"] .bg-\[\#F3F4F6\] {
            background-color: var(--bs-secondary-bg) !important;
            color: var(--bs-body-color) !important;
        }
        [data-bs-theme="dark"] .bg-slate-50,
        [data-bs-theme="dark"] .bg-slate-100,
        [data-bs-theme="dark"] .bg-slate-50\/60,
        [data-bs-theme="dark"] .bg-slate-50\/70 {
            background-color: var(--bs-tertiary-bg) !important;
        }
        [data-bs-theme="dark"] .text-slate-900,
        [data-bs-theme="dark"] .text-slate-800,
        [data-bs-theme="dark"] .text-slate-700 {
            color: var(--bs-body-color) !important;
        }
        [data-bs-theme="dark"] .text-slate-600,
        [data-bs-theme="dark"] .text-slate-500,
        [data-bs-theme="dark"] .text-slate-400 {
            color: #94a3b8 !important;
        }
        [data-bs-theme="dark"] .border-slate-200,
        [data-bs-theme="dark"] .border-slate-100,
        [data-bs-theme="dark"] .border-slate-200\/80,
        [data-bs-theme="dark"] .border-slate-200\/90,
        [data-bs-theme="dark"] .divide-slate-100 > :not([hidden]) ~ :not([hidden]),
        [data-bs-theme="dark"] .divide-slate-200 > :not([hidden]) ~ :not([hidden]) {
            border-color: var(--bs-border-color) !important;
        }
        [data-bs-theme="dark"] input:not([type="checkbox"]):not([type="radio"]):not([type="button"]):not([type="submit"]),
        [data-bs-theme="dark"] select,
        [data-bs-theme="dark"] textarea {
            background-color: var(--bs-secondary-bg) !important;
            color: var(--bs-body-color) !important;
            border-color: var(--bs-border-color) !important;
        }
        [data-bs-theme="dark"] .card {
            background-color: var(--bs-secondary-bg);
            border-color: var(--bs-border-color);
        }
        [data-bs-theme="dark"] .table {
            --bs-table-color: var(--bs-body-color);
            --bs-table-bg: transparent;
            --bs-table-border-color: var(--bs-border-color);
            --bs-table-hover-bg: rgba(255, 255, 255, 0.055);
            --bs-table-striped-bg: rgba(255, 255, 255, 0.03);
            border-color: var(--bs-border-color);
        }

        /* Table header styling for light mode */
        .table thead th,
        .table thead td,
        table thead th,
        table thead td {
            background-color: var(--bs-tertiary-bg);
            color: var(--bs-secondary-color);
            border-bottom-color: var(--bs-border-color);
        }

        /* DARK MODE TABLE & THEAD OVERRIDES - Prevent white table headers */
        [data-bs-theme="dark"] thead,
        [data-bs-theme="dark"] thead tr,
        [data-bs-theme="dark"] thead th,
        [data-bs-theme="dark"] thead td,
        [data-bs-theme="dark"] .table-light,
        [data-bs-theme="dark"] .table-light > tr > th,
        [data-bs-theme="dark"] .table-light > tr > td,
        [data-bs-theme="dark"] thead.table-light,
        [data-bs-theme="dark"] thead.table-light th,
        [data-bs-theme="dark"] thead.table-light td,
        [data-bs-theme="dark"] .table thead th,
        [data-bs-theme="dark"] .table thead td,
        [data-bs-theme="dark"] table thead th,
        [data-bs-theme="dark"] table thead td {
            --bs-table-color: #cbd5e1 !important;
            --bs-table-bg: var(--bs-tertiary-bg) !important;
            --bs-table-border-color: var(--bs-border-color) !important;
            --bs-table-color-state: #cbd5e1 !important;
            --bs-table-bg-state: var(--bs-tertiary-bg) !important;
            --bs-table-accent-bg: transparent !important;
            background-color: var(--bs-tertiary-bg) !important;
            color: #94a3b8 !important;
            border-color: var(--bs-border-color) !important;
            box-shadow: none !important;
        }

        [data-bs-theme="dark"] thead th a,
        [data-bs-theme="dark"] thead th .text-secondary {
            color: #94a3b8 !important;
        }
        [data-bs-theme="dark"] thead th a:hover {
            color: #ffffff !important;
        }

        /* Modal sticky headers */
        [data-bs-theme="dark"] thead.sticky-top,
        [data-bs-theme="dark"] thead.sticky-top th {
            background-color: var(--bs-secondary-bg) !important;
            color: #cbd5e1 !important;
            border-color: var(--bs-border-color) !important;
        }
    </style>
</head>

<body class="layout-fixed sidebar-expand-lg bg-body-tertiary" x-data="{
    roleModal: false,
    scanModal: false,
    scanCode: '',
    scanResult: null,
    scanError: null,
    scanLoading: false,
    colorMode: 'light',
    resolvedTheme: 'light',
    init() {
        this.colorMode = localStorage.getItem('lte-theme') || 'light';
        this.applyTheme(this.colorMode);
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
            if (this.colorMode === 'auto') {
                this.applyTheme('auto');
            }
        });
    },
    setColorMode(mode) {
        this.colorMode = mode;
        localStorage.setItem('lte-theme', mode);
        this.applyTheme(mode);
    },
    applyTheme(mode) {
        let resolved = mode;
        if (mode === 'auto') {
            resolved = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        this.resolvedTheme = resolved;
        document.documentElement.setAttribute('data-bs-theme', resolved);
        document.documentElement.style.colorScheme = resolved;
    },
    performScan() {
        if (!this.scanCode.trim()) return;
        this.scanLoading = true;
        this.scanResult = null;
        this.scanError = null;
        fetch('/api/v1/scan-barcode?code=' + encodeURIComponent(this.scanCode.trim()))
            .then(res => res.json().then(data => ({ status: res.status, data })))
            .then(({ status, data }) => {
                this.scanLoading = false;
                if (status === 200 && data.valid) {
                    this.scanResult = data;
                } else {
                    this.scanError = data.message || 'Kode tidak dikenali dalam sistem.';
                }
            })
            .catch(err => {
                this.scanLoading = false;
                this.scanError = 'Gagal menghubungi server API Gateway.';
            });
    }
}">

@php
    $unreadNotifCount = auth()->check() ? \App\Models\Notification::where('is_read', false)
        ->where(function($q) {
            $q->where('user_id', auth()->id())
              ->orWhere('target_role', auth()->user()->role)
              ->orWhere('target_organization_id', auth()->user()->organization_id)
              ->orWhereNull('target_role');
        })->count() : 0;
    
    $recentNotifications = auth()->check() ? \App\Models\Notification::where(function($q) {
            $q->where('user_id', auth()->id())
              ->orWhere('target_role', auth()->user()->role)
              ->orWhere('target_organization_id', auth()->user()->organization_id)
              ->orWhereNull('target_role');
        })->latest()->take(4)->get() : collect();
@endphp

    <!-- App Wrapper -->
    <div class="app-wrapper">
        
        <!-- App Header (Navbar) -->
        <nav class="app-header navbar navbar-expand bg-body shadow-xs border-bottom">
            <div class="container-fluid">
                <!-- Start Navbar Links -->
                <ul class="navbar-nav align-items-center">
                    <li class="nav-item">
                        <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button" aria-label="Toggle sidebar">
                            <i class="bi bi-list fs-4"></i>
                        </a>
                    </li>
                    <li class="nav-item d-none d-md-block ms-2">
                        <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active fw-bold' : '' }}">
                            <i class="bi bi-speedometer2 me-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item d-none d-md-block">
                        <a href="{{ route('orders.index') }}" class="nav-link {{ request()->routeIs('orders.*') ? 'active fw-bold' : '' }}">
                            <i class="bi bi-cart3 me-1"></i> Orders
                        </a>
                    </li>
                    <li class="nav-item d-none d-md-block">
                        <a href="{{ route('inventory.balances') }}" class="nav-link {{ request()->routeIs('inventory.balances') ? 'active fw-bold' : '' }}">
                            <i class="bi bi-boxes me-1"></i> Stock Balances
                        </a>
                    </li>
                </ul>

                <!-- End Navbar Links -->
                <ul class="navbar-nav ms-auto align-items-center gap-1 gap-sm-2">
                    
                    <!-- Search / Quick Scan Input in Topbar -->
                    <li class="nav-item d-none d-lg-block">
                        <div class="input-group input-group-sm" style="width: 240px;">
                            <input type="text" class="form-control" @keydown.enter.prevent="scanModal = true; scanCode = $event.target.value; performScan();">
                            <button class="btn btn-outline-secondary" type="button" @click="scanModal = true; scanCode = ''; scanResult = null; scanError = null;" title="Buka Scanner Barcode">
                                <i class="bi bi-upc-scan text-danger"></i>
                            </button>
                        </div>
                    </li>

                    <!-- Scanner Trigger on Mobile -->
                    <li class="nav-item d-lg-none">
                        <a class="nav-link" href="#" @click.prevent="scanModal = true; scanCode = ''; scanResult = null; scanError = null;" role="button" title="Scanner Barcode">
                            <i class="bi bi-upc-scan fs-5 text-danger"></i>
                        </a>
                    </li>

                    <!-- Role Simulation Switcher -->
                    <li class="nav-item">
                        <button type="button" @click="roleModal = true" class="btn btn-sm btn-outline-warning d-flex align-items-center gap-1.5 px-2.5 py-1 text-body" title="Simulasi Peran Pengguna">
                            <i class="bi bi-person-badge text-warning"></i>
                            <span class="d-none d-sm-inline fs-7 fw-bold">{{ auth()->user()->role_display_name }}</span>
                            <span class="badge text-bg-warning fs-8">Role</span>
                        </button>
                    </li>

                    <!-- Color Mode Toggle Dropdown -->
                    <li class="nav-item dropdown">
                        <button class="btn btn-link nav-link py-2 px-1 dropdown-toggle d-flex align-items-center" id="bd-theme" type="button" aria-expanded="false" data-bs-toggle="dropdown" data-bs-display="static">
                            <span class="theme-icon-active">
                                <i :class="colorMode === 'dark' ? 'bi bi-moon-stars-fill' : (colorMode === 'auto' ? 'bi bi-circle-half' : 'bi bi-sun-fill')"></i>
                            </span>
                            <span class="d-none ms-2" id="bd-theme-text">Toggle theme</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="bd-theme-text" style="--bs-dropdown-min-width: 8rem;">
                            <li>
                                <button type="button" class="dropdown-item d-flex align-items-center" :class="colorMode === 'light' ? 'active' : ''" @click="setColorMode('light')">
                                    <i class="bi bi-sun-fill me-2 opacity-50"></i> Light
                                </button>
                            </li>
                            <li>
                                <button type="button" class="dropdown-item d-flex align-items-center" :class="colorMode === 'dark' ? 'active' : ''" @click="setColorMode('dark')">
                                    <i class="bi bi-moon-stars-fill me-2 opacity-50"></i> Dark
                                </button>
                            </li>
                            <li>
                                <button type="button" class="dropdown-item d-flex align-items-center" :class="colorMode === 'auto' ? 'active' : ''" @click="setColorMode('auto')">
                                    <i class="bi bi-circle-half me-2 opacity-50"></i> Auto
                                </button>
                            </li>
                        </ul>
                    </li>

                    <!-- Notifications Dropdown Menu -->
                    <li class="nav-item dropdown position-relative">
                        <a class="nav-link" data-bs-toggle="dropdown" href="#" role="button" aria-expanded="false">
                            <i class="bi bi-bell fs-5"></i>
                            @if($unreadNotifCount > 0)
                                <span class="navbar-badge badge text-bg-danger">{{ $unreadNotifCount > 99 ? '99+' : $unreadNotifCount }}</span>
                            @endif
                        </a>
                        <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end shadow">
                            <span class="dropdown-item dropdown-header fw-bold">{{ $unreadNotifCount }} Notifikasi Baru</span>
                            <div class="dropdown-divider my-0"></div>
                            @forelse($recentNotifications as $notif)
                                <a href="{{ route('notifications.index') }}" class="dropdown-item py-2">
                                    <div class="d-flex align-items-start gap-2">
                                        <div class="mt-1">
                                            @if($notif->type === 'STOCK_ALERT')
                                                <i class="bi bi-exclamation-triangle-fill text-danger fs-6"></i>
                                            @elseif($notif->type === 'APPROVAL_REQUEST')
                                                <i class="bi bi-check-circle-fill text-warning fs-6"></i>
                                            @elseif($notif->type === 'SHIPMENT_DISPATCH')
                                                <i class="bi bi-truck text-primary fs-6"></i>
                                            @else
                                                <i class="bi bi-info-circle-fill text-info fs-6"></i>
                                            @endif
                                        </div>
                                        <div class="flex-grow-1 text-truncate">
                                            <div class="fs-7 fw-bold text-truncate text-body">{{ $notif->title }}</div>
                                            <div class="fs-8 text-secondary text-truncate">{{ $notif->message }}</div>
                                            <div class="fs-8 text-muted mt-0.5">{{ $notif->created_at->diffForHumans() }}</div>
                                        </div>
                                    </div>
                                </a>
                                <div class="dropdown-divider my-0"></div>
                            @empty
                                <div class="dropdown-item text-center text-muted py-3 fs-7">
                                    Tidak ada notifikasi baru
                                </div>
                                <div class="dropdown-divider my-0"></div>
                            @endforelse
                            <a href="{{ route('notifications.index') }}" class="dropdown-item dropdown-footer text-center fs-7 text-danger fw-bold">
                                Lihat Semua Notifikasi
                            </a>
                        </div>
                    </li>

                    <!-- User Profile Dropdown Menu -->
                    <li class="nav-item dropdown user-menu">
                        <a href="#" class="nav-link dropdown-toggle d-flex align-items-center gap-2" data-bs-toggle="dropdown" role="button">
                            <div class="rounded-circle bg-danger text-white d-flex align-items-center justify-center fw-bold fs-7" style="width: 32px; height: 32px;">
                                {{ substr(auth()->user()->name, 0, 2) }}
                            </div>
                            <span class="d-none d-md-inline fs-7 fw-semibold">{{ auth()->user()->name }}</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow" style="min-width: 250px;">
                            <!-- User image -->
                            <li class="user-header bg-danger text-white text-center p-3">
                                <div class="rounded-circle bg-white text-danger mx-auto d-flex align-items-center justify-center fw-bold fs-4 mb-2 shadow-sm" style="width: 60px; height: 60px;">
                                    {{ substr(auth()->user()->name, 0, 2) }}
                                </div>
                                <p class="mb-0 fw-bold fs-6">{{ auth()->user()->name }}</p>
                                <small class="text-white-50">{{ auth()->user()->role_display_name }} • {{ auth()->user()->organization->name ?? 'Kantor Pusat' }}</small>
                            </li>
                            <!-- Menu Body -->
                            <li class="user-body p-3 fs-7 border-bottom bg-body">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-secondary">Username:</span>
                                    <span class="fw-bold font-monospace text-body">{{ auth()->user()->username }}</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-secondary">Unit Kerja:</span>
                                    <span class="fw-bold text-body">{{ auth()->user()->organization->code ?? '-' }}</span>
                                </div>
                            </li>
                            <!-- Menu Footer -->
                            <li class="user-footer d-flex justify-content-between p-2 bg-body">
                                <button type="button" @click="roleModal = true" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-shield-lock me-1"></i> Switch Role
                                </button>
                                <form action="{{ route('logout') }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-danger">
                                        <i class="bi bi-box-arrow-right me-1"></i> Sign out
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </nav>
        <!-- /.app-header -->

        <!-- App Sidebar (Dynamically follows theme) -->
        <aside class="app-sidebar shadow" :data-bs-theme="resolvedTheme">
            <!-- Sidebar Brand -->
            <div class="sidebar-brand">
                <a href="{{ route('dashboard') }}" class="brand-link">
                    <!-- Collapsed State: Icon Only -->
                    <img src="{{ asset('images/icon-bankjatim.png') }}" alt="Bank Jatim" class="brand-image-collapsed" style="height: 32px; width: auto; object-fit: contain;">
                    
                    <!-- Expanded State: Official Logo + JIMS Badge -->
                    <div class="brand-expanded">
                        <div class="d-flex align-items-center">
                            <img src="{{ asset('images/logo-bankjatim.png') }}" alt="Bank Jatim" class="brand-logo-light" style="height: 28px; width: auto; max-width: 140px; object-fit: contain;">
                            <img src="{{ asset('images/logo-bankjatim-white.png') }}" alt="Bank Jatim" class="brand-logo-dark" style="height: 28px; width: auto; max-width: 140px; object-fit: contain;">
                        </div>
                        <span class="badge text-bg-danger px-1.5 py-0.5 fs-8 fw-bold">JIMS</span>
                    </div>
                </a>
            </div>

            <!-- Active User Profile Banner in Sidebar -->
            @auth
                <div class="px-3 py-2 border-bottom border-secondary-subtle bg-body-tertiary">
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle bg-danger text-white d-flex align-items-center justify-content-center fw-bold fs-8 shrink-0 shadow-xs" style="width: 32px; height: 32px;">
                            {{ substr(auth()->user()->name, 0, 2) }}
                        </div>
                        <div class="text-truncate" style="min-width: 0;">
                            <div class="fw-bold fs-8 text-truncate text-body">{{ auth()->user()->name }}</div>
                            <div class="fs-9 text-danger fw-semibold text-truncate">{{ auth()->user()->role_display_name }}</div>
                            <div class="fs-9 text-secondary text-truncate"><i class="bi bi-geo-alt me-0.5"></i>{{ auth()->user()->organization->name ?? 'Kantor Pusat' }}</div>
                        </div>
                    </div>
                </div>
            @endauth

            <!-- Sidebar Wrapper -->
            <div class="sidebar-wrapper">
                <nav class="mt-2" aria-label="Main navigation">
                    <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="menu" data-accordion="false" id="navigation">
                        
                        <!-- Dashboard -->
                        <li class="nav-item">
                            <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                                <i class="nav-icon bi bi-speedometer2"></i>
                                <p>Dashboard Overview</p>
                            </a>
                        </li>

                        @auth
                            <!-- Section: Operasional Transaksi -->
                            @if(auth()->user()->canAccessModule('orders') || auth()->user()->canAccessModule('warehouse') || auth()->user()->canAccessModule('receiving'))
                                <li class="nav-header text-uppercase fs-8 fw-bold px-3 pt-3 pb-1">Operasional Transaksi</li>

                                <!-- Permintaan & Orders -->
                                @if(auth()->user()->canAccessModule('orders'))
                                    <li class="nav-item {{ request()->routeIs('orders.*') ? 'menu-open' : '' }}">
                                        <a href="#" class="nav-link {{ request()->routeIs('orders.*') ? 'active' : '' }}">
                                            <i class="nav-icon bi bi-cart3"></i>
                                            <p>
                                                Permintaan & Order
                                                <i class="nav-arrow bi bi-chevron-right"></i>
                                            </p>
                                        </a>
                                        <ul class="nav nav-treeview">
                                            <li class="nav-item">
                                                <a href="{{ route('orders.index') }}" class="nav-link {{ (request()->routeIs('orders.index') || request()->routeIs('orders.create')) && !request()->routeIs('orders.approvals*') ? 'active' : '' }}">
                                                    <i class="nav-icon bi bi-circle"></i>
                                                    <p>Order</p>
                                                </a>
                                            </li>
                                            @if(auth()->user()->canAccessModule('order_approvals'))
                                                <li class="nav-item">
                                                    <a href="{{ route('orders.approvals') }}" class="nav-link {{ request()->routeIs('orders.approvals*') ? 'active' : '' }}">
                                                        <i class="nav-icon bi bi-circle"></i>
                                                        <p>Persetujuan Order</p>
                                                    </a>
                                                </li>
                                            @endif
                                        </ul>
                                    </li>
                                @endif

                                <!-- Operasional Gudang & Ekspedisi -->
                                @if(auth()->user()->canAccessModule('warehouse'))
                                    <li class="nav-item {{ request()->routeIs('warehouse.*') || request()->routeIs('distribution.*') ? 'menu-open' : '' }}">
                                        <a href="#" class="nav-link {{ request()->routeIs('warehouse.*') || request()->routeIs('distribution.*') ? 'active' : '' }}">
                                            <i class="nav-icon bi bi-box-seam"></i>
                                            <p>
                                                Gudang & Distribusi
                                                <i class="nav-arrow bi bi-chevron-right"></i>
                                            </p>
                                        </a>
                                        <ul class="nav nav-treeview">
                                            @if(auth()->user()->canAccessModule('warehouse_ops'))
                                                <li class="nav-item">
                                                    <a href="{{ route('warehouse.picking.queue') }}" class="nav-link {{ request()->routeIs('warehouse.picking.*') ? 'active' : '' }}">
                                                        <i class="nav-icon bi bi-circle"></i>
                                                        <p>Antrean Picking</p>
                                                    </a>
                                                </li>
                                            @endif
                                            <li class="nav-item">
                                                <a href="{{ route('warehouse.packing.queue') }}" class="nav-link {{ request()->routeIs('warehouse.packing.*') ? 'active' : '' }}">
                                                    <i class="nav-icon bi bi-circle"></i>
                                                    <p>Antrean Packing</p>
                                                </a>
                                            </li>
                                            @if(auth()->user()->canAccessModule('distribution'))
                                                <li class="nav-item">
                                                    <a href="{{ route('distribution.shipments.index') }}" class="nav-link {{ request()->routeIs('distribution.*') ? 'active' : '' }}">
                                                        <i class="nav-icon bi bi-circle"></i>
                                                        <p>Pengiriman & Manifest</p>
                                                    </a>
                                                </li>
                                            @endif
                                        </ul>
                                    </li>
                                @endif

                                <!-- Penerimaan Barang (Receiving) -->
                                @if(auth()->user()->canAccessModule('receiving'))
                                    <li class="nav-item {{ request()->routeIs('receiving.*') ? 'menu-open' : '' }}">
                                        <a href="#" class="nav-link {{ request()->routeIs('receiving.*') ? 'active' : '' }}">
                                            <i class="nav-icon bi bi-truck"></i>
                                            <p>
                                                Penerimaan & QC
                                                <i class="nav-arrow bi bi-chevron-right"></i>
                                            </p>
                                        </a>
                                        <ul class="nav nav-treeview">
                                            <li class="nav-item">
                                                <a href="{{ route('receiving.index') }}" class="nav-link {{ request()->routeIs('receiving.index') ? 'active' : '' }}">
                                                    <i class="nav-icon bi bi-circle"></i>
                                                    <p>Penerimaan Barang Cabang</p>
                                                </a>
                                            </li>
                                            <li class="nav-item">
                                                <a href="{{ route('receiving.discrepancies') }}" class="nav-link {{ request()->routeIs('receiving.discrepancies') ? 'active' : '' }}">
                                                    <i class="nav-icon bi bi-circle"></i>
                                                    <p>Berita Acara Selisih</p>
                                                </a>
                                            </li>
                                        </ul>
                                    </li>
                                @endif
                            @endif

                            <!-- Section: Inventory & Supply Chain -->
                            @if(auth()->user()->canAccessModule('inventory') || auth()->user()->canAccessModule('procurement') || auth()->user()->canAccessModule('finance'))
                                <li class="nav-header text-uppercase fs-8 fw-bold px-3 pt-3 pb-1">Persediaan & Pengadaan</li>

                                <!-- Manajemen Persediaan -->
                                @if(auth()->user()->canAccessModule('inventory'))
                                    <li class="nav-item {{ request()->routeIs('inventory.*') ? 'menu-open' : '' }}">
                                        <a href="#" class="nav-link {{ request()->routeIs('inventory.*') ? 'active' : '' }}">
                                            <i class="nav-icon bi bi-stack"></i>
                                            <p>
                                                Manajemen Persediaan
                                                <i class="nav-arrow bi bi-chevron-right"></i>
                                            </p>
                                        </a>
                                        <ul class="nav nav-treeview">
                                            <li class="nav-item">
                                                <a href="{{ route('inventory.balances') }}" class="nav-link {{ request()->routeIs('inventory.balances') ? 'active' : '' }}">
                                                    <i class="nav-icon bi bi-circle"></i>
                                                    <p>Stock Balances (SSoT)</p>
                                                </a>
                                            </li>
                                            @if(auth()->user()->canAccessModule('inventory_ops'))
                                                <li class="nav-item">
                                                    <a href="{{ route('inventory.stock_opname') }}" class="nav-link {{ request()->routeIs('inventory.stock_opname*') ? 'active' : '' }}">
                                                        <i class="nav-icon bi bi-circle"></i>
                                                        <p>Stock Opname Fisik</p>
                                                    </a>
                                                </li>
                                            @endif
                                            @if(auth()->user()->canAccessModule('inventory_advanced'))
                                                <li class="nav-item">
                                                    <a href="{{ route('inventory.forecasting') }}" class="nav-link {{ request()->routeIs('inventory.forecasting') ? 'active' : '' }}">
                                                        <i class="nav-icon bi bi-circle"></i>
                                                        <p>Peramalan AI (Forecasting)</p>
                                                    </a>
                                                </li>
                                                <li class="nav-item">
                                                    <a href="{{ route('inventory.switching.index') }}" class="nav-link {{ request()->routeIs('inventory.switching.*') ? 'active' : '' }}">
                                                        <i class="nav-icon bi bi-circle"></i>
                                                        <p>Switching Antar-Cabang</p>
                                                    </a>
                                                </li>
                                            @endif
                                        </ul>
                                    </li>
                                @endif

                                <!-- Pengadaan Logistik (Procurement) -->
                                @if(auth()->user()->canAccessModule('procurement'))
                                    <li class="nav-item {{ request()->routeIs('procurement.*') ? 'menu-open' : '' }}">
                                        <a href="#" class="nav-link {{ request()->routeIs('procurement.*') ? 'active' : '' }}">
                                            <i class="nav-icon bi bi-bag-check"></i>
                                            <p>
                                                Pengadaan (Procurement)
                                                <i class="nav-arrow bi bi-chevron-right"></i>
                                            </p>
                                        </a>
                                        <ul class="nav nav-treeview">
                                            <li class="nav-item">
                                                <a href="{{ route('procurement.pr.index') }}" class="nav-link {{ request()->routeIs('procurement.pr.index') ? 'active' : '' }}">
                                                    <i class="nav-icon bi bi-circle"></i>
                                                    <p>Purchase Request (PR)</p>
                                                </a>
                                            </li>
                                            @if(auth()->user()->canAccessModule('procurement_maker') || auth()->user()->isSuperAdmin())
                                                <li class="nav-item">
                                                    <a href="{{ route('procurement.pr.create') }}" class="nav-link {{ request()->routeIs('procurement.pr.create') ? 'active' : '' }}">
                                                        <i class="nav-icon bi bi-circle"></i>
                                                        <p>Buat PR Baru</p>
                                                    </a>
                                                </li>
                                            @endif
                                            <li class="nav-item">
                                                <a href="{{ route('procurement.consolidation.index') }}" class="nav-link {{ request()->routeIs('procurement.consolidation.*') ? 'active' : '' }}">
                                                    <i class="nav-icon bi bi-circle"></i>
                                                    <p>Konsolidasi PR & Vendor</p>
                                                </a>
                                            </li>
                                            <li class="nav-item">
                                                <a href="{{ route('procurement.po.index') }}" class="nav-link {{ request()->routeIs('procurement.po.*') ? 'active' : '' }}">
                                                    <i class="nav-icon bi bi-circle"></i>
                                                    <p>Purchase Order (PO)</p>
                                                </a>
                                            </li>
                                        </ul>
                                    </li>
                                @endif

                                <!-- Settlement Keuangan -->
                                @if(auth()->user()->canAccessModule('finance'))
                                    <li class="nav-item">
                                        <a href="{{ route('finance.settlements.index') }}" class="nav-link {{ request()->routeIs('finance.settlements.*') ? 'active' : '' }}">
                                            <i class="nav-icon bi bi-cash-stack"></i>
                                            <p>Settlement Alokasi Biaya</p>
                                        </a>
                                    </li>
                                @endif
                            @endif

                            <!-- Section: Master Data & Konfigurasi -->
                            @if(auth()->user()->canAccessModule('master_data') || auth()->user()->canAccessModule('master_users') || auth()->user()->canAccessModule('audit') || auth()->user()->canAccessModule('reports'))
                                <li class="nav-header text-uppercase fs-8 fw-bold px-3 pt-3 pb-1">Master Data & Administrasi</li>

                                <!-- Master Data -->
                                @if(auth()->user()->canAccessModule('master_data') || auth()->user()->canAccessModule('master_users'))
                                    <li class="nav-item {{ request()->routeIs('master.*') ? 'menu-open' : '' }}">
                                        <a href="#" class="nav-link {{ request()->routeIs('master.*') ? 'active' : '' }}">
                                            <i class="nav-icon bi bi-gear-wide-connected"></i>
                                            <p>
                                                Master Data Terpadu
                                                <i class="nav-arrow bi bi-chevron-right"></i>
                                            </p>
                                        </a>
                                        <ul class="nav nav-treeview">
                                            @if(auth()->user()->canAccessModule('master_data'))
                                                <li class="nav-item">
                                                    <a href="{{ route('master.organizations') }}" class="nav-link {{ request()->routeIs('master.organizations') ? 'active' : '' }}">
                                                        <i class="nav-icon bi bi-circle"></i>
                                                        <p>Unit Kerja & Gudang</p>
                                                    </a>
                                                </li>
                                            @endif
                                            @if(auth()->user()->canAccessModule('master_items'))
                                                <li class="nav-item">
                                                    <a href="{{ route('master.items') }}" class="nav-link {{ request()->routeIs('master.items') ? 'active' : '' }}">
                                                        <i class="nav-icon bi bi-circle"></i>
                                                        <p>Master Barang (SKU)</p>
                                                    </a>
                                                </li>
                                            @endif
                                            @if(auth()->user()->canAccessModule('master_budgets'))
                                                <li class="nav-item">
                                                    <a href="{{ route('master.budgets') }}" class="nav-link {{ request()->routeIs('master.budgets') ? 'active' : '' }}">
                                                        <i class="nav-icon bi bi-circle"></i>
                                                        <p>Pagu Anggaran Cabang</p>
                                                    </a>
                                                </li>
                                            @endif
                                            @if(auth()->user()->canAccessModule('master_vendors'))
                                                <li class="nav-item">
                                                    <a href="{{ route('master.vendors') }}" class="nav-link {{ request()->routeIs('master.vendors') ? 'active' : '' }}">
                                                        <i class="nav-icon bi bi-circle"></i>
                                                        <p>Vendor & Ekspedisi</p>
                                                    </a>
                                                </li>
                                            @endif
                                            @if(auth()->user()->canAccessModule('master_users'))
                                                <li class="nav-item">
                                                    <a href="{{ route('master.users') }}" class="nav-link {{ request()->routeIs('master.users') ? 'active' : '' }}">
                                                        <i class="nav-icon bi bi-circle"></i>
                                                        <p>Manajemen Pengguna</p>
                                                    </a>
                                                </li>
                                            @endif
                                        </ul>
                                    </li>
                                @endif

                                <!-- Audit Trail Sistem -->
                                @if(auth()->user()->canAccessModule('audit'))
                                    <li class="nav-item">
                                        <a href="{{ route('audit.index') }}" class="nav-link {{ request()->routeIs('audit.index') ? 'active' : '' }}">
                                            <i class="nav-icon bi bi-shield-check"></i>
                                            <p>Audit Trail Sistem</p>
                                        </a>
                                    </li>
                                @endif
                            @endif

                            <!-- Monitoring Notifikasi (Semua Role) -->
                            <li class="nav-item">
                                <a href="{{ route('notifications.index') }}" class="nav-link {{ request()->routeIs('notifications.index') ? 'active' : '' }}">
                                    <i class="nav-icon bi bi-bell"></i>
                                    <p>
                                        Monitoring Notifikasi
                                        @if($unreadNotifCount > 0)
                                            <span class="badge text-bg-danger float-end">{{ $unreadNotifCount }}</span>
                                        @endif
                                    </p>
                                </a>
                            </li>

                            <!-- Laporan & Ekspor -->
                            @if(auth()->user()->canAccessModule('reports'))
                                <li class="nav-item {{ request()->routeIs('reports.*') ? 'menu-open' : '' }}">
                                    <a href="#" class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}">
                                        <i class="nav-icon bi bi-bar-chart-line"></i>
                                        <p>
                                            Laporan & Rekapitulasi
                                            <i class="nav-arrow bi bi-chevron-right"></i>
                                        </p>
                                    </a>
                                    <ul class="nav nav-treeview">
                                        <li class="nav-item">
                                            <a href="{{ route('reports.stock_valuation') }}" class="nav-link {{ request()->routeIs('reports.stock_valuation') ? 'active' : '' }}">
                                                <i class="nav-icon bi bi-circle"></i>
                                                <p>Valuasi Persediaan</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="{{ route('reports.settlements') }}" class="nav-link {{ request()->routeIs('reports.settlements') ? 'active' : '' }}">
                                                <i class="nav-icon bi bi-circle"></i>
                                                <p>Rekapitulasi Settlement</p>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="{{ route('reports.procurement_coverage') }}" class="nav-link {{ request()->routeIs('reports.procurement_coverage') ? 'active' : '' }}">
                                                <i class="nav-icon bi bi-circle"></i>
                                                <p>Coverage Pengadaan</p>
                                            </a>
                                        </li>
                                    </ul>
                                </li>
                            @endif
                        @endauth
                    </ul>
                </nav>
            </div>
        </aside>
        <!-- /.app-sidebar -->

        <!-- App Main Content -->
        <main class="app-main">
            <!-- App Content Header -->
            <div class="app-content-header">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-sm-6">
                            <h3 class="mb-0 text-body">@yield('title', 'Dashboard')</h3>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-end mb-0">
                                @hasSection('breadcrumbs')
                                    @yield('breadcrumbs')
                                @else
                                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
                                    <li class="breadcrumb-item active" aria-current="page">@yield('title', 'Dashboard')</li>
                                @endif
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <!-- App Content Body -->
            <div class="app-content">
                <div class="container-fluid">
                    <!-- Toast Notification Container (Floating Top-End) -->
                    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1095;">
                        @if(session('success'))
                            <div class="toast align-items-center text-bg-success border-0 shadow-lg mb-2" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="4000">
                                <div class="d-flex">
                                    <div class="toast-body d-flex align-items-center gap-2.5 fs-7 py-2.5 px-3">
                                        <i class="bi bi-check-circle-fill fs-5 flex-shrink-0"></i>
                                        <div>
                                            <div class="fw-bold">Berhasil</div>
                                            <div class="fs-8 opacity-90">{{ session('success') }}</div>
                                        </div>
                                    </div>
                                    <button type="button" class="btn-close btn-close-white me-2.5 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                                </div>
                            </div>
                        @endif

                        @if(session('error'))
                            <div class="toast align-items-center text-bg-danger border-0 shadow-lg mb-2" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="5000">
                                <div class="d-flex">
                                    <div class="toast-body d-flex align-items-center gap-2.5 fs-7 py-2.5 px-3">
                                        <i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0"></i>
                                        <div>
                                            <div class="fw-bold">Perhatian / Gagal</div>
                                            <div class="fs-8 opacity-90">{{ session('error') }}</div>
                                        </div>
                                    </div>
                                    <button type="button" class="btn-close btn-close-white me-2.5 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                                </div>
                            </div>
                        @endif

                        @if(session('warning'))
                            <div class="toast align-items-center text-bg-warning border-0 shadow-lg mb-2" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="5000">
                                <div class="d-flex">
                                    <div class="toast-body d-flex align-items-center gap-2.5 fs-7 py-2.5 px-3">
                                        <i class="bi bi-exclamation-circle-fill fs-5 flex-shrink-0"></i>
                                        <div>
                                            <div class="fw-bold">Peringatan</div>
                                            <div class="fs-8 opacity-90">{{ session('warning') }}</div>
                                        </div>
                                    </div>
                                    <button type="button" class="btn-close me-2.5 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                                </div>
                            </div>
                        @endif

                        @if(session('info'))
                            <div class="toast align-items-center text-bg-info border-0 shadow-lg mb-2" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="4000">
                                <div class="d-flex">
                                    <div class="toast-body d-flex align-items-center gap-2.5 fs-7 py-2.5 px-3">
                                        <i class="bi bi-info-circle-fill fs-5 flex-shrink-0"></i>
                                        <div>
                                            <div class="fw-bold">Informasi</div>
                                            <div class="fs-8 opacity-90">{{ session('info') }}</div>
                                        </div>
                                    </div>
                                    <button type="button" class="btn-close btn-close-white me-2.5 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                                </div>
                            </div>
                        @endif

                        @if($errors->any())
                            <div class="toast align-items-center text-bg-danger border-0 shadow-lg mb-2" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="6000">
                                <div class="d-flex">
                                    <div class="toast-body d-flex align-items-start gap-2.5 fs-7 py-2.5 px-3">
                                        <i class="bi bi-x-circle-fill fs-5 flex-shrink-0 mt-0.5"></i>
                                        <div>
                                            <div class="fw-bold">Terjadi Kesalahan Validasi</div>
                                            <ul class="mb-0 ps-3 fs-8 opacity-90">
                                                @foreach($errors->all() as $err)
                                                    <li>{{ $err }}</li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    </div>
                                    <button type="button" class="btn-close btn-close-white me-2.5 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                                </div>
                            </div>
                        @endif
                    </div>

                    @yield('content')
                </div>
            </div>
        </main>
        <!-- /.app-main -->

        <!-- App Footer -->
        <footer class="app-footer text-secondary fs-8 bg-body border-top">
            <div class="float-end d-none d-sm-inline">
                <strong>JIMS v2.0 Enterprise</strong> • Bank Jatim Logistik
            </div>
            <strong>Copyright &copy; {{ date('Y') }} <a href="https://www.bankjatim.co.id" target="_blank" class="text-decoration-none text-danger fw-bold">PT Bank Pembangunan Daerah Jawa Timur Tbk</a>.</strong> All rights reserved.
        </footer>
    </div>
    <!-- ./app-wrapper -->

    <!-- Quick Role Switcher Modal -->
    <div x-show="roleModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4" x-cloak style="display: none;">
        <div @click.away="roleModal = false" class="card shadow-2xl border border-secondary-subtle max-w-2xl w-full p-4 p-sm-5 space-y-4" style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            <div class="d-flex align-items-center justify-content-between border-bottom pb-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-3 p-2 bg-warning-subtle text-warning-emphasis d-flex align-items-center justify-content-center">
                        <i class="fa-solid fa-users-gear fs-5"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold fs-6 mb-0 text-body">Simulasi Peran Pengguna (Role Switcher)</h5>
                        <p class="fs-8 text-secondary mb-0">Pilih salah satu akun untuk simulasi instan 1-klik</p>
                    </div>
                </div>
                <button type="button" @click="roleModal = false" class="btn-close" aria-label="Close"></button>
            </div>

            <div class="row g-2 max-h-[55vh] overflow-y-auto pr-1">
                @php
                    $allUsers = \App\Models\User::with('organization')->get();
                @endphp
                @foreach($allUsers as $u)
                    <div class="col-12 col-sm-6">
                        <form action="{{ route('quick.switch') }}" method="POST">
                            @csrf
                            <input type="hidden" name="user_id" value="{{ $u->id }}">
                            <button type="submit" class="w-100 text-start p-2.5 rounded-3 border transition {{ auth()->id() === $u->id ? 'border-warning bg-warning-subtle text-warning-emphasis' : 'border-secondary-subtle bg-body-secondary text-body' }}">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="badge bg-secondary-subtle text-secondary font-monospace fs-8 text-uppercase">{{ $u->organization->code ?? 'KP' }}</span>
                                    @if(auth()->id() === $u->id)
                                        <span class="badge text-bg-warning fs-8">AKTIF</span>
                                    @endif
                                </div>
                                <div class="fw-bold fs-7 text-truncate text-body">{{ $u->name }}</div>
                                <div class="fs-8 text-danger fw-semibold text-truncate">{{ $u->role_display_name }}</div>
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Live Barcode & QR Scanner Modal -->
    <div x-show="scanModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4" x-cloak style="display: none;">
        <div @click.away="scanModal = false" class="card shadow-2xl border border-secondary-subtle max-w-lg w-full p-4 p-sm-5 space-y-4" style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            <div class="d-flex align-items-center justify-content-between border-bottom pb-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-3 p-2 bg-danger-subtle text-danger d-flex align-items-center justify-content-center">
                        <i class="fa-solid fa-barcode fs-5"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold fs-6 mb-0 text-body">Live Barcode & SKU Scanner</h5>
                        <p class="fs-8 text-secondary mb-0">Verifikasi instan via API Gateway Bank Jatim</p>
                    </div>
                </div>
                <button type="button" @click="scanModal = false" class="btn-close" aria-label="Close"></button>
            </div>

            <!-- Input & Scan Trigger -->
            <div class="space-y-2">
                <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Input SKU, Barcode, Resi, atau Order Number</label>
                <div class="input-group">
                    <input type="text" x-model="scanCode" @keydown.enter.prevent="performScan()" class="form-control font-monospace fw-bold fs-7">
                    <button @click="performScan()" :disabled="scanLoading" class="btn btn-danger text-white fs-7 fw-bold">
                        <span x-show="!scanLoading"><i class="bi bi-search me-1"></i> Scan</span>
                        <span x-show="scanLoading"><i class="fa-solid fa-circle-notch fa-spin me-1"></i> Memeriksa...</span>
                    </button>
                </div>
            </div>

            <!-- Result Feedback -->
            <template x-if="scanError">
                <div class="alert alert-danger py-2 px-3 fs-7 d-flex align-items-center gap-2 mb-0">
                    <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
                    <span x-text="scanError"></span>
                </div>
            </template>

            <template x-if="scanResult && scanResult.type === 'ITEM'">
                <div class="card border-success bg-success-subtle p-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="badge text-bg-success font-monospace fs-8" x-text="scanResult.data.sku"></span>
                        <span class="fw-bold fs-8 text-success-emphasis" x-text="scanResult.data.category"></span>
                    </div>
                    <div class="fw-bold fs-7 text-body" x-text="scanResult.data.name"></div>
                    <div class="d-flex justify-content-between align-items-center text-secondary pt-2 mt-2 border-top border-success-subtle fs-7">
                        <span>Total Stok Bebas:</span>
                        <span class="fw-bold text-success fs-6" x-text="scanResult.data.total_available + ' ' + scanResult.data.uom"></span>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <!-- Required Scripts: OverlayScrollbars, Popper, Bootstrap 5.3, AdminLTE 4 -->
    <script src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/browser/overlayscrollbars.browser.es6.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@4.9.1/dist/js/adminlte.min.js" crossorigin="anonymous"></script>

    <!-- Configure OverlayScrollbars & Toast Notifications -->
    <script>
      document.addEventListener('DOMContentLoaded', function () {
        const sidebarWrapper = document.querySelector('.sidebar-wrapper');
        const isMobile = window.innerWidth <= 992;

        if (
          sidebarWrapper &&
          typeof OverlayScrollbarsGlobal !== 'undefined' &&
          OverlayScrollbarsGlobal.OverlayScrollbars !== undefined &&
          !isMobile
        ) {
          OverlayScrollbarsGlobal.OverlayScrollbars(sidebarWrapper, {
            scrollbars: {
              theme: 'os-theme-light',
              autoHide: 'leave',
              clickScroll: true,
            },
          });
        }

        // Auto-show all rendered server-side toasts
        const toastElList = document.querySelectorAll('.toast-container .toast');
        toastElList.forEach(function (toastEl) {
          const toast = new bootstrap.Toast(toastEl);
          toast.show();
        });
      });

      // Global helper for dynamic client-side toasts
      window.showToast = function(message, type = 'success', title = '') {
        const container = document.querySelector('.toast-container');
        if (!container) return;

        const icons = {
          success: 'bi-check-circle-fill',
          danger: 'bi-exclamation-triangle-fill',
          error: 'bi-exclamation-triangle-fill',
          warning: 'bi-exclamation-circle-fill',
          info: 'bi-info-circle-fill'
        };
        const bgClass = (type === 'error' || type === 'danger') ? 'text-bg-danger' : `text-bg-${type}`;
        const iconClass = icons[type] || 'bi-info-circle-fill';
        const defaultTitle = type === 'success' ? 'Berhasil' : ((type === 'danger' || type === 'error') ? 'Perhatian / Gagal' : (type === 'warning' ? 'Peringatan' : 'Informasi'));

        const toastEl = document.createElement('div');
        toastEl.className = `toast align-items-center ${bgClass} border-0 shadow-lg mb-2`;
        toastEl.setAttribute('role', 'alert');
        toastEl.setAttribute('aria-live', 'assertive');
        toastEl.setAttribute('aria-atomic', 'true');
        toastEl.setAttribute('data-bs-delay', '4500');

        toastEl.innerHTML = `
          <div class="d-flex">
            <div class="toast-body d-flex align-items-center gap-2.5 fs-7 py-2.5 px-3">
              <i class="bi ${iconClass} fs-5 flex-shrink-0"></i>
              <div>
                <div class="fw-bold">${title || defaultTitle}</div>
                <div class="fs-8 opacity-90">${message}</div>
              </div>
            </div>
            <button type="button" class="btn-close ${type === 'warning' ? '' : 'btn-close-white'} me-2.5 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
          </div>
        `;

        container.appendChild(toastEl);
        const toast = new bootstrap.Toast(toastEl);
        toast.show();
        toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
      };

      // Global Modal & Dialog Form Reset System (Refresh form state & dismiss "Fill out this field" on close/cancel)
      (function () {
        function isActualModal(el) {
          if (!el || !(el instanceof HTMLElement)) return false;

          // Strictly exclude tab panels and elements bound to tab navigation
          const xShow = el.getAttribute('x-show') || '';
          if (/tab/i.test(xShow)) return false;
          if (el.getAttribute('role') === 'tabpanel' || el.closest('[role="tabpanel"]')) return false;

          // Real modal containers have .modal class or are fixed fullscreen overlays (fixed inset-0) or explicit Alpine modal containers
          const isBsModal = el.classList.contains('modal');
          const isBackdropOverlay = el.classList.contains('fixed') && el.classList.contains('inset-0');
          const isAlpineModal = el.hasAttribute('x-show') && /modal/i.test(xShow);

          return isBsModal || isBackdropOverlay || isAlpineModal;
        }

        function isCreateForm(form) {
          if (!form || !(form instanceof HTMLFormElement)) return false;
          const methodAttr = (form.getAttribute('method') || 'GET').toUpperCase();
          if (methodAttr !== 'POST') return false;
          const methodInput = form.querySelector('input[name="_method"]');
          if (methodInput) {
            const method = (methodInput.value || '').toUpperCase();
            if (method !== 'POST') return false;
          }
          return true;
        }

        function resetFormState(form) {
          if (!form || !(form instanceof HTMLFormElement)) return;
          // Never reset a GET search/filter form
          const method = (form.getAttribute('method') || 'GET').toUpperCase();
          if (method === 'GET') return;

          // 1. Immediately dismiss active browser tooltip ("Fill out this field") by blurring focused element
          if (document.activeElement && form.contains(document.activeElement)) {
            try {
              document.activeElement.blur();
            } catch (e) {}
          }

          // 2. Clear HTML5 constraint validation and custom validity
          const controls = form.querySelectorAll('input, select, textarea');
          controls.forEach(function (control) {
            if (typeof control.setCustomValidity === 'function') {
              control.setCustomValidity('');
            }
            control.classList.remove('is-invalid', 'is-valid');
          });

          // 3. Reset form native state back to pristine default
          form.reset();
          form.classList.remove('was-validated');

          // 4. Safely dispatch input events ONLY for Alpine x-model data binding
          // Do NOT dispatch 'change' events to prevent triggering inline onchange handlers (e.g. form.submit())
          controls.forEach(function (control) {
            if (control.hasAttribute('x-model')) {
              control.dispatchEvent(new Event('input', { bubbles: true }));
            }
          });
        }

        function clearFormValidationOnly(form) {
          if (!form || !(form instanceof HTMLFormElement)) return;

          if (document.activeElement && form.contains(document.activeElement)) {
            try {
              document.activeElement.blur();
            } catch (e) {}
          }

          const controls = form.querySelectorAll('input, select, textarea');
          controls.forEach(function (control) {
            if (typeof control.setCustomValidity === 'function') {
              control.setCustomValidity('');
            }
            control.classList.remove('is-invalid', 'is-valid');
          });
          form.classList.remove('was-validated');
        }

        function resetModalContainer(container, isClosing) {
          if (!container || !(container instanceof HTMLElement)) return;
          if (!isActualModal(container)) return;

          const forms = container.querySelectorAll('form');
          forms.forEach(function (form) {
            const method = (form.getAttribute('method') || 'GET').toUpperCase();
            if (method === 'GET') return; // NEVER reset GET search/filter forms

            if (isClosing || isCreateForm(form)) {
              resetFormState(form);
            } else {
              clearFormValidationOnly(form);
            }
          });

          // Blur any lingering active element in container
          if (document.activeElement && container.contains(document.activeElement)) {
            try {
              document.activeElement.blur();
            } catch (e) {}
          }
        }

        // Intercept clicks on Batal, Cancel, Tutup, Close, and backdrop clicks
        document.addEventListener('click', function (e) {
          const target = e.target;
          if (!target || !(target instanceof Element)) return;

          const isCloseBtn = !!target.closest('.btn-close, [data-bs-dismiss="modal"]');
          
          const btn = target.closest('button, a');
          let isCancelBtn = false;
          if (btn) {
            const txt = (btn.textContent || '').trim().toLowerCase();
            if (txt === 'batal' || txt === 'cancel' || txt === '×' || txt === 'x' || txt.startsWith('batal ') || txt.endsWith(' batal')) {
              isCancelBtn = true;
            }
          }

          const isBackdrop = target.classList.contains('fixed') && target.classList.contains('inset-0');

          if (isCloseBtn || isCancelBtn || isBackdrop) {
            const modalContainer = target.closest('.modal, .fixed.inset-0, [x-show*="Modal"], [x-show*="modal"]');
            if (modalContainer && isActualModal(modalContainer)) {
              resetModalContainer(modalContainer, true);
            } else if (isBackdrop && isActualModal(target)) {
              resetModalContainer(target, true);
            }
          }
        }, true); // Use capture phase so blur and form reset happen immediately

        // Dismiss & reset on Escape key
        document.addEventListener('keydown', function (e) {
          if (e.key === 'Escape') {
            const openModals = document.querySelectorAll('.fixed.inset-0:not([style*="display: none"]), .modal.show');
            openModals.forEach(function (m) {
              if (isActualModal(m)) {
                resetModalContainer(m, true);
              }
            });
          }
        });

        // Bootstrap 5 modal lifecycle listeners
        document.addEventListener('hidden.bs.modal', function (e) {
          if (isActualModal(e.target)) {
            resetModalContainer(e.target, true);
          }
        });

        document.addEventListener('show.bs.modal', function (e) {
          if (isActualModal(e.target)) {
            resetModalContainer(e.target, false);
          }
        });

        // Observe style & class mutations on Alpine.js modal containers ONLY
        document.addEventListener('DOMContentLoaded', function () {
          const observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
              const el = mutation.target;
              if (!el || !(el instanceof HTMLElement)) return;

              if (!isActualModal(el)) return;

              const styleDisplay = el.style.display;
              const isHidden =
                styleDisplay === 'none' ||
                el.classList.contains('d-none') ||
                el.classList.contains('hidden') ||
                el.hasAttribute('hidden');

              if (isHidden) {
                resetModalContainer(el, true);
              } else if (styleDisplay !== 'none') {
                resetModalContainer(el, false);
              }
            });
          });

          observer.observe(document.body, {
            attributes: true,
            subtree: true,
            attributeFilter: ['style', 'class', 'hidden']
          });
        });
      })();
    </script>

    @yield('scripts')
</body>
</html>
