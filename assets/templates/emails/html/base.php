<?php
/**
 * MXP Password Manager - HTML Email Base Template
 *
 * @package MXP_Password_Manager
 * @since 2.0.0
 *
 * Variables available:
 * @var string $content     Main content HTML
 * @var string $site_name   Site name
 * @var string $site_url    Site URL
 * @var string $timestamp   Notification timestamp
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($site_name ?? ''); ?></title>
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
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            font-size: 16px;
            line-height: 1.6;
            color: #374151;
            background-color: #f3f4f6;
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        table {
            border-spacing: 0;
            border-collapse: collapse;
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }
        img {
            border: 0;
            -ms-interpolation-mode: bicubic;
        }
        .wrapper {
            width: 100%;
            table-layout: fixed;
            background-color: #f3f4f6;
            padding-bottom: 40px;
        }
        .webkit {
            max-width: 600px;
            margin: 0 auto;
        }
        .outer {
            margin: 0 auto;
            width: 100%;
            max-width: 600px;
        }
        .container {
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            border: 1px solid #e5e7eb;
        }
        .header {
            background-color: #ffffff;
            padding: 32px 40px 0;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            color: #111827;
            font-size: 24px;
            font-weight: 700;
            letter-spacing: -0.025em;
        }
        .logo-bar {
            height: 4px;
            width: 48px;
            background-color: #0073aa;
            margin: 24px auto 0;
            border-radius: 2px;
        }
        .content {
            padding: 40px;
        }
        .content h2 {
            margin: 0 0 24px;
            color: #111827;
            font-size: 20px;
            font-weight: 600;
            line-height: 1.4;
        }
        .content p {
            margin: 0 0 24px;
            color: #4b5563;
        }
        
        /* Info Box Styles */
        .info-box {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-left-width: 4px;
            padding: 20px;
            margin: 24px 0;
            border-radius: 6px;
        }
        .info-box.success { border-left-color: #10b981; background-color: #ecfdf5; }
        .info-box.warning { border-left-color: #f59e0b; background-color: #fffbeb; }
        .info-box.danger { border-left-color: #ef4444; background-color: #fef2f2; }
        .info-box.info { border-left-color: #3b82f6; background-color: #eff6ff; }
        
        .info-box strong {
            color: #111827;
            display: block;
            font-size: 16px;
            margin-bottom: 4px;
        }
        .info-box span {
            color: #6b7280;
            font-size: 14px;
            display: block;
            word-break: break-all;
        }

        /* Button Styles */
        .button-container {
            text-align: center;
            margin: 32px 0;
        }
        .button {
            display: inline-block;
            padding: 14px 32px;
            background-color: #0073aa;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 16px;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            transition: background-color 0.2s;
            mso-padding-alt: 0;
            text-underline-color: #0073aa;
        }
        .button:hover {
            background-color: #005177;
        }
        /* Outlook Button Support */
        <!--[if mso]>
        v\:* {behavior:url(#default#VML);display:inline-block;}
        <![endif]-->

        /* Details Table Styles */
        .details-table {
            width: 100%;
            margin: 24px 0;
            border-collapse: separate;
            border-spacing: 0;
            background-color: #ffffff;
        }
        .details-table td {
            padding: 16px 0;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: top;
        }
        .details-table tr:last-child td {
            border-bottom: none;
        }
        .details-label {
            width: 100px;
            color: #6b7280;
            font-size: 14px;
            font-weight: 500;
            padding-right: 16px;
            white-space: nowrap;
        }
        .details-value {
            color: #111827;
            font-weight: 500;
            text-align: right;
        }

        /* Footer Styles */
        .footer {
            background-color: #f9fafb;
            padding: 32px 40px;
            text-align: center;
            border-top: 1px solid #f3f4f6;
        }
        .footer p {
            margin: 0 0 12px;
            color: #9ca3af;
            font-size: 13px;
        }
        .footer a {
            color: #6b7280;
            text-decoration: underline;
        }
        .timestamp {
            display: inline-block;
            padding: 4px 12px;
            background-color: #f3f4f6;
            border-radius: 12px;
            color: #6b7280;
            font-size: 12px;
            margin-top: 12px;
        }

        /* Responsive Styles */
        @media only screen and (max-width: 600px) {
            .wrapper { padding: 0 !important; }
            .container { border-radius: 0 !important; border: none !important; }
            .header, .content, .footer { padding: 24px 20px !important; }
            .details-table { display: block; width: 100%; }
            .details-table tr { display: block; margin-bottom: 16px; border-bottom: 1px solid #f3f4f6; padding-bottom: 16px; }
            .details-table tr:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
            .details-table td { display: block; width: 100%; padding: 4px 0 !important; border: none !important; text-align: left !important; }
            .details-label { width: auto; font-size: 13px; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px; }
            .details-value { font-size: 16px; }
        }
    </style>
</head>
<body>
    <center class="wrapper">
        <div class="webkit">
            <table class="outer" align="center">
                <tr>
                    <td>
                        <div class="container">
                            <div class="header">
                                <h1><?php echo esc_html($site_name ?? '帳號密碼管理系統'); ?></h1>
                                <div class="logo-bar"></div>
                            </div>
                            <div class="content">
                                <?php echo $content ?? ''; ?>
                            </div>
                            <div class="footer">
                                <p>此郵件由 <a href="<?php echo esc_url($site_url ?? ''); ?>"><?php echo esc_html($site_name ?? ''); ?></a> 自動發送</p>
                                <p>如有任何問題，請聯繫系統管理員</p>
                                <div class="timestamp">
                                    <?php echo esc_html($timestamp ?? current_time('Y-m-d H:i:s')); ?>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
    </center>
</body>
</html>
