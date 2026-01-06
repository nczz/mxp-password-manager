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
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            font-size: 16px;
            line-height: 1.6;
            color: #333333;
            background-color: #f4f4f4;
        }
        .wrapper {
            width: 100%;
            padding: 20px 0;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        .header {
            background: linear-gradient(135deg, #0073aa 0%, #005a87 100%);
            padding: 30px 40px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            color: #ffffff;
            font-size: 24px;
            font-weight: 600;
        }
        .header .logo {
            width: 60px;
            height: 60px;
            margin-bottom: 15px;
        }
        .content {
            padding: 40px;
        }
        .content h2 {
            margin: 0 0 20px;
            color: #1e1e1e;
            font-size: 20px;
            font-weight: 600;
        }
        .content p {
            margin: 0 0 16px;
            color: #50575e;
        }
        .info-box {
            background-color: #f8f9fa;
            border-left: 4px solid #0073aa;
            padding: 16px 20px;
            margin: 20px 0;
            border-radius: 0 4px 4px 0;
        }
        .info-box strong {
            color: #1e1e1e;
            display: block;
            margin-bottom: 4px;
        }
        .info-box span {
            color: #50575e;
            font-size: 14px;
        }
        .button {
            display: inline-block;
            padding: 12px 24px;
            background-color: #0073aa;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 4px;
            font-weight: 500;
            margin: 20px 0;
        }
        .button:hover {
            background-color: #005a87;
        }
        .details {
            background-color: #f8f9fa;
            border-radius: 4px;
            padding: 20px;
            margin: 20px 0;
        }
        .details-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e2e4e7;
        }
        .details-row:last-child {
            border-bottom: none;
        }
        .details-label {
            color: #50575e;
            font-size: 14px;
        }
        .details-value {
            color: #1e1e1e;
            font-weight: 500;
        }
        .footer {
            background-color: #f8f9fa;
            padding: 30px 40px;
            text-align: center;
            border-top: 1px solid #e2e4e7;
        }
        .footer p {
            margin: 0 0 8px;
            color: #50575e;
            font-size: 14px;
        }
        .footer a {
            color: #0073aa;
            text-decoration: none;
        }
        .footer .timestamp {
            color: #8c8f94;
            font-size: 12px;
            margin-top: 16px;
        }
        @media only screen and (max-width: 640px) {
            .container {
                margin: 0 10px;
            }
            .header, .content, .footer {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="container">
            <div class="header">
                <h1><?php echo esc_html($site_name ?? '帳號密碼管理系統'); ?></h1>
            </div>
            <div class="content">
                <?php echo $content ?? ''; ?>
            </div>
            <div class="footer">
                <p>此郵件由 <a href="<?php echo esc_url($site_url ?? ''); ?>"><?php echo esc_html($site_name ?? ''); ?></a> 自動發送</p>
                <p>如有任何問題，請聯繫系統管理員</p>
                <p class="timestamp">發送時間：<?php echo esc_html($timestamp ?? current_time('Y-m-d H:i:s')); ?></p>
            </div>
        </div>
    </div>
</body>
</html>
