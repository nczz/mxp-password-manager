# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

MXP Password Manager (mxp-password-manager) 是一個 WordPress Multisite 外掛，用於企業內部帳號密碼集中管理。

**核心功能：**
- 服務帳號資訊儲存與 AES-256-GCM 加密
- 使用者授權管理
- 即時 TOTP 驗證碼產生 (前端 CryptoJS)
- 操作稽核日誌
- Email 通知系統 (HTML + 純文字)
- Hooks 與 Filters 擴充機制

## Architecture

### Directory Structure
```
mxp-password-manager/
├── mxp-password-manager.php       # 主程式入口，Mxp_AccountManager 類別
├── update.php                      # 版本遷移系統 (Mxp_Update)
├── includes/
│   ├── class-mxp-encryption.php   # AES-256-GCM 加密模組
│   ├── class-mxp-notification.php # Email 通知模組
│   ├── class-mxp-settings.php     # 網路層級設定頁面
│   └── class-mxp-hooks.php        # WordPress Actions/Filters 管理
└── assets/
    ├── css/main.css
    ├── js/main.js                  # 前端邏輯含 TOTP 產生器
    └── templates/emails/           # HTML + 純文字 Email 範本
```

### Key Classes
- **Mxp_AccountManager**: 主控制器，處理 AJAX、選單、資源載入
- **Mxp_Encryption**: 靜態加密/解密方法，金鑰來源優先順序：wp-config 常數 > 環境變數 > 資料庫
- **Mxp_Notification**: Email 通知發送，支援使用者偏好設定
- **Mxp_Settings**: 網路管理後台設定頁面
- **Mxp_Hooks**: 集中管理 do_action/apply_filters

### Database Tables (prefix: wp_)
- `to_service_list`: 服務帳號資料，account/password/2fa_token/note 欄位加密儲存
- `to_auth_list`: 使用者與服務的授權對應
- `to_audit_log`: 操作稽核記錄

### AJAX Endpoints
- `wp_ajax_to_get_service`: 取得服務詳細資料 (含解密)
- `wp_ajax_to_update_service_info`: 更新服務資料
- `wp_ajax_to_add_new_account_service`: 新增服務

## Development Constraints

- **WordPress Multisite only**: 外掛僅在子站台 (blog_id != 1) 運作
- **No Node.js/npm**: 所有前端資源本地載入或 CDN
- **PHP 7.4+** 需要 OpenSSL 擴充
- **內建加密**: 使用 PHP openssl_encrypt/decrypt，不依賴外部套件
- **Email**: 使用 WordPress 內建 wp_mail()

## Security Notes

- 所有 AJAX 請求需驗證 WordPress Nonce
- 使用 $wpdb->prepare() 防止 SQL Injection
- 敏感欄位 (account, password, 2fa_token, note) 使用 AES-256-GCM 加密
- 存取控制：授權清單檢查 + mxp_view_all_services capability

## Hooks Reference

**常用 Actions:**
- `mxp_service_created`, `mxp_service_updated`, `mxp_service_viewed`
- `mxp_auth_granted`, `mxp_auth_revoked`
- `mxp_notification_sent`

**常用 Filters:**
- `mxp_encrypt_fields`: 自訂加密欄位
- `mxp_can_view_service`, `mxp_can_edit_service`: 自訂存取權限
- `mxp_notification_message`, `mxp_notification_recipients`: 自訂通知
