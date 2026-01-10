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
        /* Responsive Styles - These will be ignored by some clients but handle mobile for those that support it */
        @media only screen and (max-width: 600px) {
            .wrapper { padding: 0 !important; }
            .container { width: 100% !important; border-radius: 0 !important; border: none !important; }
            .header-td, .content-td, .footer-td { padding: 24px 20px !important; }
            .details-table { display: block !important; width: 100% !important; }
            .details-table tbody { display: block !important; width: 100% !important; }
            .details-table tr { display: block !important; width: 100% !important; margin-bottom: 16px; border-bottom: 1px solid #f3f4f6; padding-bottom: 16px; }
            .details-table tr:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
            .details-table td { display: block !important; width: 100% !important; padding: 4px 0 !important; border: none !important; text-align: left !important; }
            .details-label { width: auto !important; font-size: 13px !important; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px; }
            .details-value { font-size: 16px !important; text-align: left !important; }
            .button { display: block !important; width: 100% !important; box-sizing: border-box !important; text-align: center !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 16px; line-height: 1.6; color: #374151; background-color: #f3f4f6; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%;">
    <center class="wrapper" style="width: 100%; table-layout: fixed; background-color: #f3f4f6; padding-bottom: 40px;">
        <div class="webkit" style="max-width: 600px; margin: 0 auto;">
            <table class="outer" align="center" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 auto; width: 100%; max-width: 600px; border-spacing: 0; border-collapse: collapse; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">
                <tr>
                    <td style="padding: 0;">
                        <!-- Container as a Table for better compatibility -->
                        <table class="container" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); border: 1px solid #e5e7eb; border-spacing: 0; border-collapse: separate;">
                            <!-- Header -->
                            <tr>
                                <td class="header-td" style="background-color: #ffffff; padding: 32px 40px 0; text-align: center;">
                                    <h1 style="margin: 0; color: #111827; font-size: 24px; font-weight: 700; letter-spacing: -0.025em; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; line-height: 1.2;"><?php echo esc_html($site_name ?? '帳號密碼管理系統'); ?></h1>
                                    <div class="logo-bar" style="height: 4px; width: 48px; background-color: #0073aa; margin: 24px auto 0; border-radius: 2px; font-size: 0; line-height: 0;">&nbsp;</div>
                                </td>
                            </tr>
                            <!-- Content -->
                            <tr>
                                <td class="content-td" style="padding: 40px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 16px; line-height: 1.6; color: #374151; background-color: #ffffff;">
                                    <?php echo $content ?? ''; ?>
                                </td>
                            </tr>
                            <!-- Footer -->
                            <tr>
                                <td class="footer-td" style="background-color: #f9fafb; padding: 32px 40px; text-align: center; border-top: 1px solid #f3f4f6; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">
                                    <p style="margin: 0 0 12px; color: #9ca3af; font-size: 13px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; line-height: 1.5;">此郵件由 <a href="<?php echo esc_url($site_url ?? ''); ?>" style="color: #6b7280; text-decoration: underline;"><?php echo esc_html($site_name ?? ''); ?></a> 自動發送</p>
                                    <p style="margin: 0 0 12px; color: #9ca3af; font-size: 13px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; line-height: 1.5;">如有任何問題，請聯繫系統管理員</p>
                                    <div class="timestamp" style="display: inline-block; padding: 4px 12px; background-color: #f3f4f6; border-radius: 12px; color: #6b7280; font-size: 12px; margin-top: 12px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; line-height: 1.4;">
                                        <?php echo esc_html($timestamp ?? current_time('Y-m-d H:i:s')); ?>
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </div>
    </center>
</body>
</html>
