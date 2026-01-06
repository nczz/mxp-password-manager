<?php
/**
 * MXP Password Manager - Service Updated Email Template (Plain Text)
 *
 * @package MXP_Password_Manager
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

echo "您好 {$user_name}，\n\n";
echo "您有權存取的服務資訊已被更新：\n\n";
echo "服務名稱：{$service_name}\n";
if (!empty($service_url)) {
    echo "服務網址：{$service_url}\n";
}
echo "\n";
echo "更新者：{$action_by}\n";
echo "更新時間：{$timestamp}\n";
if (!empty($changed_fields) && is_array($changed_fields)) {
    echo "變更項目：" . implode('、', $changed_fields) . "\n";
}
echo "\n";
echo "您可以登入系統查看最新的服務資訊。\n\n";
echo "查看服務：{$site_url}/wp-admin/admin.php?page=to_account_manager\n\n";
echo "---\n";
echo "此郵件由 {$site_name} 自動發送\n";
echo "發送時間：{$timestamp}\n";
