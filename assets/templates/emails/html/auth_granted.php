<?php
/**
 * MXP Password Manager - Authorization Granted Email Template (HTML)
 *
 * @package MXP_Password_Manager
 * @since 2.0.0
 *
 * Variables available:
 * @var string $user_name     Recipient's display name
 * @var string $service_name  Service name
 * @var string $service_url   Service URL
 * @var string $action_by     Person who granted access
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
<h2>您已獲得新的服務存取權限</h2>

<p>您好 <?php echo esc_html($user_name); ?>，</p>

<p>您已被授權存取以下服務的帳號資訊：</p>

<div class="info-box">
    <strong><?php echo esc_html($service_name); ?></strong>
    <?php if (!empty($service_url)): ?>
        <span><?php echo esc_html($service_url); ?></span>
    <?php endif; ?>
</div>

<div class="details">
    <div class="details-row">
        <span class="details-label">授權者</span>
        <span class="details-value"><?php echo esc_html($action_by); ?></span>
    </div>
    <div class="details-row">
        <span class="details-label">授權時間</span>
        <span class="details-value"><?php echo esc_html($timestamp); ?></span>
    </div>
</div>

<p>您現在可以登入系統查看此服務的帳號密碼資訊。</p>

<p style="text-align: center;">
    <a href="<?php echo esc_url($site_url); ?>/wp-admin/admin.php?page=to_account_manager" class="button">
        查看服務詳情
    </a>
</p>

<p style="color: #6c757d; font-size: 14px;">
    如果您認為此授權有誤，請聯繫授權者或系統管理員。
</p>
<?php
$content = ob_get_clean();

// Include base template
include dirname(__FILE__) . '/base.php';
