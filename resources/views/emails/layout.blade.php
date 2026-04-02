<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $subject ?? '' }}</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style>
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        body { margin: 0; padding: 0; width: 100% !important; height: 100% !important; background-color: #f4f4f7; }
        a { color: {{ $primaryColor ?? '#8288bf' }}; }
        @media only screen and (max-width: 620px) {
            .email-container { width: 100% !important; max-width: 100% !important; }
            .email-content { padding: 0 16px !important; }
            .email-body-cell { padding: 24px 20px !important; }
            .action-button { display: block !important; width: 100% !important; text-align: center !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f4f7; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">

    {{-- Preheader text (hidden, shows in email preview) --}}
    <div style="display: none; max-height: 0; overflow: hidden; mso-hide: all;">
        {{ Str::limit(strip_tags($body ?? ''), 120) }}
    </div>

    {{-- Outer wrapper table --}}
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #f4f4f7;">
        <tr>
            <td align="center" style="padding: 32px 16px;">

                {{-- Email container --}}
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" class="email-container" style="max-width: 600px; width: 100%;">

                    {{-- Logo --}}
                    @if(!empty($logo))
                    <tr>
                        <td align="center" style="padding: 0 0 24px 0;">
                            <img src="{{ $logo }}" alt="{{ config('app.name', 'Visadorm') }}" width="150" style="display: block; max-width: 150px; height: auto;">
                        </td>
                    </tr>
                    @endif

                    {{-- Main card --}}
                    <tr>
                        <td>
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #ffffff; border-radius: 8px; overflow: hidden;">

                                {{-- Header image --}}
                                @if(!empty($headerImage))
                                <tr>
                                    <td style="padding: 0;">
                                        <img src="{{ $headerImage }}" alt="" width="600" style="display: block; width: 100%; max-width: 600px; height: auto;">
                                    </td>
                                </tr>
                                @endif

                                {{-- Body content --}}
                                <tr>
                                    <td class="email-body-cell" style="padding: 40px 48px;">

                                        {{-- Subject heading --}}
                                        <h1 style="margin: 0 0 20px 0; font-size: 20px; font-weight: 700; color: #1a1a2e; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                                            {{ $subject ?? '' }}
                                        </h1>

                                        {{-- Body text --}}
                                        <div style="margin: 0 0 28px 0; font-size: 15px; line-height: 1.6; color: #4a4a68; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                                            {!! nl2br(e($body ?? '')) !!}
                                        </div>

                                        {{-- Action button --}}
                                        @if(!empty($actionUrl) && !empty($actionText))
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                            <tr>
                                                <td align="center" style="padding: 8px 0 16px 0;">
                                                    <!--[if mso]>
                                                    <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="{{ $actionUrl }}" style="height:44px;v-text-anchor:middle;width:220px;" arcsize="14%" fillcolor="{{ $primaryColor ?? '#8288bf' }}">
                                                        <w:anchorlock/>
                                                        <center style="color:#ffffff;font-family:sans-serif;font-size:15px;font-weight:600;">{{ $actionText }}</center>
                                                    </v:roundrect>
                                                    <![endif]-->
                                                    <!--[if !mso]><!-->
                                                    <a href="{{ $actionUrl }}" class="action-button" target="_blank" style="display: inline-block; background-color: {{ $primaryColor ?? '#8288bf' }}; color: #ffffff; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 15px; font-weight: 600; text-decoration: none; padding: 12px 32px; border-radius: 6px; text-align: center; mso-hide: all;">
                                                        {{ $actionText }}
                                                    </a>
                                                    <!--<![endif]-->
                                                </td>
                                            </tr>
                                        </table>
                                        @endif

                                    </td>
                                </tr>

                            </table>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    @if(!empty($footer))
                    <tr>
                        <td style="padding: 24px 0 0 0;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #f8f8fb; border-radius: 8px;">
                                <tr>
                                    <td align="center" style="padding: 20px 32px; font-size: 12px; line-height: 1.5; color: #8b8ba3; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                                        {!! $footer !!}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    @endif

                </table>
                {{-- /Email container --}}

            </td>
        </tr>
    </table>
    {{-- /Outer wrapper --}}

</body>
</html>
