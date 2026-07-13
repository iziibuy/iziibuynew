<!DOCTYPE html>
<html lang="en">

<head>
    <meta content="text/html; charset=utf-8" http-equiv="Content-Type" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>2iZii payment card registration</title>
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
                            <p style="margin: 0 0 18px;">Hello {{ $name !== '' ? $name : 'there' }},</p>

                            <p style="margin: 0 0 18px;">We hope all is well with you.</p>

                            <p style="margin: 0 0 18px;">We are contacting you to inform you of an update related to
                                the license agreement for your 2iZii solution. In connection with this update, we ask
                                that you re-register your payment card.</p>

                            <p style="margin: 0 0 18px;">To ensure uninterrupted access to all features in 2iZii and
                                iZiiBuy, we ask that you enter updated payment information by Sunday, July 19, 2026.</p>

                            <p style="margin: 0 0 24px;">As compensation for any ambiguities in connection with this
                                process, we will credit the license cost for June. This assumes that payment cards are
                                registered by the deadline above. Otherwise, the entire month of June will be charged as
                                normal.</p>

                            <table border="0" cellpadding="0" cellspacing="0" role="presentation"
                                style="margin: 0 auto 24px;">
                                <tr>
                                    <td align="center" bgcolor="#579c73" style="border-radius: 8px;">
                                        <a href="{{ $registrationUrl }}"
                                            style="background-color: #579c73; border-radius: 8px; color: #ffffff; display: inline-block; font-size: 16px; font-weight: 700; padding: 13px 24px; text-decoration: none;">
                                            Register payment card
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 0 0 18px;">If you have any questions or need help with registration, you
                                are most welcome to contact us. We are happy to help.</p>

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
