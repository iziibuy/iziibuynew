<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Elavon CheckoutJS Test</title>
    <style>
        :root {
            --bg: #0f172a;
            --card: #111827;
            --line: #1f2937;
            --text: #e5e7eb;
            --muted: #94a3b8;
            --accent: #0ea5e9;
            --ok: #22c55e;
            --warn: #f59e0b;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, sans-serif;
            background: radial-gradient(circle at top, #1e293b, var(--bg));
            color: var(--text);
            padding: 2rem 1rem;
        }
        .wrap { width: min(760px, 100%); margin: 0 auto; }
        .card {
            background: rgba(17, 24, 39, 0.92);
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
        h1 { margin: 0 0 .4rem; font-size: 1.5rem; }
        p { color: var(--muted); margin: 0 0 1rem; }
        label { display: block; font-size: .85rem; color: var(--muted); margin-bottom: .35rem; }
        input, select {
            width: 100%;
            border-radius: 10px;
            border: 1px solid var(--line);
            background: #0b1220;
            color: var(--text);
            padding: .75rem .9rem;
            margin-bottom: 1rem;
        }
        .row { display: grid; grid-template-columns: 1fr 1fr; gap: .8rem; }
        @media (max-width: 640px) { .row { grid-template-columns: 1fr; } }
        button, .btn {
            appearance: none;
            border: 0;
            border-radius: 999px;
            padding: .85rem 1.2rem;
            font-weight: 700;
            cursor: pointer;
            background: var(--accent);
            color: #041018;
            text-decoration: none;
            display: inline-block;
        }
        .meta { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .85rem; }
        .ok { color: var(--ok); }
        .warn { color: var(--warn); }
        .errors { color: #fca5a5; margin-bottom: 1rem; }
        ul { margin: 0; padding-left: 1.1rem; color: var(--muted); }
        code { color: #7dd3fc; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <h1>Elavon CheckoutJS test</h1>
            <p>
                Creates a temporary <code>ExternalOrder</code>, stores it in session, opens your CheckoutJS page,
                then returns here through success/failed redirects.
            </p>
            <p class="meta">Configured origin: <code>{{ $originUrl }}</code></p>

            @if ($errors->any())
                <div class="errors">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            @if ($plugins->isEmpty())
                <p class="warn">No Elavon plugins with merchant alias / public / secret keys were found.</p>
            @else
                <form method="post" action="{{ route('test.elavon.checkoutjs.start') }}">
                    @csrf
                    <label for="payment_method_access_id">Plugin</label>
                    <select name="payment_method_access_id" id="payment_method_access_id" required>
                        @foreach ($plugins as $plugin)
                            <option value="{{ $plugin->id }}" @selected((int) $selectedPluginId === (int) $plugin->id)>
                                #{{ $plugin->id }} — {{ $plugin->company_name }} ({{ $plugin->site_mode ?: 'mode?' }})
                            </option>
                        @endforeach
                    </select>

                    <div class="row">
                        <div>
                            <label for="amount">Amount</label>
                            <input id="amount" type="number" step="0.01" min="1" name="amount" value="{{ old('amount', $amount) }}" required>
                        </div>
                        <div>
                            <label for="currency">Currency</label>
                            <input id="currency" type="text" name="currency" maxlength="3" value="{{ old('currency', $currency) }}" required>
                        </div>
                    </div>

                    <div class="row">
                        <div>
                            <label for="customer_name">Customer name</label>
                            <input id="customer_name" type="text" name="customer_name" value="{{ old('customer_name', 'CheckoutJS Tester') }}">
                        </div>
                        <div>
                            <label for="customer_email">Customer email</label>
                            <input id="customer_email" type="email" name="customer_email" value="{{ old('customer_email', 'checkoutjs-test@example.com') }}">
                        </div>
                    </div>

                    <button type="submit">Create temporary order &amp; pay</button>
                </form>
            @endif
        </div>

        @if ($sessionOrder)
            <div class="card">
                <h1>Last session order</h1>
                <p class="meta">
                    Order #{{ $sessionOrder->id }} /
                    {{ $sessionOrder->uuid ?? $sessionOrder->ulid }} /
                    status: <strong class="{{ strtoupper((string) $sessionOrder->status) === 'COMPLETED' ? 'ok' : 'warn' }}">{{ $sessionOrder->status }}</strong>
                </p>
                <ul>
                    <li>Amount: {{ $sessionOrder->amount }} {{ $sessionOrder->currency }}</li>
                    <li>Merchant order: {{ $sessionOrder->orderId }}</li>
                    <li>Payment session: {{ $sessionOrder->payment_id }}</li>
                    <li>Plugin: {{ $sessionOrder->paymentMethodAccess?->company_name }}</li>
                </ul>
                @if (filled($sessionOrder->payment_url) && strtoupper((string) $sessionOrder->status) === 'PENDING')
                    <p style="margin-top:1rem;">
                        <a class="btn" href="{{ $sessionOrder->payment_url }}">Resume CheckoutJS payment</a>
                    </p>
                @endif
            </div>
        @endif
    </div>
</body>
</html>
