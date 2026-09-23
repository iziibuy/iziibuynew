<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light">
    <title>{{ __('Pay securely') }} · {{ $companyName }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="{{ $hostedFieldsScriptUrl }}"></script>
    <style>
        :root {
            --ink: #14211c;
            --muted: #5d6b64;
            --line: #e3e6e1;
            --paper: {{ $checkoutTheme->background() }};
            --card: #ffffff;
            --forest: {{ $checkoutTheme->primary() }};
            --forest-deep: {{ $checkoutTheme->primaryDeep() }};
            --forest-soft: {{ $checkoutTheme->primarySoft() }};
            --on-primary: {{ $checkoutTheme->onPrimary() }};
            --mint: #e7f3ec;
            --gold: {{ $checkoutTheme->secondary() }};
            --ok: #1c6b45;
            --danger: #9f2d2d;
            --danger-bg: #fdf2f2;
            --info-bg: #eef6f2;
            --shadow: 0 24px 70px rgba(20, 33, 28, 0.12);
        }

        * { box-sizing: border-box; }

        html, body { margin: 0; }

        body {
            min-height: 100vh;
            font-family: Inter, ui-sans-serif, system-ui, sans-serif;
            color: var(--ink);
            background:
                radial-gradient(900px 420px at 100% -10%, color-mix(in srgb, var(--gold) 28%, transparent), transparent 55%),
                radial-gradient(800px 480px at -10% 110%, rgba(20, 53, 44, 0.08), transparent 50%),
                var(--paper);
            -webkit-font-smoothing: antialiased;
        }

        .shell {
            min-height: 100vh;
            display: grid;
            align-content: center;
            justify-items: center;
            gap: 1rem;
            padding: 2.5rem 1.25rem 2.75rem;
        }

        .checkout {
            width: min(1040px, 100%);
            min-height: 640px;
            display: grid;
            grid-template-columns: minmax(280px, 380px) minmax(0, 1fr);
            background: var(--card);
            border-radius: 28px;
            overflow: hidden;
            box-shadow: var(--shadow);
            border: 1px solid rgba(20, 33, 28, 0.06);
        }

        .summary {
            position: relative;
            display: flex;
            flex-direction: column;
            gap: 1.75rem;
            padding: 2rem 1.75rem 1.5rem;
            color: var(--on-primary);
            background:
                radial-gradient(420px 220px at 0% 0%, color-mix(in srgb, var(--gold) 28%, transparent), transparent 62%),
                linear-gradient(180deg, var(--forest) 0%, var(--forest-deep) 100%);
        }

        .summary-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .secure-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            margin: 0;
            padding: 0.35rem 0.65rem 0.35rem 0.45rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.12);
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .secure-pill svg { flex: none; }

        .merchant {
            display: flex;
            align-items: center;
            gap: 0.9rem;
        }

        .avatar {
            position: relative;
            display: grid;
            place-items: center;
            width: 52px;
            height: 52px;
            flex: none;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.16);
            overflow: hidden;
            font-size: 1.15rem;
            font-weight: 600;
        }

        .avatar img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .merchant-copy { min-width: 0; }

        .merchant-kicker {
            margin: 0 0 0.15rem;
            color: color-mix(in srgb, var(--on-primary) 68%, transparent);
            font-size: 0.75rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .merchant h1 {
            margin: 0;
            font-size: 1.35rem;
            font-weight: 700;
            line-height: 1.15;
            letter-spacing: -0.02em;
            overflow-wrap: anywhere;
        }

        .amount-block { display: grid; gap: 0.35rem; }

        .amount-kicker {
            margin: 0;
            color: color-mix(in srgb, var(--on-primary) 68%, transparent);
            font-size: 0.78rem;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .amount {
            display: flex;
            align-items: baseline;
            flex-wrap: wrap;
            gap: 0.55rem;
            margin: 0;
        }

        .amount strong {
            font-size: clamp(2.15rem, 3.6vw, 2.75rem);
            font-weight: 700;
            letter-spacing: -0.035em;
            font-variant-numeric: tabular-nums;
            line-height: 0.95;
        }

        .amount span {
            color: color-mix(in srgb, var(--on-primary) 78%, transparent);
            font-size: 1rem;
            font-weight: 600;
            letter-spacing: 0.04em;
        }

        .facts {
            display: grid;
            gap: 0.95rem;
            margin: 0;
        }

        .fact {
            display: grid;
            gap: 0.15rem;
            padding-top: 0.95rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .fact dt {
            margin: 0;
            color: color-mix(in srgb, var(--on-primary) 62%, transparent);
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .fact dd {
            margin: 0;
            font-size: 0.98rem;
            font-weight: 600;
            line-height: 1.4;
            overflow-wrap: anywhere;
        }

        .summary-foot {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            margin-top: auto;
            padding-top: 1.25rem;
            color: color-mix(in srgb, var(--on-primary) 72%, transparent);
            font-size: 0.82rem;
        }

        .summary-foot svg { flex: none; color: var(--gold); }

        .pay {
            display: flex;
            flex-direction: column;
            padding: 2rem 2rem 1.5rem;
        }

        .pay-head { margin-bottom: 1.35rem; }

        .pay-head h2 {
            margin: 0 0 0.35rem;
            font-size: 1.35rem;
            letter-spacing: -0.02em;
        }

        .lead {
            margin: 0;
            max-width: 42rem;
            color: var(--muted);
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .brands {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
            margin-top: 0.9rem;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 28px;
            border: 1px solid var(--line);
            border-radius: 6px;
            background: #fff;
            overflow: hidden;
        }

        .brand svg { display: block; width: auto; }

        .brand-visa { border: 0; }

        .brand-visa svg { height: 28px; border-radius: 6px; }

        .brand-mastercard { padding: 0 0.45rem; }

        .brand-mastercard svg { height: 18px; }

        .field { display: grid; gap: 0.4rem; }

        .field label {
            font-size: 0.8rem;
            font-weight: 700;
            color: #3c4a44;
        }

        .field-shell {
            min-height: 52px;
            border: 1.5px solid #c5cec8;
            border-radius: 12px;
            background: #fff;
            overflow: hidden;
            transition: border-color 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
        }

        .field-shell.is-focused {
            border-color: var(--forest-soft);
            background: #fff;
            box-shadow: 0 0 0 4px color-mix(in srgb, var(--forest) 14%, transparent);
        }

        .field-shell.has-error {
            border-color: rgba(159, 45, 45, 0.7);
            background: #fff;
            box-shadow: 0 0 0 4px rgba(159, 45, 45, 0.1);
        }

        .field-height { height: 52px; }

        .field-shell iframe { display: block; width: 100%; }

        .stripe-field {
            position: relative;
            margin: 0;
        }

        .stripe-field .stripe-shell {
            position: relative;
            display: grid;
            align-items: end;
            min-height: 56px;
            height: 56px;
            padding: 0 14px 0 14px;
            border: 1.5px solid #c5cec8;
            border-radius: 12px;
            background: #fff;
            overflow: hidden;
            transition:
                border-color 0.22s ease,
                box-shadow 0.28s cubic-bezier(0.16, 1, 0.3, 1),
                transform 0.28s cubic-bezier(0.16, 1, 0.3, 1),
                background 0.22s ease;
        }

        .stripe-field.has-icon .stripe-shell { padding-right: 52px; }

        .stripe-field.has-brands .stripe-shell { padding-right: 92px; }

        .stripe-field.is-focused .stripe-shell {
            border-color: var(--forest-soft);
            box-shadow:
                0 0 0 4px color-mix(in srgb, var(--forest) 14%, transparent),
                0 10px 24px color-mix(in srgb, var(--forest) 10%, transparent);
            transform: translateY(-1px);
        }

        .stripe-field.has-error .stripe-shell {
            border-color: rgba(159, 45, 45, 0.7);
            box-shadow: 0 0 0 4px rgba(159, 45, 45, 0.1);
            transform: none;
            animation: stripe-shake 0.36s cubic-bezier(0.36, 0.07, 0.19, 0.97);
        }

        .stripe-field.is-valid:not(.is-focused):not(.has-error) .stripe-shell {
            border-color: color-mix(in srgb, var(--ok) 55%, #c5cec8);
        }

        .stripe-label {
            position: absolute;
            left: 14px;
            top: 50%;
            z-index: 2;
            margin: 0;
            color: #8b948e;
            font-size: 0.95rem;
            font-weight: 500;
            line-height: 1;
            pointer-events: none;
            transform: translateY(-50%);
            transform-origin: left center;
            transition:
                top 0.22s cubic-bezier(0.16, 1, 0.3, 1),
                transform 0.22s cubic-bezier(0.16, 1, 0.3, 1),
                color 0.2s ease,
                font-size 0.22s cubic-bezier(0.16, 1, 0.3, 1),
                font-weight 0.2s ease;
        }

        .stripe-field.is-active .stripe-label,
        .stripe-field.is-focused .stripe-label {
            top: 10px;
            transform: translateY(0);
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            color: var(--muted);
        }

        .stripe-field.is-focused .stripe-label {
            color: var(--forest);
        }

        .stripe-field.has-error .stripe-label {
            color: var(--danger);
        }

        .stripe-host {
            position: relative;
            z-index: 1;
            width: 100%;
            height: 28px;
            margin-bottom: 8px;
            margin-top: 20px;
            overflow: hidden;
        }

        .stripe-host iframe {
            display: block;
            width: 100%;
            height: 100%;
        }

        .stripe-input {
            display: block;
            width: 100%;
            height: 28px;
        }

        .stripe-bar {
            position: absolute;
            left: 16px;
            right: 16px;
            bottom: 0;
            height: 2px;
            border-radius: 999px;
            background: var(--forest);
            transform: scaleX(0);
            transform-origin: center;
            transition: transform 0.32s cubic-bezier(0.16, 1, 0.3, 1);
            pointer-events: none;
        }

        .stripe-field.is-focused .stripe-bar {
            transform: scaleX(1);
        }

        .stripe-field.has-error .stripe-bar {
            background: var(--danger);
            transform: scaleX(1);
        }

        .stripe-icon {
            position: absolute;
            top: 50%;
            right: 12px;
            z-index: 2;
            display: grid;
            place-items: center;
            width: 34px;
            height: 24px;
            color: #9aa49c;
            pointer-events: none;
            transform: translateY(-50%) scale(0.86);
            opacity: 0;
            transition:
                opacity 0.28s cubic-bezier(0.16, 1, 0.3, 1),
                transform 0.36s cubic-bezier(0.16, 1, 0.3, 1),
                color 0.2s ease;
        }

        .stripe-field.is-focused .stripe-icon,
        .stripe-field.is-active .stripe-icon {
            opacity: 1;
            transform: translateY(-50%) scale(1);
        }

        .stripe-field.is-focused .stripe-icon {
            color: var(--forest);
            animation: stripe-icon-in 0.45s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .stripe-brands {
            position: absolute;
            top: 50%;
            right: 10px;
            z-index: 2;
            display: grid;
            grid-auto-flow: column;
            align-items: center;
            gap: 0.25rem;
            pointer-events: none;
            transform: translateY(-50%);
        }

        .stripe-brands .mark {
            display: grid;
            place-items: center;
            height: 22px;
            overflow: hidden;
            border-radius: 4px;
            opacity: 0.28;
            transform: translateY(4px) scale(0.92);
            filter: grayscale(1);
            transition:
                opacity 0.28s ease,
                transform 0.36s cubic-bezier(0.16, 1, 0.3, 1),
                filter 0.28s ease;
        }

        .stripe-field.is-focused .stripe-brands .mark,
        .stripe-field.is-active .stripe-brands .mark {
            opacity: 1;
            transform: translateY(0) scale(1);
            filter: none;
        }

        .stripe-field.is-focused .stripe-brands .mark:nth-child(2) {
            transition-delay: 0.05s;
        }

        .stripe-brands .mark svg { display: block; height: 22px; width: auto; }

        .stripe-brands .mark-mc {
            padding: 0 0.25rem;
            background: #fff;
            border: 1px solid var(--line);
        }

        .stripe-brands .mark-mc svg { height: 14px; }

        .stripe-field.is-focused .stripe-icon .cvv-panel {
            animation: stripe-cvv-pulse 1.1s ease-in-out infinite;
        }

        @keyframes stripe-cvv-pulse {
            0%, 100% { opacity: 0.45; }
            50% { opacity: 1; }
        }

        @keyframes stripe-icon-in {
            0% { opacity: 0; transform: translateY(-42%) scale(0.7) rotate(-8deg); }
            60% { opacity: 1; transform: translateY(-52%) scale(1.06) rotate(2deg); }
            100% { opacity: 1; transform: translateY(-50%) scale(1) rotate(0deg); }
        }

        @keyframes stripe-shake {
            10%, 90% { transform: translateX(-1px); }
            20%, 80% { transform: translateX(2px); }
            30%, 50%, 70% { transform: translateX(-3px); }
            40%, 60% { transform: translateX(3px); }
        }

        @media (prefers-reduced-motion: reduce) {
            .stripe-field .stripe-shell,
            .stripe-label,
            .stripe-bar,
            .stripe-icon,
            .stripe-brands .mark {
                transition: none !important;
                animation: none !important;
            }

            .stripe-field.is-focused .stripe-icon .cvv-panel {
                animation: none !important;
            }
        }

        .fields {
            display: grid;
            gap: 0.95rem;
        }

        .row-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.85rem;
        }

        #checkout-form {
            display: flex;
            flex: 1;
            flex-direction: column;
        }

        .actions {
            display: grid;
            gap: 0.65rem;
            margin-top: 1.35rem;
        }

        .btn {
            appearance: none;
            border: 0;
            border-radius: 14px;
            min-height: 54px;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
        }

        .btn:focus-visible {
            outline: 2px solid var(--forest);
            outline-offset: 3px;
        }

        .btn-primary {
            position: relative;
            display: grid;
            place-items: center;
            color: var(--on-primary);
            background: var(--forest);
            box-shadow: 0 10px 24px rgba(20, 53, 44, 0.22);
            transition: background 0.15s ease, transform 0.15s ease, box-shadow 0.15s ease;
        }

        .btn-primary:hover:not(:disabled) {
            background: var(--forest-soft);
            transform: translateY(-1px);
        }

        .btn-primary:active:not(:disabled) { transform: translateY(0); }

        .btn-primary:disabled {
            opacity: 0.55;
            cursor: not-allowed;
            box-shadow: none;
        }

        .btn-primary.is-loading,
        .btn-primary.is-loading:disabled {
            opacity: 1;
            cursor: progress;
        }

        .btn-label {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-primary.is-loading .btn-label { visibility: hidden; }

        .btn-primary.is-loading::after {
            content: "";
            position: absolute;
            width: 18px;
            height: 18px;
            border: 2px solid color-mix(in srgb, var(--on-primary) 35%, transparent);
            border-top-color: var(--on-primary);
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
        }

        .btn-ghost {
            display: grid;
            place-items: center;
            min-height: 42px;
            color: var(--muted);
            background: transparent;
            font-weight: 600;
            font-size: 0.92rem;
        }

        .btn-ghost:hover { color: var(--ink); }

        .status {
            margin-top: 0.35rem;
            border-radius: 12px;
            padding: 0.75rem 0.9rem;
            font-size: 0.9rem;
            font-weight: 600;
            line-height: 1.4;
        }

        .status:empty { display: none; }
        .status.error { color: var(--danger); background: var(--danger-bg); }
        .status.ok { color: var(--ok); background: var(--mint); }
        .status.info { color: var(--forest); background: var(--info-bg); }

        .trust {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem 1rem;
            margin-top: auto;
            padding-top: 1.35rem;
        }

        .trust p {
            display: flex;
            align-items: flex-start;
            gap: 0.45rem;
            margin: 0;
            color: var(--muted);
            font-size: 0.78rem;
            line-height: 1.35;
        }

        .trust svg { flex: none; margin-top: 1px; color: var(--forest-soft); }

        .footnote {
            margin: 0;
            color: #7b877f;
            font-size: 0.78rem;
            text-align: center;
        }

        .skeleton { display: grid; gap: 0.85rem; }
        .skeleton span {
            display: block;
            height: 52px;
            border-radius: 12px;
            background: linear-gradient(90deg, #e8ece8, #f7f8f6, #e8ece8);
            background-size: 200% 100%;
            animation: shimmer 1.2s linear infinite;
        }

        .hidden { display: none !important; }

        @keyframes shimmer {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        @media (max-width: 860px) {
            .shell { padding: 1rem 0.85rem 1.5rem; align-content: start; }
            .checkout { grid-template-columns: 1fr; min-height: 0; border-radius: 22px; }
            .summary { gap: 1rem; padding: 1.2rem 1.15rem 1rem; }
            .facts { gap: 0.65rem; }
            .fact { padding-top: 0.7rem; }
            .amount strong { font-size: 2.35rem; }
            .summary-foot { padding-top: 0.25rem; }
            .pay { padding: 1.35rem 1.25rem 1.15rem; }
            .trust { grid-template-columns: 1fr; }
        }

        @media (max-width: 420px) {
            .row-2 { grid-template-columns: 1fr; }
        }

        @media (prefers-reduced-motion: reduce) {
            .skeleton span,
            .btn-primary.is-loading::after { animation: none; }
            .btn-primary { transition: none; }
        }
        @if ($checkoutTheme->customCss() !== '')
            {!! $checkoutTheme->customCss() !!}
        @endif
    </style>
</head>
<body>
    @php
        $formattedAmount = number_format((float) $order->amount, 2, '.', ' ');
        $merchantInitial = mb_strtoupper(mb_substr(trim((string) $companyName), 0, 1)) ?: '•';
    @endphp
    <div class="shell">
        <main class="checkout">
            <aside class="summary" aria-label="{{ __('Order summary') }}">
                <div class="summary-top">
                    <p class="secure-pill">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M7 11V8a5 5 0 0 1 10 0v3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                            <rect x="5" y="11" width="14" height="9" rx="2" stroke="currentColor" stroke-width="1.8"/>
                        </svg>
                        {{ __('Secure checkout') }}
                    </p>
                </div>

                <div class="merchant">
                    <div class="avatar" aria-hidden="true">
                        @if (! empty($companyLogo))
                            <img src="{{ $companyLogo }}" alt="" onerror="this.remove()">
                        @endif
                        <span>{{ $merchantInitial }}</span>
                    </div>
                    <div class="merchant-copy">
                        <p class="merchant-kicker">{{ __('Paying') }}</p>
                        <h1>{{ $companyName }}</h1>
                    </div>
                </div>

                <div class="amount-block">
                    <p class="amount-kicker">{{ __('Amount due') }}</p>
                    <p class="amount">
                        <strong>{{ $formattedAmount }}</strong>
                        <span>{{ $order->currency }}</span>
                    </p>
                </div>

                <dl class="facts">
                    @if ($order->description)
                        <div class="fact">
                            <dt>{{ __('Description') }}</dt>
                            <dd>{{ $order->description }}</dd>
                        </div>
                    @endif
                    @if ($order->orderId)
                        <div class="fact">
                            <dt>{{ __('Order') }}</dt>
                            <dd>{{ $order->orderId }}</dd>
                        </div>
                    @endif
                    <div class="fact">
                        <dt>{{ __('Customer') }}</dt>
                        <dd>{{ $order->customer_name }}</dd>
                    </div>
                    @if ($order->customer_email)
                        <div class="fact">
                            <dt>{{ __('Email') }}</dt>
                            <dd>{{ $order->customer_email }}</dd>
                        </div>
                    @endif
                </dl>

                <div class="summary-foot">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M12 3 5 6v6c0 4.2 2.8 7.4 7 9 4.2-1.6 7-4.8 7-9V6l-7-3Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
                        <path d="m9 12 2 2 4-4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span>{{ $checkoutTheme->summaryNote(__('Card details stay with Elavon Hosted Fields.')) }}</span>
                </div>
            </aside>

            <section class="pay" aria-labelledby="card-details-heading">
                <div class="pay-head">
                    <h2 id="card-details-heading">{{ $checkoutTheme->heading(__('Card details')) }}</h2>
                    <p class="lead">{{ $checkoutTheme->lead(__('Enter your card on this page. Card data is processed securely by Elavon Hosted Fields.')) }}</p>
                    <div class="brands" aria-label="{{ __('Accepted cards') }}">
                        <span class="brand brand-visa">
                            <svg role="img" aria-label="Visa" viewBox="0 0 780 500" xmlns="http://www.w3.org/2000/svg">
                                <path d="M780 0H0V500H780V0Z" fill="#1434CB"/>
                                <path d="M489.823 143.111C442.988 143.111 401.134 167.393 401.134 212.256C401.134 263.706 475.364 267.259 475.364 293.106C475.364 303.989 462.895 313.731 441.6 313.731C411.377 313.731 388.789 300.119 388.789 300.119L379.123 345.391C379.123 345.391 405.145 356.889 439.692 356.889C490.898 356.889 531.19 331.415 531.19 285.784C531.19 231.419 456.652 227.971 456.652 203.981C456.652 195.455 466.887 186.114 488.122 186.114C512.081 186.114 531.628 196.014 531.628 196.014L541.087 152.289C541.087 152.289 519.818 143.111 489.823 143.111ZM61.3294 146.411L60.1953 153.011C60.1953 153.011 79.8988 156.618 97.645 163.814C120.495 172.064 122.122 176.868 125.971 191.786L167.905 353.486H224.118L310.719 146.411H254.635L198.989 287.202L176.282 167.861C174.199 154.203 163.651 146.411 150.74 146.411H61.3294ZM333.271 146.411L289.275 353.486H342.756L386.598 146.411H333.271ZM631.554 146.411C618.658 146.411 611.825 153.318 606.811 165.386L528.458 353.486H584.542L595.393 322.136H663.72L670.318 353.486H719.805L676.633 146.411H631.554ZM638.848 202.356L655.473 280.061H610.935L638.848 202.356Z" fill="#fff"/>
                            </svg>
                        </span>
                        <span class="brand brand-mastercard">
                            <svg role="img" aria-label="Mastercard" viewBox="109 32 562 348" xmlns="http://www.w3.org/2000/svg">
                                <path d="M465.738 69.1387H313.812V342.088H465.738V69.1387Z" fill="#FF5A00"/>
                                <path d="M323.926 205.613C323.926 150.158 349.996 100.94 390 69.1387C360.559 45.9902 323.42 32 282.91 32C186.945 32 109.297 109.648 109.297 205.613C109.297 301.578 186.945 379.227 282.91 379.227C323.42 379.227 360.559 365.237 390 342.088C349.94 310.737 323.926 261.069 323.926 205.613Z" fill="#EB001B"/>
                                <path d="M670.711 205.613C670.711 301.578 593.062 379.227 497.098 379.227C456.588 379.227 419.449 365.237 390.008 342.088C430.518 310.231 456.082 261.069 456.082 205.613C456.082 150.158 430.012 100.94 390.008 69.1387C419.393 45.9902 456.532 32 497.041 32C593.062 32 670.711 110.154 670.711 205.613Z" fill="#F79E1B"/>
                            </svg>
                        </span>
                    </div>
                </div>

                <div id="fields-skeleton" class="skeleton" aria-hidden="true">
                    <span></span>
                    <span></span>
                    <div class="row-2"><span></span><span></span></div>
                </div>

                <form id="checkout-form" class="hidden" autocomplete="off" novalidate>
                    <div class="fields">
                        <div class="field">
                            <label for="cardholder-name">{{ __('Cardholder name') }}</label>
                            <div id="cardholder-name" class="field-shell field-height"></div>
                        </div>

                        <div class="stripe-field has-brands" data-field="cardNumber">
                            <div class="stripe-shell">
                                <label class="stripe-label" for="card-number">{{ __('Card number') }}</label>
                                <div id="card-number" class="stripe-host"></div>
                                <div class="stripe-brands" aria-hidden="true">
                                    <span class="mark mark-visa">
                                        <svg viewBox="0 0 780 500" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M780 0H0V500H780V0Z" fill="#1434CB"/>
                                            <path d="M489.823 143.111C442.988 143.111 401.134 167.393 401.134 212.256C401.134 263.706 475.364 267.259 475.364 293.106C475.364 303.989 462.895 313.731 441.6 313.731C411.377 313.731 388.789 300.119 388.789 300.119L379.123 345.391C379.123 345.391 405.145 356.889 439.692 356.889C490.898 356.889 531.19 331.415 531.19 285.784C531.19 231.419 456.652 227.971 456.652 203.981C456.652 195.455 466.887 186.114 488.122 186.114C512.081 186.114 531.628 196.014 531.628 196.014L541.087 152.289C541.087 152.289 519.818 143.111 489.823 143.111ZM61.3294 146.411L60.1953 153.011C60.1953 153.011 79.8988 156.618 97.645 163.814C120.495 172.064 122.122 176.868 125.971 191.786L167.905 353.486H224.118L310.719 146.411H254.635L198.989 287.202L176.282 167.861C174.199 154.203 163.651 146.411 150.74 146.411H61.3294ZM333.271 146.411L289.275 353.486H342.756L386.598 146.411H333.271ZM631.554 146.411C618.658 146.411 611.825 153.318 606.811 165.386L528.458 353.486H584.542L595.393 322.136H663.72L670.318 353.486H719.805L676.633 146.411H631.554ZM638.848 202.356L655.473 280.061H610.935L638.848 202.356Z" fill="#fff"/>
                                        </svg>
                                    </span>
                                    <span class="mark mark-mc">
                                        <svg viewBox="109 32 562 348" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M465.738 69.1387H313.812V342.088H465.738V69.1387Z" fill="#FF5A00"/>
                                            <path d="M323.926 205.613C323.926 150.158 349.996 100.94 390 69.1387C360.559 45.9902 323.42 32 282.91 32C186.945 32 109.297 109.648 109.297 205.613C109.297 301.578 186.945 379.227 282.91 379.227C323.42 379.227 360.559 365.237 390 342.088C349.94 310.737 323.926 261.069 323.926 205.613Z" fill="#EB001B"/>
                                            <path d="M670.711 205.613C670.711 301.578 593.062 379.227 497.098 379.227C456.588 379.227 419.449 365.237 390.008 342.088C430.518 310.231 456.082 261.069 456.082 205.613C456.082 150.158 430.012 100.94 390.008 69.1387C419.393 45.9902 456.532 32 497.041 32C593.062 32 670.711 110.154 670.711 205.613Z" fill="#F79E1B"/>
                                        </svg>
                                    </span>
                                </div>
                                <span class="stripe-bar" aria-hidden="true"></span>
                            </div>
                        </div>

                        <div class="row-2">
                            <div class="stripe-field has-icon" data-field="cardExpirationDate">
                                <div class="stripe-shell">
                                    <label class="stripe-label" for="card-exp-date">{{ __('Expiry') }}</label>
                                    <div id="card-exp-date" class="stripe-host"></div>
                                    <span class="stripe-icon" aria-hidden="true">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                            <rect x="3.5" y="5" width="17" height="15" rx="2.5" stroke="currentColor" stroke-width="1.7"/>
                                            <path d="M3.5 10h17M8 3.5V7M16 3.5V7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                                        </svg>
                                    </span>
                                    <span class="stripe-bar" aria-hidden="true"></span>
                                </div>
                            </div>
                            <div class="stripe-field has-icon" data-field="cardCvv">
                                <div class="stripe-shell">
                                    <label class="stripe-label" for="card-cvv">{{ __('CVV') }}</label>
                                    <div id="card-cvv" class="stripe-host"></div>
                                    <span class="stripe-icon" aria-hidden="true">
                                        <svg width="22" height="16" viewBox="0 0 24 16" fill="none">
                                            <rect x="0.75" y="0.75" width="22.5" height="14.5" rx="2.5" stroke="currentColor" stroke-width="1.5"/>
                                            <rect x="0.75" y="3.5" width="22.5" height="3.2" fill="currentColor" opacity="0.22"/>
                                            <rect class="cvv-panel" x="14" y="8.4" width="7" height="4.2" rx="0.8" fill="currentColor" opacity="0.55"/>
                                        </svg>
                                    </span>
                                    <span class="stripe-bar" aria-hidden="true"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="actions">
                        <button type="submit" class="btn btn-primary" id="pay-button" disabled>
                            <span class="btn-label">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M7 11V8a5 5 0 0 1 10 0v3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                    <rect x="5" y="11" width="14" height="9" rx="2" stroke="currentColor" stroke-width="1.8"/>
                                </svg>
                                {{ $checkoutTheme->payButtonLabel(__('Pay')) }} {{ $formattedAmount }} {{ $order->currency }}
                            </span>
                        </button>
                        <a class="btn btn-ghost" href="{{ $cancelUrl }}">{{ $checkoutTheme->cancelLabel(__('Cancel payment')) }}</a>
                    </div>
                    <div id="status" class="status" role="status" aria-live="polite"></div>
                    <div class="trust">
                        <p>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <rect x="5" y="11" width="14" height="9" rx="2" stroke="currentColor" stroke-width="1.7"/>
                                <path d="M8 11V8a4 4 0 0 1 8 0v3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                            </svg>
                            {{ __('256-bit encrypted connection') }}
                        </p>
                        <p>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M12 3 5 6v6c0 4.2 2.8 7.4 7 9 4.2-1.6 7-4.8 7-9V6l-7-3Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
                            </svg>
                            {{ __('PCI-compliant card fields') }}
                        </p>
                    </div>
                </form>
            </section>
        </main>
        <p class="footnote">{{ $checkoutTheme->footer() ?: __('You can cancel and return to the store at any time before paying.') }}</p>
    </div>

    <script>
        (function () {
            const sessionId = @json($sessionId);
            const completeUrl = @json($completeUrl);
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const billTo = {
                fullName: @json($order->customer_name),
                street1: @json($order->customer_address ?: ''),
                postalCode: @json($order->customer_post_code ?: ''),
                city: @json($order->city ?: ''),
                countryCode: 'NOR',
                email: @json($order->customer_email ?: ''),
                primaryPhone: @json($order->customer_phone ?: ''),
            };
            const shopperEmailAddress = @json($order->customer_email ?: '');

            const form = document.getElementById('checkout-form');
            const skeleton = document.getElementById('fields-skeleton');
            const payButton = document.getElementById('pay-button');
            const statusEl = document.getElementById('status');
            const fieldIds = {
                cardholderName: 'cardholder-name',
                cardNumber: 'card-number',
                cardExpirationDate: 'card-exp-date',
                cardCvv: 'card-cvv',
            };
            const fieldTypes = {
                cardholderName: 'cardholderName',
                cardNumber: 'cardNumber',
                cardExpirationDate: 'cardExpirationDate',
                cardCvv: 'cardCvv',
            };

            let elavonHostedFields = null;
            let submitting = false;

            function setStatus(message, type) {
                statusEl.textContent = message || '';
                statusEl.className = 'status' + (type ? ' ' + type : '');
                statusEl.setAttribute('role', type === 'error' ? 'alert' : 'status');
            }

            function fieldElement(fieldType) {
                return document.getElementById(fieldIds[fieldType] || '');
            }

            function fieldWrap(fieldType) {
                const host = fieldElement(fieldType);
                return host ? host.closest('.stripe-field') : null;
            }

            function markFieldError(fieldType, hasError) {
                const el = fieldElement(fieldType);
                const wrap = fieldWrap(fieldType);
                if (el) {
                    el.classList.toggle('has-error', !!hasError);
                }
                if (wrap) {
                    wrap.classList.toggle('has-error', !!hasError);
                    if (hasError) {
                        wrap.classList.remove('is-valid');
                    }
                }
            }

            function markFieldFocus(fieldType, isFocused) {
                const el = fieldElement(fieldType);
                const wrap = fieldWrap(fieldType);
                if (el) {
                    el.classList.toggle('is-focused', !!isFocused);
                    if (isFocused) {
                        el.classList.remove('has-error');
                    }
                }
                if (wrap) {
                    wrap.classList.toggle('is-focused', !!isFocused);
                    if (isFocused) {
                        wrap.classList.remove('has-error');
                    }
                }
            }

            function markFieldValue(fieldType, isEmpty) {
                const wrap = fieldWrap(fieldType);
                if (wrap) {
                    wrap.classList.toggle('is-active', !isEmpty);
                }
            }

            function markFieldValid(fieldType, isValid) {
                const wrap = fieldWrap(fieldType);
                if (wrap) {
                    wrap.classList.toggle('is-valid', !!isValid);
                    if (isValid) {
                        wrap.classList.remove('has-error');
                    }
                }
            }

            function setPaying(isPaying) {
                submitting = isPaying;
                payButton.disabled = isPaying;
                payButton.classList.toggle('is-loading', isPaying);
                payButton.setAttribute('aria-busy', isPaying ? 'true' : 'false');
            }

            function finalizeOnServer() {
                return fetch(completeUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ session_id: sessionId }),
                }).then(async (response) => {
                    const payload = await response.json();
                    if (!response.ok || !payload.status) {
                        throw new Error(payload.message || 'Payment could not be confirmed.');
                    }
                    return payload;
                });
            }

            function messageHandler(message) {
                if (message.type === 'fieldFocusChanged' || (message.fieldType && typeof message.isFocused === 'boolean' && message.type !== 'fieldValueChanged' && message.type !== 'fieldValidityChanged')) {
                    markFieldFocus(message.fieldType, message.isFocused === true);
                    if (message.isFocused === true && elavonHostedFields) {
                        elavonHostedFields.removeClass(message.fieldType, 'field-error');
                    }
                }

                if (message.type === 'fieldValueChanged' && message.fieldType) {
                    markFieldValue(message.fieldType, message.isEmpty === true);
                }

                if (message.type === 'fieldValidityChanged' && message.fieldType) {
                    markFieldValid(message.fieldType, message.isValid === true);
                    if (message.isValid === false && message.error) {
                        markFieldError(message.fieldType, true);
                    } else if (message.isValid === true) {
                        markFieldError(message.fieldType, false);
                    }
                }

                if (message.type === 'error') {
                    setPaying(false);
                    setStatus(message.message || 'Something went wrong. Please try again.', 'error');
                    return;
                }

                if (message.type === 'transactionCreated') {
                    if (message.isAuthorized) {
                        setStatus('Payment approved. Confirming…', 'ok');
                        finalizeOnServer()
                            .then((payload) => { window.location.href = payload.redirect_url; })
                            .catch((error) => {
                                setPaying(false);
                                setStatus(error.message || 'Unable to confirm payment.', 'error');
                            });
                        return;
                    }

                    setPaying(false);
                    setStatus(message.canRetry ? 'Card declined. Please try another card.' : 'Card declined.', 'error');
                }
            }

            function revealForm() {
                skeleton.classList.add('hidden');
                form.classList.remove('hidden');
            }

            function onReady(error) {
                revealForm();

                if (error) {
                    setStatus('Unable to load secure card fields. Ensure this domain is allowed in Elavon for Hosted Fields.', 'error');
                    return;
                }

                payButton.disabled = false;
            }

            function initializeHostedFields() {
                if (!window.ElavonHostedFields) {
                    setStatus('Elavon CheckoutJS script failed to load.', 'error');
                    revealForm();
                    return;
                }

                elavonHostedFields = new window.ElavonHostedFields({
                    sessionId: sessionId,
                    fields: {
                        cardholderName: { wrapperId: 'cardholder-name', className: 'field-height' },
                        cardNumber: { wrapperId: 'card-number', className: 'stripe-input' },
                        cardExpirationDate: { wrapperId: 'card-exp-date', className: 'stripe-input' },
                        cardCvv: { wrapperId: 'card-cvv', className: 'stripe-input' },
                    },
                    styles: {
                        'input': {
                            'width': '100%',
                            'height': '28px',
                            'padding': '0',
                            'font-size': '16px',
                            'font-family': 'Inter, sans-serif',
                            'color': '#14211c',
                            'border': '0',
                            'outline': 'none',
                            'background': 'transparent',
                            'box-sizing': 'border-box',
                        },
                        'input:focus': {
                            'outline': 'none',
                        },
                        '::placeholder': {
                            'color': 'transparent',
                        },
                    },
                    onReady: onReady,
                    messageHandler: messageHandler,
                    hideUntilRequested: false,
                });
            }

            form.addEventListener('submit', function (event) {
                event.preventDefault();
                if (submitting || !elavonHostedFields) {
                    return;
                }

                const state = elavonHostedFields.getState();
                if (!state.isValid) {
                    Object.keys(fieldTypes).forEach((key) => {
                        const type = fieldTypes[key];
                        if (!state.fields?.[type]?.isValid) {
                            elavonHostedFields.addClass(type, 'field-error');
                            markFieldError(type, true);
                        }
                    });
                    setStatus('Please complete all card fields.', 'error');
                    return;
                }

                setPaying(true);
                setStatus('Processing payment…', 'info');

                elavonHostedFields.submit({
                    billTo: billTo,
                    shipTo: billTo,
                    shopperEmailAddress: shopperEmailAddress,
                });
            });

            initializeHostedFields();
        })();
    </script>
</body>
</html>
