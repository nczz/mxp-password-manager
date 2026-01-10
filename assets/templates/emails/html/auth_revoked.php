<?php
/**
 * MXP Password Manager - Authorization Revoked Email Template (HTML)
 *
 * @package MXP_Password_Manager
 * @since 2.0.0
 *
 * Variables available:
 * @var string $user_name     Recipient's display name
 * @var string $service_name  Service name
 * @var string $action_by     Person who revoked access
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
<h2 style="margin: 0 0 24px; color: #111827; font-size: 20px; font-weight: 600; line-height: 1.4; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">服務存取權限已被移除</h2>

<p style="margin: 0 0 24px; color: #4b5563; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 16px; line-height: 1.6;">您好 <?php echo esc_html($user_name); ?>，</p>

<p style="margin: 0 0 24px; color: #4b5563; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 16px; line-height: 1.6;">您的以下服務存取權限已被移除：</p>

<div class="info-box danger" style="background-color: #fef2f2; border: 1px solid #fecaca; border-left: 4px solid #dc2626; padding: 20px; margin: 24px 0; border-radius: 8px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">
    <strong style="color: #111827; display: block; font-size: 16px; margin-bottom: 4px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;"><?php echo esc_html($service_name); ?></strong>
</div>

<table class="details-table" width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation" style="width: 100%; margin: 24px 0; border-collapse: separate; border-spacing: 0; background-color: #ffffff;">
    <tr>
        <td class="details-label" valign="top" style="width: 100px; color: #6b7280; font-size: 14px; font-weight: 500; padding: 16px 16px 16px 0; border-bottom: 1px solid #f3f4f6; white-space: nowrap; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">操作者</td>
        <td class="details-value" valign="top" style="color: #111827; font-weight: 500; text-align: right; padding: 16px 0; border-bottom: 1px solid #f3f4f6; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;"><?php echo esc_html($action_by); ?></td>
    </tr>
    <tr>
        <td class="details-label" valign="top" style="width: 100px; color: #6b7280; font-size: 14px; font-weight: 500; padding: 16px 16px 16px 0; border-bottom: none; white-space: nowrap; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">操作時間</td>
        <td class="details-value" valign="top" style="color: #111827; font-weight: 500; text-align: right; padding: 16px 0; border-bottom: none; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;"><?php echo esc_html($timestamp); ?></td>
    </tr>
</table>

<p style="margin: 0 0 24px; color: #4b5563; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 16px; line-height: 1.6;">您將無法再查看此服務的帳號密碼資訊。</p>

<p style="margin: 0 0 24px; color: #6b7280; font-size: 14px; text-align: center; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; line-height: 1.6;">
    如果您認為此操作有誤，或需要重新獲得存取權限，請聯繫服務擁有者或系統管理員。
</p>
<?php
$content = ob_get_clean();

// Include base template
include dirname(__FILE__) . '/base.php';
