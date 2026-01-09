<?php
/**
 * MXP Password Manager - Service Created Email Template (Plain Text)
 *
 * @package MXP_Password_Manager
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

echo "您好 {$user_name}，\n\n";
echo "已為您授權存取新建立的服務：\n\n";
echo "服務名稱：{$service_name}\n";
if (!empty($service_url)) {
    echo "服務網址：{$service_url}\n";
}
echo "\n";
echo "建立者：{$action_by}\n";
echo "建立時間：{$timestamp}\n\n";
echo "您現在可以登入系統查看此服務的帳號密碼資訊。\n\n";
echo "查看服務：{$site_url}/wp-admin/admin.php?page=mxp-password-manager\n\n";
echo "---\n";
echo "此郵件由 {$site_name} 自動發送\n";
echo "發送時間：{$timestamp}\n";
