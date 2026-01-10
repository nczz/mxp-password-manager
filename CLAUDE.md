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
- GitHub 自動更新 (v3.2.0，開箱即用)

## Architecture

### Directory Structure
```
mxp-password-manager/
├── mxp-password-manager.php          # 主程式入口，Mxp_Pm_Settings 等類別
├── update.php                         # 版本遷移系統 (Mxp_Pm_Update)
├── includes/
│   ├── class-mxp-pm-encryption.php    # AES-256-GCM 加密模組 (Mxp_Pm_Encryption)
│   ├── class-mxp-pm-notification.php  # Email 通知模組 (Mxp_Pm_Notification)
│   ├── class-mxp-pm-settings.php      # 網路層級設定頁面 (Mxp_Pm_Settings)
│   ├── class-mxp-pm-hooks.php         # WordPress Actions/Filters 管理 (Mxp_Pm_Hooks)
│   ├── class-mxp-pm-multisite.php     # 多站台中控模組 (Mxp_Pm_Multisite)
│   ├── class-mxp-pm-github-updater-config.php  # GitHub 更新配置 (MXP_GitHub_Updater_Config)
│   └── class-mxp-pm-updater.php       # GitHub 自動更新主類 (Mxp_Pm_Updater)
└── assets/
    ├── css/main.css
    ├── js/main.js                     # 前端邏輯含 TOTP 產生器
    ├── icon-128x128.svg               # 外掛圖標 (v3.4.0+)
    └── templates/emails/              # HTML + 純文字 Email 範本
```

### Key Classes
- **Mxp_Pm_Settings**: 主控制器，處理 AJAX、選單、資源載入
- **Mxp_Pm_Encryption**: 靜態加密/解密方法，金鑰來源優先順序：wp-config 常數 > 環境變數 > 資料庫
- **Mxp_Pm_Notification**: Email 通知發送，支援使用者偏好設定
- **Mxp_Pm_Settings**: 網路管理後台設定頁面
- **Mxp_Pm_Hooks**: 集中管理 do_action/apply_filters
- **Mxp_Pm_Multisite**: 多站台中控功能，包含中控角色、跨站授權、服務範圍管理 (v3.0.0)
- **Mxp_Pm_Updater** (v3.2.0): GitHub 自動更新主類，集成 WordPress 更新系統
- **MXP_GitHub_Updater_Config** (v3.2.0): GitHub 更新配置管理類

### Database Tables (prefix: wp_)
- `mxp_pm_service_list`: 服務帳號資料，含 scope/owner_blog_id 欄位 (v3.0.0)
- `mxp_pm_auth_list`: 使用者與服務的授權對應，含 granted_from_blog_id (v3.0.0)
- `mxp_pm_audit_log`: 操作稽核記錄
- `mxp_pm_service_categories`: 服務分類
- `mxp_pm_service_tags`: 服務標籤
- `mxp_pm_service_tag_map`: 服務與標籤對應
- `mxp_pm_site_access`: 站台存取控制 (v3.0.0)
- `mxp_pm_central_admins`: 中控管理員 (v3.0.0)

## Key Classes (v3.2.0+)
- **MXP_GitHub_Updater_Config**: GitHub 更新配置管理類
  - 管理 GitHub repository、Token、緩存等配置
  - 支援多種 token 來源（wp-config、option、環境變量）
  - 提供 plugin 信息獲取方法
  
- **MXP_GitHub_Updater**: GitHub 自動更新主類
  - 整合 WordPress 更新系統 (`pre_set_site_transient_update_plugins`, `plugins_api`)
  - GitHub API 客戶端（獲取 releases、解析版本、下載）
  - 完整的錯誤處理和 Rate Limiting 機制
  - 支援手動檢查更新、更新通知關閉
  - 自動緩存管理（默認 12 小時）
  - 與現有資料庫遷移系統無縫集成

### Database Tables (prefix: wp_)
- `to_service_list`: 服務帳號資料，含 scope/owner_blog_id 欄位 (v3.0.0)
- `to_auth_list`: 使用者與服務的授權對應，含 granted_from_blog_id (v3.0.0)
- `to_audit_log`: 操作稽核記錄
- `to_site_access`: 站台存取控制 (v3.0.0)
- `to_central_admins`: 中控管理員 (v3.0.0)

### AJAX Endpoints
- `wp_ajax_to_get_service`: 取得服務詳細資料 (含解密)
- `wp_ajax_to_update_service_info`: 更新服務資料
- `wp_ajax_to_add_new_account_service`: 新增服務

## Development Constraints

- **WordPress Compatibility**: 支援 WordPress 單站和 Multisite 環境
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

## GitHub Auto-Update System (v3.2.0)

### 開箱即用設計

- **無需設定**: 外掛安裝後自動使用預設的 GitHub repository
- **自動檢查**: WordPress 定期檢查 GitHub Releases
- **API 限制**: 無需 Token 即可使用（60 次/小時，足夠自動更新）

### 組成架構

- **Mxp_Pm_Updater**: GitHub 更新主類
  - 使用 `pre_set_site_transient_update_plugins` hook 注入更新檢查
  - 使用 `plugins_api` hook 提供外掛詳情
  - 使用 `upgrader_process_complete` hook 清除緩存
  - AJAX 端點：手動檢查更新、關閉通知

- **MXP_GitHub_Updater_Config**: 配置管理類
  - GitHub repository 設置（優先順序：設定 > 常量）
  - GitHub Token 設置（可選，提高 API 限制）
  - 緩存時長、Beta 版本等選項

### GitHub Releases 要求

- **版本標籤**: 使用語義化版本（v3.2.0, v3.3.0）
- **Assets**: 必須包含外掛 ZIP 文件
- **更新日誌**: Markdown 格式的 release notes

### 開發注意事項

- 更新包必須包含完整的外掛代碼
- 資料庫遷移在更新包解壓後自動執行（Mxp_Pm_Update::apply_update()）
- Token 優先順序：wp-config 常數 > option > 環境變量
- 緩存默認 12 小時，可配置
