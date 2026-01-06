<?php
/**
 * MXP Password Manager - Password Changed Email Template (Plain Text)
 *
 * @package MXP_Password_Manager
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

echo "您好 {$user_name}，\n\n";
echo "您有權存取的服務密碼已被變更：\n\n";
echo "服務名稱：{$service_name}\n";
if (!empty($service_url)) {
    echo "服務網址：{$service_url}\n";
}
echo "\n";
echo "變更者：{$action_by}\n";
echo "變更時間：{$timestamp}\n\n";
echo "請登入系統查看新的密碼資訊。\n\n";
echo "查看新密碼：{$site_url}/wp-admin/admin.php?page=to_account_manager\n\n";
echo "---\n";
echo "如果您正在使用此服務，請確保更新您本地儲存的密碼資訊。\n\n";
echo "此郵件由 {$site_name} 自動發送\n";
echo "發送時間：{$timestamp}\n";
