<?php
/**
 * MXP Password Manager - Service Created Email Template (HTML)
 *
 * @package MXP_Password_Manager
 * @since 2.0.0
 *
 * Variables available:
 * @var string $user_name     Recipient's display name
 * @var string $service_name  Service name
 * @var string $service_url   Service URL
 * @var string $action_by     Person who created the service
 * @var string $site_name     Site name
 * @var string $site_url      Site URL
 * @var string $timestamp     Notification timestamp
 */

if (!defined('ABSPATH')) {
    exit;
}

// Build content
ob_start();
?>
<h2 style="margin: 0 0 24px; color: #111827; font-size: 20px; font-weight: 600; line-height: 1.4; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">新服務已建立</h2>

<p style="margin: 0 0 24px; color: #4b5563; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 16px; line-height: 1.6;">您好 <?php echo esc_html($user_name); ?>，</p>

<p style="margin: 0 0 24px; color: #4b5563; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 16px; line-height: 1.6;">已為您授權存取新建立的服務：</p>

<div class="info-box success" style="background-color: #ecfdf5; border: 1px solid #e5e7eb; border-left: 4px solid #10b981; padding: 20px; margin: 24px 0; border-radius: 6px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">
    <strong style="color: #111827; display: block; font-size: 16px; margin-bottom: 4px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;"><?php echo esc_html($service_name); ?></strong>
    <?php if (!empty($service_url)): ?>
        <span style="color: #6b7280; font-size: 14px; display: block; word-break: break-all; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;"><?php echo esc_html($service_url); ?></span>
    <?php endif; ?>
</div>

<table class="details-table" width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation" style="width: 100%; margin: 24px 0; border-collapse: separate; border-spacing: 0; background-color: #ffffff;">
    <tr>
        <td class="details-label" valign="top" style="width: 100px; color: #6b7280; font-size: 14px; font-weight: 500; padding: 16px 16px 16px 0; border-bottom: 1px solid #f3f4f6; white-space: nowrap; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">建立者</td>
        <td class="details-value" valign="top" style="color: #111827; font-weight: 500; text-align: right; padding: 16px 0; border-bottom: 1px solid #f3f4f6; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;"><?php echo esc_html($action_by); ?></td>
    </tr>
    <tr>
        <td class="details-label" valign="top" style="width: 100px; color: #6b7280; font-size: 14px; font-weight: 500; padding: 16px 16px 16px 0; border-bottom: none; white-space: nowrap; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">建立時間</td>
        <td class="details-value" valign="top" style="color: #111827; font-weight: 500; text-align: right; padding: 16px 0; border-bottom: none; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;"><?php echo esc_html($timestamp); ?></td>
    </tr>
</table>

<p style="margin: 0 0 24px; color: #4b5563; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 16px; line-height: 1.6;">您現在可以登入系統查看此服務的帳號密碼資訊。</p>

<div class="button-container" style="text-align: center; margin: 32px 0;">
    <!--[if mso]>
    <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="<?php echo esc_url($site_url); ?>/wp-admin/admin.php?page=mxp-password-manager" style="height:48px;v-text-anchor:middle;width:200px;" arcsize="13%" stroke="f" fillcolor="#0073aa">
    <w:anchorlock/>
    <center>
    <![endif]-->
    <a href="<?php echo esc_url($site_url); ?>/wp-admin/admin.php?page=mxp-password-manager" class="button" style="display: inline-block; padding: 14px 32px; background-color: #0073aa; color: #ffffff !important; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 16px; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; mso-padding-alt: 0; transition: background-color 0.2s;">
        查看服務詳情
    </a>
    <!--[if mso]>
    </center>
    </v:roundrect>
    <![endif]-->
</div>
<?php
$content = ob_get_clean();

// Include base template
include dirname(__FILE__) . '/base.php';
