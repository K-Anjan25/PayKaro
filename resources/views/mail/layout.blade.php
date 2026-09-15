<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>
</head>
{{--
    The email shell.

    Two constraints shape this file, and both are why it does not simply include
    `partials/brand-meta` and `assets/app.css`:

      - an inbox is not a browser. There is no stylesheet request, no CSS custom
        properties in half the clients in use, and no external images by default.
        So the tokens from `public/assets/app.css` (obsidian ink, the azure accent,
        the slate rules) are written as inline styles here, and the palette is
        intentionally a *copy*: a mail template is a rendering, not a theme.
      - most of these messages are opened on a phone by an accounts-payable clerk,
        so the layout is a single 600px table that degrades to full width, with the
        numbers in the first screen and the bank details reachable below.

    The wordmark is set as type — "Pay" in ink and "Karo" in azure — which is also
    what the design package's manifest asks for: 100% removal of graphic logo
    marks. Nothing here needs an image to load.
--}}
<body style="margin:0; padding:0; background:#f8faff; -webkit-text-size-adjust:100%;">

{{-- Preheader: the line an inbox shows next to the subject. It is the first thing
     read and the last thing most senders write. --}}
<div style="display:none; max-height:0; overflow:hidden; opacity:0;">@yield('preheader', config('paykaro.descriptor'))</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8faff; padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%; max-width:600px;">

                {{-- Masthead --}}
                <tr>
                    <td style="padding:0 4px 16px;">
                        <span style="font-family:'Plus Jakarta Sans',Helvetica,Arial,sans-serif; font-size:22px; font-weight:800; letter-spacing:-.03em; color:#0b132b;">Pay<span style="color:#1d4ed8;">Karo</span></span>
                        <span style="font-family:'Plus Jakarta Sans',Helvetica,Arial,sans-serif; font-size:13px; color:#64748b;">&nbsp;· {{ config('paykaro.descriptor') }}</span>
                    </td>
                </tr>

                {{-- The message. `$invoice` is always present: every mail in this
                     app is about exactly one receivable. --}}
                <tr>
                    <td style="background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; padding:28px;">
                        @yield('content')
                    </td>
                </tr>

                {{-- Bank details: what a buyer needs to actually pay, and the one
                     block that makes the invoice email more useful than a PDF. --}}
                @if (($business = $invoice->business ?? null) && $business->bank_acc_no)
                    <tr>
                        <td style="padding:16px 4px 0;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9; border:1px solid #e2e8f0; border-radius:12px;">
                                <tr>
                                    <td style="padding:16px; font-family:'Plus Jakarta Sans',Helvetica,Arial,sans-serif; font-size:13px; color:#334155; line-height:1.7;">
                                        <strong style="display:block; color:#0b132b; font-size:12px; letter-spacing:.08em; text-transform:uppercase; margin-bottom:6px;">Remittance account</strong>
                                        {{ $business->bank_name ?: 'Bank not recorded' }}<br>
                                        A/c {{ $business->bank_acc_no }}<br>
                                        IFSC {{ $business->bank_ifsc ?: '—' }}<br>
                                        GSTIN {{ $business->gstin ?: '—' }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                @endif

                {{-- Footer --}}
                <tr>
                    <td style="padding:18px 4px 0; font-family:'Plus Jakarta Sans',Helvetica,Arial,sans-serif; font-size:12px; color:#64748b; line-height:1.7;">
                        @yield('footer')
                        <div style="margin-top:8px;">
                            {{ $business->name ?? config('app.name') }}@if ($business && $business->gstin) · GSTIN {{ $business->gstin }}@endif<br>
                            Sent from {{ config('app.name') }} — {{ config('paykaro.headline') }}.
                        </div>
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>
</body>
</html>
