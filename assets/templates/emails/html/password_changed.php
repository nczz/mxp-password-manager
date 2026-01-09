<?php
/**
 * MXP Password Manager - Password Changed Email Template (HTML)
 *
 * @package MXP_Password_Manager
 * @since 2.0.0
 *
 * Variables available:
 * @var string $user_name     Recipient's display name
 * @var string $service_name  Service name
 * @var string $service_url   Service URL
 * @var string $action_by     Person who changed the password
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
<h2>服務密碼已變更</h2>

<p>您好 <?php echo esc_html($user_name); ?>，</p>

<p>您有權存取的服務密碼已被變更：</p>

<div class="info-box" style="border-left-color: #ffc107;">
    <strong><?php echo esc_html($service_name); ?></strong>
    <?php if (!empty($service_url)): ?>
        <span><?php echo esc_html($service_url); ?></span>
    <?php endif; ?>
</div>

<div class="details">
    <div class="details-row">
        <span class="details-label">變更者</span>
        <span class="details-value"><?php echo esc_html($action_by); ?></span>
    </div>
    <div class="details-row">
        <span class="details-label">變更時間</span>
        <span class="details-value"><?php echo esc_html($timestamp); ?></span>
    </div>
</div>

<p>請登入系統查看新的密碼資訊。</p>

<p style="text-align: center;">
    <a href="<?php echo esc_url($site_url); ?>/wp-admin/admin.php?page=mxp-password-manager" class="button">
        查看新密碼
    </a>
</p>

<p style="color: #6c757d; font-size: 14px;">
    如果您正在使用此服務，請確保更新您本地儲存的密碼資訊。
</p>
<?php
$content = ob_get_clean();

// Include base template
include dirname(__FILE__) . '/base.php';
