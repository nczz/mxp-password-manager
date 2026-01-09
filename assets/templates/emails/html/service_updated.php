<?php
/**
 * MXP Password Manager - Service Updated Email Template (HTML)
 *
 * @package MXP_Password_Manager
 * @since 2.0.0
 *
 * Variables available:
 * @var string $user_name     Recipient's display name
 * @var string $service_name  Service name
 * @var string $service_url   Service URL
 * @var string $action_by     Person who updated the service
 * @var array  $changed_fields Changed fields (optional)
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
<h2>服務資訊已更新</h2>

<p>您好 <?php echo esc_html($user_name); ?>，</p>

<p>您有權存取的服務資訊已被更新：</p>

<div class="info-box">
    <strong><?php echo esc_html($service_name); ?></strong>
    <?php if (!empty($service_url)): ?>
        <span><?php echo esc_html($service_url); ?></span>
    <?php endif; ?>
</div>

<div class="details">
    <div class="details-row">
        <span class="details-label">更新者</span>
        <span class="details-value"><?php echo esc_html($action_by); ?></span>
    </div>
    <div class="details-row">
        <span class="details-label">更新時間</span>
        <span class="details-value"><?php echo esc_html($timestamp); ?></span>
    </div>
    <?php if (!empty($changed_fields) && is_array($changed_fields)): ?>
        <div class="details-row">
            <span class="details-label">變更項目</span>
            <span class="details-value"><?php echo esc_html(implode('、', $changed_fields)); ?></span>
        </div>
    <?php endif; ?>
</div>

<p>您可以登入系統查看最新的服務資訊。</p>

<p style="text-align: center;">
    <a href="<?php echo esc_url($site_url); ?>/wp-admin/admin.php?page=mxp-password-manager" class="button">
        查看服務詳情
    </a>
</p>
<?php
$content = ob_get_clean();

// Include base template
include dirname(__FILE__) . '/base.php';
