<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Pay securely') }} · {{ $companyName }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Fraunces:opsz,wght@9..144,600;9..144,700&display=swap" rel="stylesheet">
    <script src="{{ $hostedFieldsScriptUrl }}"></script>
    <style>
        :root {
            --ink: #0b1f33;
            --muted: #5b6f82;
            --line: rgba(11, 31, 51, 0.12);
            --surface: rgba(255, 255, 255, 0.92);
            --accent: #0e6b8a;
            --accent-deep: #084c63;
            --ok: #1f7a4d;
            --danger: #b42318;
            --glow: rgba(14, 107, 138, 0.28);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "DM Sans", sans-serif;
            color: var(--ink);
            background:
                radial-gradient(1200px 600px at 10% -10%, rgba(56, 189, 248, 0.28), transparent 55%),
                radial-gradient(900px 500px at 100% 0%, rgba(14, 107, 138, 0.22), transparent 50%),
                linear-gradient(165deg, #e8f3f7 0%, #f7fafc 42%, #dcecf3 100%);
        }

        .shell {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 2rem 1rem 3rem;
        }

        .layout {
            width: min(980px, 100%);
            display: grid;
            grid-template-columns: 1.05fr 0.95fr;
            gap: 1.25rem;
            align-items: stretch;
        }

        @media (max-width: 860px) {
            .layout { grid-template-columns: 1fr; }
        }

        .panel {
            background: var(--surface);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.7);
            border-radius: 24px;
            box-shadow: 0 24px 60px rgba(8, 40, 58, 0.12);
            overflow: hidden;
        }

        .brand {
            padding: 1.75rem 1.75rem 1.25rem;
            border-bottom: 1px solid var(--line);
        }

        .brand-kicker {
            font-size: 0.75rem;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--muted);
            margin: 0 0 0.45rem;
        }

        .brand h1 {
            margin: 0;
            font-family: Fraunces, Georgia, serif;
            font-size: clamp(1.6rem, 2.4vw, 2.1rem);
            line-height: 1.15;
            color: var(--accent-deep);
        }

        .summary { padding: 1.5rem 1.75rem 1.75rem; }

        .amount {
            display: flex;
            align-items: baseline;
            gap: 0.45rem;
            margin-bottom: 1.25rem;
        }

        .amount strong {
            font-family: Fraunces, Georgia, serif;
            font-size: 2.4rem;
            letter-spacing: -0.02em;
        }

        .amount span { color: var(--muted); font-weight: 600; }

        .meta { display: grid; gap: 0.85rem; }

        .meta-row {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px dashed var(--line);
            font-size: 0.95rem;
        }

        .meta-row:last-child { border-bottom: 0; padding-bottom: 0; }
        .meta-row dt { color: var(--muted); margin: 0; }
        .meta-row dd { margin: 0; font-weight: 600; text-align: right; }

        .pay-panel { padding: 1.75rem; }

        .pay-panel h2 { margin: 0 0 0.35rem; font-size: 1.2rem; }
        .pay-panel p.lead { margin: 0 0 1.35rem; color: var(--muted); font-size: 0.95rem; }

        .field { margin-bottom: 1rem; }
        .field label {
            display: block;
            margin-bottom: 0.4rem;
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--muted);
        }

        .field-shell {
            min-height: 48px;
            border: 1px solid var(--line);
            border-radius: 12px;
            background: #fff;
        }

        .field-shell.has-error {
            border-color: rgba(180, 35, 24, 0.55);
            box-shadow: 0 0 0 3px rgba(180, 35, 24, 0.12);
        }

        .field-height { height: 48px; }

        .row-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.85rem;
        }

        .actions { display: grid; gap: 0.75rem; margin-top: 1.35rem; }

        .btn {
            appearance: none;
            border: 0;
            border-radius: 999px;
            min-height: 52px;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
            transition: transform .15s ease, box-shadow .15s ease;
        }

        .btn:disabled { opacity: 0.55; cursor: not-allowed; }

        .btn-primary {
            color: #fff;
            background: linear-gradient(135deg, var(--accent), var(--accent-deep));
            box-shadow: 0 12px 28px var(--glow);
        }

        .btn-ghost {
            background: transparent;
            color: var(--muted);
            border: 1px solid var(--line);
            text-decoration: none;
            display: grid;
            place-items: center;
        }

        .status { min-height: 1.25rem; margin-top: 0.85rem; font-size: 0.9rem; font-weight: 600; }
        .status.error { color: var(--danger); }
        .status.ok { color: var(--ok); }
        .status.info { color: var(--accent-deep); }

        .secure {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            margin-top: 1.25rem;
            color: var(--muted);
            font-size: 0.82rem;
        }

        .secure-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--ok);
            box-shadow: 0 0 0 4px rgba(31, 122, 77, 0.15);
        }

        .skeleton { display: grid; gap: 0.75rem; margin-bottom: 1rem; }
        .skeleton span {
            display: block;
            height: 48px;
            border-radius: 12px;
            background: linear-gradient(90deg, #e8eef2, #f7fafc, #e8eef2);
            background-size: 200% 100%;
            animation: shimmer 1.2s linear infinite;
        }

        @keyframes shimmer {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        .hidden { display: none !important; }
    </style>
</head>
<body>
    <div class="shell">
        <div class="layout">
            <section class="panel">
                <div class="brand">
                    <p class="brand-kicker">{{ __('Secure checkout') }}</p>
                    <h1>{{ $companyName }}</h1>
                </div>
                <div class="summary">
                    <div class="amount">
                        <strong>{{ number_format((float) $order->amount, 2, '.', ' ') }}</strong>
                        <span>{{ $order->currency }}</span>
                    </div>
                    <dl class="meta">
                        @if ($order->description)
                            <div class="meta-row">
                                <dt>{{ __('Description') }}</dt>
                                <dd>{{ $order->description }}</dd>
                            </div>
                        @endif
                        @if ($order->orderId)
                            <div class="meta-row">
                                <dt>{{ __('Order') }}</dt>
                                <dd>{{ $order->orderId }}</dd>
                            </div>
                        @endif
                        <div class="meta-row">
                            <dt>{{ __('Customer') }}</dt>
                            <dd>{{ $order->customer_name }}</dd>
                        </div>
                        @if ($order->customer_email)
                            <div class="meta-row">
                                <dt>{{ __('Email') }}</dt>
                                <dd>{{ $order->customer_email }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </section>

            <section class="panel pay-panel">
                <h2>{{ __('Card details') }}</h2>
                <p class="lead">{{ __('Enter your card on this page. Card data is processed securely by Elavon Hosted Fields.') }}</p>

                <div id="fields-skeleton" class="skeleton" aria-hidden="true">
                    <span></span><span></span>
                    <div class="row-2"><span></span><span></span></div>
                </div>

                <form id="checkout-form" class="hidden" autocomplete="off" novalidate>
                    <div class="field">
                        <label for="cardholder-name">{{ __('Cardholder name') }}</label>
                        <div id="cardholder-name" class="field-shell field-height"></div>
                    </div>
                    <div class="field">
                        <label for="card-number">{{ __('Card number') }}</label>
                        <div id="card-number" class="field-shell field-height"></div>
                    </div>
                    <div class="row-2">
                        <div class="field">
                            <label for="card-exp-date">{{ __('Expiry') }}</label>
                            <div id="card-exp-date" class="field-shell field-height"></div>
                        </div>
                        <div class="field">
                            <label for="card-cvv">{{ __('CVV') }}</label>
                            <div id="card-cvv" class="field-shell field-height"></div>
                        </div>
                    </div>
                    <div class="actions">
                        <button type="submit" class="btn btn-primary" id="pay-button" disabled>
                            {{ __('Pay') }} {{ number_format((float) $order->amount, 2, '.', ' ') }} {{ $order->currency }}
                        </button>
                        <a class="btn btn-ghost" href="{{ $cancelUrl }}">{{ __('Cancel payment') }}</a>
                    </div>
                    <div id="status" class="status" role="status" aria-live="polite"></div>
                    <div class="secure">
                        <span class="secure-dot" aria-hidden="true"></span>
                        <span>{{ __('256-bit encryption · PCI-compliant Elavon Hosted Fields') }}</span>
                    </div>
                </form>
            </section>
        </div>
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
            }

            function markFieldError(fieldType, hasError) {
                const map = {
                    cardholderName: 'cardholder-name',
                    cardNumber: 'card-number',
                    cardExpirationDate: 'card-exp-date',
                    cardCvv: 'card-cvv',
                };
                const el = document.getElementById(map[fieldType]);
                if (el) {
                    el.classList.toggle('has-error', !!hasError);
                }
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
                if (message.fieldType && message.isFocused === true && elavonHostedFields) {
                    elavonHostedFields.removeClass(message.fieldType, 'field-error');
                    markFieldError(message.fieldType, false);
                }

                if (message.type === 'error') {
                    submitting = false;
                    payButton.disabled = false;
                    setStatus(message.message || 'Something went wrong. Please try again.', 'error');
                    return;
                }

                if (message.type === 'transactionCreated') {
                    if (message.isAuthorized) {
                        setStatus('Payment approved. Confirming…', 'ok');
                        finalizeOnServer()
                            .then((payload) => { window.location.href = payload.redirect_url; })
                            .catch((error) => {
                                submitting = false;
                                payButton.disabled = false;
                                setStatus(error.message || 'Unable to confirm payment.', 'error');
                            });
                        return;
                    }

                    submitting = false;
                    payButton.disabled = false;
                    setStatus(message.canRetry ? 'Card declined. Please try another card.' : 'Card declined.', 'error');
                }
            }

            function onReady(error) {
                skeleton.classList.add('hidden');
                form.classList.remove('hidden');

                if (error) {
                    setStatus('Unable to load secure card fields. Ensure this domain is allowed in Elavon for Hosted Fields.', 'error');
                    return;
                }

                payButton.disabled = false;
                setStatus('Ready for payment.', 'info');
            }

            function initializeHostedFields() {
                if (!window.ElavonHostedFields) {
                    setStatus('Elavon CheckoutJS script failed to load.', 'error');
                    skeleton.classList.add('hidden');
                    form.classList.remove('hidden');
                    return;
                }

                elavonHostedFields = new window.ElavonHostedFields({
                    sessionId: sessionId,
                    fields: {
                        cardholderName: { wrapperId: 'cardholder-name', className: 'field-height' },
                        cardNumber: { wrapperId: 'card-number', className: 'field-height' },
                        cardExpirationDate: { wrapperId: 'card-exp-date', className: 'field-height', placeholder: 'MM/YY' },
                        cardCvv: { wrapperId: 'card-cvv', className: 'field-height', placeholder: 'CVV' },
                    },
                    styles: {
                        'input': {
                            'width': '100%',
                            'height': '48px',
                            'padding': '0 14px',
                            'font-size': '16px',
                            'font-family': 'DM Sans, sans-serif',
                            'color': '#0b1f33',
                            'border': '0',
                            'outline': 'none',
                            'background': 'transparent',
                            'box-sizing': 'border-box',
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

                submitting = true;
                payButton.disabled = true;
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
