<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Invoice</title>
    <link rel="stylesheet" href="{{ asset('assets/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/custom/invoice.css') }}">
</head>

<body>

    <div class=""
        style="background-color: #fff; margin: 0; padding: 0; -webkit-text-size-adjust: none; text-size-adjust: none; ">
        <table border="0" cellpadding="0" cellspacing="0" class="nl-container" role="presentation"
            style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; background-color: #fff;" width="100%">

        </table>
        <table align="center" border="0" cellpadding="0" cellspacing="0" class="row row-4" role="presentation"
            style="mso-table-lspace: 0pt; mso-table-rspace: 0pt;" width="100%">
            <tbody>
                <tr>
                    <td>
                        <table align="center" border="0" cellpadding="0" cellspacing="0" class="row-content stack"
                            role="presentation"
                            style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; color: #000000; width: 680px;background-color: #f8f9fc;padding: 20px 10px 0;"
                            width="680">
                            <tbody>
                                <tr>
                                    <td class="column column-1"
                                        style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; vertical-align: top; padding-top: 5px; padding-bottom: 5px; border-top: 0px; border-right: 0px; border-bottom: 0px; border-left: 0px;"
                                        width="100%">
                                        <table border="0" cellpadding="0" cellspacing="0"
                                            class="image_block block-1" role="presentation"
                                            style="mso-table-lspace: 0pt; mso-table-rspace: 0pt;" width="100%">
                                            <tr>
                                                <td class="pad"
                                                    style="width:100%;padding-right:0px;padding-left:0px;">
                                                    <div align="center" class="alignment" style="line-height:10px"><img
                                                            alt="{{ $order->shop->user_name }}"
                                                            src="{{ Iziibuy::image($order->shop->logo) }}"
                                                            style="display: block; height: auto; border: 0; width: 272px; max-width: 100%;"
                                                            title="Light blue sphere with flowers" width="272" />
                                                    </div>
                                                </td>
                                            </tr>
                                        </table>
                                        <table border="0" cellpadding="10" cellspacing="0"
                                            class="text_block block-2" role="presentation"
                                            style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; word-break: break-word; "
                                            width="100%">
                                            <tr>
                                                <td class="pad">
                                                    <div style="font-family: Georgia, serif">
                                                        <div class=""
                                                            style="font-size: 12px; font-family: 'Playfair Display', Georgia, serif; mso-line-height-alt: 14.399999999999999px; color: #44464a; line-height: 1.2;">
                                                            <p
                                                                style="margin: 0; margin-top:10px; font-size: 14px; text-align: center; mso-line-height-alt: 16.8px;">
                                                                @php
                                                                    if ($order->status == 5) {
                                                                        $subject = __('words.product_delivered_email_subject');
                                                                    } else {
                                                                        $subject = __('words.payment_confirmed_email_subject');
                                                                    }
                                                                    
                                                                @endphp
                                                                <span
                                                                    style="font-size:20px; font-weight:777; background-color: @if ($order->status == 5) #579c73 @else #FFFF00 @endif;padding:5px 20px; border:1px solid #555;border-radius:10px">{{ $subject }}</span>
                                                            </p>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        </table>
                                        <table border="0" cellpadding="10" cellspacing="0"
                                            class="text_block block-3" role="presentation"
                                            style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; word-break: break-word;"
                                            width="100%">
                                            <tr>
                                                <td class="pad">
                                                    <div style="font-family: sans-serif">
                                                        <div class=""
                                                            style="font-size: 12px; mso-line-height-alt: 14.399999999999999px; color: #787771; line-height: 1.2; font-family: Nunito, Arial, Helvetica Neue, Helvetica, sans-serif;">
                                                            <p
                                                                style="margin: 0; font-size: 14px; text-align: left; mso-line-height-alt: 16.8px;">
                                                                <b>{{ __('words.company') }}:</b>
                                                                {{ $order->shop->company_name }}
                                                            </p>
                                                            <p
                                                                style="margin: 0; font-size: 14px; text-align: left; mso-line-height-alt: 16.8px;">
                                                                <b>{{ __('words.invoice_tax') }}</b>
                                                                {{ $order->shop->company_registration }}
                                                            </p>
                                                            <p
                                                                style="margin: 0; font-size: 14px; text-align: left; mso-line-height-alt: 16.8px;">
                                                                <b>{{ __('words.invoice_address') }}:</b>
                                                                {{ $order->shop->street }}
                                                            </p>
                                                            <p
                                                                style="margin: 0; font-size: 14px; text-align: left; mso-line-height-alt: 16.8px;">
                                                                <b>{{ __('words.invoice_place') }}:</b>
                                                                {{ $order->shop->post_code }} {{ $order->shop->city }}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="pad">
                                                    <div style="font-family: sans-serif">
                                                        <div class=""
                                                            style="font-size: 12px; mso-line-height-alt: 14.399999999999999px; color: #787771; line-height: 1.2; font-family: Nunito, Arial, Helvetica Neue, Helvetica, sans-serif;">
                                                            <p
                                                                style="margin: 0; font-size: 14px; text-align: left; mso-line-height-alt: 16.8px;">
                                                                {{ __('words.email_hi') }} {{ $order->first_name }}
                                                                {{ $order->last_name }}</p>
                                                            <p
                                                                style="margin: 0; margin-top:10px; font-size: 14px; text-align: left; mso-line-height-alt: 16.8px;">
                                                                {{ __('words.email_greeting') }}
                                                            </p>

                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        </table>
                                        <table border="0" cellpadding="25" cellspacing="0"
                                            class="image_block block-4" role="presentation"
                                            style="mso-table-lspace: 0pt; mso-table-rspace: 0pt;" width="100%">
                                            <tr>
                                                <td class="pad">
                                                    {{--  <div align="center" class="alignment" style="line-height:10px"><img alt="Separator" src="{{secure_asset('images/separator.png')}}" style="display: block; height: auto; border: 0; width: 136px; max-width: 100%;" title="Separator" width="136"/></div>  --}}
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </td>
                </tr>
            </tbody>
        </table>
        <table align="center" border="0" cellpadding="0" cellspacing="0" class="row row-5" role="presentation"
            style="mso-table-lspace: 0pt; mso-table-rspace: 0pt;" width="100%">
            <tbody>
                <tr>
                    <td>
                        <table align="center" border="0" cellpadding="0" cellspacing="0"
                            class="row-content stack" role="presentation"
                            style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; background-color: #ffffff; color: #000000; width: 680px;background-color: #f8f9fc;padding: 0 10px;"
                            width="680">
                            <tbody>
                                <tr>
                                    <td class="column column-1"
                                        style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; padding-left: 10px; padding-right: 10px; vertical-align: top; border-top: 0px; border-right: 0px; border-bottom: 0px; border-left: 0px;"
                                        width="66.66666666666667%">
                                        <table border="0" cellpadding="0" cellspacing="0"
                                            class="text_block block-2" role="presentation"
                                            style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; word-break: break-word;"
                                            width="100%">
                                            <tr>
                                                <td class="pad"
                                                    style="padding-bottom:10px;padding-left:10px;padding-right:10px;padding-top:25px;">
                                                    <div style="font-family: sans-serif">
                                                        <div class=""
                                                            style="font-size: 12px; mso-line-height-alt: 14.399999999999999px; color: #44464a; line-height: 1.2; font-family: Nunito, Arial, Helvetica Neue, Helvetica, sans-serif;">
                                                            <p
                                                                style="margin: 0; font-size: 14px; mso-line-height-alt: 16.8px;">
                                                                {{ __('words.dashboard_order_no') }}: <span
                                                                    style="color:#c4a07a;"><strong>{{ $order->id }}</strong></span>
                                                            </p>
                                                        </div>
                                                    </div>
                                            </tr>
                                        </table>
                                        <table border="0" cellpadding="0" cellspacing="0"
                                            class="text_block block-3" role="presentation"
                                            style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; word-break: break-word;"
                                            width="100%">
                                            <tr>
                                                <td class="pad"
                                                    style="padding-bottom:15px;padding-left:10px;padding-right:10px;padding-top:10px;">
                                                    <div style="font-family: sans-serif">
                                                        <div class=""
                                                            style="font-size: 12px; mso-line-height-alt: 14.399999999999999px; color: #44464a; line-height: 1.2; font-family: Nunito, Arial, Helvetica Neue, Helvetica, sans-serif;">
                                                            <p
                                                                style="margin: 0; font-size: 14px; mso-line-height-alt: 16.8px;">
                                                                {{ __('words.date') }}:
                                                                {{ $order->created_at->format('M d, Y') }}</p>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>

                                </tr>
                            </tbody>
                        </table>
                    </td>
                </tr>
            </tbody>
        </table>
        <table align="center" border="0" cellpadding="0" cellspacing="0" class="row row-6" role="presentation"
            style="mso-table-lspace: 0pt; mso-table-rspace: 0pt;" width="100%">
            <tbody>
                <tr>
                    <td>
                        <table align="center" border="0" cellpadding="0" cellspacing="0"
                            class="row-content stack" role="presentation"
                            style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; color: #000000; width: 680px; background-color: #f8f9fc;padding:0 10px;"
                            width="680">
                            <tbody>
                                <tr>
                                    <td class="column column-1"
                                        style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; vertical-align: top; padding-top: 5px; padding-bottom: 5px; border-top: 0px; border-right: 0px; border-bottom: 0px; border-left: 0px;"
                                        width="100%">
                                        <div class="spacer_block" style="height:15px;line-height:15px;font-size:1px;">
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </td>
                </tr>
            </tbody>
        </table>
        <table align="center" border="0" cellpadding="0" cellspacing="0" class="row row-7" role="presentation"
            style="mso-table-lspace: 0pt; mso-table-rspace: 0pt;" width="100%">
            <tbody>
                <tr>
                    <td>
                        <table align="center" border="0" cellpadding="0" cellspacing="0" class="row-content"
                            role="presentation"
                            style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; color: #000000; width: 680px;  background-color: #f8f9fc;padding:0 10px;"
                            width="680">
                            <tbody>
                                <tr>
                                    <td class="column column-1"
                                        style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; background-color: #f9feff; padding-left: 15px; padding-right: 15px; vertical-align: top; border-top: 0px; border-right: 0px; border-bottom: 0px; border-left: 0px;"
                                        width="33.333333333333336%">
                                        <table border="0" cellpadding="0" cellspacing="0"
                                            class="text_block block-2" role="presentation"
                                            style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; word-break: break-word;"
                                            width="100%">
                                            <tr>
                                                <td class="pad"
                                                    style="padding-bottom:15px;padding-left:10px;padding-right:10px;padding-top:15px;">
                                                    <div style="font-family: sans-serif">
                                                        <div class=""
                                                            style="font-size: 12px; mso-line-height-alt: 14.399999999999999px; color: #c4a07a; line-height: 1.2; font-family: Nunito, Arial, Helvetica Neue, Helvetica, sans-serif;">
                                                            <p
                                                                style="margin: 0; font-size: 14px; mso-line-height-alt: 16.8px;">
                                                                {{ __('words.cart_table_product') }}</p>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                    <td class="column column-2"
                                        style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; background-color: #f9feff; padding-left: 15px; padding-right: 15px; vertical-align: top; border-top: 0px; border-right: 0px; border-bottom: 0px; border-left: 0px;"
                                        width="33.333333333333336%">
                                        <table border="0" cellpadding="0" cellspacing="0"
                                            class="text_block block-2" role="presentation"
                                            style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; word-break: break-word;"
                                            width="100%">
                                            <tr>
                                                <td class="pad"
                                                    style="padding-bottom:15px;padding-left:10px;padding-right:10px;padding-top:15px;">
                                                    <div style="font-family: sans-serif">
                                                        <div class=""
                                                            style="font-size: 12px; mso-line-height-alt: 14.399999999999999px; color: #c4a07a; line-height: 1.2; font-family: Nunito, Arial, Helvetica Neue, Helvetica, sans-serif;">
                                                            <p
                                                                style="margin: 0; font-size: 14px; text-align: center; mso-line-height-alt: 16.8px;">
                                                                {{ __('words.cart_table_number') }}</p>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                    <td class="column column-3"
                                        style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; background-color: #f9feff; padding-left: 15px; padding-right: 15px; vertical-align: top; border-top: 0px; border-right: 0px; border-bottom: 0px; border-left: 0px;"
                                        width="33.333333333333336%">
                                        <table border="0" cellpadding="0" cellspacing="0"
                                            class="text_block block-2" role="presentation"
                                            style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; word-break: break-word;"
                                            width="100%">
                                            <tr>
                                                <td class="pad"
                                                    style="padding-bottom:15px;padding-left:10px;padding-right:10px;padding-top:15px;">
                                                    <div style="font-family: sans-serif">
                                                        <div class=""
                                                            style="font-size: 12px; mso-line-height-alt: 14.399999999999999px; color: #c4a07a; line-height: 1.2; font-family: Nunito, Arial, Helvetica Neue, Helvetica, sans-serif;">
                                                            <p
                                                                style="margin: 0; font-size: 14px; text-align: right; mso-line-height-alt: 16.8px;">
                                                                {{ __('words.cart_account_table_title') }}</p>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </td>
                </tr>
            </tbody>
        </table>
        @foreach ($order->products as $product)
            <table align="center" border="0" cellpadding="0" cellspacing="0" class="row row-12"
                role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt;" width="100%">
                <tbody>
                    <tr>
                        <td>
                            <table align="center" border="0" cellpadding="0" cellspacing="0"
                                class="row-content" role="presentation"
                                style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; color: #000000;  background-color: #f8f9fc;padding:0 10px; width: 680px;"
                                width="680">
                                <tbody>
                                    <tr>
                                        <td class="column column-1"
                                            style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; padding-left: 5px; padding-right: 5px; vertical-align: top; border-top: 0px; border-right: 0px; border-bottom: 0px; border-left: 0px;"
                                            width="33.333333333333336%">
                                            <table border="0" cellpadding="0" cellspacing="0"
                                                class="text_block block-2" role="presentation"
                                                style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; word-break: break-word;"
                                                width="100%">
                                                <tr>
                                                    <td class="pad"
                                                        style="padding-bottom:15px;padding-left:10px;padding-top:15px;">
                                                        <div style="font-family: sans-serif">
                                                            <div class=""
                                                                style="font-size: 12px; mso-line-height-alt: 14.399999999999999px; color: #393d47; line-height: 1.2; font-family: Nunito, Arial, Helvetica Neue, Helvetica, sans-serif;">
                                                                <p
                                                                    style="margin: 0; font-size: 14px; mso-line-height-alt: 16.8px;">
                                                                    {{ $product->name }} <br>
                                                                    {{ $product->pivot->variation }} <br>
                                                                    {{ __('words.dashboard_sku') }}:
                                                                    {{ $product->sku }}
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                        <td class="column column-2"
                                            style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; padding-left: 5px; padding-right: 5px; vertical-align: top; border-top: 0px; border-right: 0px; border-bottom: 0px; border-left: 0px;"
                                            width="33.333333333333336%">
                                            <table border="0" cellpadding="0" cellspacing="0"
                                                class="text_block block-2" role="presentation"
                                                style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; word-break: break-word;"
                                                width="100%">
                                                <tr>
                                                    <td class="pad"
                                                        style="padding-bottom:15px;padding-left:5px;padding-right:5px;padding-top:15px;">
                                                        <div style="font-family: sans-serif">
                                                            <div class=""
                                                                style="font-size: 12px; mso-line-height-alt: 14.399999999999999px; color: #393d47; line-height: 1.2; font-family: Nunito, Arial, Helvetica Neue, Helvetica, sans-serif;">
                                                                <p
                                                                    style="margin: 0; font-size: 14px; text-align: center; mso-line-height-alt: 16.8px;">
                                                                    {{ $product->pivot->quantity }}</p>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                        <td class="column column-3"
                                            style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; padding-left: 5px; padding-right: 5px; vertical-align: top; border-top: 0px; border-right: 0px; border-bottom: 0px; border-left: 0px;"
                                            width="33.333333333333336%">
                                            <table border="0" cellpadding="0" cellspacing="0"
                                                class="text_block block-2" role="presentation"
                                                style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; word-break: break-word;"
                                                width="100%">
                                                <tr>
                                                    <td class="pad"
                                                        style="padding-bottom:15px;padding-right:10px;padding-top:15px;">
                                                        <div style="font-family: sans-serif">
                                                            <div class=""
                                                                style="font-size: 12px; mso-line-height-alt: 14.399999999999999px; color: #393d47; line-height: 1.2; font-family: Nunito, Arial, Helvetica Neue, Helvetica, sans-serif;">
                                                                <p
                                                                    style="margin: 0; font-size: 14px; text-align: right; mso-line-height-alt: 16.8px;">
                                                                    {{ Iziibuy::price($product->pivot->price, $order->shop, $order->currency) }}
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                </tbody>
            </table>
        @endforeach
        <table align="center" border="0" cellpadding="0" cellspacing="0" class="row row-13"
            role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt;" width="100%">
            <tbody>
                <tr>
                    <td>
                        <table align="center" border="0" cellpadding="0" cellspacing="0"
                            class="row-content stack" role="presentation"
                            style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; color: #000000; width: 680px;  background-color: #f8f9fc;padding:0 10px;"
                            width="680">
                            <tbody>
                                <tr>
                                    <td class="column column-1"
                                        style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; vertical-align: top; padding-top: 5px; padding-bottom: 5px; border-top: 0px; border-right: 0px; border-bottom: 0px; border-left: 0px;"
                                        width="100%">
                                        <table border="0" cellpadding="0" cellspacing="0"
                                            class="divider_block block-1" role="presentation"
                                            style="mso-table-lspace: 0pt; mso-table-rspace: 0pt;" width="100%">
                                            <tr>
                                                <td class="pad">
                                                    <div align="center" class="alignment">
                                                        <table border="0" cellpadding="0" cellspacing="0"
                                                            role="presentation"
                                                            style="mso-table-lspace: 0pt; mso-table-rspace: 0pt;"
                                                            width="100%">
                                                            <tr>
                                                                <td class="divider_inner"
                                                                    style="font-size: 1px; line-height: 1px; border-top: 1px solid #E1ECEF;">
                                                                    <span> </span>
                                                                </td>
                                                            </tr>
                                                        </table>
                                                    </div>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </td>
                </tr>
            </tbody>
        </table>
        <table align="center" border="0" cellpadding="0" cellspacing="0" class="row row-14"
            role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt;" width="100%">
            <tbody>
                <tr>
                    <td>
                        <table align="center" border="0" cellpadding="0" cellspacing="0" class="row-content"
                            role="presentation"
                            style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; color: #000000; width: 680px;  background-color: #f8f9fc;padding:0 10px;"
                            width="680">
                            <tbody>
                                <tr>
                                    <td class="column column-1"
                                        style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; padding-left: 5px; padding-right: 5px; vertical-align: top; border-top: 0px; border-right: 0px; border-bottom: 0px; border-left: 0px;"
                                        width="33.333333333333336%">
                                        <table border="0" cellpadding="0" cellspacing="0"
                                            class="text_block block-2" role="presentation"
                                            style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; word-break: break-word;"
                                            width="100%">
                                            <tr>
                                                <td class="pad"
                                                    style="padding-bottom:15px;padding-left:10px;padding-right:10px;padding-top:15px;">
                                                    <div style="font-family: sans-serif">
                                                        <div class=""
                                                            style="font-size: 12px; mso-line-height-alt: 14.399999999999999px; color: #393d47; line-height: 1.2; font-family: Nunito, Arial, Helvetica Neue, Helvetica, sans-serif;">
                                                            <p
                                                                style="margin: 0; font-size: 14px; mso-line-height-alt: 16.8px;">
                                                                <span
                                                                    style="font-size:16px;"><strong>{{ __('words.subscription_ex_vat') }}</strong></span>
                                                            </p>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>

                                    <td class="column column-3"
                                        style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; padding-left: 5px; padding-right: 5px; vertical-align: top; border-top: 0px; border-right: 0px; border-bottom: 0px; border-left: 0px;"
                                        width="33.333333333333336%">
                                        <table border="0" cellpadding="0" cellspacing="0"
                                            class="text_block block-2" role="presentation"
                                            style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; word-break: break-word;"
                                            width="100%">
                                            <tr>
                                                <td class="pad"
                                                    style="padding-bottom:15px;padding-left:10px;padding-right:10px;padding-top:15px;">
                                                    <div style="font-family: sans-serif">
                                                        <div class=""
                                                            style="font-size: 12px; mso-line-height-alt: 14.399999999999999px; color: #393d47; line-height: 1.2; font-family: Nunito, Arial, Helvetica Neue, Helvetica, sans-serif;">
                                                            <p
                                                                style="margin: 0; font-size: 14px; text-align: right; mso-line-height-alt: 16.8px;">
                                                                <span
                                                                    style="font-size:16px;">{{ Iziibuy::withSymbol($order->subtotal, $order->currency) }}</span>
                                                            </p>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </td>
                </tr>
            </tbody>
        </table>
        <table align="center" border="0" cellpadding="0" cellspacing="0" class="row row-15"
            role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt;" width="100%">
            <tbody>
                <tr>
                    <td>
                        <table align="center" border="0" cellpadding="0" cellspacing="0"
                            class="row-content stack" role="presentation"
                            style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; color: #000000; width: 680px;  background-color: #f8f9fc;padding:0 10px;"
                            width="680">
                            <tbody>
                                <tr>
                                    <td class="column column-1"
                                        style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; vertical-align: top; padding-top: 5px; padding-bottom: 5px; border-top: 0px; border-right: 0px; border-bottom: 0px; border-left: 0px;"
                                        width="100%">
                                        <table border="0" cellpadding="0" cellspacing="0"
                                            class="divider_block block-1" role="presentation"
                                            style="mso-table-lspace: 0pt; mso-table-rspace: 0pt;" width="100%">
                                            <tr>
                                                <td class="pad">
                                                    <div align="center" class="alignment">
                                                        <table border="0" cellpadding="0" cellspacing="0"
                                                            role="presentation"
                                                            style="mso-table-lspace: 0pt; mso-table-rspace: 0pt;"
                                                            width="100%">
                                                            <tr>
                                                                <td class="divider_inner"
                                                                    style="font-size: 1px; line-height: 1px; border-top: 1px solid #E1ECEF;">
                                                                    <span> </span>
                                                                </td>
                                                            </tr>
                                                        </table>
                                                    </div>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </td>
                </tr>
            </tbody>
        </table>
        @if ($order->discount > 0)
            <table align="center" border="0" cellpadding="0" cellspacing="0" class="row row-16"
                role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt;" width="100%">
                <tbody>
                    <tr>
                        <td>
                            <table align="center" border="0" cellpadding="0" cellspacing="0"
                                class="row-content" role="presentation"
                                style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; color: #000000; width: 680px;  background-color: #f8f9fc;padding:0 10px;"
                                width="680">
                                <tbody>
                                    <tr>
                                        <td class="column column-1"
                                            style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; padding-left: 5px; padding-right: 5px; vertical-align: top; border-top: 0px; border-right: 0px; border-bottom: 0px; border-left: 0px;"
                                            width="33.333333333333336%">
                                            <table border="0" cellpadding="0" cellspacing="0"
                                                class="text_block block-2" role="presentation"
                                                style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; word-break: break-word;"
                                                width="100%">
                                                <tr>
                                                    <td class="pad"
                                                        style="padding-bottom:15px;padding-left:10px;padding-right:10px;padding-top:15px;">
                                                        <div style="font-family: sans-serif">
                                                            <div class=""
                                                                style="font-size: 12px; mso-line-height-alt: 14.399999999999999px; color: #393d47; line-height: 1.2; font-family: Nunito, Arial, Helvetica Neue, Helvetica, sans-serif;">
                                                                <p
                                                                    style="margin: 0; font-size: 14px; mso-line-height-alt: 16.8px;">
                                                                    <span
                                                                        style="font-size:16px;"><strong>Rabatt</strong></span>
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                        <td class="column column-2"
                                            style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; vertical-align: top; border-top: 0px; border-right: 0px; border-bottom: 0px; border-left: 0px;"
                                            width="33.333333333333336%">
                                            <table border="0" cellpadding="0" cellspacing="0"
                                                class="empty_block block-2" role="presentation"
                                                style="mso-table-lspace: 0pt; mso-table-rspace: 0pt;" width="100%">
                                                <tr>
                                                    <td class="pad"
                                                        style="padding-right:0px;padding-bottom:5px;padding-left:0px;padding-top:5px;">
                                                        <div></div>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                        <td class="column column-3"
                                            style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; padding-left: 5px; padding-right: 5px; vertical-align: top; border-top: 0px; border-right: 0px; border-bottom: 0px; border-left: 0px;"
                                            width="33.333333333333336%">
                                            <table border="0" cellpadding="0" cellspacing="0"
                                                class="text_block block-2" role="presentation"
                                                style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; word-break: break-word;"
                                                width="100%">
                                                <tr>
                                                    <td class="pad"
                                                        style="padding-bottom:15px;padding-left:10px;padding-right:10px;padding-top:15px;">
                                                        <div style="font-family: sans-serif">
                                                            <div class=""
                                                                style="font-size: 12px; mso-line-height-alt: 14.399999999999999px; color: #393d47; line-height: 1.2; font-family: Nunito, Arial, Helvetica Neue, Helvetica, sans-serif;">
                                                                <p
                                                                    style="margin: 0; font-size: 14px; text-align: right; mso-line-height-alt: 16.8px;">
                                                                    <span
                                                                        style="font-size:16px;">{{ Iziibuy::withSymbol($order->discount, $order->currency) }}</span>
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                </tbody>
            </table>
            <table align="center" border="0" cellpadding="0" cellspacing="0" class="row row-17"
                role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt;" width="100%">
                <tbody>
                    <tr>
                        <td>
                            <table align="center" border="0" cellpadding="0" cellspacing="0"
                                class="row-content stack" role="presentation"
                                style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; color: #000000; width: 680px;  background-color: #f8f9fc;padding:0 10px;"
                                width="680">
                                <tbody>
                                    <tr>
                                        <td class="column column-1"
                                            style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; vertical-align: top; padding-top: 5px; padding-bottom: 5px; border-top: 0px; border-right: 0px; border-bottom: 0px; border-left: 0px;"
                                            width="100%">
                                            <table border="0" cellpadding="0" cellspacing="0"
                                                class="divider_block block-1" role="presentation"
                                                style="mso-table-lspace: 0pt; mso-table-rspace: 0pt;" width="100%">
                                                <tr>
                                                    <td class="pad">
                                                        <div align="center" class="alignment">
                                                            <table border="0" cellpadding="0" cellspacing="0"
                                                                role="presentation"
                                                                style="mso-table-lspace: 0pt; mso-table-rspace: 0pt;"
                                                                width="100%">
                                                                <tr>
                                                                    <td class="divider_inner"
                                                                        style="font-size: 1px; line-height: 1px; border-top: 1px solid #E1ECEF;">
                                                                        <span> </span>
                                                                    </td>
                                                                </tr>
                                                            </table>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                </tbody>
            </table>
        @endif
        <table align="center" border="0" cellpadding="0" cellspacing="0" class="row row-16"
            role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt;" width="100%">
            <tbody>
                <tr>
                    <td>
                        <table align="center" border="0" cellpadding="0" cellspacing="0" class="row-content"
                            role="presentation"
                            style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; color: #000000; width: 680px;  background-color: #f8f9fc;padding:0 10px;"
                            width="680">
                            <tbody>
                                <tr>
                                    <td class="column column-1"
                                        style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; padding-left: 5px; padding-right: 5px; vertical-align: top; border-top: 0px; border-right: 0px; border-bottom: 0px; border-left: 0px;"
                                        width="33.333333333333336%">
                                        <table border="0" cellpadding="0" cellspacing="0"
                                            class="text_block block-2" role="presentation"
                                            style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; word-break: break-word;"
                                            width="100%">
                                            <tr>
                                                <td class="pad"
                                                    style="padding-bottom:15px;padding-left:10px;padding-right:10px;padding-top:15px;">
                                                    <div style="font-family: sans-serif">
                                                        <div class=""
                                                            style="font-size: 12px; mso-line-height-alt: 14.399999999999999px; color: #393d47; line-height: 1.2; font-family: Nunito, Arial, Helvetica Neue, Helvetica, sans-serif;">
                                                            <p
                                                                style="margin: 0; font-size: 14px; mso-line-height-alt: 16.8px;">
                                                                <span
                                                                    style="font-size:16px;"><strong>{{ __('words.cart_tax') }}</strong></span>
                                                            </p>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>

                                    <td class="column column-3"
                                        style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; padding-left: 5px; padding-right: 5px; vertical-align: top; border-top: 0px; border-right: 0px; border-bottom: 0px; border-left: 0px;"
                                        width="33.333333333333336%">
                                        <table border="0" cellpadding="0" cellspacing="0"
                                            class="text_block block-2" role="presentation"
                                            style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; word-break: break-word;"
                                            width="100%">
                                            <tr>
                                                <td class="pad"
                                                    style="padding-bottom:15px;padding-left:10px;padding-right:10px;padding-top:15px;">
                                                    <div style="font-family: sans-serif">
                                                        <div class=""
                                                            style="font-size: 12px; mso-line-height-alt: 14.399999999999999px; color: #393d47; line-height: 1.2; font-family: Nunito, Arial, Helvetica Neue, Helvetica, sans-serif;">
                                                            <p
                                                                style="margin: 0; font-size: 14px; text-align: right; mso-line-height-alt: 16.8px;">
                                                                <span
                                                                    style="font-size:16px;">{{ Iziibuy::withSymbol($order->tax, $order->shop) }}</span>
                                                            </p>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="column column-1"
                                        style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; padding-left: 5px; padding-right: 5px; vertical-align: top; border-top: 0px; border-right: 0px; border-bottom: 0px; border-left: 0px;"
                                        width="33.333333333333336%">
                                        <table border="0" cellpadding="0" cellspacing="0"
                                            class="text_block block-2" role="presentation"
                                            style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; word-break: break-word;"
                                            width="100%">
                                            <tr>
                                                <td class="pad"
                                                    style="padding-bottom:15px;padding-left:10px;padding-right:10px;padding-top:15px;">
                                                    <div style="font-family: sans-serif">
                                                        <div class=""
                                                            style="font-size: 12px; mso-line-height-alt: 14.399999999999999px; color: #393d47; line-height: 1.2; font-family: Nunito, Arial, Helvetica Neue, Helvetica, sans-serif;">
                                                            <p
                                                                style="margin: 0; font-size: 14px; mso-line-height-alt: 16.8px;">
                                                                <span
                                                                    style="font-size:16px;"><strong>{{ __('words.cart_account_table_title') }}</strong></span>
                                                            </p>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>

                                    <td class="column column-3"
                                        style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; padding-left: 5px; padding-right: 5px; vertical-align: top; border-top: 0px; border-right: 0px; border-bottom: 0px; border-left: 0px;"
                                        width="50%">
                                        <table border="0" cellpadding="0" cellspacing="0"
                                            class="text_block block-2" role="presentation"
                                            style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; word-break: break-word;"
                                            width="100%">
                                            <tr>
                                                <td class="pad"
                                                    style="padding-bottom:15px;padding-left:10px;padding-right:10px;padding-top:15px;">
                                                    <div style="font-family: sans-serif">
                                                        <div class=""
                                                            style="font-size: 12px; mso-line-height-alt: 14.399999999999999px; color: #393d47; line-height: 1.2; font-family: Nunito, Arial, Helvetica Neue, Helvetica, sans-serif;">
                                                            <p
                                                                style="margin: 0; font-size: 14px; text-align: right; mso-line-height-alt: 16.8px;">
                                                                <span
                                                                    style="font-size:20px;font-weight:bold;">{{ Iziibuy::withSymbol($order->total, $order->currency) }}</span>
                                                            </p>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </td>
                </tr>
            </tbody>
        </table>
                @if ($order->platform_percentage > 0)
                    <p style="text-align:right;padding:0 20px;margin:0 0 10px;font-size:14px;">
                        {{ __('Platform commission') }} ({{ rtrim(rtrim(number_format($order->platform_percentage, 2), '0'), '.') }}%):
                        <strong>{{ Iziibuy::withSymbol($order->platform_amount, $order->currency) }}</strong>
                    </p>
                @endif
        @if ($order->shipping_cost > 0)
            <table align="center" border="0" cellpadding="0" cellspacing="0" class="row row-16"
                role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt;" width="100%">
                <tbody>
                    <tr>
                        <td>
                            <table align="center" border="0" cellpadding="0" cellspacing="0"
                                class="row-content" role="presentation"
                                style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; color: #000000; width: 680px; background-color: #f8f9fc;padding:0 10px;"
                                width="680">
                                <tbody>
                                    <tr>
                                        <td class="column column-1"
                                            style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; padding-left: 5px; padding-right: 5px; vertical-align: top; border-top: 0px; border-right: 0px; border-bottom: 0px; border-left: 0px;"
                                            width="33.333333333333336%">
                                            <table border="0" cellpadding="0" cellspacing="0"
                                                class="text_block block-2" role="presentation"
                                                style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; word-break: break-word;"
                                                width="100%">
                                                <tr>
                                                    <td class="pad"
                                                        style="padding-bottom:15px;padding-left:10px;padding-right:10px;padding-top:15px;">
                                                        <div style="font-family: sans-serif">
                                                            <div class=""
                                                                style="font-size: 12px; mso-line-height-alt: 14.399999999999999px; color: #393d47; line-height: 1.2; font-family: Nunito, Arial, Helvetica Neue, Helvetica, sans-serif;">
                                                                <p
                                                                    style="margin: 0; font-size: 14px; mso-line-height-alt: 16.8px;">
                                                                    <span
                                                                        style="font-size:16px;"><strong>Frakt</strong></span>
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                        <td class="column column-2"
                                            style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; vertical-align: top; border-top: 0px; border-right: 0px; border-bottom: 0px; border-left: 0px;"
                                            width="33.333333333333336%">
                                            <table border="0" cellpadding="0" cellspacing="0"
                                                class="empty_block block-2" role="presentation"
                                                style="mso-table-lspace: 0pt; mso-table-rspace: 0pt;" width="100%">
                                                <tr>
                                                    <td class="pad"
                                                        style="padding-right:0px;padding-bottom:5px;padding-left:0px;padding-top:5px;">
                                                        <div></div>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                        <td class="column column-3"
                                            style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; padding-left: 5px; padding-right: 5px; vertical-align: top; border-top: 0px; border-right: 0px; border-bottom: 0px; border-left: 0px;"
                                            width="33.333333333333336%">
                                            <table border="0" cellpadding="0" cellspacing="0"
                                                class="text_block block-2" role="presentation"
                                                style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; word-break: break-word;"
                                                width="100%">
                                                <tr>
                                                    <td class="pad"
                                                        style="padding-bottom:15px;padding-left:10px;padding-right:10px;padding-top:15px;">
                                                        <div style="font-family: sans-serif">
                                                            <div class=""
                                                                style="font-size: 12px; mso-line-height-alt: 14.399999999999999px; color: #393d47; line-height: 1.2; font-family: Nunito, Arial, Helvetica Neue, Helvetica, sans-serif;">
                                                                <p
                                                                    style="margin: 0; font-size: 14px; text-align: right; mso-line-height-alt: 16.8px;">
                                                                    <span
                                                                        style="font-size:16px;">{{ Iziibuy::withSymbol($order->shipping_cost, $order->shop) }}</span>
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                </tbody>
            </table>
        @endif
        <table align="center" border="0" cellpadding="0" cellspacing="0" class="row row-17"
            role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt;" width="100%">
            <tbody>
                <tr>
                    <td>
                        <table align="center" border="0" cellpadding="0" cellspacing="0"
                            class="row-content stack" role="presentation"
                            style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; color: #000000; width: 680px;  background-color: #f8f9fc;padding:0 10px;"
                            width="680">
                            <tbody>
                                <tr>
                                    <td class="column column-1"
                                        style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; vertical-align: top; padding-top: 5px; padding-bottom: 5px; border-top: 0px; border-right: 0px; border-bottom: 0px; border-left: 0px;"
                                        width="100%">
                                        <table border="0" cellpadding="0" cellspacing="0"
                                            class="divider_block block-1" role="presentation"
                                            style="mso-table-lspace: 0pt; mso-table-rspace: 0pt;" width="100%">
                                            <tr>
                                                <td class="pad">
                                                    <div align="center" class="alignment">
                                                        <table border="0" cellpadding="0" cellspacing="0"
                                                            role="presentation"
                                                            style="mso-table-lspace: 0pt; mso-table-rspace: 0pt;"
                                                            width="100%">
                                                            <tr>
                                                                <td class="divider_inner"
                                                                    style="font-size: 1px; line-height: 1px; border-top: 1px solid #E1ECEF;">
                                                                    <span> </span>
                                                                </td>
                                                            </tr>
                                                        </table>
                                                    </div>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </td>
                </tr>
            </tbody>
        </table>
        <table align="center" border="0" cellpadding="0" cellspacing="0" class="row row-18"
            role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt;" width="100%">
            <tbody>
                <tr>
                    <td>
                        <table align="center" border="0" cellpadding="0" cellspacing="0"
                            class="row-content stack" role="presentation"
                            style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; color: #000000; width: 680px;  background-color: #f8f9fc;padding:0 10px;"
                            width="680">
                            <tbody>
                                <tr>
                                    <td class="column column-1"
                                        style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; padding-left: 5px; padding-right: 5px; vertical-align: top; padding-top: 5px; padding-bottom: 5px; border-top: 0px; border-right: 0px; border-bottom: 0px; border-left: 0px;"
                                        width="100%">
                                        <table border="0" cellpadding="10" cellspacing="0"
                                            class="text_block block-1" role="presentation"
                                            style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; word-break: break-word;"
                                            width="100%">
                                            <tr>
                                                <td class="pad">
                                                    <div style="font-family: sans-serif">
                                                        <div class=""
                                                            style="font-size: 12px; mso-line-height-alt: 14.399999999999999px; color: #68a0a9; line-height: 1.2; font-family: Nunito, Arial, Helvetica Neue, Helvetica, sans-serif;">
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </td>
                </tr>
            </tbody>
        </table>
        <table align="center" border="0" cellpadding="0" cellspacing="0" class="row row-19"
            role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt;" width="100%">
            <tbody>
                <tr>
                    <td>
                        <table align="center" border="0" cellpadding="0" cellspacing="0" class="row row-20"
                            role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt;" width="100%">
                            <tbody>
                                <tr>
                                    <td>
                                        <table align="center" border="0" cellpadding="0" cellspacing="0"
                                            class="row-content stack" role="presentation"
                                            style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; background-color: #ffffff; color: #000000; width: 680px;  background-color: #f8f9fc;padding:0 10px;"
                                            width="680">
                                            <tbody>
                                                <tr>
                                                    <td class="column column-1"
                                                        style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; vertical-align: top; padding-top: 15px; padding-bottom: 15px; border-top: 0px; border-right: 0px; border-bottom: 0px; border-left: 0px;"
                                                        width="100%">
                                                        <table border="0" cellpadding="0" cellspacing="0"
                                                            class="text_block block-1" role="presentation"
                                                            style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; word-break: break-word;"
                                                            width="100%">
                                                            <tr>
                                                                <td class="pad"
                                                                    style="padding-bottom:15px;padding-left:35px;padding-right:35px;padding-top:15px;">
                                                                    <div style="font-family: sans-serif">
                                                                        {{-- <p
                                                                                    style="margin: 0; padding-bottom:5px; font-size: 14px; font-weight:700;  mso-line-height-alt: 21px;">
                                                                                    Adresse</p>
                                                                                <div class=""
                                                                                    style="font-size: 12px; border:1px solid #777; padding:10px 7px; mso-line-height-alt: 18px; color: #44464a; line-height: 1.5; font-family: Nunito, Arial, Helvetica Neue, Helvetica, sans-serif;">
                                                                                    <p
                                                                                        style="margin: 0; font-size: 14px;  mso-line-height-alt: 21px;">
                                                                                        {{ $order->address }}</p>
                                                                                    <p
                                                                                        style="margin: 0; font-size: 14px;  mso-line-height-alt: 21px;">
                                                                                        Tel :{{ $order->phone }}</p>
                                                                                    <p
                                                                                        style="margin: 0; font-size: 14px;  mso-line-height-alt: 21px;">
                                                                                        {{ $order->state }}</p>
                                                                                    <p
                                                                                        style="margin: 0; font-size: 14px;  mso-line-height-alt: 21px;">
                                                                                        {{ $order->post_code }},{{ $order->city }}
                                                                                    </p>
                                                                                </div> --}}
                                                                        <div class=""
                                                                            style="margin-top:15px; font-size: 12px; mso-line-height-alt: 18px; color: #44464a; line-height: 1.5; font-family: Nunito, Arial, Helvetica Neue, Helvetica, sans-serif;">
                                                                            <p
                                                                                style="margin: 0; font-size: 14px;  mso-line-height-alt: 21px;">
                                                                                {{ __('words.email_thankyou_pera') }}
                                                                            </p>
                                                                            <p
                                                                                style="margin: 0; font-size: 14px;  mso-line-height-alt: 21px;">
                                                                                {{ __('words.email_regards') }}
                                                                                {{ $order->shop->company_name }},
                                                                            </p>
                                                                        </div>
                                                                        <div class=""
                                                                            style="margin-top:15px; font-size: 12px; mso-line-height-alt: 18px; color: #44464a; line-height: 1.5; font-family: Nunito, Arial, Helvetica Neue, Helvetica, sans-serif;">
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        </table>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <table align="center" border="0" cellpadding="0" cellspacing="0" class="row row-21"
                            role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt;" width="100%">
                            <tbody>
                                <tr>
                                    <td>
                                        <table align="center" border="0" cellpadding="0" cellspacing="0"
                                            class="row-content stack" role="presentation"
                                            style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; color: #000000; width: 680px;  background-color: #f8f9fc;padding:0 10px;"
                                            width="680">
                                            <tbody>
                                                <tr>
                                                    <td class="column column-1"
                                                        style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; vertical-align: top; padding-top: 5px; padding-bottom: 5px; border-top: 0px; border-right: 0px; border-bottom: 0px; border-left: 0px;"
                                                        width="100%">
                                                        <table border="0" cellpadding="0" cellspacing="0"
                                                            class="image_block block-2" role="presentation"
                                                            style="mso-table-lspace: 0pt; mso-table-rspace: 0pt;"
                                                            width="100%">
                                                            <tr>
                                                                <td class="pad"
                                                                    style="padding-bottom:25px;padding-left:25px;padding-right:25px;padding-top:50px;width:100%;">
                                                                    <div align="center" class="alignment"
                                                                        style="line-height:10px">
                                                                        <p
                                                                            style="margin: 0; font-size: 14px;  mso-line-height-alt: 21px; text-align:center">
                                                                            {{ __('words.email_footer') }} <a
                                                                                href="{{ env('APP_URL') }}"
                                                                                style="text-decoration: none; color:#555; font-weight:700">iziibuy.com</a>
                                                                        </p>
                                                                        {{--  <img alt="Separator" src="{{secure_asset('images/separator.png')}}" style="display: block; height: auto; border: 0; width: 136px; max-width: 100%;" title="Separator" width="136"/>  --}}
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        </table>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
    </div>
    </td>
    </tr>
    </table>
    </td>
    </tr>
    </tbody>
    </table>
    </td>
    </tr>
    </tbody>
    </table>
    <table align="center" border="0" cellpadding="0" cellspacing="0" class="row row-24" role="presentation"
        style="mso-table-lspace: 0pt; mso-table-rspace: 0pt;" width="100%">
        <tbody>
            <tr>
                <td>
                    <table align="center" border="0" cellpadding="0" cellspacing="0" class="row-content stack"
                        role="presentation"
                        style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; color: #000000; width: 680px;"
                        width="680">
                        <tbody>
                            <tr>
                                <td class="column column-1"
                                    style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; vertical-align: top; padding-top: 5px; padding-bottom: 5px; border-top: 0px; border-right: 0px; border-bottom: 0px; border-left: 0px;"
                                    width="100%">
                                    <table border="0" cellpadding="0" cellspacing="0"
                                        class="icons_block block-1" role="presentation"
                                        style="mso-table-lspace: 0pt; mso-table-rspace: 0pt;" width="100%">
                                        <tr>
                                            <td class="pad"
                                                style="vertical-align: middle; color: #9d9d9d; font-family: inherit; font-size: 15px; padding-bottom: 5px; padding-top: 5px; text-align: center;">
                                                <table cellpadding="0" cellspacing="0" role="presentation"
                                                    style="mso-table-lspace: 0pt; mso-table-rspace: 0pt;"
                                                    width="100%">
                                                    <tr>
                                                        <td class="alignment"
                                                            style="vertical-align: middle; text-align: center;">
                                                            <!--[if vml]><table align="left" cellpadding="0" cellspacing="0" role="presentation" style="display:inline-block;padding-left:0px;padding-right:0px;mso-table-lspace: 0pt;mso-table-rspace: 0pt;"><![endif]-->
                                                            <!--[if !vml]><!-->
                                                            <table cellpadding="0" cellspacing="0"
                                                                class="icons-inner" role="presentation"
                                                                style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; display: inline-block; margin-right: -4px; padding-left: 0px; padding-right: 0px;">
                                                                <!--<![endif]-->
                                                            </table>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </td>
            </tr>
        </tbody>
    </table>
    </td>
    </tr>
    </tbody>
    </table>
    </div>

    </div>



    </div>

    </div>

    </div>
</body>

</html>
