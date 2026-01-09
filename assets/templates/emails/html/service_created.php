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
<h2>新服務已建立</h2>

<p>您好 <?php echo esc_html($user_name); ?>，</p>

<p>已為您授權存取新建立的服務：</p>

<div class="info-box" style="border-left-color: #28a745;">
    <strong><?php echo esc_html($service_name); ?></strong>
    <?php if (!empty($service_url)): ?>
        <span><?php echo esc_html($service_url); ?></span>
    <?php endif; ?>
</div>

<div class="details">
    <div class="details-row">
        <span class="details-label">建立者</span>
        <span class="details-value"><?php echo esc_html($action_by); ?></span>
    </div>
    <div class="details-row">
        <span class="details-label">建立時間</span>
        <span class="details-value"><?php echo esc_html($timestamp); ?></span>
    </div>
</div>

<p>您現在可以登入系統查看此服務的帳號密碼資訊。</p>

<p style="text-align: center;">
    <a href="<?php echo esc_url($site_url); ?>/wp-admin/admin.php?page=mxp-password-manager" class="button">
        查看服務詳情
    </a>
</p>
<?php
$content = ob_get_clean();

// Include base template
include dirname(__FILE__) . '/base.php';
