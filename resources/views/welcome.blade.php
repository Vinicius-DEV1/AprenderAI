<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $siteName }} - Prepare-se para o ENEM e Concursos</title>
    <meta name="description"
        content="A plataforma completa de preparação para ENEM e Concursos Públicos com correção instantânea por IA e plano de estudos personalizado.">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:title" content="{{ $siteName }} - Inteligência Artificial para sua Aprovação">
    <meta property="og:description"
        content="Prepare-se para o ENEM e Concursos com correção instantânea e feedbacks personalizados da nossa IA.">
    <meta property="og:image" content="{{ asset('img/social-preview.png') }}">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="{{ url()->current() }}">
    <meta property="twitter:title" content="{{ $siteName }} - Inteligência Artificial para sua Aprovação">
    <meta property="twitter:description"
        content="Prepare-se para o ENEM e Concursos com correção instantânea e feedbacks personalizados da nossa IA.">
    <meta property="twitter:image" content="{{ asset('img/social-preview.png') }}">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap"
        rel="stylesheet">

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js"></script>

    <style>
        /* ── Base ── */
        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Inter', sans-serif;
        }

        /* ── Navbar ── */
        .lp-nav {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 50;
            background: #ffffff;
            box-shadow: 0 1px 8px rgba(0, 0, 0, .08);
        }

        .lp-nav-inner {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1.5rem;
            height: 66px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .lp-logo {
            font-size: 1.45rem;
            font-weight: 800;
            color: #0f2b6e;
            text-decoration: none;
            letter-spacing: -0.5px;
        }

        .lp-logo span {
            color: #f59e0b;
        }

        .lp-nav-links {
            display: flex;
            align-items: center;
            gap: 1.75rem;
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .lp-nav-links a {
            font-size: .9rem;
            font-weight: 500;
            color: #374151;
            text-decoration: none;
            transition: color .2s;
        }

        .lp-nav-links a:hover {
            color: #0f2b6e;
        }

        .lp-btn-cta {
            display: inline-block;
            padding: .55rem 1.4rem;
            background: #f59e0b;
            color: #0f2b6e !important;
            font-weight: 800;
            font-size: .88rem;
            border-radius: 6px;
            text-transform: uppercase;
            letter-spacing: .5px;
            transition: background .2s, transform .15s;
            text-decoration: none;
        }

        .lp-btn-cta:hover {
            background: #d97706;
            transform: translateY(-1px);
        }

        /* ── Hero ── */
        .lp-hero {
            background: linear-gradient(135deg, #0a1f5e 0%, #0f2b6e 40%, #1a4db8 100%);
            padding-top: 66px;
            /* offset fixed navbar */
            min-height: 580px;
            position: relative;
            overflow: hidden;
        }

        .lp-hero-inner {
            max-width: 1200px;
            margin: 0 auto;
            padding: 5rem 1.5rem 4rem;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            align-items: center;
        }

        @media (max-width: 768px) {
            .lp-hero-inner {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .lp-hero-btns {
                justify-content: center;
            }

            .lp-nav-links {
                display: none;
            }
        }

        .lp-hero-title {
            font-size: clamp(2.2rem, 4vw, 3.2rem);
            font-weight: 900;
            color: #ffffff;
            line-height: 1.15;
            margin-bottom: 1.25rem;
        }

        .lp-hero-title span {
            color: #f59e0b;
        }

        .lp-hero-subtitle {
            font-size: 1.05rem;
            color: #bfcfe8;
            line-height: 1.65;
            margin-bottom: 2rem;
            max-width: 480px;
        }

        .lp-hero-btns {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .lp-btn-primary {
            padding: .85rem 2rem;
            background: #f59e0b;
            color: #0f2b6e;
            font-weight: 800;
            font-size: 1rem;
            border-radius: 8px;
            text-decoration: none;
            transition: background .2s, transform .15s;
            display: inline-block;
        }

        .lp-btn-primary:hover {
            background: #d97706;
            transform: translateY(-2px);
        }

        .lp-btn-outline {
            padding: .85rem 2rem;
            background: transparent;
            color: #ffffff;
            font-weight: 700;
            font-size: 1rem;
            border-radius: 8px;
            border: 2px solid rgba(255, 255, 255, .5);
            text-decoration: none;
            transition: border-color .2s, background .2s;
            display: inline-block;
        }

        .lp-btn-outline:hover {
            border-color: #fff;
            background: rgba(255, 255, 255, .08);
        }

        /* ── Hero Illustration ── */
        .lp-hero-illus {
            display: flex;
            justify-content: flex-end;
            align-items: center;
        }

        /* ── Dashboard Card ── */
        .lp-dash-card {
            width: 100%;
            max-width: 420px;
            transform: translateX(1rem);
            background: rgba(255, 255, 255, .06);
            border: 1px solid rgba(255, 255, 255, .12);
            border-radius: 22px;
            padding: 2rem 2rem 1.75rem;
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            box-shadow:
                0 32px 80px rgba(0, 0, 0, .45),
                0 0 0 1px rgba(255, 255, 255, .06);
            position: relative;
            overflow: hidden;
        }

        /* warm glow top-right accent */
        .lp-dash-card::before {
            content: '';
            position: absolute;
            top: -60px;
            right: -60px;
            width: 240px;
            height: 240px;
            border-radius: 50%;
            background: rgba(245, 158, 11, .14);
            filter: blur(55px);
            pointer-events: none;
        }

        .lp-dash-header {
            margin-bottom: 1.5rem;
        }

        .lp-dash-header h2 {
            font-size: 1.1rem;
            font-weight: 800;
            color: #ffffff;
            margin: 0 0 .3rem;
            letter-spacing: -.2px;
        }

        .lp-dash-header p {
            font-size: .8rem;
            color: #93c5fd;
            margin: 0;
        }

        .lp-dash-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: .85rem;
            margin-bottom: 1.5rem;
        }

        .lp-dash-metric {
            background: rgba(255, 255, 255, .07);
            border: 1px solid rgba(255, 255, 255, .09);
            border-radius: 14px;
            padding: 1rem .9rem;
            position: relative;
            overflow: hidden;
        }

        .lp-dash-metric::after {
            content: '';
            position: absolute;
            bottom: -18px;
            right: -18px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: rgba(245, 158, 11, .08);
        }

        .lp-dash-num {
            display: block;
            font-size: 1.55rem;
            font-weight: 900;
            color: #f59e0b;
            line-height: 1;
            margin-bottom: .35rem;
            letter-spacing: -.5px;
        }

        .lp-dash-lbl {
            display: block;
            font-size: .72rem;
            color: #bfcfe8;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: .4px;
        }

        .lp-dash-progress {
            background: rgba(255, 255, 255, .05);
            border: 1px solid rgba(255, 255, 255, .09);
            border-radius: 12px;
            padding: .9rem 1rem;
        }

        .lp-dash-progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: .55rem;
        }

        .lp-dash-progress-label {
            font-size: .75rem;
            color: #93c5fd;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .4px;
        }

        .lp-dash-progress-pct {
            font-size: .78rem;
            color: #f59e0b;
            font-weight: 800;
        }

        .lp-dash-bar-track {
            height: 7px;
            background: rgba(255, 255, 255, .1);
            border-radius: 99px;
            overflow: hidden;
        }

        .lp-dash-bar-fill {
            height: 100%;
            width: 75%;
            background: linear-gradient(90deg, #f59e0b, #fbbf24);
            border-radius: 99px;
        }

        @media (max-width: 768px) {
            .lp-hero-illus {
                justify-content: center;
            }

            .lp-dash-card {
                transform: none;
                max-width: 100%;
            }
        }

        .lp-illus-card {
            background: rgba(255, 255, 255, .08);
            border: 1px solid rgba(255, 255, 255, .15);
            border-radius: 20px;
            padding: 2.5rem;
            width: 100%;
            max-width: 380px;
            backdrop-filter: blur(8px);
            position: relative;
            overflow: hidden;
        }

        .lp-illus-card::before {
            content: '';
            position: absolute;
            top: -60px;
            right: -60px;
            width: 200px;
            height: 200px;
            background: rgba(245, 158, 11, .15);
            border-radius: 50%;
        }

        .lp-illus-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #f59e0b, #fbbf24);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.25rem;
            font-size: 2.5rem;
        }

        .lp-illus-card h3 {
            color: #fff;
            font-size: 1.15rem;
            font-weight: 700;
            margin-bottom: .5rem;
        }

        .lp-illus-card p {
            color: #bfcfe8;
            font-size: .9rem;
            margin-bottom: 1.25rem;
        }

        .lp-illus-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: .75rem;
        }

        .lp-illus-stat {
            background: rgba(255, 255, 255, .07);
            border-radius: 10px;
            padding: .75rem;
            text-align: center;
        }

        .lp-illus-stat .num {
            display: block;
            color: #f59e0b;
            font-size: 1.2rem;
            font-weight: 800;
        }

        .lp-illus-stat .lbl {
            display: block;
            color: #93c5fd;
            font-size: .72rem;
            margin-top: .15rem;
        }

        .lp-illus-bar {
            margin-top: 1rem;
        }

        .lp-illus-bar-label {
            display: flex;
            justify-content: space-between;
            color: #93c5fd;
            font-size: .75rem;
            margin-bottom: .3rem;
        }

        .lp-illus-bar-track {
            height: 6px;
            background: rgba(255, 255, 255, .12);
            border-radius: 99px;
            overflow: hidden;
        }

        .lp-illus-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #f59e0b, #fbbf24);
            border-radius: 99px;
        }

        /* ── Metrics strip ── */
        .lp-metrics {
            background: #0a1f5e;
            padding: 2.5rem 1.5rem;
        }

        .lp-metrics-inner {
            max-width: 1000px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
            text-align: center;
        }

        @media (max-width: 600px) {
            .lp-metrics-inner {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
        }

        .lp-metric-num {
            font-size: 2.4rem;
            font-weight: 900;
            color: #f59e0b;
            line-height: 1;
            margin-bottom: .4rem;
        }

        .lp-metric-label {
            font-size: .85rem;
            color: #93c5fd;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        .lp-metric-label strong {
            color: #ffffff;
        }

        .lp-metrics-divider {
            border-right: 1px solid rgba(255, 255, 255, .1);
        }

        @media (max-width: 600px) {
            .lp-metrics-divider {
                border-right: none;
                border-bottom: 1px solid rgba(255, 255, 255, .1);
                padding-bottom: 1.5rem;
            }
        }

        /* ── Features ── */
        .lp-features {
            background: #f8fafc;
            padding: 5rem 1.5rem;
        }

        .lp-section-inner {
            max-width: 1100px;
            margin: 0 auto;
        }

        .lp-section-title {
            text-align: center;
            font-size: 1.7rem;
            font-weight: 800;
            color: #0f2b6e;
            margin-bottom: 3rem;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        .lp-features-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 1.5rem;
        }

        @media (max-width: 900px) {
            .lp-features-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 540px) {
            .lp-features-grid {
                grid-template-columns: 1fr;
            }
        }

        .lp-feat-card {
            background: #fff;
            border-radius: 14px;
            padding: 1.75rem 1.25rem;
            text-align: center;
            border: 1px solid #e2e8f0;
            transition: box-shadow .25s, transform .25s;
        }

        .lp-feat-card:hover {
            box-shadow: 0 8px 28px rgba(15, 43, 110, .1);
            transform: translateY(-3px);
        }

        .lp-feat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #0f2b6e;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.1rem;
            font-size: 1.5rem;
        }

        .lp-feat-card h3 {
            font-size: .92rem;
            font-weight: 700;
            color: #0f2b6e;
            margin-bottom: .5rem;
        }

        .lp-feat-card p {
            font-size: .8rem;
            color: #64748b;
            line-height: 1.55;
        }

        /* ── Plans ── */
        .lp-plans {
            background: #fff;
            padding: 5rem 1.5rem;
        }

        .lp-plans-title {
            text-align: center;
            font-size: 1.55rem;
            font-weight: 800;
            color: #0f2b6e;
            margin-bottom: .6rem;
        }

        .lp-plans-title span {
            color: #f59e0b;
        }

        .lp-plans-subtitle {
            text-align: center;
            color: #64748b;
            font-size: .9rem;
            margin-bottom: 1rem;
        }

        .lp-plans-toggle-wrap {
            display: flex;
            justify-content: center;
            margin-bottom: 2.5rem;
        }

        .lp-plans-grid {
            max-width: 960px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            align-items: start;
        }

        @media (max-width: 768px) {
            .lp-plans-grid {
                grid-template-columns: 1fr;
                max-width: 400px;
            }
        }

        /* Free card */
        .lp-plan-free {
            background: #fff;
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            padding: 2rem 1.5rem;
        }

        /* Básico card */
        .lp-plan-basic {
            background: #1a4db8;
            border-radius: 16px;
            padding: 2rem 1.5rem;
            position: relative;
            box-shadow: 0 12px 40px rgba(26, 77, 184, .3);
        }

        /* Plus card */
        .lp-plan-plus {
            background: #0a1f5e;
            border-radius: 16px;
            padding: 2rem 1.5rem;
            position: relative;
            box-shadow: 0 12px 40px rgba(10, 31, 94, .35);
        }

        .lp-plan-badge {
            display: inline-block;
            padding: .25rem .9rem;
            border-radius: 20px;
            font-size: .72rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .5px;
            margin-bottom: 1rem;
        }

        .lp-badge-popular {
            background: #f59e0b;
            color: #0f2b6e;
        }

        .lp-badge-value {
            background: rgba(255, 255, 255, .12);
            color: #f59e0b;
            border: 1px solid rgba(245, 158, 11, .3);
        }

        .lp-plan-name-free {
            font-size: 1.5rem;
            font-weight: 800;
            color: #0f2b6e;
            margin-bottom: .5rem;
        }

        .lp-plan-name-paid {
            font-size: 1.5rem;
            font-weight: 800;
            color: #fff;
            margin-bottom: .5rem;
        }

        .lp-plan-price-free {
            font-size: 2.4rem;
            font-weight: 900;
            color: #0f2b6e;
        }

        .lp-plan-price-paid {
            font-size: 2.4rem;
            font-weight: 900;
            color: #fff;
        }

        .lp-plan-price-unit {
            font-size: .85rem;
            font-weight: 500;
        }

        .lp-plan-price-unit-free {
            color: #64748b;
        }

        .lp-plan-price-unit-paid {
            color: rgba(255, 255, 255, .7);
        }

        .lp-plan-annual-note {
            font-size: .78rem;
            margin-top: .35rem;
            margin-bottom: 1.25rem;
        }

        .lp-plan-annual-note-free {
            color: #94a3b8;
        }

        .lp-plan-annual-note-paid {
            color: #93c5fd;
        }

        .lp-plan-list {
            list-style: none;
            margin: 0 0 1.5rem;
            padding: 0;
        }

        .lp-plan-list li {
            display: flex;
            align-items: center;
            gap: .5rem;
            font-size: .88rem;
            padding: .45rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, .06);
        }

        .lp-plan-list-free li {
            color: #374151;
            border-bottom-color: #f1f5f9;
        }

        .lp-plan-list-paid li {
            color: rgba(255, 255, 255, .9);
        }

        .lp-check-free {
            color: #22c55e;
            font-size: 1rem;
            flex-shrink: 0;
        }

        .lp-check-paid {
            color: #86efac;
            font-size: 1rem;
            flex-shrink: 0;
        }

        /* Plan buttons */
        .lp-plan-btn-free {
            display: block;
            width: 100%;
            padding: .8rem;
            background: #22c55e;
            color: #fff;
            font-weight: 800;
            font-size: .92rem;
            border-radius: 8px;
            text-align: center;
            text-decoration: none;
            transition: background .2s;
        }

        .lp-plan-btn-free:hover {
            background: #16a34a;
        }

        .lp-plan-btn-basic {
            display: block;
            width: 100%;
            padding: .8rem;
            background: #f59e0b;
            color: #0f2b6e;
            font-weight: 800;
            font-size: .92rem;
            border-radius: 8px;
            text-align: center;
            text-decoration: none;
            transition: background .2s;
        }

        .lp-plan-btn-basic:hover {
            background: #d97706;
        }

        .lp-plan-btn-plus {
            display: block;
            width: 100%;
            padding: .8rem;
            background: #f59e0b;
            color: #0f2b6e;
            font-weight: 800;
            font-size: .92rem;
            border-radius: 8px;
            text-align: center;
            text-decoration: none;
            transition: background .2s;
        }

        .lp-plan-btn-plus:hover {
            background: #d97706;
        }

        .lp-plan-btn-annual {
            display: block;
            width: 100%;
            margin-top: .65rem;
            padding: .55rem;
            background: transparent;
            border: 1px solid rgba(255, 255, 255, .25);
            color: rgba(255, 255, 255, .75);
            font-weight: 600;
            font-size: .78rem;
            border-radius: 7px;
            text-align: center;
            text-decoration: none;
            transition: border-color .2s, background .2s;
        }

        .lp-plan-btn-annual:hover {
            border-color: rgba(255, 255, 255, .6);
            background: rgba(255, 255, 255, .07);
            color: #fff;
        }

        /* ── Testimonials ── */
        .lp-testimonials {
            background: #0a1f5e;
            padding: 5rem 1.5rem;
        }

        .lp-test-title {
            text-align: center;
            font-size: 2rem;
            font-weight: 800;
            color: #fff;
            margin-bottom: .5rem;
        }

        .lp-test-subtitle {
            text-align: center;
            color: #93c5fd;
            font-size: 1rem;
            margin-bottom: 3rem;
        }

        .lp-test-subtitle strong {
            color: #fff;
        }

        .lp-test-grid {
            max-width: 1100px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.25rem;
        }

        @media (max-width: 900px) {
            .lp-test-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 540px) {
            .lp-test-grid {
                grid-template-columns: 1fr;
            }
        }

        .lp-test-card {
            background: rgba(255, 255, 255, .06);
            border: 1px solid rgba(255, 255, 255, .1);
            border-radius: 14px;
            padding: 1.5rem;
            transition: background .25s;
        }

        .lp-test-card:hover {
            background: rgba(255, 255, 255, .1);
        }

        .lp-test-avatar {
            display: flex;
            align-items: center;
            gap: .75rem;
            margin-bottom: 1rem;
        }

        .lp-test-avatar img {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(255, 255, 255, .2);
        }

        .lp-test-name {
            font-weight: 700;
            color: #fff;
            font-size: .92rem;
        }

        .lp-test-tag {
            color: #93c5fd;
            font-size: .75rem;
        }

        .lp-test-stars {
            color: #f59e0b;
            font-size: .85rem;
            margin-top: .15rem;
        }

        .lp-test-quote {
            color: rgba(255, 255, 255, .8);
            font-size: .875rem;
            line-height: 1.65;
            font-style: italic;
        }

        /* ── Vantagem Competitiva ── */
        .lp-vantagem {
            background: linear-gradient(to bottom, #ffffff, #f8fafc);
            padding: 5rem 1.5rem;
        }

        .lp-vantagem-inner {
            max-width: 1100px;
            margin: 0 auto;
        }

        .lp-vantagem-title {
            text-align: center;
            font-size: clamp(1.45rem, 3vw, 2rem);
            font-weight: 900;
            color: #0f2b6e;
            line-height: 1.25;
            margin-bottom: .6rem;
        }

        .lp-vantagem-sub {
            text-align: center;
            font-size: .95rem;
            color: #64748b;
            margin-bottom: 3rem;
            font-style: italic;
        }

        .lp-vantagem-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
        }

        @media (max-width: 900px) {
            .lp-vantagem-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 540px) {
            .lp-vantagem-grid {
                grid-template-columns: 1fr;
            }
        }

        .lp-vantagem-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 2rem 1.75rem;
            box-shadow: 0 2px 12px rgba(15, 43, 110, .07);
            transition: box-shadow .3s ease, transform .3s ease;
        }

        .lp-vantagem-card:hover {
            box-shadow: 0 12px 40px rgba(15, 43, 110, .15);
            transform: translateY(-4px);
        }

        /* Ícones refinados da seção de vantagem competitiva */
        .lp-vantagem-icon {
            width: 60px;
            height: 60px;
            border-radius: 999px;
            background: radial-gradient(circle at 30% 20%, #3b82f6, #0f2b6e);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.25rem;
            font-size: 1.3rem;
            flex-shrink: 0;
            color: #ffffff;
            box-shadow:
                0 10px 25px rgba(15, 43, 110, .35),
                0 0 0 1px rgba(255, 255, 255, .15);
            transition: transform .3s ease, box-shadow .3s ease, background .3s ease;
        }

        .lp-vantagem-card:hover .lp-vantagem-icon {
            transform: translateY(-2px) scale(1.05);
            box-shadow:
                0 18px 45px rgba(15, 43, 110, .45),
                0 0 0 1px rgba(255, 255, 255, .25);
            background: radial-gradient(circle at 10% 0%, #60a5fa, #0f2b6e);
        }

        .lp-vantagem-card h3 {
            font-size: 1rem;
            font-weight: 700;
            color: #0f2b6e;
            margin-bottom: .6rem;
            line-height: 1.35;
        }

        .lp-vantagem-card p {
            font-size: .855rem;
            color: #64748b;
            line-height: 1.65;
            margin: 0;
        }

        /* ── FAQ ── */
        .lp-faq {
            background: #f8fafc;
            padding: 5rem 1.5rem;
        }

        .lp-faq-inner {
            max-width: 850px;
            margin: 0 auto;
        }

        .lp-faq-title {
            text-align: center;
            font-size: 1.7rem;
            font-weight: 800;
            color: #0f2b6e;
            margin-bottom: .5rem;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        .lp-faq-subtitle {
            text-align: center;
            color: #64748b;
            font-size: .95rem;
            margin-bottom: 2.75rem;
        }

        .lp-faq-item {
            border-bottom: 1px solid #e2e8f0;
        }

        .lp-faq-item:first-of-type {
            border-top: 1px solid #e2e8f0;
        }

        .lp-faq-btn {
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            padding: 1.25rem 0;
            background: none;
            border: none;
            cursor: pointer;
            text-align: left;
        }

        .lp-faq-question {
            font-size: 1rem;
            font-weight: 700;
            color: #0f2b6e;
            line-height: 1.45;
        }

        .lp-faq-icon {
            flex-shrink: 0;
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: #0f2b6e;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: .95rem;
            font-weight: 700;
            transition: background .2s, transform .3s;
            line-height: 1;
        }

        .lp-faq-icon.open {
            background: #f59e0b;
            transform: rotate(45deg);
        }

        .lp-faq-answer {
            overflow: hidden;
            transition: max-height .35s ease, opacity .3s ease;
            max-height: 0;
            opacity: 0;
        }

        .lp-faq-answer.open {
            max-height: 200px;
            opacity: 1;
        }

        .lp-faq-answer p {
            padding: 0 0 1.25rem;
            color: #475569;
            font-size: .9rem;
            line-height: 1.7;
            margin: 0;
        }

        /* ── Footer ── */
        .lp-footer {
            background: #060f2e;
            padding: 4rem 1.5rem 2rem;
        }

        .lp-footer-inner {
            max-width: 1100px;
            margin: 0 auto;
        }

        .lp-footer-top {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 3rem;
            margin-bottom: 3rem;
        }

        @media (max-width: 700px) {
            .lp-footer-top {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
        }

        .lp-footer-logo {
            font-size: 1.5rem;
            font-weight: 800;
            color: #fff;
            margin-bottom: .75rem;
            display: block;
        }

        .lp-footer-logo span {
            color: #f59e0b;
        }

        .lp-footer-desc {
            color: #64748b;
            font-size: .85rem;
            line-height: 1.65;
            max-width: 300px;
        }

        .lp-footer-col h4 {
            color: #94a3b8;
            font-size: .75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: .85rem;
        }

        .lp-footer-col ul {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .lp-footer-col ul li {
            margin-bottom: .55rem;
        }

        .lp-footer-col ul li a {
            color: #94a3b8;
            text-decoration: none;
            font-size: .85rem;
            transition: color .2s;
        }

        .lp-footer-col ul li a:hover {
            color: #fff;
        }

        .lp-footer-border {
            border-top: 1px solid rgba(255, 255, 255, .06);
            padding-top: 1.75rem;
            text-align: center;
            color: #4b5563;
            font-size: .8rem;
        }
    </style>
</head>

<body style="margin:0;padding:0;background:#fff;">

    {{-- NAVBAR --}}
    <nav class="lp-nav" id="top">
        <div class="lp-nav-inner">
            <a href="{{ route('home') }}" class="lp-logo">Aprender<span>AI</span></a>

            <ul class="lp-nav-links">
                <li><a href="#depoimentos">Depoimentos</a></li>
                <li><a href="{{ route('fair-use') }}">Política de Uso</a></li>
                <li><a href="{{ route('privacy') }}">Política de Privacidade</a></li>
                <li><a href="#plans" class="lp-btn-cta">Assine Agora</a></li>
            </ul>
        </div>
    </nav>

    {{-- HERO --}}
    <section class="lp-hero">
        <div class="lp-hero-inner">
            <div>
                <h1 class="lp-hero-title">
                    Estude com IA.<br>
                    Passe na <span>Frente!</span>
                </h1>
                <p class="lp-hero-subtitle">
                    A preparação inteligente para o ENEM e Concursos Públicos.
                    Comece hoje e conquiste a sua aprovação.
                </p>
                <div class="lp-hero-btns">
                    <a href="{{ route('register', ['plan' => 'free']) }}" class="lp-btn-primary">
                        Comece Gratuitamente
                    </a>
                    <a href="{{ route('login') }}" class="lp-btn-outline">
                        Já tenho conta
                    </a>
                </div>
            </div>

            <div class="lp-hero-illus">
                <div class="lp-dash-card">
                    <div class="lp-dash-header">
                        <h2>Painel Inteligente</h2>
                        <p>Sua evolução acompanhada com estratégia.</p>
                    </div>

                    <div class="lp-dash-grid">
                        <div class="lp-dash-metric">
                            <span class="lp-dash-num">35%</span>
                            <span class="lp-dash-lbl">Evolução média</span>
                        </div>
                        <div class="lp-dash-metric">
                            <span class="lp-dash-num">1.200+</span>
                            <span class="lp-dash-lbl">Redações analisadas</span>
                        </div>
                        <div class="lp-dash-metric">
                            <span class="lp-dash-num">100 MIL</span>
                            <span class="lp-dash-lbl">Questões resolvidas</span>
                        </div>
                        <div class="lp-dash-metric">
                            <span class="lp-dash-num">24/7</span>
                            <span class="lp-dash-lbl">Correção automática</span>
                        </div>
                    </div>

                    <div class="lp-dash-progress">
                        <div class="lp-dash-progress-header">
                            <span class="lp-dash-progress-label">Progresso do Plano</span>
                            <span class="lp-dash-progress-pct">75%</span>
                        </div>
                        <div class="lp-dash-bar-track">
                            <div class="lp-dash-bar-fill"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- METRICS --}}
    <section class="lp-metrics">
        <div class="lp-metrics-inner">
            <div class="lp-metrics-divider">
                <div class="lp-metric-num">35%</div>
                <div class="lp-metric-label">MAIS ACERTOS EM <strong>30 DIAS</strong></div>
            </div>
            <div class="lp-metrics-divider">
                <div class="lp-metric-num">1.200+</div>
                <div class="lp-metric-label">REDAÇÕES <strong>NOTA 900+</strong></div>
            </div>
            <div>
                <div class="lp-metric-num">100 MIL</div>
                <div class="lp-metric-label"><strong>ALUNOS IMPACTADOS</strong></div>
            </div>
        </div>
    </section>

    {{-- FEATURES --}}
    <section class="lp-features" id="features">
        <div class="lp-section-inner">
            <h2 class="lp-section-title">A melhor plataforma de simulados e estudos</h2>
            <div class="lp-features-grid">
                <div class="lp-feat-card">
                    <div class="lp-feat-icon">💬</div>
                    <h3>Questões Comentadas</h3>
                    <p>Banco de questões atualizado com explicações para acelerar sua aprendizagem.</p>
                </div>
                <div class="lp-feat-card">
                    <div class="lp-feat-icon">🤖</div>
                    <h3>Correção Inteligente por IA</h3>
                    <p>Correção automática e análises detalhadas para você entender cada erro.</p>
                </div>
                <div class="lp-feat-card">
                    <div class="lp-feat-icon">✍️</div>
                    <h3>Redação Nota 1000</h3>
                    <p>Envie suas redações e receba feedback por competência em poucos segundos.</p>
                </div>
                <div class="lp-feat-card">
                    <div class="lp-feat-icon">📅</div>
                    <h3>Plano de Estudos Personalizado</h3>
                    <p>Monte seu plano de acordo com seu objetivo, tempo e desempenho.</p>
                </div>
                <div class="lp-feat-card">
                    <div class="lp-feat-icon">📊</div>
                    <h3>Desempenho em Tempo Real</h3>
                    <p>Acompanhe sua evolução em gráficos e métricas por disciplina e prova.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- PLANS --}}
    @php
        $freePlan = $plans->where('slug', 'free')->first();
        $basicPlan = $plans->where('slug', 'basic')->first();
        $plusPlan = $plans->where('slug', 'plus')->first();
        $basicAnual = $plans->where('slug', 'basic-annual')->first();
        $plusAnual = $plans->where('slug', 'plus-annual')->first();

        $basicSaving = ($basicPlan && $basicAnual) ? ($basicPlan->price * 12) - $basicAnual->price : 0;
        $plusSaving = ($plusPlan && $plusAnual) ? ($plusPlan->price * 12) - $plusAnual->price : 0;
    @endphp

    <section class="lp-plans" id="plans" x-data="{
            periodo: 'mensal',
            basic: {
                monthly: '{{ number_format($basicPlan->price, 2, ',', '.') }}',
                annual_monthly: '{{ number_format($basicAnual->price / 12, 2, ',', '.') }}',
                total_annual: '{{ number_format($basicAnual->price, 2, ',', '.') }}',
                saving: '{{ number_format($basicSaving, 2, ',', '.') }}',
                discount: {{ $basicAnual->discount_percentage }}
            },
            plus: {
                monthly: '{{ number_format($plusPlan->price, 2, ',', '.') }}',
                annual_monthly: '{{ number_format($plusAnual->price / 12, 2, ',', '.') }}',
                total_annual: '{{ number_format($plusAnual->price, 2, ',', '.') }}',
                saving: '{{ number_format($plusSaving, 2, ',', '.') }}',
                discount: {{ $plusAnual->discount_percentage }}
            }
        }">
        <div style="max-width:980px;margin:0 auto;">
            <h2 class="lp-plans-title">
                Escolha seu plano e dê o primeiro passo<br>
                <span>para a aprovação</span>
            </h2>
            <p class="lp-plans-subtitle">Planos mensais e opção de plano anual com <strong>20% de desconto</strong>.</p>

            <div class="lp-plans-toggle-wrap">
                <div
                    style="display:flex;align-items:center;gap:1rem;background:#f1f5f9;border-radius:50px;padding:.35rem .75rem;">
                    <span style="font-size:.85rem;font-weight:600;cursor:pointer;transition:color .2s;"
                        :style="periodo==='mensal' ? 'color:#0f2b6e' : 'color:#94a3b8'"
                        @click="periodo='mensal'">Mensal</span>

                    <button type="button" @click="periodo = (periodo === 'mensal' ? 'anual' : 'mensal')"
                        class="relative inline-flex h-6 w-12 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-300 ease-in-out focus:outline-none"
                        :class="periodo === 'anual' ? 'bg-blue-700' : 'bg-slate-300'" role="switch"
                        :aria-checked="periodo === 'anual' ? 'true' : 'false'">
                        <span
                            class="pointer-events-none inline-block h-4 w-4 mt-px ml-px transform rounded-full bg-white shadow ring-0 transition-transform duration-300"
                            :class="periodo === 'anual' ? 'translate-x-5' : 'translate-x-0'"></span>
                    </button>

                    <span
                        style="font-size:.85rem;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:.4rem;transition:color .2s;"
                        :style="periodo==='anual' ? 'color:#0f2b6e' : 'color:#94a3b8'" @click="periodo='anual'">
                        Anual
                        <span
                            style="background:#dcfce7;color:#15803d;font-size:.7rem;font-weight:800;padding:.15rem .5rem;border-radius:99px;">-20%
                            OFF</span>
                    </span>
                </div>
            </div>

            <div class="lp-plans-grid">

                <div class="lp-plan-free">
                    <div class="lp-plan-name-free">Gratuito</div>
                    <div style="margin-bottom:.25rem;">
                        <span class="lp-plan-price-free">R$ 0</span>
                        <span class="lp-plan-price-unit lp-plan-price-unit-free">/mês</span>
                    </div>
                    <div class="lp-plan-annual-note lp-plan-annual-note-free" style="margin-bottom:1.25rem;">
                        Sempre gratuito, sem cartão de crédito.
                    </div>
                    <ul class="lp-plan-list lp-plan-list-free">
                        <li><span class="lp-check-free">✓</span> 5 provas/mês</li>
                        <li><span class="lp-check-free">✓</span> Correção básica</li>
                        <li><span class="lp-check-free">✓</span> Estatísticas simples</li>
                    </ul>
                    <a href="{{ route('register', ['plan' => 'free']) }}" class="lp-plan-btn-free">
                        Começar Agora
                    </a>
                </div>

                <div class="lp-plan-basic">
                    <div class="lp-plan-badge lp-badge-popular">⭐ Mais Popular</div>
                    <div class="lp-plan-name-paid">Básico</div>
                    <div style="margin-bottom:.25rem;">
                        <span class="lp-plan-price-paid">R$&nbsp;<span
                                x-text="periodo === 'anual' ? basic.annual_monthly : basic.monthly">{{ number_format($basicPlan->price, 2, ',', '.') }}</span></span>
                        <span class="lp-plan-price-unit lp-plan-price-unit-paid">/mês</span>
                    </div>
                    <template x-if="periodo === 'anual'">
                        <div class="lp-plan-annual-note lp-plan-annual-note-paid">
                            Ou R$ <span x-text="basic.total_annual"></span>/ano — economize R$ <span
                                x-text="basic.saving"></span>
                        </div>
                    </template>
                    <template x-if="periodo === 'mensal'">
                        <div class="lp-plan-annual-note lp-plan-annual-note-paid">
                            Ou R$ {{ number_format($basicAnual->price, 2, ',', '.') }} no plano anual (20% OFF)
                        </div>
                    </template>
                    <ul class="lp-plan-list lp-plan-list-paid">
                        <li><span class="lp-check-paid">✓</span> 10 provas/mês</li>
                        <li><span class="lp-check-paid">✓</span> Correção detalhada</li>
                        <li><span class="lp-check-paid">✓</span> 2 redações/mês</li>
                        <li><span class="lp-check-paid">✓</span> Estatísticas completas</li>
                        <li><span class="lp-check-paid">✓</span> Radar de concursos</li>
                    </ul>
                    <a :href="'{{ route('register') }}?plan=' + (periodo === 'anual' ? 'basic-annual' : 'basic')"
                        class="lp-plan-btn-basic">
                        Assinar Agora
                    </a>
                    <a :href="'{{ route('register') }}?plan=basic-annual'" class="lp-plan-btn-annual">
                        Assinar Plano Anual (20% OFF)
                    </a>
                </div>

                <div class="lp-plan-plus">
                    <div class="lp-plan-badge lp-badge-value">🏆 Melhor Valor</div>
                    <div class="lp-plan-name-paid">Plus</div>
                    <div style="margin-bottom:.25rem;">
                        <span class="lp-plan-price-paid">R$&nbsp;<span
                                x-text="periodo === 'anual' ? plus.annual_monthly : plus.monthly">{{ number_format($plusPlan->price, 2, ',', '.') }}</span></span>
                        <span class="lp-plan-price-unit lp-plan-price-unit-paid">/mês</span>
                    </div>
                    <template x-if="periodo === 'anual'">
                        <div class="lp-plan-annual-note lp-plan-annual-note-paid">
                            Ou R$ <span x-text="plus.total_annual"></span>/ano — economize R$ <span
                                x-text="plus.saving"></span>
                        </div>
                    </template>
                    <template x-if="periodo === 'mensal'">
                        <div class="lp-plan-annual-note lp-plan-annual-note-paid">
                            Ou R$ {{ number_format($plusAnual->price, 2, ',', '.') }} no plano anual (20% OFF)
                        </div>
                    </template>
                    <ul class="lp-plan-list lp-plan-list-paid">
                        <li><span class="lp-check-paid">✓</span> <strong style="color:#fff;">Simulados
                                ilimitados</strong></li>
                        <li><span class="lp-check-paid">✓</span> <strong style="color:#fff;">15 redações/mês</strong>
                        </li>
                        <li><span class="lp-check-paid">✓</span> Plano personalizado</li>
                        <li><span class="lp-check-paid">✓</span> Análise estratégica</li>
                        <li><span class="lp-check-paid">✓</span> Radar de concursos</li>
                    </ul>
                    <a :href="'{{ route('register') }}?plan=' + (periodo === 'anual' ? 'plus-annual' : 'plus')"
                        class="lp-plan-btn-plus">
                        Assinar Agora
                    </a>
                    <a :href="'{{ route('register') }}?plan=plus-annual'" class="lp-plan-btn-annual">
                        Assinar Plano Anual (20% OFF)
                    </a>
                </div>

            </div>
        </div>
    </section>

    {{-- VANTAGEM COMPETITIVA --}}
    <section class="lp-vantagem">
        <div class="lp-vantagem-inner">
            <h2 class="lp-vantagem-title">Mais do que estudar. &Eacute; criar vantagem competitiva.</h2>
            <p class="lp-vantagem-sub">Quem estuda com m&eacute;todo evolui. Quem estuda com estrat&eacute;gia passa.
            </p>

            <div class="lp-vantagem-grid">

                <div class="lp-vantagem-card">
                    <div class="lp-vantagem-icon">&#9881;</div>
                    <h3>Clareza Estrat&eacute;gica</h3>
                    <p>N&atilde;o &eacute; sobre estudar mais. &Eacute; sobre estudar certo. Descubra exatamente onde
                        voc&ecirc; perde pontos e transforme erros em progresso real.</p>
                </div>

                <div class="lp-vantagem-card">
                    <div class="lp-vantagem-icon">&#9654;</div>
                    <h3>Seguran&ccedil;a no Dia da Prova</h3>
                    <p>Simule sob press&atilde;o, cronometre seu desempenho e chegue no dia decisivo com
                        confian&ccedil;a constru&iacute;da na pr&aacute;tica.</p>
                </div>

                <div class="lp-vantagem-card">
                    <div class="lp-vantagem-icon">&#9650;</div>
                    <h3>Evolu&ccedil;&atilde;o Baseada em Dados</h3>
                    <p>Nada de achismo. Acompanhe m&eacute;tricas claras, hist&oacute;rico de desempenho e crescimento
                        cont&iacute;nuo em cada disciplina.</p>
                </div>

                <div class="lp-vantagem-card">
                    <div class="lp-vantagem-icon">&#10024;</div>
                    <h3>Intelig&ecirc;ncia que Trabalha por Voc&ecirc;</h3>
                    <p>A IA analisa seus padr&otilde;es, identifica fragilidades e ajusta sua prepara&ccedil;&atilde;o
                        automaticamente.</p>
                </div>

                <div class="lp-vantagem-card">
                    <div class="lp-vantagem-icon">&#9679;</div>
                    <h3>Alto Retorno Sobre o Seu Tempo</h3>
                    <p>Cada hora de estudo passa a ter dire&ccedil;&atilde;o. Menos desperd&iacute;cio. Mais resultado.
                    </p>
                </div>

                <div class="lp-vantagem-card">
                    <div class="lp-vantagem-icon">&#9788;</div>
                    <h3>Acesso Real, Sem Barreiras</h3>
                    <p>Prepara&ccedil;&atilde;o estruturada, acess&iacute;vel e dispon&iacute;vel 24/7 &mdash; para quem
                        decide levar a aprova&ccedil;&atilde;o a s&eacute;rio.</p>
                </div>

            </div>
        </div>
    </section>

    {{-- TESTIMONIALS --}}
    <section class="lp-testimonials" id="depoimentos">
        <div>
            <h2 class="lp-test-title">Histórias de Sucesso</h2>
            <p class="lp-test-subtitle">Quem estudou com a gente, passou <strong>de verdade.</strong></p>
            <div class="lp-test-grid">
                <div class="lp-test-card">
                    <div class="lp-test-avatar">
                        <img src="https://i.pravatar.cc/96?img=12" alt="Lucas Andrade">
                        <div>
                            <div class="lp-test-name">Lucas Andrade</div>
                            <div class="lp-test-tag">ENEM</div>
                            <div class="lp-test-stars">★★★★★</div>
                        </div>
                    </div>
                    <p class="lp-test-quote">"Eu sempre estudava muito, mas não sabia exatamente onde estava errando.
                        Quando comecei a usar as análises da plataforma, consegui organizar melhor minha revisão e minha
                        nota subiu de forma consistente."</p>
                </div>
                <div class="lp-test-card">
                    <div class="lp-test-avatar">
                        <img src="https://i.pravatar.cc/96?img=32" alt="Mary S.">
                        <div>
                            <div class="lp-test-name">Mary S.</div>
                            <div class="lp-test-tag">Concurso Administrativo</div>
                            <div class="lp-test-stars">★★★★★</div>
                        </div>
                    </div>
                    <p class="lp-test-quote">"O que mais me ajudou foi conseguir visualizar meu desempenho por
                        disciplina. Antes eu estudava no escuro, agora sei exatamente onde preciso melhorar."</p>
                </div>
                <div class="lp-test-card">
                    <div class="lp-test-avatar">
                        <img src="https://i.pravatar.cc/96?img=45" alt="Feeh Costa">
                        <div>
                            <div class="lp-test-name">Feeh Costa</div>
                            <div class="lp-test-tag">Redação</div>
                            <div class="lp-test-stars">★★★★★</div>
                        </div>
                    </div>
                    <p class="lp-test-quote">"Eu travava muito na redação. Depois que comecei a receber o feedback por
                        competência, consegui entender meus erros estruturais e evoluir muito mais rápido."</p>
                </div>
                <div class="lp-test-card">
                    <div class="lp-test-avatar">
                        <img src="https://i.pravatar.cc/96?img=8" alt="Rafael Mendes">
                        <div>
                            <div class="lp-test-name">Rafael Mendes</div>
                            <div class="lp-test-tag">Polícia Militar</div>
                            <div class="lp-test-stars">★★★★★</div>
                        </div>
                    </div>
                    <p class="lp-test-quote">"O cronômetro e os simulados completos mudaram minha preparação. Hoje
                        consigo administrar o tempo muito melhor na hora da prova."</p>
                </div>
            </div>
        </div>
    </section>

    {{-- FAQ --}}
    <section class="lp-faq" id="faq">
        <div class="lp-faq-inner">
            <h2 class="lp-faq-title">Perguntas Frequentes</h2>
            <p class="lp-faq-subtitle">Tire suas dúvidas sobre a plataforma AprenderAI.</p>

            @php
                $faqs = [
                    [
                        'q' => 'A plataforma é totalmente online?',
                        'a' => 'Sim. O AprenderAI funciona 100% online. Você pode acessar de qualquer lugar, pelo computador ou celular, sem necessidade de instalação.',
                    ],
                    [
                        'q' => 'O AprenderAI serve para ENEM e concursos?',
                        'a' => 'Sim. A plataforma foi desenvolvida tanto para preparação para o ENEM quanto para concursos públicos, com simulados, questões e plano de estudos personalizados.',
                    ],
                    [
                        'q' => 'Como funciona a correção por IA?',
                        'a' => 'Nossa inteligência analisa suas respostas e redações, identifica padrões de erro e fornece explicações detalhadas para acelerar sua evolução.',
                    ],
                    [
                        'q' => 'Posso testar gratuitamente antes de assinar?',
                        'a' => 'Sim. O plano gratuito permite que você conheça a plataforma e resolva provas antes de optar por um plano pago.',
                    ],
                    [
                        'q' => 'Os simulados seguem o padrão oficial das provas?',
                        'a' => 'Sim. Os simulados são estruturados para replicar o formato real do ENEM e de concursos, incluindo controle de tempo.',
                    ],
                    [
                        'q' => 'Como funciona o plano anual com desconto?',
                        'a' => 'Ao optar pelo plano anual, você recebe 20% de desconto em relação ao valor mensal, mantendo todos os benefícios do plano escolhido.',
                    ],
                    [
                        'q' => 'A plataforma acompanha meu desempenho?',
                        'a' => 'Sim. Você pode acompanhar sua evolução por disciplina, identificar pontos fracos e visualizar seu progresso ao longo do tempo.',
                    ],
                    [
                        'q' => 'Posso cancelar quando quiser?',
                        'a' => 'Sim. Você pode gerenciar sua assinatura conforme as regras do plano contratado.',
                    ],
                ];
            @endphp

            @foreach ($faqs as $item)
                <div class="lp-faq-item" x-data="{ open: false }">
                    <button class="lp-faq-btn" @click="open = !open" type="button">
                        <span class="lp-faq-question">{{ $item['q'] }}</span>
                        <span class="lp-faq-icon" :class="{ 'open': open }">+</span>
                    </button>
                    <div class="lp-faq-answer" :class="{ 'open': open }" x-show="open"
                        x-transition:enter="transition-all ease-out duration-300" x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100" style="display:none;">
                        <p>{{ $item['a'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- FOOTER --}}
    <footer class="lp-footer">
        <div class="lp-footer-inner">
            <div class="lp-footer-top">
                <div>
                    <span class="lp-footer-logo">Aprender<span>AI</span></span>
                    <p class="lp-footer-desc">A plataforma que usa tecnologia para democratizar o acesso à aprovação.
                        Experiência premium focada em performance.</p>
                </div>
                <div class="lp-footer-col">
                    <h4>Produto</h4>
                    <ul>
                        <li><a href="#features">Recursos</a></li>
                        <li><a href="#plans">Planos</a></li>
                        <li><a href="#depoimentos">Depoimentos</a></li>
                    </ul>
                </div>
                <div class="lp-footer-col">
                    <h4>Uso Legal</h4>
                    <ul>
                        <li><a href="{{ route('privacy') }}">Política de Privacidade</a></li>
                        <li><a href="{{ route('fair-use') }}">Política de Uso Justo</a></li>
                        <li><a href="{{ route('fair-use') }}">Política de Uso</a></li>
                    </ul>
                </div>
            </div>
            <div class="lp-footer-border">
                &copy; {{ date('Y') }} aprenderAI. Todos os direitos reservados.
            </div>
        </div>
    </footer>

</body>

</html>