<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CheckoutJS {{ $outcome === 'success' ? 'Success' : 'Failed' }}</title>
    <style>
        :root {
            --bg: #0f172a;
            --card: #111827;
            --line: #1f2937;
            --text: #e5e7eb;
            --muted: #94a3b8;
            --accent: #0ea5e9;
            --ok: #22c55e;
            --bad: #f87171;
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
        h1 { margin: 0 0 .5rem; }
        .success { color: var(--ok); }
        .failed { color: var(--bad); }
        p, li { color: var(--muted); }
        code, pre {
            color: #7dd3fc;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: .85rem;
        }
        pre {
            white-space: pre-wrap;
            word-break: break-word;
            background: #0b1220;
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 1rem;
        }
        a.btn {
            display: inline-block;
            margin-top: 1rem;
            text-decoration: none;
            border-radius: 999px;
            padding: .85rem 1.2rem;
            font-weight: 700;
            background: var(--accent);
            color: #041018;
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <h1 class="{{ $outcome === 'success' ? 'success' : 'failed' }}">
                CheckoutJS {{ $outcome === 'success' ? 'success redirect' : 'failed / cancel redirect' }}
            </h1>
            <p>This page is the redirect target configured on the temporary test order.</p>

            <h3>Query params from redirect</h3>
            <pre>{{ json_encode($query, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>

            @if ($sessionPayload)
                <h3>Session payload</h3>
                <pre>{{ json_encode($sessionPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            @endif

            @if ($sessionOrder)
                <h3>Order in database</h3>
                <pre>{{ json_encode([
                    'id' => $sessionOrder->id,
                    'uuid' => $sessionOrder->uuid ?? $sessionOrder->ulid ?? null,
                    'orderId' => $sessionOrder->orderId,
                    'status' => $sessionOrder->status,
                    'amount' => $sessionOrder->amount,
                    'currency' => $sessionOrder->currency,
                    'payment_id' => $sessionOrder->payment_id,
                    'response' => $sessionOrder->response,
                    'paid_at' => optional($sessionOrder->paid_at)?->toDateTimeString(),
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            @endif

            <a class="btn" href="{{ route('test.elavon.checkoutjs') }}">Back to CheckoutJS test</a>
        </div>
    </div>
</body>
</html>
