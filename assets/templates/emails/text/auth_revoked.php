<?php
/**
 * MXP Password Manager - Authorization Revoked Email Template (Plain Text)
 *
 * @package MXP_Password_Manager
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

echo "您好 {$user_name}，\n\n";
echo "您的以下服務存取權限已被移除：\n\n";
echo "服務名稱：{$service_name}\n\n";
echo "操作者：{$action_by}\n";
echo "操作時間：{$timestamp}\n\n";
echo "您將無法再查看此服務的帳號密碼資訊。\n\n";
echo "---\n";
echo "如果您認為此操作有誤，或需要重新獲得存取權限，請聯繫服務擁有者或系統管理員。\n\n";
echo "此郵件由 {$site_name} 自動發送\n";
echo "發送時間：{$timestamp}\n";
