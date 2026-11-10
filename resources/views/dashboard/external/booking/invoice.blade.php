<x-iziipay>
    <style>
        /* --- CSS Variables for Invoice Design (NEW STYLES) --- */
        :root {
            --invoice-bg: #2a6495;
            /* Main page background */
            --card-bg: #ffffff;
            /* Invoice card background */
            --text-color-dark: #313d5a;
            /* Darker text for titles/important info */
            --text-color-medium: #6c757d;
            /* Medium grey for general text */
            --text-color-light: #f1f3f6;
            /* Light text (e.g., for badge text) */
            --border-color-light: #e0e0e0;
            /* Light border for tables/sections */

            /* Buttons */
            --button-primary-bg: #04345c;
            /* Pay Invoice button */
            --button-primary-color: #ffffff;
            --button-primary-hover-bg: #032a49;
            --button-success-bg: #059669;
            /* Download button */
            --button-success-color: #ffffff;
            --button-success-hover-bg: #157347;
            --button-secondary-bg: #e9ecef;
            /* Secondary/Print button */
            --button-secondary-color: #6b7280;
            --button-secondary-hover-bg: #d4d8db;

            /* Status Badges */
            --badge-red-bg: #ef4444;
            /* Unpaid status badge background */
            --badge-color: #ffffff;
            --badge-success-bg: #198754;
            /* Paid status badge background */
            --section-bg-light: #e5e7eb;
            /* Background for invoice details table & summary */
        }

        /* --- General Body and Centering --- */
        /* Note: The main body background is applied to the page body via the parent view,
      but the styles below are for the card elements */
        body {
            background-color: var(--invoice-bg);
            font-family: "Inter", sans-serif;
        }

        .container {
            padding: 2rem 0;
        }

        /* --- Invoice Card Styling --- */
        .invoice-card {
            background-color: var(--card-bg);
            border-radius: 1rem;
            max-width: 800px;
            width: 100%;
            color: var(--text-color-dark);
        }

        /* --- Status and Meta Information --- */
        .invoice-status-label {
            color: var(--text-color-medium);
            font-size: 0.9rem;
            margin-right: 0.5rem;
        }

        .invoice-status-badge-unpaid {
            background-color: var(--badge-red-bg) !important;
            color: var(--badge-color) !important;
            padding: 0.5em 0.8em;
            border-radius: 0.3rem;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .invoice-status-badge-paid {
            background-color: var(--badge-success-bg) !important;
            color: var(--badge-color) !important;
            padding: 0.5em 0.8em;
            border-radius: 0.3rem;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .invoice-meta-top div {
            font-size: 0.9rem;
            color: var(--text-color-medium);
            margin-bottom: 0.25rem;
        }

        /* --- Section Titles --- */
        .invoice-section-title {
            color: var(--text-color-dark);
            font-weight: bold;
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }

        .invoice-text {
            color: var(--text-color-medium);
            font-size: 0.95rem;
        }

        /* --- Invoice Details Table --- */
        .invoice-details-table {
            border: 1px solid var(--border-color-light);
            border-radius: 0.5rem;
            overflow: hidden;
        }

        .invoice-details-table .table {
            margin-bottom: 0;
        }

        .invoice-table-header {
            background-color: var(--section-bg-light);
            border-bottom: 1px solid var(--border-color-light);
        }

        .invoice-table-th {
            color: var(--text-color-dark);
            font-weight: 600;
            padding: 0.75rem 1rem;
            font-size: 0.85rem;
        }

        .invoice-table-td {
            color: var(--text-color-medium);
            padding: 0.75rem 1rem;
            font-size: 0.9rem;
            border-color: var(--border-color-light);
            border-bottom: 1px solid var(--border-color-light);
        }

        .invoice-details-table tbody {
            background-color: var(--section-bg-light);
            /* Use card background for white table body */
        }

        .invoice-details-table tbody tr:last-child td {
            border-bottom: none;
        }

        /* --- Summary Section --- */
        .invoice-summary {
            background-color: var(--section-bg-light);
            border-radius: 0.5rem;
            border: 1px solid var(--border-color-light);
            font-size: 1rem;
        }

        .invoice-total {
            font-size: 1.15rem;
            color: var(--text-color-dark);
        }

        /* --- Action Buttons --- */
        .btn-invoice-primary {
            background-color: var(--button-primary-bg);
            color: var(--button-primary-color);
            border-color: var(--button-primary-bg);
            border-radius: 0.5rem;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
        }

        .btn-invoice-primary:hover {
            background-color: var(--button-primary-hover-bg);
            border-color: var(--button-primary-hover-bg);
            color: var(--button-primary-color);
        }

        .btn-invoice-success {
            background-color: var(--button-success-bg);
            color: var(--button-success-color);
            border-color: var(--button-success-bg);
            border-radius: 0.5rem;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
        }

        .btn-invoice-success:hover {
            background-color: var(--button-success-hover-bg);
            border-color: var(--button-success-hover-bg);
            color: var(--button-success-color);
        }

        .btn-invoice-secondary {
            background-color: var(--button-secondary-bg);
            color: var(--button-secondary-color);
            border-color: var(--button-secondary-bg);
            border-radius: 0.5rem;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
        }

        .btn-invoice-secondary:hover {
            background-color: var(--button-secondary-hover-bg);
            border-color: var(--button-secondary-hover-bg);
            color: var(--button-secondary-color);
        }

        /* --- Footer Section --- */
        .invoice-footer {
            background-color: var(--section-bg-light);
            border-radius: 0.5rem;
            border: 1px solid var(--border-color-light);
            color: var(--text-color-dark);
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .invoice-card {
                margin: 1rem;
            }

            .invoice-meta-top {
                text-align: start !important;
                margin-top: 1rem;
            }
        }

        /* KEEP THE PRINT STYLES FROM THE OLD CODE */
        @media print {

            .btn,
            .no-print {
                display: none !important;
            }
        }
    </style>

    <div class="">
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="{{ route('external.dashboard') }}" class="text-light">
                        <i class="fas fa-home me-1"></i>Dashboard
                    </a>
                </li>
                <li class="breadcrumb-item">
                    <a href="{{ route('external.booking.index') }}" class="text-light">
                        <i class="fas fa-calendar-alt me-1"></i>Booking History
                    </a>
                </li>
                <li class="breadcrumb-item active text-light" aria-current="page">
                    <i class="fas fa-file-invoice me-1"></i>Invoice #{{ $externalBooking->booking_number }}
                </li>
              
            </ol>
        </nav>
        <div class="row justify-content-center">
            <div class="col-lg-12 d-flex justify-content-center">

                <div class="invoice-card p-4 shadow-lg">

                    <div class="row mb-4 align-items-center">
                        <div class="col-6">
                            <span class="invoice-status-label">{{ __('words.payment_status') }}:</span>
                            <span
                                class="badge {{ $externalBooking->payment_status == 'PAID' ? 'invoice-status-badge-paid' : 'invoice-status-badge-unpaid' }}">
                                {{ __($externalBooking->paymentStatus() ?? 'words.pending') }}
                            </span>
                            <div class="mt-2 invoice-meta-top">
                                <div>{{ __('words.transaction_id') }}: {{ $externalBooking->elavon_transaction_id }}</div>
                                <div>{{ __('words.payment_id') }}: {{ $externalBooking->payment_id }}</div>
                            </div>
                        </div>
                        <div class="col-6 text-end invoice-meta-top">
                            <div>{{ __('words.date') }}: {{ $externalBooking->created_at->format('Y-m-d') }}</div>
                            <div>{{ __('words.payment_terms') }}: {{ __('words.immediate') }}</div>
                            <div>{{ __('words.invoice') }} #: {{ $externalBooking->id }}</div>
                          
                        </div>
                    </div>

                    <h5 class="invoice-section-title">{{ __('words.invoice_to') }}:</h5>
                    {{-- <p class="mb-1 invoice-text">John Smith</p> --}}
                    <p class="mb-4 invoice-text">{{ $externalBooking->phone_number }}</p>

                    <h5 class="invoice-section-title">{{ __('words.invoice_details') }}</h5>
                    <div class="table-responsive invoice-details-table mb-4">
                        <table class="table table-borderless align-middle mb-0">
                            <thead class="invoice-table-header">
                                <tr>
                                    <th scope="col" class="invoice-table-th">{{ __('words.description') }}</th>
                                    <th scope="col" class="text-center invoice-table-th">{{ __('words.qty') }}</th>
                                    <th scope="col" class="invoice-table-th">{{ __('words.unit') }}</th>
                                    <th scope="col" class="text-end invoice-table-th">{{ __('words.price') }}</th>
                                    <th scope="col" class="text-end invoice-table-th">{{ __('words.vat') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="invoice-table-td">
                                        {{ __('words.booking_number') }}<br /><small
                                            class="invoice-text">{{ $externalBooking->booking_number }}</small>
                                    </td>
                                    <td class="text-center invoice-table-td">1</td>
                                    <td class="invoice-table-td">{{ __('words.taxi') }}</td>
                                    <td class="text-end invoice-table-td">{{ Iziibuy::price($externalBooking->total, currency: $externalBooking->currency) }}
                                    </td>
                                    <td class="text-end invoice-table-td">{{ $externalBooking->taxPercentage() }} %
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <h5 class="invoice-section-title mb-3">{{ __('words.summary') }}</h5>
                    <div class="row invoice-summary p-3 mb-5">
                        <div class="col-6"></div>
                        <div class="col-6">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="invoice-text">{{ __('words.net') }}:</span>
                                <span class="invoice-text">{{ Iziibuy::price($externalBooking->subtotal, currency: $externalBooking->currency) }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="invoice-text">{{ __('words.vat') }}
                                    {{ $externalBooking->taxPercentage() }}%:</span>
                                <span class="invoice-text">{{ Iziibuy::price($externalBooking->tax, currency: $externalBooking->currency) }}</span>
                            </div>
                            @if ($externalBooking->platform_percentage > 0)
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="invoice-text">{{ __('Platform commission') }} ({{ rtrim(rtrim(number_format($externalBooking->platform_percentage, 2), '0'), '.') }}%):</span>
                                    <span class="invoice-text">{{ number_format((float) $externalBooking->platform_amount, 2) }} {{ $externalBooking->currency }}</span>
                                </div>
                            @endif
                            <div class="d-flex justify-content-between fw-bold invoice-total">
                                <span>{{ __('words.total') }}:</span>
                                <span>{{ Iziibuy::price($externalBooking->total, currency: $externalBooking->currency) }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mb-5 no-print">
                        @if ($externalBooking->payment_status != 'PAID')
                            <a href="{{ route('external-payment', $externalBooking) }}" type="button"
                                class="btn btn-invoice-primary">
                                {{ __('words.pay_invoice') }}
                            </a>
                        @endif


                        <button type="button" class="btn btn-invoice-secondary"
                            onclick="window.print()">{{ __('words.print') }}</button>
                    </div>

                    @if ($externalBooking->payment_status == 'PAID')
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <strong>{{ __('words.payment_completed') }}</strong><br>
                                    <small>{{ __('words.thank_you_for_your_payment_booking_confirmed') }}</small>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if ($externalBooking->payment_url && $externalBooking->payment_status != 'PAID')
                        <div class="mt-4 info-card no-print">
                            <h6 class="mb-3 fw-bold text-light">
                                <i class="fas fa-link me-2"></i>{{ __('words.payment_link') }}
                            </h6>
                            <div class="input-group">
                                <input type="text" class="form-control" value="{{ $externalBooking->payment_url }}"
                                    readonly id="paymentLink">
                                <button class="btn btn-outline-secondary" type="button" onclick="copyPaymentLink()"
                                    title="{{ __('words.copy_payment_link') }}">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                            <small
                                class="text-light mt-2 d-block">{{ __('words.share_link_to_complete_payment') }}</small>
                        </div>
                    @endif


                    <div class="invoice-footer p-4">
                        @php

                            $companyAddress = $paymentMethodAccess->company_address;
                            $fullAddress = '';
                            if ($companyAddress) {
                                $parts = [];
                                if (!empty($companyAddress->street)) {
                                    $parts[] = $companyAddress->street;
                                }
                                if (!empty($companyAddress->zip) && !empty($companyAddress->city)) {
                                    $parts[] = $companyAddress->zip . ' ' . $companyAddress->city;
                                } elseif (!empty($companyAddress->zip)) {
                                    $parts[] = $companyAddress->zip;
                                } elseif (!empty($companyAddress->city)) {
                                    $parts[] = $companyAddress->city;
                                }
                                $fullAddress = implode(', ', $parts);
                            }
                        @endphp

                        <h6 class="fw-bold mb-2">{{ $paymentMethodAccess->company_name }}</h6>

                        <p class="mb-1 invoice-text">{{ $fullAddress }}</p>
                        <p class="mb-1 invoice-text">{{ __('words.phone') }}:
                            {{ @$paymentMethodAccess?->company_address?->contact_number ?? 'N/A' }}</p>

                        <p class="mb-1 invoice-text">{{ __('words.mail') }}: {{ $paymentMethodAccess?->company_email }}
                        </p>

                        <p class="mb-0 invoice-text">{{ __('words.org_nr') }}:
                            {{ @$paymentMethodAccess?->company_registration ?? 'N/A' }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function copyPaymentLink() {
            const paymentLink = document.getElementById('paymentLink').value;

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(paymentLink).then(() => {
                    Swal.fire({
                        icon: 'success',
                        title: 'Copied!',
                        text: 'Payment link copied to clipboard',
                        timer: 2000,
                        showConfirmButton: false
                    });
                }).catch(() => {
                    fallbackCopy(paymentLink);
                });
            } else {
                fallbackCopy(paymentLink);
            }
        }

        function fallbackCopy(text) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.left = '-9999px';
            document.body.appendChild(textarea);
            textarea.focus();
            textarea.select();

            try {
                document.execCommand('copy');
                Swal.fire({
                    icon: 'success',
                    title: 'Copied!',
                    text: 'Payment link copied to clipboard',
                    timer: 2000,
                    showConfirmButton: false
                });
            } catch (err) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to copy payment link'
                });
            }

            document.body.removeChild(textarea);
        }

        // Print functionality
        document.querySelector('.btn-invoice-secondary[onclick="window.print()"]').addEventListener('click', function() {
            window.print();
        });
    </script>
</x-iziipay>
