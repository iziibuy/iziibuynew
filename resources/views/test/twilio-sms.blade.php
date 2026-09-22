<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Twilio SMS Test</title>
    <style>
        :root {
            --bg: #0f172a;
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
        .wrap { width: min(680px, 100%); margin: 0 auto; }
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
        input, textarea {
            width: 100%;
            border-radius: 10px;
            border: 1px solid var(--line);
            background: #0b1220;
            color: var(--text);
            padding: .75rem .9rem;
            margin-bottom: 1rem;
            font: inherit;
        }
        button {
            appearance: none;
            border: 0;
            border-radius: 999px;
            padding: .85rem 1.2rem;
            font-weight: 700;
            cursor: pointer;
            background: var(--accent);
            color: #041018;
        }
        .meta { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .85rem; }
        .ok { color: var(--ok); }
        .bad { color: var(--bad); }
        .errors { color: #fca5a5; margin-bottom: 1rem; }
        code { color: #7dd3fc; }
        pre {
            white-space: pre-wrap;
            word-break: break-word;
            background: #0b1220;
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 1rem;
            color: #7dd3fc;
            font-size: .85rem;
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <h1>Twilio SMS test</h1>
            <p>Sends a one-off SMS through <code>SmsService</code> using <code>TWILIO_SID</code>, <code>TWILIO_TOKEN</code>, and <code>TWILIO_FROM</code>.</p>
            <p class="meta">
                From: <code>{{ $from ?: 'not set' }}</code>
                · Configured: <strong class="{{ $configured ? 'ok' : 'bad' }}">{{ $configured ? 'yes' : 'no' }}</strong>
            </p>

            @if (session('success'))
                <p class="ok">{{ session('success') }}</p>
            @endif

            @if ($errors->any())
                <div class="errors">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form method="post" action="{{ route('test.twilio.sms.send') }}">
                @csrf
                <label for="phone">Phone (E.164, e.g. +4799999999)</label>
                <input id="phone" type="text" name="phone" value="{{ $phone }}" placeholder="+4799999999" required>

                <label for="message">Message</label>
                <textarea id="message" name="message" rows="4" required>{{ $message }}</textarea>

                <button type="submit">Send test SMS</button>
            </form>
        </div>

        @if ($lastResult)
            <div class="card">
                <h1>Last result</h1>
                <pre>{{ json_encode($lastResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
        @endif
    </div>
</body>
</html>
