<!DOCTYPE html>
<html lang="en">

<head>
    <meta content="text/html; charset=utf-8" http-equiv="Content-Type" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>{{ __('Payment due for :month', ['month' => $monthLabel]) }}</title>
</head>

<body style="background-color: #f8f9fc; margin: 0; padding: 0; -webkit-text-size-adjust: none; text-size-adjust: none;">
    <table border="0" cellpadding="0" cellspacing="0" role="presentation"
        style="background-color: #f8f9fc; margin: 0; padding: 0;" width="100%">
        <tr>
            <td align="center" style="padding: 32px 16px;">
                <table border="0" cellpadding="0" cellspacing="0" role="presentation"
                    style="background-color: #ffffff; border-radius: 12px; color: #44464a; font-family: Nunito, Arial, Helvetica, sans-serif; max-width: 680px; overflow: hidden;"
                    width="100%">
                    <tr>
                        <td align="center" style="padding: 32px 32px 0;">
                            <img src="{{ secure_asset('images/landing/logo.png') }}" alt="2iZii"
                                style="border: 0; display: block; height: auto; max-width: 200px; width: 200px;" />
                        </td>
                    </tr>
                    <tr>
                        <td style="font-size: 16px; line-height: 1.6; padding: 24px 40px 32px;">
                            <p style="margin: 0 0 18px;">Hello {{ filled($row['name'] ?? null) ? $row['name'] : 'there' }},</p>

                            <p style="margin: 0 0 18px;">We hope all is well with you.</p>

                            <p style="margin: 0 0 24px;">This is a friendly reminder that your
                                {{ mb_strtolower((string) ($row['type_label'] ?? 'account')) }}
                                <strong>{{ $row['company'] ?? '' }}</strong> has an outstanding balance for
                                {{ $monthLabel }}.</p>

                            <table border="0" cellpadding="0" cellspacing="0" role="presentation"
                                style="margin: 0 0 24px; background-color: #f8f9fc; border-radius: 8px; width: 100%;">
                                <tr>
                                    <td style="padding: 14px 16px; font-size: 15px;">Monthly price</td>
                                    <td align="right" style="padding: 14px 16px; font-size: 15px;">
                                        {{ number_format((float) ($row['monthly_price'] ?? 0), 2) }} NOK</td>
                                </tr>
                                <tr>
                                    <td style="padding: 14px 16px; font-size: 16px; font-weight: 700; border-top: 1px solid #e5e7eb;">
                                        Amount due</td>
                                    <td align="right"
                                        style="padding: 14px 16px; font-size: 16px; font-weight: 700; border-top: 1px solid #e5e7eb;">
                                        {{ number_format((float) ($row['due_amount'] ?? 0), 2) }} NOK</td>
                                </tr>
                            </table>

                            @if (filled($row['last_paid_at'] ?? null))
                                <p style="margin: 0 0 18px; padding: 16px; background-color: #e3f2fd; border-radius: 8px;">
                                    <strong>Last payment:</strong>
                                    {{ \Illuminate\Support\Carbon::parse($row['last_paid_at'])->format('d M Y') }}
                                </p>
                            @endif

                            <p style="margin: 0 0 18px;">To ensure uninterrupted access to all features in 2iZii and
                                iZiiBuy, please make sure your payment card details are up to date so the next charge
                                can go through.</p>

                            <p style="margin: 0 0 18px;">If you have already paid, you can ignore this message. If you
                                have any questions or need help, you are most welcome to contact us. We are happy to
                                help.</p>

                            <p style="margin: 0;">Best regards,</p>
                            <p style="margin: 0;">2iZii / iZiiBuy</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>
