<x-iziipay>

    <style>
        :root {
            --page-bg: #2a6495;
            --page-bg-end: #295e78;
            --panel-bg: #04345c;
            --status-approved: #39b54a;
            --status-pending: #f5a623;
            --status-pending-text: #3a2a08;
            --status-declined: #ef4b4b;
            --white: #ffffff;
            --header-text: #0b4e6a;
            --cell-text: #0e4b67;
            --table-sep: #0f5b78;
            --txn-color: #2a6495;
            --pagination-border: #ccc;
            --pagination-active-bg: #093645;
            --pagination-active-color: #fff;
            --btn-bg: #fff;
            --btn-color: #2a6495;
            --btn-hover-bg: #2a6495;
            --btn-hover-color: #fff;
        }

        body {
            margin: 0;
            font-family: "Inter", sans-serif;
            background: linear-gradient(180deg, var(--page-bg), var(--page-bg-end) 120%);
            color: var(--white);
        }

        .page-wrap {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            /* padding: 40px 20px; */
        }

        .panel {
            width: 100%;
            max-width: 1100px;
            background: var(--panel-bg);
            border-radius: 12px;
            padding: 34px;
        }

        .panel-header h1 {
            font-weight: 700;
            font-size: 20px;
            margin: 0 0 10px 0;
        }

        .panel-header p {
            color: #accbd8;
            font-size: 13px;
            margin: 0;
        }

        .panel-header a {
            text-decoration: none;
        }

        .table-card {
            background: var(--white);
            border-radius: 6px;
            overflow: hidden;
        }

        table.custom-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px !important;
            color: var(--cell-text);
        }

        thead th {
            padding: 16px;
            font-weight: 600;
            color: var(--header-text);
            border-bottom: 1px solid var(--table-sep);
            text-align: left;
            font-size: 14px
        }

        tbody td {
            padding: 16px;
            border-bottom: 1px solid var(--table-sep);
            font-weight: 500;
            font-size: 14px;
            color: var(--cell-text);
            text-align: left !important;
        }

        .col-amount {
            font-weight: 600;
            text-align: center;
            word-break: keep-all;
        }

        .col-status {
            text-align: center;
        }

        .col-txn {
            text-align: right;
            color: var(--txn-color);
        }

        .badge-status {
            padding: 6px 14px;
            border-radius: 999px;
            color: var(--white);
            font-weight: 600;
            font-size: 13px;
        }

        .badge-approved {
            background: var(--status-approved);
        }

        .badge-pending {
            background: var(--status-pending);
            color: var(--status-pending-text);
        }

        .badge-declined {
            background: var(--status-declined);
        }

        .btn-outline-light {
            border-color: transparent !important;
            background: transparent !important;
            padding: 0 !important;
            line-height: 0;
        }

        .btn-outline-light:hover,
        .btn-outline-light:focus {
            background-color: transparent !important;
            border-color: transparent !important;
            opacity: 0.88;
        }

        .handling-actions {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            flex-wrap: nowrap;
        }

        .handling-actions img {
            display: block;
            width: 36px;
            height: 36px;
        }

        .footer-section {
            margin-top: 20px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .custom-pagination {
            display: flex;
            justify-content: center;
            gap: 6px;
            flex-wrap: wrap;
        }

        .custom-pagination .page-link {
            border-radius: 8px;
            min-width: 34px;
            height: 34px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--pagination-border);
            color: var(--txn-color);
            font-weight: 400;
        }

        .custom-pagination .active .page-link {
            background: var(--pagination-active-bg);
            color: var(--pagination-active-color);
        }

        .actions {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .actions a {
            text-decoration: none;
        }

        .btn-pill {
            padding: 10px 18px;
            border-radius: 12px;
            border: none;
            font-weight: 600;
            background: var(--btn-bg);
            color: var(--btn-color);
            min-width: 120px;
            transition: all 0.3s ease;
        }

        .btn-pill:hover {
            background: var(--btn-hover-bg);
            color: var(--btn-hover-color);
            cursor: pointer;
        }

        .btn-pill.active {
            background: var(--btn-hover-bg);
            color: var(--btn-hover-color);
        }

        .btn-pill.active:hover {
            background: var(--btn-bg);
            color: var(--btn-color);
        }

        @media (max-width: 600px) {
            .btn-pill {
                min-width: 100%;
            }
        }
    </style>
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="{{ route('external.dashboard') }}" class="text-light">
                    <i class="fas fa-home me-1"></i>Dashboard
                </a>
            </li>
            <li class="breadcrumb-item active text-light" aria-current="page">
                <i class="fas fa-calendar-alt me-1"></i>Booking History
            </li>
        </ol>
    </nav>
    <div class="page-wrap">

        <div class="panel">
            <div class="d-flex justify-content-between align-items-center flex-column flex-md-row">
                <div class="panel-header mb-4">
                    <h1>{{ __('words.transaction_history') }}</h1>
                    <p>{{ __('words.transaction_history_overview') }}</p>
                </div>
                <div class="panel-header mb-3">
                    <a href="{{ route('external.booking.create') }}" class="btn-pill"> <i class="fas fa-plus me-1"></i>
                        {{ __('words.new_booking') }}</a>
                </div>
            </div>

            <div class="table-card">
                <div style="overflow-x: auto">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                {{-- <th>ID</th> --}}
                                <th>{{ __('words.booking_number') }}</th>
                                <th>{{ __('words.phone_number') }}</th>
                                <th>{{ __('words.amount') }}</th>
                                <th>{{ __('words.status') }}</th>
                                <th>{{ __('words.transaction_id') }}</th>
                                <th>{{ __('words.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($bookings as $booking)
                                {{-- @dd($booking) --}}
                                <tr>
                                    {{-- <td>{{ $booking->id }}</td> --}}
                                    <td>{{ $booking->booking_number }}</td>
                                    <td>{{ $booking->phone_number }} </td>
                                    <td class="col-amount">
                                        {{ Iziibuy::price($booking->total, currency: $booking->currency) }}</td>
                                    <td class="col-status">

                                        <span
                                            class="badge-status
    {{ $booking->status == 'PENDING' ? 'badge-pending' : ($booking->status == 'DECLINED' ? 'badge-declined' : 'badge-approved') }}">
                                            {{ __($booking->status()) }}
                                        </span>
                                    </td>
                                    <td class="col-txn">{{ $booking->elavon_transaction_id  ? $booking->elavon_transaction_id : 'N/A' }}</td>
                                    <td>
                                        <div class="btn-group handling-actions" role="group">
                                            @if ($booking->status === 'PENDING')
                                                <button type="button"
                                                    class="btn btn-sm btn-outline-light btn-send-sms p-0"
                                                    data-url="{{ route('external.booking.send-sms', $booking) }}"
                                                    data-phone="{{ $booking->phone_number }}"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#smsModal"
                                                    title="Sms fra oversikt">
                                                    <img src="{{ asset('assets/dashboard/icon-sms.svg') }}" width="36"
                                                        height="36" alt="Sms fra oversikt">
                                                </button>
                                                <button type="button"
                                                    class="btn btn-sm btn-outline-light btn-copy-url p-0"
                                                    data-ensure-url="{{ route('external.booking.ensure-payment-link', $booking) }}"
                                                    title="{{ __('words.copy_payment_link') }}">
                                                    <img src="{{ asset('assets/dashboard/icon-copy-link.svg') }}"
                                                        width="36" height="36"
                                                        alt="{{ __('words.copy_payment_link') }}">
                                                </button>
                                                <button type="button"
                                                    class="btn btn-sm btn-outline-light btn-send-email p-0"
                                                    data-url="{{ route('external.booking.send-email', $booking) }}"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#emailModal"
                                                    title="Epost fra system">
                                                    <img src="{{ asset('assets/dashboard/icon-email.svg') }}" width="36"
                                                        height="36" alt="Epost fra system">
                                                </button>
                                            @endif
                                            <a href="{{ route('external.booking.invoice', $booking) }}"
                                                class="btn btn-sm btn-outline-light p-0"
                                                title="{{ __('words.view_invoice') }}">
                                                <img src="{{ asset('assets/dashboard/icon-invoice.svg') }}" width="36"
                                                    height="36" alt="{{ __('words.view_invoice') }}">
                                            </a>
                                            <button type="button"
                                                class="btn btn-sm btn-outline-light btn-copy-booking p-0"
                                                data-booking-number="{{ $booking->booking_number }}"
                                                title="Copy">
                                                <img src="{{ asset('assets/dashboard/icon-copy.svg') }}" width="36"
                                                    height="36" alt="Copy">
                                            </button>
                                            {{-- <x-helpers.delete :url="route('external.booking.destroy', $booking)" :id="$booking->id" /> --}}
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center">{{ __('words.no_bookings_found') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="footer-section">
                {{ $bookings->links('vendor.pagination.custom') }}

                <div class="actions">
                    <a href="{{ route('external.booking.export') }}"
                        class="btn-pill active">{{ __('words.export_csv') }}</a>

                    <button class="btn-pill" data-bs-toggle="modal"
                        data-bs-target="#filterModal">{{ __('words.filter') }}</button>

                    <a href="{{ url()->current() }}" class="btn-pill">{{ __('words.refresh') }}</a>

                    <button class="btn-pill" data-bs-toggle="modal"
                        data-bs-target="#dateFilterModal">{{ __('words.date_filter') }}</button>

                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="filterModal" tabindex="-1" aria-labelledby="filterModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="GET" action="{{ route('external.booking.index') }}">
                <div class="modal-content shadow-lg border-0 rounded-4">

                    <div class="modal-header border-bottom-0 pt-4 px-4 pb-2">
                        <h5 class="modal-title fw-bold text-dark" id="filterModalLabel">
                            <i class="bi bi-funnel-fill me-2 text-primary"></i>{{ __('words.filter_bookings') }}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                            aria-label="{{ __('words.close') }}"></button>
                    </div>

                    <div class="modal-body px-4 py-3">

                        <div class="mb-3">
                            <label for="booking_number"
                                class="form-label text-muted small mb-1">{{ __('words.booking_number') }}</label>
                            <input type="text" class="form-control form-control-lg rounded-3" id="booking_number"
                                name="booking_number" placeholder="{{ __('words.e.g.,_12324') }}"
                                aria-label="{{ __('words.booking_number') }}">
                        </div>

                        <div class="mb-3">
                            <label for="phone_number"
                                class="form-label text-muted small mb-1">{{ __('words.phone_number') }}</label>
                            <input type="tel" class="form-control form-control-lg rounded-3" id="phone_number"
                                name="phone_number" placeholder="{{ __('words.e.g.,_+1234567890') }}"
                                aria-label="{{ __('words.phone_number') }}">
                        </div>

                        <div class="mb-4">
                            <label for="status"
                                class="form-label text-muted small mb-1">{{ __('words.booking_status') }}</label>
                            <select name="status" id="status" class="form-select form-select-lg rounded-3">
                                <option value="" selected>— {{ __('words.any_status') }} —</option>

                                <option value="PENDING">{{ __('words.pending') }}</option>
                                {{-- <option value="CANCELLED">{{ __('words.cancelled') }}</option> --}}
                                <option value="COMPLETED">{{ __('words.completed') }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="modal-footer justify-content-between border-top-0 pt-2 pb-4 px-4">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4"
                            data-bs-dismiss="modal">
                            <i class="fa fa-times me-1"></i> {{ __('words.reset') }}
                        </button>

                        <button type="submit" class="btn btn-primary rounded-pill px-4 shadow-sm">
                            <i class="fa fa-search me-1"></i> {{ __('words.apply_filters') }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <div class="modal fade" id="dateFilterModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="GET" action="{{ route('external.booking.index') }}">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title text-dark">{{ __('words.filter_by_date') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label">{{ __('words.from_date') }}</label>
                        <input type="date" class="form-control mb-2" name="from_date">

                        <label class="form-label">{{ __('words.to_date') }}</label>
                        <input type="date" class="form-control mb-2" name="to_date">
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" data-bs-dismiss="modal"> <i class="fa fa-times me-1"></i>
                            {{ __('words.reset') }}</button>
                        <button class="btn btn-primary" type="submit"> <i class="fa fa-search me-1"></i>
                            {{ __('words.apply') }}</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <div class="modal fade" id="smsModal" tabindex="-1" aria-labelledby="smsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-lg border-0 rounded-4">
                <div class="modal-header border-bottom-0 pt-4 px-4 pb-2">
                    <h5 class="modal-title fw-bold text-dark" id="smsModalLabel">
                        Sms fra oversikt
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body px-4 py-3">
                    <label for="overview_phone" class="form-label text-muted small mb-1">Phone number</label>
                    <input type="tel" class="form-control form-control-lg rounded-3" id="overview_phone"
                        placeholder="+4712345678" required>
                    <div class="invalid-feedback">Please enter a phone number.</div>
                </div>
                <div class="modal-footer justify-content-between border-top-0 pt-2 pb-4 px-4">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">
                        Cancel
                    </button>
                    <button type="button" class="btn btn-primary rounded-pill px-4 shadow-sm" id="btn-confirm-send-sms">
                        Send
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="emailModal" tabindex="-1" aria-labelledby="emailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-lg border-0 rounded-4">
                <div class="modal-header border-bottom-0 pt-4 px-4 pb-2">
                    <h5 class="modal-title fw-bold text-dark" id="emailModalLabel">
                        Epost fra system
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body px-4 py-3">
                    <label for="overview_email" class="form-label text-muted small mb-1">Email</label>
                    <input type="email" class="form-control form-control-lg rounded-3" id="overview_email"
                        placeholder="customer@example.com" required>
                    <div class="invalid-feedback">Please enter a valid email address.</div>
                </div>
                <div class="modal-footer justify-content-between border-top-0 pt-2 pb-4 px-4">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">
                        Cancel
                    </button>
                    <button type="button" class="btn btn-primary rounded-pill px-4 shadow-sm" id="btn-confirm-send-email">
                        Send
                    </button>
                </div>
            </div>
        </div>
    </div>
    <script>
        var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        var pendingSmsUrl = null;
        var pendingEmailUrl = null;

        function postJson(url, body) {
            return fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: body ? JSON.stringify(body) : JSON.stringify({})
            }).then(function(response) {
                return response.json().then(function(data) {
                    return { ok: response.ok, status: response.status, data: data };
                }).catch(function() {
                    return { ok: response.ok, status: response.status, data: {} };
                });
            });
        }

        function hideModal(modalId) {
            var modalEl = document.getElementById(modalId);
            if (modalEl && window.bootstrap) {
                var modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
                modal.hide();
            }
        }

        function showCopiedToast(text) {
            Swal.fire({
                icon: 'success',
                title: 'Copied!',
                text: text || 'Copied to clipboard',
                timer: 2000,
                showConfirmButton: false
            });
        }

        function copyText(text, successText) {
            if (navigator.clipboard && window.isSecureContext) {
                return navigator.clipboard.writeText(text).then(function() {
                    showCopiedToast(successText);
                }).catch(function() {
                    fallbackCopy(text, successText);
                });
            }

            fallbackCopy(text, successText);
            return Promise.resolve();
        }

        document.addEventListener('click', function(event) {
            var copyBookingTrigger = event.target.closest('.btn-copy-booking');
            if (copyBookingTrigger) {
                event.preventDefault();
                var bookingNumber = copyBookingTrigger.getAttribute('data-booking-number') || '';
                if (!bookingNumber) return;
                copyText(bookingNumber, 'Booking number copied to clipboard');
                return;
            }

            var smsTrigger = event.target.closest('.btn-send-sms');
            if (smsTrigger) {
                pendingSmsUrl = smsTrigger.getAttribute('data-url');
                var phoneInput = document.getElementById('overview_phone');
                if (phoneInput) {
                    phoneInput.value = smsTrigger.getAttribute('data-phone') || '';
                    phoneInput.classList.remove('is-invalid');
                }
                return;
            }

            var emailTrigger = event.target.closest('.btn-send-email');
            if (emailTrigger) {
                pendingEmailUrl = emailTrigger.getAttribute('data-url');
                var emailInput = document.getElementById('overview_email');
                if (emailInput) {
                    emailInput.value = '';
                    emailInput.classList.remove('is-invalid');
                }
                return;
            }

            var trigger = event.target.closest('.btn-copy-url');
            if (!trigger) return;

            event.preventDefault();
            var ensureUrl = trigger.getAttribute('data-ensure-url') || '';
            if (!ensureUrl) return;

            trigger.disabled = true;
            postJson(ensureUrl).then(function(result) {
                if (result.ok && result.data.success && result.data.message) {
                    return copyText(result.data.message, 'Payment message copied to clipboard');
                }

                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: (result.data && result.data.message) || 'Failed to get payment message'
                });
            }).catch(function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to get payment message'
                });
            }).finally(function() {
                trigger.disabled = false;
            });
        });

        document.getElementById('btn-confirm-send-sms')?.addEventListener('click', function() {
            var phoneInput = document.getElementById('overview_phone');
            var phone = (phoneInput?.value || '').trim();
            if (!phone) {
                phoneInput.classList.add('is-invalid');
                return;
            }
            phoneInput.classList.remove('is-invalid');

            if (!pendingSmsUrl) return;

            var sendBtn = this;
            sendBtn.disabled = true;
            postJson(pendingSmsUrl, { phone_number: phone }).then(function(result) {
                if (result.ok && result.data.success) {
                    hideModal('smsModal');
                    Swal.fire({
                        icon: 'success',
                        title: 'Sent!',
                        text: result.data.message || 'SMS sent successfully.',
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: (result.data && result.data.message) || 'Failed to send SMS'
                    });
                }
            }).catch(function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to send SMS'
                });
            }).finally(function() {
                sendBtn.disabled = false;
            });
        });

        document.getElementById('btn-confirm-send-email')?.addEventListener('click', function() {
            var emailInput = document.getElementById('overview_email');
            var email = (emailInput?.value || '').trim();
            if (!email || !emailInput.checkValidity()) {
                emailInput.classList.add('is-invalid');
                return;
            }
            emailInput.classList.remove('is-invalid');

            if (!pendingEmailUrl) return;

            var sendBtn = this;
            sendBtn.disabled = true;
            postJson(pendingEmailUrl, { email: email }).then(function(result) {
                if (result.ok && result.data.success) {
                    hideModal('emailModal');
                    Swal.fire({
                        icon: 'success',
                        title: 'Sent!',
                        text: result.data.message || 'Email sent successfully.',
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: (result.data && result.data.message) || 'Failed to send email'
                    });
                }
            }).catch(function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to send email'
                });
            }).finally(function() {
                sendBtn.disabled = false;
            });
        });

        function fallbackCopy(text, successText) {
            var textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.left = '-9999px';
            document.body.appendChild(textarea);
            textarea.focus();
            textarea.select();
            try {
                document.execCommand('copy');
                showCopiedToast(successText);
            } catch (e) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to copy'
                });
            }
            document.body.removeChild(textarea);
        }
    </script>
</x-iziipay>
