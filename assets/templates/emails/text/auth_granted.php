<?php
/**
 * MXP Password Manager - Authorization Granted Email Template (Plain Text)
 *
 * @package MXP_Password_Manager
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

echo "您好 {$user_name}，\n\n";
echo "您已被授權存取以下服務的帳號資訊：\n\n";
echo "服務名稱：{$service_name}\n";
if (!empty($service_url)) {
    echo "服務網址：{$service_url}\n";
}
echo "\n";
echo "授權者：{$action_by}\n";
echo "授權時間：{$timestamp}\n\n";
echo "您現在可以登入系統查看此服務的帳號密碼資訊。\n\n";
echo "查看服務：{$site_url}/wp-admin/admin.php?page=to_account_manager\n\n";
echo "---\n";
echo "如果您認為此授權有誤，請聯繫授權者或系統管理員。\n\n";
echo "此郵件由 {$site_name} 自動發送\n";
echo "發送時間：{$timestamp}\n";
