<!DOCTYPE html
    PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <style type="text/css">
        body {
            background-color: #f6f9fc;
            color: #51545e;
            font-family: Arial, Helvetica, sans-serif;
            margin: 0;
            padding: 0;
            width: 100%;
        }

        .email-wrapper {
            background-color: #f6f9fc;
            margin: 0;
            padding: 20px 0;
            width: 100%;
        }

        .email-content {
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            margin: 0 auto;
            width: 100%;
            max-width: 570px;
            overflow: hidden;
        }

        .email-masthead {
            background-color: #1a2b4c;
            padding: 25px 35px;
            text-align: center;
        }

        .email-masthead_name {
            color: #ffffff !important;
            font-size: 16px;
            font-weight: bold;
            text-decoration: none;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .email-body {
            padding: 35px;
        }

        .email-body_inner {
            width: 100%;
        }

        h1 {
            color: #333333;
            font-size: 22px;
            font-weight: bold;
            margin-top: 0;
            text-align: left;
        }

        p {
            color: #51545e;
            font-size: 16px;
            line-height: 1.625;
            margin: 0.4em 0 1.1875em;
        }

        .action-box {
            background-color: #f9fafb;
            border-radius: 6px;
            border: 1px solid #eef2f6;
            padding: 25px;
            margin: 30px 0;
            text-align: center;
        }

        .action-box table {
            margin: 0 auto;
        }

        .button {
            background-color: #e96a25;
            border-radius: 6px;
            color: #ffffff !important;
            display: inline-block;
            font-weight: bold;
            padding: 12px 24px;
            text-decoration: none;
        }

        .email-footer {
            padding: 30px;
            text-align: center;
        }

        .email-footer p {
            color: #a8aaaf;
            font-size: 12px;
            text-align: center;
        }

        .table-summary {
            width: 100%;
            border-top: 1px solid #edf2f7;
            border-bottom: 1px solid #edf2f7;
            margin: 20px 0;
            border-collapse: collapse;
        }

        .table-summary th {
            font-size: 12px;
            color: #718096;
            text-align: left;
            padding: 10px 0;
            text-transform: uppercase;
        }

        .table-summary td {
            font-size: 15px;
            color: #2d3748;
            padding: 12px 0;
        }

        .text-right {
            text-align: right;
        }
    </style>
</head>

<body>
    <table class="email-wrapper" width="100%" cellpadding="0" cellspacing="0" role="presentation">
        <tr>
            <td align="center">
                <table class="email-content" cellpadding="0" cellspacing="0" role="presentation">
                    <tr>
                        <td class="email-masthead">
                            <a href="{{ config('app.url') }}" class="email-masthead_name">
                                {{ $settings->name ?? config('app.name') }}
                            </a>
                        </td>
                    </tr>
                    <!-- Email Body -->
                    <tr>
                        <td class="email-body">
                            <table class="email-body_inner" align="center" width="100%" cellpadding="0" cellspacing="0"
                                role="presentation">
                                <tr>
                                    <td>
                                        <h1>Hello, {{ $order->user->name }}</h1>
                                        <p>Thank you for your order! We have successfully received your Request for Quotation (RFQ) #{{ str_pad($order->id, 6, '0', STR_PAD_LEFT) }}.</p>

                                        <p>Our team is processing your request, please wait while we review and confirm your order. Below is your order summary:</p>

                                        <table class="table-summary">
                                            <tr>
                                                <td><strong>Estimated Value:</strong></td>
                                                <td class="text-right">
                                                    <strong style="color: #1a2b4c; font-size: 18px;">
                                                        {{ $settings->currency ?? '$' }}{{ number_format($order->total, 2) }}
                                                    </strong>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td><strong>Item Count:</strong></td>
                                                <td class="text-right">{{ $order->items->count() }} SKU(s)</td>
                                            </tr>
                                        </table>

                                        <div class="action-box">
                                            <p style="margin-bottom: 15px; font-weight: bold;">Track your order status</p>
                                            <table border="0" cellpadding="0" cellspacing="0" role="presentation">
                                                <tr>
                                                    <td>
                                                        <a href="{{ route('dashboard') }}" class="button"
                                                            target="_blank">View Dashboard</a>
                                                    </td>
                                                </tr>
                                            </table>
                                        </div>

                                        <p>If you have any questions, feel free to contact our support team.</p>

                                        <p>Thank you for your business,<br />The {{ $settings->name ?? 'Procurement' }}
                                            Team</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <table class="email-footer" align="center" width="570" cellpadding="0" cellspacing="0"
                                role="presentation">
                                <tr>
                                    <td align="center">
                                        <p>&copy; {{ date('Y') }} {{ $settings->name ?? config('app.name') }}. All
                                            rights reserved.</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>
