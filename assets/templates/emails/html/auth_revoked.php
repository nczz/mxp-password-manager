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
<h2>服務存取權限已被移除</h2>

<p>您好 <?php echo esc_html($user_name); ?>，</p>

<p>您的以下服務存取權限已被移除：</p>

<div class="info-box" style="border-left-color: #dc3545;">
    <strong><?php echo esc_html($service_name); ?></strong>
</div>

<div class="details">
    <div class="details-row">
        <span class="details-label">操作者</span>
        <span class="details-value"><?php echo esc_html($action_by); ?></span>
    </div>
    <div class="details-row">
        <span class="details-label">操作時間</span>
        <span class="details-value"><?php echo esc_html($timestamp); ?></span>
    </div>
</div>

<p>您將無法再查看此服務的帳號密碼資訊。</p>

<p style="color: #6c757d; font-size: 14px;">
    如果您認為此操作有誤，或需要重新獲得存取權限，請聯繫服務擁有者或系統管理員。
</p>
<?php
$content = ob_get_clean();

// Include base template
include dirname(__FILE__) . '/base.php';
