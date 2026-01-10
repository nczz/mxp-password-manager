# Software Design Document (SDD)

## MXP Password Manager

**版本:** 2.0.0
**作者:** Chun
**最後更新:** 2026-01-06

---

## 目錄

1. [簡介](#1-簡介)
2. [系統概述](#2-系統概述)
3. [架構設計](#3-架構設計)
4. [資料庫設計](#4-資料庫設計)
5. [模組設計](#5-模組設計)
6. [API 設計](#6-api-設計)
7. [前端設計](#7-前端設計)
8. [安全性設計](#8-安全性設計)
9. [Hooks 與 Filters 參考](#9-hooks-與-filters-參考)
10. [使用者偏好設定](#10-使用者偏好設定)
11. [部署與依賴](#11-部署與依賴)
12. [版本歷史](#12-版本歷史)

---

## 1. 簡介

### 1.1 目的

本文件描述「帳號密碼管理工具」(MXP Password Manager) WordPress 外掛的軟體設計規格，提供開發團隊完整的技術參考。

### 1.2 範圍

本外掛提供企業內部帳號密碼集中管理功能，包含：

- 服務帳號資訊儲存與內建加密模組
- 使用者授權管理
- 即時 TOTP 驗證碼產生
- 操作稽核日誌
- Email 通知系統（支援 HTML + 純文字）
- 使用者通知偏好設定
- 完整的 Hooks 與 Filters 擴充機制

### 1.3 術語定義

| 術語 | 定義 |
|------|------|
| TOTP | Time-based One-Time Password，基於時間的一次性密碼 |
| 2FA | Two-Factor Authentication，雙因素認證 |
| AES-256-GCM | Advanced Encryption Standard with Galois/Counter Mode |
| Nonce | Number used once，用於防止 CSRF 攻擊的一次性數值 |

### 1.4 參考文件

- [RFC 4226](https://tools.ietf.org/html/rfc4226) - HOTP Algorithm
- [RFC 6238](https://tools.ietf.org/html/rfc6238) - TOTP Algorithm
- [RFC 4648](https://tools.ietf.org/html/rfc4648) - Base32 Encoding
- [WordPress Plugin API](https://developer.wordpress.org/plugins/)

---

## 2. 系統概述

### 2.1 系統架構圖

```
┌─────────────────────────────────────────────────────────────────┐
│                         瀏覽器 (Browser)                         │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────────────────┐  │
│  │   jQuery    │  │   Select2   │  │  CryptoJS (TOTP 產生)   │  │
│  └─────────────┘  └─────────────┘  └─────────────────────────┘  │
└───────────────────────────┬─────────────────────────────────────┘
                            │ AJAX Requests
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│                    WordPress Backend                             │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │               Mxp_AccountManager (Main Class)               ││
│  │  ┌──────────────┐ ┌──────────────┐ ┌──────────────────────┐ ││
│  │  │ AJAX Handler │ │ Auth Manager │ │ Audit Logger         │ ││
│  │  └──────────────┘ └──────────────┘ └──────────────────────┘ ││
│  └─────────────────────────────────────────────────────────────┘│
│                              │                                   │
│  ┌───────────────────────────┼───────────────────────────────┐  │
│  │               內建模組 (Built-in Modules)                  │  │
│  │  ┌─────────────┐ ┌─────────────┐ ┌─────────────┐          │  │
│  │  │ Mxp_       │ │ Mxp_        │ │ Mxp_        │          │  │
│  │  │ Encryption │ │ Notification│ │ Settings    │          │  │
│  │  │ (加密模組)  │ │ (通知模組)  │ │ (設定模組)   │          │  │
│  │  └─────────────┘ └─────────────┘ └─────────────┘          │  │
│  └───────────────────────────────────────────────────────────┘  │
│                              │                                   │
│  ┌───────────────────────────┼───────────────────────────────┐  │
│  │               Mxp_Hooks (事件鉤子管理)                    │  │
│  │  Actions: mxp_service_created, mxp_auth_granted, ...    │  │
│  │  Filters: mxp_encrypt_fields, mxp_can_view_service, ... │  │
│  └───────────────────────────────────────────────────────────┘  │
└───────────────────────────┬─────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│                     MySQL Database                               │
│  ┌─────────────────┐ ┌─────────────┐ ┌────────────────────────┐ │
│  │ to_service_list │ │ to_auth_list│ │ to_audit_log           │ │
│  │ (服務帳號資料)   │ │ (授權清單)   │ │ (稽核日誌)             │ │
│  └─────────────────┘ └─────────────┘ └────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
```

### 2.2 系統環境

| 項目 | 規格 |
|------|------|
| 平台 | WordPress Multisite |
| PHP 版本 | 7.4+ (需 OpenSSL 擴充) |
| 資料庫 | MySQL 5.7+ / MariaDB 10.3+ |
| 瀏覽器 | 現代瀏覽器 (Chrome, Firefox, Safari, Edge) |

### 2.3 設計約束

1. **WordPress Multisite 限制**: 外掛僅在子站台運作，主站台 (blog_id == 1) 停用
2. **無 Node.js 依賴**: 不使用 npm/yarn，所有前端資源本地載入或 CDN
3. **內建加密模組**: 使用 PHP OpenSSL 擴充實作 AES-256-GCM 加密
4. **Email 通知**: 使用 WordPress 內建 `wp_mail()` 函數發送通知
5. **網路層級設定**: 加密設定由超級管理員於網路層級統一管理

---

## 3. 架構設計

### 3.1 目錄結構

```
mxp-password-manager/
├── mxp-password-manager.php          # 主程式入口
├── update.php                         # 版本遷移系統
├── SDD.md                             # 本設計文件
├── includes/
│   ├── class-mxp-pm-encryption.php    # 加密模組 (Mxp_Pm_Encryption)
│   ├── class-mxp-pm-notification.php  # 通知模組 (Mxp_Pm_Notification)
│   ├── class-mxp-pm-settings.php      # 設定頁面 (Mxp_Pm_Settings)
│   ├── class-mxp-pm-hooks.php         # Hooks 管理 (Mxp_Pm_Hooks)
│   ├── class-mxp-pm-multisite.php     # 多站台中控模組 (Mxp_Pm_Multisite)
│   ├── class-mxp-pm-github-updater-config.php  # GitHub 更新配置 (MXP_GitHub_Updater_Config)
│   └── class-mxp-pm-updater.php       # GitHub 自動更新主類 (Mxp_Pm_Updater)
└── assets/
    ├── css/
    │   └── main.css
    ├── js/
    │   └── main.js                    # 主要前端邏輯 (含 TOTP)
    ├── icon-128x128.svg               # 外掛圖標 (v3.4.0+)
    ├── vendor/
    │   ├── select2/
    │   └── cryptojs/
    └── templates/
        └── emails/
            ├── html/
            │   ├── base.php
            │   ├── auth_granted.php
            │   ├── auth_revoked.php
            │   ├── password_changed.php
            │   ├── service_created.php
            │   └── service_updated.php
            └── text/
                ├── auth_granted.php
                ├── auth-revoked.php
                ├── service-updated.php
                └── password-changed.php
```

### 3.2 類別圖

```
┌─────────────────────────────────────────────────────────────────┐
│                     Mxp_AccountManager                          │
├─────────────────────────────────────────────────────────────────┤
│ + $VERSION : string = '2.0.0'                                   │
│ - $instance : Mxp_AccountManager = null                        │
│ + $plugin_slug : string = 'mxp-password-manager'             │
├─────────────────────────────────────────────────────────────────┤
│ + __construct()                                                  │
│ + install() : void                                               │
│ + update(ver: string) : void                                    │
│ + init() : void                                                  │
│ + create_plugin_menu() : void                                   │
│ + load_assets(hook: string) : void                              │
│ + ajax_to_get_service() : void                                  │
│ + ajax_to_update_service_info() : void                          │
│ + ajax_to_add_new_account_service() : void                      │
│ + get_all_team_users() : array                                  │
│ + add_audit_log(params: array) : void                           │
│ + username_maps() : array                                       │
│ + to_account_manager_dashboard_cb() : void                      │
└───────────────┬─────────────────────────────────────────────────┘
                │
    ┌───────────┼───────────┬───────────────┐
    │           │           │               │
    ▼           ▼           ▼               ▼
┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐
│ Mxp_Encryption │ │ Mxp_Notification│ │ Mxp_Settings   │ │ Mxp_Hooks      │
├─────────────────┤ ├─────────────────┤ ├─────────────────┤ ├─────────────────┤
│ AES-256-GCM     │ │ Email 通知       │ │ 網路層級設定    │ │ Actions/Filters │
│ 金鑰管理        │ │ HTML+純文字      │ │ 權限管理        │ │ 事件觸發        │
└─────────────────┘ └─────────────────┘ └─────────────────┘ └─────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                        Mxp_Update                               │
├─────────────────────────────────────────────────────────────────┤
│ + $version_list : array = ['1.0.0', '2.0.0']                    │
├─────────────────────────────────────────────────────────────────┤
│ + apply_update(ver: string) : void                              │
│ - mxp_update_to_v1_0_0() : bool                                │
│ - mxp_update_to_v2_0_0() : bool                                │
└─────────────────────────────────────────────────────────────────┘
```

### 3.3 初始化流程

```
Plugin Load
    │
    ▼
┌─────────────────────┐
│ 載入 includes/      │
│ 類別檔案            │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│ new Mxp_Account    │
│ Manager()           │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│ Mxp_Hooks::init()  │
│ 註冊所有 hooks      │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│ Check version from  │
│ network_option      │
└──────────┬──────────┘
           │
     ┌─────┴─────┐
     │           │
     ▼           ▼
┌─────────┐ ┌─────────────┐
│ No      │ │ Version     │
│ Version │ │ Mismatch    │
│ Found   │ │ Found       │
└────┬────┘ └──────┬──────┘
     │             │
     ▼             ▼
┌─────────┐ ┌─────────────┐
│ install()│ │ update()    │
│ Create  │ │ Run         │
│ Tables  │ │ Migrations  │
└────┬────┘ └──────┬──────┘
     │             │
     └──────┬──────┘
            │
            ▼
┌─────────────────────┐
│ init()              │
│ Register WP Hooks   │
└─────────────────────┘
```

---

## 4. 資料庫設計

### 4.1 資料表概覽

| 資料表名稱 | 用途 | 主鍵 |
|-----------|------|------|
| `{prefix}to_service_list` | 儲存服務帳號資料 | `sid` |
| `{prefix}to_service_categories` | 服務分類 | `cid` |
| `{prefix}to_service_tags` | 服務標籤 | `tid` |
| `{prefix}to_service_tag_map` | 服務與標籤對應 | `mid` |
| `{prefix}to_auth_list` | 使用者授權對應 | `sid` |
| `{prefix}to_audit_log` | 操作稽核日誌 | `sid` |

> **注意**: `{prefix}` 為 WordPress Multisite 的 `$wpdb->base_prefix`

### 4.2 實體關係圖 (ERD)

```
┌───────────────────────────────────────┐
│       to_service_categories           │
├───────────────────────────────────────┤
│ PK │ cid           │ INT UNSIGNED     │──┐
│    │ category_name │ VARCHAR(100)     │  │
│    │ category_icon │ VARCHAR(50)      │  │
│    │ sort_order    │ INT              │  │
│    │ created_time  │ DATETIME         │  │
└───────────────────────────────────────┘  │
                                           │
┌───────────────────────────────────────┐  │
│           to_service_list             │  │
├───────────────────────────────────────┤  │
│ PK │ sid           │ INT UNSIGNED     │──┼──┐
│ FK │ category_id   │ INT UNSIGNED     │◀─┘  │
│    │ service_name  │ VARCHAR(500)     │     │
│    │ login_url     │ TEXT             │     │
│    │ account       │ VARCHAR(500) 🔐  │     │
│    │ password      │ TEXT 🔐          │     │
│    │ reg_email     │ VARCHAR(500)     │     │
│    │ reg_phone     │ VARCHAR(500)     │     │
│    │ reg_phone2    │ VARCHAR(500)     │     │
│    │ 2fa_token     │ TEXT 🔐          │     │
│    │ recover_code  │ TEXT             │     │
│    │ note          │ TEXT 🔐          │     │
│    │ status        │ ENUM             │     │ ← 新增：active/archived/suspended
│    │ priority      │ TINYINT          │     │ ← 新增：重要程度 1-5
│    │ last_used     │ DATETIME         │     │ ← 新增：最後使用時間
│    │ created_time  │ DATETIME         │     │ ← 新增：建立時間
│    │ updated_time  │ DATETIME         │     │
└───────────────────────────────────────┘     │
        │                                     │
        │  ┌──────────────────────────────────┤
        │  │                                  │
        ▼  ▼                                  ▼
┌───────────────────────────┐  ┌───────────────────────────┐
│      to_auth_list         │  │      to_audit_log         │
├───────────────────────────┤  ├───────────────────────────┤
│ PK │ sid        │ INT     │  │ PK │ sid        │ INT     │
│ FK │ service_id │ INT     │  │ FK │ service_id │ INT     │
│    │ user_id    │ INT     │  │    │ user_id    │ INT     │
│    │ added_time │ DATETIME│  │    │ user_name  │ VARCHAR │
└───────────────────────────┘  │    │ action     │ VARCHAR │
                               │    │ field_name │ VARCHAR │
┌───────────────────────────┐  │    │ old_value  │ TEXT    │
│      to_service_tags      │  │    │ new_value  │ TEXT    │
├───────────────────────────┤  │    │ added_time │ DATETIME│
│ PK │ tid        │ INT     │  └───────────────────────────┘
│    │ tag_name   │ VARCHAR │
│    │ tag_color  │ VARCHAR │  ← 標籤顏色 (HEX)
│    │ created_by │ INT     │
└───────────────────────────┘
        │
        ▼
┌───────────────────────────┐
│    to_service_tag_map     │
├───────────────────────────┤
│ PK │ mid        │ INT     │
│ FK │ service_id │ INT     │
│ FK │ tag_id     │ INT     │
└───────────────────────────┘

🔐 = 加密儲存欄位 (使用 Mxp_Encryption)
```

### 4.3 資料表詳細規格

#### 4.3.1 to_service_list

| 欄位 | 類型 | 約束 | 說明 |
|------|------|------|------|
| `sid` | INT(10) UNSIGNED | PRIMARY KEY, AUTO_INCREMENT | 服務 ID |
| `category_id` | INT(10) UNSIGNED | DEFAULT NULL, INDEX | 分類 ID (FK) |
| `service_name` | VARCHAR(500) | NOT NULL, DEFAULT '' | 服務名稱 |
| `login_url` | TEXT | | 登入網址 |
| `account` | VARCHAR(500) | DEFAULT '' | 登入帳號 (加密) |
| `password` | TEXT | | 登入密碼 (加密) |
| `reg_email` | VARCHAR(500) | DEFAULT '' | 註冊信箱 |
| `reg_phone` | VARCHAR(500) | DEFAULT '' | 註冊電話 1 |
| `reg_phone2` | VARCHAR(500) | DEFAULT '' | 註冊電話 2 |
| `2fa_token` | TEXT | | 2FA 金鑰 (加密) |
| `recover_code` | TEXT | | 救援碼 |
| `note` | TEXT | | 備註 (加密) |
| `status` | ENUM('active','archived','suspended') | DEFAULT 'active', INDEX | 狀態 |
| `priority` | TINYINT(1) UNSIGNED | DEFAULT 3 | 重要程度 (1-5, 5 最高) |
| `last_used` | DATETIME | DEFAULT NULL | 最後使用時間 |
| `created_time` | DATETIME | DEFAULT CURRENT_TIMESTAMP | 建立時間 |
| `updated_time` | DATETIME | DEFAULT CURRENT_TIMESTAMP ON UPDATE | 最後更新時間 |

**狀態說明：**
- `active`: 啟用中，正常顯示於列表
- `archived`: 已歸檔，預設隱藏但可查詢
- `suspended`: 已停用，帳號可能已失效或待驗證

#### 4.3.2 to_service_categories (新增)

| 欄位 | 類型 | 約束 | 說明 |
|------|------|------|------|
| `cid` | INT(10) UNSIGNED | PRIMARY KEY, AUTO_INCREMENT | 分類 ID |
| `category_name` | VARCHAR(100) | NOT NULL, UNIQUE | 分類名稱 |
| `category_icon` | VARCHAR(50) | DEFAULT 'dashicons-category' | Dashicons 圖示類別 |
| `sort_order` | INT(10) | DEFAULT 0 | 排序順序 |
| `created_time` | DATETIME | DEFAULT CURRENT_TIMESTAMP | 建立時間 |

**預設分類：**
- 開發工具 (dashicons-editor-code)
- 雲端服務 (dashicons-cloud)
- 社交媒體 (dashicons-share)
- 金融服務 (dashicons-bank)
- 企業內部 (dashicons-building)
- 其他 (dashicons-category)

#### 4.3.3 to_service_tags (新增)

| 欄位 | 類型 | 約束 | 說明 |
|------|------|------|------|
| `tid` | INT(10) UNSIGNED | PRIMARY KEY, AUTO_INCREMENT | 標籤 ID |
| `tag_name` | VARCHAR(50) | NOT NULL, UNIQUE | 標籤名稱 |
| `tag_color` | VARCHAR(7) | DEFAULT '#6c757d' | 標籤顏色 (HEX) |
| `created_by` | INT(10) UNSIGNED | NOT NULL | 建立者 ID |
| `created_time` | DATETIME | DEFAULT CURRENT_TIMESTAMP | 建立時間 |

#### 4.3.4 to_service_tag_map (新增)

| 欄位 | 類型 | 約束 | 說明 |
|------|------|------|------|
| `mid` | INT(10) UNSIGNED | PRIMARY KEY, AUTO_INCREMENT | 對應 ID |
| `service_id` | INT(10) UNSIGNED | NOT NULL, INDEX | 服務 ID (FK) |
| `tag_id` | INT(10) UNSIGNED | NOT NULL, INDEX | 標籤 ID (FK) |

**約束**: UNIQUE(`service_id`, `tag_id`)

#### 4.3.5 to_auth_list

| 欄位 | 類型 | 約束 | 說明 |
|------|------|------|------|
| `sid` | INT(10) UNSIGNED | PRIMARY KEY, AUTO_INCREMENT | 主鍵 |
| `service_id` | INT(10) UNSIGNED | NOT NULL | 服務 ID (FK) |
| `user_id` | INT(10) UNSIGNED | NOT NULL | WordPress 使用者 ID |
| `added_time` | DATETIME | DEFAULT CURRENT_TIMESTAMP | 授權時間 |

#### 4.3.6 to_audit_log

| 欄位 | 類型 | 約束 | 說明 |
|------|------|------|------|
| `sid` | INT(10) UNSIGNED | PRIMARY KEY, AUTO_INCREMENT | 主鍵 |
| `service_id` | INT(10) UNSIGNED | NOT NULL | 服務 ID (FK) |
| `user_id` | INT(10) UNSIGNED | NOT NULL | 操作使用者 ID |
| `user_name` | VARCHAR(100) | DEFAULT '' | 操作者顯示名稱 |
| `action` | VARCHAR(100) | DEFAULT '' | 操作類型 |
| `field_name` | VARCHAR(100) | DEFAULT '' | 修改欄位名稱 |
| `old_value` | TEXT | | 原始值 |
| `new_value` | TEXT | | 新值 |
| `added_time` | DATETIME | DEFAULT CURRENT_TIMESTAMP | 操作時間 |

### 4.4 操作類型 (Action Types)

| 操作 | 說明 |
|------|------|
| `查看` | 使用者查看服務詳細資料 |
| `新增` | 新增授權使用者或新增服務 |
| `移除` | 移除授權使用者 |
| `更新` | 更新服務資料欄位 |
| `歸檔` | 將服務歸檔 |
| `取消歸檔` | 將服務從歸檔中恢復 |
| `停用` | 將服務標記為停用 |

### 4.5 資料庫索引設計

為支援高效搜尋與篩選，建議建立以下索引：

```sql
-- to_service_list 索引
CREATE INDEX idx_service_status ON to_service_list(status);
CREATE INDEX idx_service_category ON to_service_list(category_id);
CREATE INDEX idx_service_priority ON to_service_list(priority);
CREATE INDEX idx_service_name ON to_service_list(service_name(100));
CREATE INDEX idx_service_updated ON to_service_list(updated_time);
CREATE INDEX idx_service_status_category ON to_service_list(status, category_id);

-- to_service_tag_map 索引
CREATE INDEX idx_tagmap_service ON to_service_tag_map(service_id);
CREATE INDEX idx_tagmap_tag ON to_service_tag_map(tag_id);
```

---

## 5. 模組設計

### 5.1 主程式模組 (mxp-password-manager.php)

#### 5.1.1 建構子與初始化

```php
public function __construct() {
    // 1. 載入 includes/ 目錄下的類別
    // 2. 初始化 Mxp_Hooks
    // 3. 從 network_option 取得版本
    // 4. 若無版本 → 執行 install()
    // 5. 若版本不符 → 執行 update()
    // 6. 執行 init() 註冊 hooks
}
```

#### 5.1.2 資料表安裝

```php
public function install() {
    // 使用 dbDelta() 建立三個資料表
    // 初始化加密金鑰（若使用資料庫模式）
    // 更新 network_option 版本號
}
```

#### 5.1.3 Hook 註冊

| Hook | 回調函數 | 說明 |
|------|---------|------|
| `admin_menu` | `create_plugin_menu()` | 建立管理選單 |
| `network_admin_menu` | `create_network_settings_menu()` | 建立網路設定選單 |
| `admin_enqueue_scripts` | `load_assets()` | 載入前端資源 |
| `wp_ajax_to_get_service` | `ajax_to_get_service()` | AJAX: 取得服務 |
| `wp_ajax_to_update_service_info` | `ajax_to_update_service_info()` | AJAX: 更新服務 |
| `wp_ajax_to_add_new_account_service` | `ajax_to_add_new_account_service()` | AJAX: 新增服務 |
| `show_user_profile` | `render_user_notification_settings()` | 使用者偏好設定 |
| `edit_user_profile` | `render_user_notification_settings()` | 使用者偏好設定 |
| `personal_options_update` | `save_user_notification_settings()` | 儲存偏好設定 |
| `edit_user_profile_update` | `save_user_notification_settings()` | 儲存偏好設定 |

### 5.2 版本遷移模組 (update.php)

```php
class Mxp_Update {
    public static $version_list = array('1.0.0', '2.0.0');

    public static function apply_update($ver) {
        // 依序執行版本遷移函數
    }

    private static function mxp_update_to_v1_0_0() {
        // 版本 1.0.0 遷移邏輯
        return true;
    }

    private static function mxp_update_to_v2_0_0() {
        // 版本 2.0.0 遷移邏輯
        // - 遷移加密資料至新格式
        // - 建立使用者偏好設定預設值
        return true;
    }
}
```

### 5.3 前端模組 (assets/js/main.js)

#### 5.3.1 TOTP 產生器

```javascript
// RFC 4226/6238 相容的 TOTP 實作
function generateTOTP(secret) {
    // 1. 計算時間區間 (30 秒)
    // 2. Base32 解碼金鑰
    // 3. HMAC-SHA1 運算
    // 4. 動態截斷取得 6 位數碼
    return otpCode;
}
```

#### 5.3.2 核心函數

| 函數 | 說明 |
|------|------|
| `generateTOTP(secret)` | 產生 TOTP 驗證碼 |
| `hexToBytes(hex)` | 十六進位轉位元組陣列 |
| `base32ToBytes(base32)` | Base32 轉位元組陣列 |
| `updateOTPDisplay(element, secret)` | 更新 OTP 顯示與倒數 |
| `createOTPDisplay(container, secret, fieldId)` | 建立 OTP UI 元件 |
| `add_account_info_table(prefix, table_id, action, data)` | 動態建立服務表單 |
| `add_service_info_btn()` | 新增服務按鈕處理 |
| `update_service_info_btn()` | 更新服務按鈕處理 |

### 5.4 加密模組 (includes/class-mxp-encryption.php)

#### 5.4.1 類別定義

```php
class Mxp_Encryption {
    /**
     * 加密演算法
     */
    private static $cipher = 'aes-256-gcm';

    /**
     * 取得加密金鑰（混合模式）
     * 優先順序: wp-config 常數 > 環境變數 > 資料庫
     */
    public static function get_key(): string;

    /**
     * 加密資料
     * @param string $plaintext 明文
     * @return string Base64 編碼的密文
     */
    public static function encrypt(string $plaintext): string;

    /**
     * 解密資料
     * @param string $ciphertext Base64 編碼的密文
     * @return string 明文
     */
    public static function decrypt(string $ciphertext): string;

    /**
     * 產生新的加密金鑰
     * @return string 32 位元組的金鑰
     */
    public static function generate_key(): string;

    /**
     * 執行金鑰輪替
     * @param string $old_key 舊金鑰
     * @param string $new_key 新金鑰
     * @return bool 是否成功
     */
    public static function rotate_key(string $old_key, string $new_key): bool;

    /**
     * 檢查加密是否已設定
     * @return bool
     */
    public static function is_configured(): bool;

    /**
     * 取得目前金鑰來源
     * @return string 'constant' | 'env' | 'database' | 'none'
     */
    public static function get_key_source(): string;
}
```

#### 5.4.2 金鑰儲存方式 (混合模式)

```php
public static function get_key(): string {
    // 1. 優先檢查 wp-config.php 常數
    if (defined('MXP_ENCRYPTION_KEY') && MXP_ENCRYPTION_KEY) {
        return MXP_ENCRYPTION_KEY;
    }

    // 2. 檢查環境變數
    if (!empty($_ENV['MXP_ENCRYPTION_KEY'])) {
        return $_ENV['MXP_ENCRYPTION_KEY'];
    }

    // 3. Fallback 到資料庫 (網路層級)
    return get_site_option('mxp_encryption_key', '');
}
```

| 優先順序 | 來源 | 說明 | 安全性 |
|----------|------|------|--------|
| 1 | `MXP_ENCRYPTION_KEY` 常數 | wp-config.php 定義 | 高 |
| 2 | `$_ENV['MXP_ENCRYPTION_KEY']` | 環境變數 | 高 |
| 3 | `get_site_option()` | 資料庫儲存 | 中 |

#### 5.4.3 加密流程

```
加密:
明文 → AES-256-GCM 加密 (含 IV + Tag) → Base64 編碼 → 儲存

解密:
儲存 → Base64 解碼 → 分離 IV + Tag + 密文 → AES-256-GCM 解密 → 明文
```

#### 5.4.4 加密欄位

| 欄位 | 加密 | 說明 |
|------|------|------|
| `account` | ✓ | 登入帳號 |
| `password` | ✓ | 登入密碼 |
| `2fa_token` | ✓ | TOTP 金鑰 |
| `note` | ✓ | 備註內容 |

可透過 `mxp_encrypt_fields` filter 自訂加密欄位。

### 5.5 通知模組 (includes/class-mxp-notification.php)

#### 5.5.1 類別定義

```php
class Mxp_Notification {
    /**
     * 通知類型常數
     */
    const NOTIFY_AUTH_GRANTED = 'auth_granted';
    const NOTIFY_AUTH_REVOKED = 'auth_revoked';
    const NOTIFY_SERVICE_UPDATED = 'service_updated';
    const NOTIFY_PASSWORD_CHANGED = 'password_changed';
    const NOTIFY_SERVICE_CREATED = 'service_created';

    /**
     * 發送通知給單一使用者
     * @param int $user_id 使用者 ID
     * @param string $type 通知類型
     * @param array $data 通知資料
     * @return bool 是否發送成功
     */
    public static function send_to_user(int $user_id, string $type, array $data): bool;

    /**
     * 發送通知給服務的所有授權使用者
     * @param int $service_id 服務 ID
     * @param string $type 通知類型
     * @param array $data 通知資料
     * @return array 發送結果
     */
    public static function send_to_service_users(int $service_id, string $type, array $data): array;

    /**
     * 取得 Email 範本
     * @param string $template_name 範本名稱
     * @param array $data 範本資料
     * @param string $format 格式 ('html' | 'text')
     * @return string 渲染後的內容
     */
    public static function get_template(string $template_name, array $data, string $format = 'html'): string;

    /**
     * 取得使用者通知偏好
     * @param int $user_id 使用者 ID
     * @return array 偏好設定
     */
    public static function get_user_preferences(int $user_id): array;

    /**
     * 檢查是否應發送通知給使用者
     * @param int $user_id 使用者 ID
     * @param string $type 通知類型
     * @return bool
     */
    public static function should_notify_user(int $user_id, string $type): bool;

    /**
     * 取得使用者偏好的 Email 格式
     * @param int $user_id 使用者 ID
     * @return string 'html' | 'text'
     */
    public static function get_preferred_format(int $user_id): string;
}
```

#### 5.5.2 通知類型

| 類型常數 | 事件 | 通知對象 | 預設內容 |
|---------|------|---------|----------|
| `NOTIFY_AUTH_GRANTED` | 新增授權 | 被授權使用者 | 「您已獲得 {服務名稱} 的存取權限」 |
| `NOTIFY_AUTH_REVOKED` | 移除授權 | 被移除使用者 | 「您的 {服務名稱} 存取權限已被移除」 |
| `NOTIFY_SERVICE_UPDATED` | 服務更新 | 所有授權使用者 | 「{服務名稱} 的資訊已更新」 |
| `NOTIFY_PASSWORD_CHANGED` | 密碼變更 | 所有授權使用者 | 「{服務名稱} 的密碼已變更」 |
| `NOTIFY_SERVICE_CREATED` | 服務建立 | 被授權使用者 | 「新服務 {服務名稱} 已建立」 |

#### 5.5.3 Email 範本結構

```
assets/templates/emails/
├── html/
│   ├── base.php              # HTML 基底範本（含樣式）
│   ├── auth-granted.php      # 授權新增通知
│   ├── auth-revoked.php      # 授權移除通知
│   ├── service-updated.php   # 服務更新通知
│   └── password-changed.php  # 密碼變更通知
└── text/
    ├── auth-granted.php      # 純文字版本
    ├── auth-revoked.php
    ├── service-updated.php
    └── password-changed.php
```

#### 5.5.4 範本變數

| 變數 | 說明 |
|------|------|
| `{service_name}` | 服務名稱 |
| `{user_name}` | 使用者顯示名稱 |
| `{site_name}` | 網站名稱 |
| `{site_url}` | 網站網址 |
| `{action_by}` | 操作者名稱 |
| `{timestamp}` | 操作時間 |

### 5.6 設定模組 (includes/class-mxp-settings.php)

#### 5.6.1 類別定義

```php
class Mxp_Settings {
    /**
     * 初始化設定頁面
     */
    public static function init(): void;

    /**
     * 註冊網路設定頁面
     */
    public static function register_network_settings_page(): void;

    /**
     * 渲染設定頁面
     */
    public static function render_settings_page(): void;

    /**
     * 儲存設定
     */
    public static function save_settings(): void;

    /**
     * 取得設定值
     * @param string $key 設定鍵
     * @param mixed $default 預設值
     * @return mixed
     */
    public static function get(string $key, $default = null);

    /**
     * 更新設定值
     * @param string $key 設定鍵
     * @param mixed $value 設定值
     * @return bool
     */
    public static function update(string $key, $value): bool;

    /**
     * 檢查使用者是否有權限
     * @param string $capability 權限名稱
     * @return bool
     */
    public static function user_can(string $capability): bool;
}
```

#### 5.6.2 設定頁面結構 (網路層級)

```
WordPress 網路管理後台 (Network Admin)
└── 設定
    └── 帳號管理設定 (mxp-account-settings)
        ├── 加密設定
        │   ├── 加密狀態顯示
        │   │   ├── 目前金鑰來源 (常數/環境變數/資料庫)
        │   │   ├── 加密演算法: AES-256-GCM
        │   │   └── 已加密資料筆數
        │   ├── 金鑰管理
        │   │   ├── [按鈕] 自動產生金鑰 (僅限資料庫模式)
        │   │   └── [按鈕] 執行金鑰輪替
        │   └── 設定說明
        │       └── wp-config.php 設定範例
        │
        ├── 權限設定
        │   ├── 加密管理員
        │   │   └── [多選] 選擇可管理加密設定的使用者
        │   └── 服務管理員
        │       └── [多選] 選擇可查看所有服務的使用者
        │
        └── 通知設定
            ├── 全域開關
            │   └── [勾選] 啟用 Email 通知
            ├── 預設格式
            │   └── [選擇] HTML / 純文字
            └── 寄件者設定
                ├── 寄件者名稱
                └── 寄件者 Email
```

#### 5.6.3 自訂權限 (Capabilities)

| 權限名稱 | 說明 | 預設授予 |
|---------|------|---------|
| `mxp_manage_encryption` | 管理加密設定 | Super Admin |
| `mxp_rotate_encryption_key` | 執行金鑰輪替 | Super Admin |
| `mxp_view_all_services` | 查看所有服務 | Super Admin |
| `mxp_manage_notifications` | 管理通知設定 | Super Admin |

### 5.7 Hooks 模組 (includes/class-mxp-hooks.php)

#### 5.7.1 類別定義

```php
class Mxp_Hooks {
    /**
     * 初始化所有 hooks
     */
    public static function init(): void;

    /**
     * 觸發 action
     * @param string $hook_name Hook 名稱
     * @param mixed ...$args 參數
     */
    public static function do_action(string $hook_name, ...$args): void;

    /**
     * 應用 filter
     * @param string $hook_name Hook 名稱
     * @param mixed $value 原始值
     * @param mixed ...$args 額外參數
     * @return mixed 過濾後的值
     */
    public static function apply_filters(string $hook_name, $value, ...$args);
}
```

---

## 6. API 設計

### 6.1 AJAX 端點概覽

| 端點 | 方法 | 說明 |
|------|------|------|
| `wp_ajax_to_get_service` | POST | 取得服務詳細資料 |
| `wp_ajax_to_update_service_info` | POST | 更新服務資料 |
| `wp_ajax_to_add_new_account_service` | POST | 新增服務 |
| `wp_ajax_to_search_services` | POST | 搜尋與篩選服務 (新增) |
| `wp_ajax_to_archive_service` | POST | 歸檔服務 (新增) |
| `wp_ajax_to_restore_service` | POST | 恢復歸檔服務 (新增) |
| `wp_ajax_to_batch_action` | POST | 批次操作 (新增) |
| `wp_ajax_to_manage_categories` | POST | 分類管理 (新增) |
| `wp_ajax_to_manage_tags` | POST | 標籤管理 (新增) |

### 6.2 取得服務 API

**端點**: `wp_ajax_to_get_service`

**請求參數**:

| 參數 | 類型 | 必填 | 說明 |
|------|------|------|------|
| `nonce` | string | 是 | 安全驗證碼 |
| `sid` | integer | 是 | 服務 ID |

**成功回應**:

```json
{
  "success": true,
  "data": {
    "code": 200,
    "data": {
      "sid": 1,
      "service_name": "GitHub",
      "login_url": "https://github.com/login",
      "account": "user@example.com",
      "password": "decrypted_password",
      "2fa_token": "JBSWY3DPEHPK3PXP",
      "note": "公司帳號",
      "auth_list": [1, 2, 3],
      "audit_log": [
        "[2026-01-06 10:00:00] 王小明 -> 查看"
      ]
    }
  }
}
```

**觸發的 Hooks**:
- Action: `mxp_service_viewed` (`$service_id`, `$user_id`)

### 6.3 更新服務 API

**端點**: `wp_ajax_to_update_service_info`

**請求參數**:

| 參數 | 類型 | 必填 | 說明 |
|------|------|------|------|
| `nonce` | string | 是 | 安全驗證碼 |
| `sid` | integer | 是 | 服務 ID |
| `change_fields[]` | array | 是 | 要更新的欄位名稱陣列 |
| `update_fields[]` | array | 是 | 新的欄位值陣列 |

**觸發的 Hooks**:
- Action: `mxp_service_updated` (`$service_id`, `$changed_fields`, `$old_values`)
- Action: `mxp_auth_granted` (`$service_id`, `$user_id`) - 新增授權時
- Action: `mxp_auth_revoked` (`$service_id`, `$user_id`) - 移除授權時

### 6.4 新增服務 API

**端點**: `wp_ajax_to_add_new_account_service`

**請求參數**:

| 參數 | 類型 | 必填 | 說明 |
|------|------|------|------|
| `nonce` | string | 是 | 安全驗證碼 |
| `service_name` | string | 是 | 服務名稱 |
| `auth_list[]` | array | 是 | 授權使用者 ID |
| `login_url` | string | 否 | 登入網址 |
| `account` | string | 否 | 帳號 |
| `password` | string | 否 | 密碼 |
| `2fa_token` | string | 否 | 2FA 金鑰 |
| `note` | string | 否 | 備註 |

**觸發的 Hooks**:
- Action: `mxp_service_created` (`$service_id`, `$service_data`)
- Action: `mxp_auth_granted` (`$service_id`, `$user_id`) - 每個授權使用者

### 6.5 搜尋與篩選服務 API (新增)

**端點**: `wp_ajax_to_search_services`

**請求參數**:

| 參數 | 類型 | 必填 | 說明 |
|------|------|------|------|
| `nonce` | string | 是 | 安全驗證碼 |
| `keyword` | string | 否 | 搜尋關鍵字 |
| `status[]` | array | 否 | 狀態篩選 (`active`, `archived`, `suspended`) |
| `category_id[]` | array | 否 | 分類 ID 篩選 |
| `tag_id[]` | array | 否 | 標籤 ID 篩選 |
| `priority_min` | integer | 否 | 最低重要程度 (1-5) |
| `priority_max` | integer | 否 | 最高重要程度 (1-5) |
| `date_from` | string | 否 | 更新時間起始 (Y-m-d) |
| `date_to` | string | 否 | 更新時間結束 (Y-m-d) |
| `auth_user_id[]` | array | 否 | 授權使用者 ID 篩選 |
| `search_fields[]` | array | 否 | 搜尋範圍 (`service_name`, `account`, `note`, `login_url`, `reg_email`) |
| `sort_by` | string | 否 | 排序欄位 (`updated_time`, `service_name`, `priority`, `last_used`) |
| `sort_order` | string | 否 | 排序方向 (`ASC`, `DESC`) |
| `page` | integer | 否 | 頁碼 (預設: 1) |
| `per_page` | integer | 否 | 每頁筆數 (預設: 10, 最大: 100) |

**成功回應**:

```json
{
  "success": true,
  "data": {
    "code": 200,
    "data": {
      "services": [
        {
          "sid": 1,
          "service_name": "GitHub",
          "category_id": 1,
          "category_name": "開發工具",
          "tags": [
            {"tid": 1, "tag_name": "公司", "tag_color": "#3498db"},
            {"tid": 5, "tag_name": "主要", "tag_color": "#9b59b6"}
          ],
          "account": "user@company.com",
          "status": "active",
          "priority": 5,
          "last_used": "2026-01-05 10:00:00",
          "updated_time": "2026-01-05 15:30:00"
        }
      ],
      "pagination": {
        "current_page": 1,
        "per_page": 10,
        "total_items": 45,
        "total_pages": 5
      },
      "aggregations": {
        "by_status": {"active": 45, "archived": 15, "suspended": 3},
        "by_category": [
          {"cid": 1, "name": "開發工具", "count": 12}
        ]
      }
    }
  }
}
```

### 6.6 歸檔服務 API (新增)

**端點**: `wp_ajax_to_archive_service`

**請求參數**:

| 參數 | 類型 | 必填 | 說明 |
|------|------|------|------|
| `nonce` | string | 是 | 安全驗證碼 |
| `sid` | integer | 是 | 服務 ID |

**成功回應**:

```json
{
  "success": true,
  "data": {
    "code": 200,
    "message": "服務已成功歸檔"
  }
}
```

**觸發的 Hooks**:
- Action: `mxp_service_archived` (`$service_id`, `$user_id`)

### 6.7 恢復歸檔服務 API (新增)

**端點**: `wp_ajax_to_restore_service`

**請求參數**:

| 參數 | 類型 | 必填 | 說明 |
|------|------|------|------|
| `nonce` | string | 是 | 安全驗證碼 |
| `sid` | integer | 是 | 服務 ID |
| `restore_to` | string | 否 | 恢復後狀態 (`active`, `suspended`)，預設 `active` |

**成功回應**:

```json
{
  "success": true,
  "data": {
    "code": 200,
    "message": "服務已成功恢復"
  }
}
```

**觸發的 Hooks**:
- Action: `mxp_service_restored` (`$service_id`, `$user_id`, `$restore_to`)

### 6.8 批次操作 API (新增)

**端點**: `wp_ajax_to_batch_action`

**請求參數**:

| 參數 | 類型 | 必填 | 說明 |
|------|------|------|------|
| `nonce` | string | 是 | 安全驗證碼 |
| `action_type` | string | 是 | 操作類型 (`archive`, `restore`, `change_category`, `add_tags`, `change_status`, `delete`) |
| `service_ids[]` | array | 是 | 服務 ID 陣列 |
| `category_id` | integer | 條件必填 | 新分類 ID (當 action_type = `change_category`) |
| `tag_ids[]` | array | 條件必填 | 標籤 ID 陣列 (當 action_type = `add_tags`) |
| `new_status` | string | 條件必填 | 新狀態 (當 action_type = `change_status`) |

**成功回應**:

```json
{
  "success": true,
  "data": {
    "code": 200,
    "message": "批次操作完成",
    "affected_count": 3,
    "failed_ids": []
  }
}
```

**觸發的 Hooks**:
- Action: `mxp_batch_action_completed` (`$action_type`, `$service_ids`, `$user_id`)

### 6.9 分類管理 API (新增)

**端點**: `wp_ajax_to_manage_categories`

**請求參數**:

| 參數 | 類型 | 必填 | 說明 |
|------|------|------|------|
| `nonce` | string | 是 | 安全驗證碼 |
| `action_type` | string | 是 | 操作類型 (`list`, `create`, `update`, `delete`, `reorder`) |
| `cid` | integer | 條件必填 | 分類 ID (update/delete 時必填) |
| `category_name` | string | 條件必填 | 分類名稱 (create/update 時必填) |
| `category_icon` | string | 否 | Dashicons 圖示類別 |
| `order[]` | array | 條件必填 | 分類 ID 順序陣列 (reorder 時必填) |

**成功回應 (list)**:

```json
{
  "success": true,
  "data": {
    "code": 200,
    "data": [
      {
        "cid": 1,
        "category_name": "開發工具",
        "category_icon": "dashicons-editor-code",
        "sort_order": 1,
        "service_count": 12
      }
    ]
  }
}
```

### 6.10 標籤管理 API (新增)

**端點**: `wp_ajax_to_manage_tags`

**請求參數**:

| 參數 | 類型 | 必填 | 說明 |
|------|------|------|------|
| `nonce` | string | 是 | 安全驗證碼 |
| `action_type` | string | 是 | 操作類型 (`list`, `create`, `update`, `delete`) |
| `tid` | integer | 條件必填 | 標籤 ID (update/delete 時必填) |
| `tag_name` | string | 條件必填 | 標籤名稱 (create/update 時必填) |
| `tag_color` | string | 否 | 標籤顏色 (HEX 格式) |

**成功回應 (list)**:

```json
{
  "success": true,
  "data": {
    "code": 200,
    "data": [
      {
        "tid": 1,
        "tag_name": "公司",
        "tag_color": "#3498db",
        "created_by": 1,
        "created_by_name": "王小明",
        "usage_count": 25
      }
    ]
  }
}
```

---

## 7. 前端設計

### 7.1 使用者介面佈局

#### 7.1.1 主介面佈局 (三欄式設計)

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                           帳號密碼管理工具                                        │
├─────────────────────────────────────────────────────────────────────────────────┤
│ ┌─────────────────────────────────────────────────────────────────────────────┐ │
│ │ 🔍 [__________搜尋服務名稱、帳號、備註...__________] [進階篩選 ▼]           │ │
│ └─────────────────────────────────────────────────────────────────────────────┘ │
├────────────────┬────────────────────────────────────────────────────────────────┤
│   側邊導航列    │                        服務列表區                               │
│ ┌────────────┐ │ ┌────────────────────────────────────────────────────────────┐ │
│ │ 📋 全部     │ │ │ 顯示: [啟用中 ▼] 排序: [最近更新 ▼]    [+ 新增服務] [⚙]  │ │
│ │    (45)    │ │ ├────────────────────────────────────────────────────────────┤ │
│ ├────────────┤ │ │                                                            │ │
│ │ ⭐ 常用     │ │ │ ┌─ GitHub ────────────────────────────── ★★★★★ ─────┐     │ │
│ │    (8)     │ │ │ │ 📁 開發工具  |  🏷️ 公司 主要                      │     │ │
│ ├────────────┤ │ │ │ 👤 user@company.com                               │     │ │
│ │ 📂 分類     │ │ │ │ 📅 更新: 2026-01-05  |  最後使用: 1 天前          │     │ │
│ │ ├ 💻 開發  │ │ │ │                [查看詳情] [📋 複製密碼] [歸檔]    │     │ │
│ │ │   (12)   │ │ │ └────────────────────────────────────────────────────┘     │ │
│ │ ├ ☁️ 雲端  │ │ │                                                            │ │
│ │ │   (8)    │ │ │ ┌─ AWS Console ───────────────────────── ★★★★☆ ─────┐     │ │
│ │ ├ 💬 社交  │ │ │ │ 📁 雲端服務  |  🏷️ AWS 生產環境                   │     │ │
│ │ │   (5)    │ │ │ │ 👤 admin@company.com                              │     │ │
│ │ ├ 🏦 金融  │ │ │ │ 📅 更新: 2026-01-03  |  最後使用: 3 天前          │     │ │
│ │ │   (6)    │ │ │ │                [查看詳情] [📋 複製密碼] [歸檔]    │     │ │
│ │ ├ 🏢 企業  │ │ │ └────────────────────────────────────────────────────┘     │ │
│ │ │   (10)   │ │ │                                                            │ │
│ │ └ 📦 其他  │ │ │ ┌─ Slack ─────────────────────────────── ★★★☆☆ ─────┐     │ │
│ │     (4)    │ │ │ │ 📁 社交媒體  |  🏷️ 通訊                          │     │ │
│ ├────────────┤ │ │ │ 👤 team@company.com                               │     │ │
│ │ 🏷️ 標籤    │ │ │ │ 📅 更新: 2025-12-20  |  最後使用: 7 天前          │     │ │
│ │ ├ 公司     │ │ │ │                [查看詳情] [📋 複製密碼] [歸檔]    │     │ │
│ │ ├ 個人     │ │ │ └────────────────────────────────────────────────────┘     │ │
│ │ ├ 生產環境 │ │ │                                                            │ │
│ │ └ 測試環境 │ │ │ ─────────────────────────────────────────────────────────  │ │
│ ├────────────┤ │ │                    [載入更多...] (1-10 / 45)               │ │
│ │ 📦 已歸檔  │ │ └────────────────────────────────────────────────────────────┘ │
│ │    (15)    │ │                                                                │
│ ├────────────┤ │                                                                │
│ │ ⚠️ 已停用  │ │                                                                │
│ │    (3)     │ │                                                                │
│ └────────────┘ │                                                                │
└────────────────┴────────────────────────────────────────────────────────────────┘
```

#### 7.1.2 進階篩選面板

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                              進階篩選                                [✕ 關閉]   │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                 │
│  狀態:                                                                          │
│  [☑ 啟用中] [☐ 已歸檔] [☐ 已停用]                                              │
│                                                                                 │
│  分類:                                                                          │
│  [☑ 全選] [☑ 開發工具] [☑ 雲端服務] [☑ 社交媒體] [☑ 金融服務] [☑ 企業內部]     │
│                                                                                 │
│  標籤: (可多選)                                                                  │
│  [公司 ×] [生產環境 ×] [___新增標籤___▼]                                        │
│                                                                                 │
│  重要程度:                                                                       │
│  [☐ ★☆☆☆☆] [☐ ★★☆☆☆] [☑ ★★★☆☆] [☑ ★★★★☆] [☑ ★★★★★]                          │
│                                                                                 │
│  更新時間範圍:                                                                   │
│  [起始日期 📅] ~ [結束日期 📅]    [快選: 本週 | 本月 | 近三個月 | 全部]         │
│                                                                                 │
│  授權人員: (可多選)                                                              │
│  [王小明 ×] [李四 ×] [___選擇人員___▼]                                          │
│                                                                                 │
│  搜尋範圍:                                                                       │
│  [☑ 服務名稱] [☑ 帳號] [☑ 備註] [☐ 登入網址] [☐ 註冊信箱]                      │
│                                                                                 │
├─────────────────────────────────────────────────────────────────────────────────┤
│                     [重置篩選]                    [套用篩選]                     │
└─────────────────────────────────────────────────────────────────────────────────┘
```

#### 7.1.3 服務詳情展開視圖

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│ [◀ 返回列表]                        GitHub                         [⭐] [⋮]    │
├─────────────────────────────────────────────────────────────────────────────────┤
│ 狀態: [🟢 啟用中 ▼]    分類: [💻 開發工具 ▼]    重要程度: [★★★★★]              │
│ 標籤: [公司 ×] [主要 ×] [+ 新增標籤]                                             │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                 │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │ 基本資訊                                                     [編輯 ✏️]  │   │
│  ├─────────────────────────────────────────────────────────────────────────┤   │
│  │ 服務名稱:   GitHub                                                      │   │
│  │ 登入網址:   https://github.com/login                        [🔗 開啟]   │   │
│  │ 帳號:       user@company.com                                [📋 複製]   │   │
│  │ 密碼:       ••••••••••••                      [👁️ 顯示] [📋 複製]       │   │
│  └─────────────────────────────────────────────────────────────────────────┘   │
│                                                                                 │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │ 雙因素認證 (2FA)                                                        │   │
│  ├─────────────────────────────────────────────────────────────────────────┤   │
│  │ 2FA Token: JBSWY3DPEHPK3PXP                                 [📋 複製]   │   │
│  │                                                                         │   │
│  │            ┌────────────────────────────────┐                           │   │
│  │            │        驗證碼: 123 456         │                           │   │
│  │            │  ████████████████░░░░  23 秒   │                           │   │
│  │            │        [📋 複製驗證碼]          │                           │   │
│  │            └────────────────────────────────┘                           │   │
│  │                                                                         │   │
│  │ 救援碼:    ABCD-EFGH-IJKL-MNOP                              [📋 複製]   │   │
│  └─────────────────────────────────────────────────────────────────────────┘   │
│                                                                                 │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │ 聯絡資訊                                                     [編輯 ✏️]  │   │
│  ├─────────────────────────────────────────────────────────────────────────┤   │
│  │ 註冊信箱:   admin@company.com                               [📋 複製]   │   │
│  │ 註冊電話1:  +886-2-1234-5678                                [📋 複製]   │   │
│  │ 註冊電話2:  +886-912-345-678                                [📋 複製]   │   │
│  └─────────────────────────────────────────────────────────────────────────┘   │
│                                                                                 │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │ 備註                                                         [編輯 ✏️]  │   │
│  ├─────────────────────────────────────────────────────────────────────────┤   │
│  │ 公司主要 GitHub 帳號，用於存放所有專案程式碼。                           │   │
│  │ 注意: 請勿在此帳號上進行實驗性操作。                                     │   │
│  └─────────────────────────────────────────────────────────────────────────┘   │
│                                                                                 │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │ 授權人員                                                     [管理 ⚙️]  │   │
│  ├─────────────────────────────────────────────────────────────────────────┤   │
│  │ 👤 王小明 (admin)  |  👤 李四 (developer)  |  👤 張三 (developer)        │   │
│  │                                                                         │   │
│  │ [+ 新增授權人員]                                                        │   │
│  └─────────────────────────────────────────────────────────────────────────┘   │
│                                                                                 │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │ 稽核日誌                                    [展開全部 ▼] [匯出 CSV]     │   │
│  ├─────────────────────────────────────────────────────────────────────────┤   │
│  │ 📅 2026-01-06 10:00:00  |  👤 王小明  |  查看                           │   │
│  │ 📅 2026-01-05 15:30:00  |  👤 李四    |  更新 password                  │   │
│  │ 📅 2026-01-04 09:15:00  |  👤 王小明  |  新增授權: 張三                  │   │
│  │ 📅 2026-01-03 14:20:00  |  👤 張三    |  查看                           │   │
│  │ ... 顯示更多 (共 28 筆)                                                 │   │
│  └─────────────────────────────────────────────────────────────────────────┘   │
│                                                                                 │
├─────────────────────────────────────────────────────────────────────────────────┤
│ 建立時間: 2025-06-15 09:00:00  |  最後更新: 2026-01-05 15:30:00                 │
│ 最後使用: 1 天前 (2026-01-05 10:00:00)                                          │
├─────────────────────────────────────────────────────────────────────────────────┤
│                      [📦 歸檔此服務]        [🗑️ 刪除服務]                       │
└─────────────────────────────────────────────────────────────────────────────────┘
```

#### 7.1.4 歸檔管理介面

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                              📦 已歸檔服務管理                                   │
├─────────────────────────────────────────────────────────────────────────────────┤
│ 🔍 [__________搜尋已歸檔服務...__________]     排序: [歸檔時間 ▼]               │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                 │
│  ☐ ┌─ Old Slack Workspace ──────────────────── 歸檔於: 2025-11-20 ──────┐      │
│    │ 📁 社交媒體  |  原狀態: 啟用中                                      │      │
│    │ 👤 old-team@company.com                                            │      │
│    │                        [查看詳情] [↩️ 恢復啟用] [🗑️ 永久刪除]       │      │
│    └────────────────────────────────────────────────────────────────────┘      │
│                                                                                 │
│  ☐ ┌─ Legacy AWS Account ───────────────────── 歸檔於: 2025-10-15 ──────┐      │
│    │ 📁 雲端服務  |  原狀態: 啟用中                                      │      │
│    │ 👤 legacy-admin@company.com                                        │      │
│    │                        [查看詳情] [↩️ 恢復啟用] [🗑️ 永久刪除]       │      │
│    └────────────────────────────────────────────────────────────────────┘      │
│                                                                                 │
│  ☐ ┌─ Test Environment DB ──────────────────── 歸檔於: 2025-09-01 ──────┐      │
│    │ 📁 企業內部  |  原狀態: 停用                                        │      │
│    │ 👤 test-db@company.com                                             │      │
│    │                        [查看詳情] [↩️ 恢復啟用] [🗑️ 永久刪除]       │      │
│    └────────────────────────────────────────────────────────────────────┘      │
│                                                                                 │
├─────────────────────────────────────────────────────────────────────────────────┤
│ 已選擇: 0 項                    [批次恢復]           [批次刪除]                  │
│                                                                                 │
│ 提示: 已歸檔超過 180 天的服務可設定自動清理                      [自動清理設定]  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

#### 7.1.5 快速操作工具列 (Toolbar)

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│ 已選擇 3 項服務:                                                                │
│                                                                                 │
│ [📁 批次變更分類]  [🏷️ 批次新增標籤]  [📦 批次歸檔]  [⚠️ 批次停用]  [✕ 取消選擇] │
└─────────────────────────────────────────────────────────────────────────────────┘
```

#### 7.1.6 新增/編輯服務表單 (Modal)

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                              新增服務                              [✕ 關閉]     │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                 │
│  基本資訊                                                                        │
│  ─────────────────────────────────────────────────────────────────────────────  │
│  服務名稱 *:  [_______________________________]                                 │
│  登入網址:    [_______________________________]                                 │
│  帳號:        [_______________________________]                                 │
│  密碼:        [_______________________________] [🎲 產生隨機密碼]               │
│                                                                                 │
│  分類與標籤                                                                      │
│  ─────────────────────────────────────────────────────────────────────────────  │
│  分類:        [-- 選擇分類 -- ▼]                                                │
│  標籤:        [公司 ×] [___新增或選擇標籤___▼]                                  │
│  重要程度:    [☆] [☆] [★] [☆] [☆]  (預設: 3)                                   │
│                                                                                 │
│  雙因素認證                                                                      │
│  ─────────────────────────────────────────────────────────────────────────────  │
│  2FA Token:   [_______________________________]                                 │
│  救援碼:      [_______________________________]                                 │
│                                                                                 │
│  聯絡資訊                                                                        │
│  ─────────────────────────────────────────────────────────────────────────────  │
│  註冊信箱:    [_______________________________]                                 │
│  註冊電話1:   [_______________________________]                                 │
│  註冊電話2:   [_______________________________]                                 │
│                                                                                 │
│  其他                                                                            │
│  ─────────────────────────────────────────────────────────────────────────────  │
│  備註:        ┌─────────────────────────────────────────────────────────────┐   │
│               │                                                             │   │
│               │                                                             │   │
│               └─────────────────────────────────────────────────────────────┘   │
│                                                                                 │
│  授權人員 *                                                                      │
│  ─────────────────────────────────────────────────────────────────────────────  │
│  [王小明 ×] [李四 ×] [___選擇人員___▼]                                          │
│                                                                                 │
├─────────────────────────────────────────────────────────────────────────────────┤
│                              [取消]              [儲存服務]                      │
└─────────────────────────────────────────────────────────────────────────────────┘
```

#### 7.1.7 分類與標籤管理介面 (設定頁面)

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                           分類與標籤管理                                         │
├─────────────────────────────────────────────────────────────────────────────────┤
│ ┌─ 分類管理 ────────────────────────────────────────────────────────────────┐   │
│ │                                                                           │   │
│ │  [拖曳排序]  名稱              圖示                  服務數    操作        │   │
│ │  ──────────────────────────────────────────────────────────────────────   │   │
│ │  ≡  💻 開發工具      dashicons-editor-code      12      [✏️] [🗑️]       │   │
│ │  ≡  ☁️ 雲端服務      dashicons-cloud             8      [✏️] [🗑️]       │   │
│ │  ≡  💬 社交媒體      dashicons-share             5      [✏️] [🗑️]       │   │
│ │  ≡  🏦 金融服務      dashicons-bank              6      [✏️] [🗑️]       │   │
│ │  ≡  🏢 企業內部      dashicons-building         10      [✏️] [🗑️]       │   │
│ │  ≡  📦 其他          dashicons-category          4      [✏️] [🗑️]       │   │
│ │                                                                           │   │
│ │  [+ 新增分類]                                                             │   │
│ └───────────────────────────────────────────────────────────────────────────┘   │
│                                                                                 │
│ ┌─ 標籤管理 ────────────────────────────────────────────────────────────────┐   │
│ │                                                                           │   │
│ │  標籤名稱        顏色          使用次數    建立者      操作               │   │
│ │  ──────────────────────────────────────────────────────────────────────   │   │
│ │  公司            ████ #3498db      25      王小明      [✏️] [🗑️]         │   │
│ │  個人            ████ #2ecc71       8      李四        [✏️] [🗑️]         │   │
│ │  生產環境        ████ #e74c3c      12      王小明      [✏️] [🗑️]         │   │
│ │  測試環境        ████ #f39c12       6      張三        [✏️] [🗑️]         │   │
│ │  主要            ████ #9b59b6      15      王小明      [✏️] [🗑️]         │   │
│ │                                                                           │   │
│ │  [+ 新增標籤]                                                             │   │
│ └───────────────────────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────────────────┘
```

### 7.2 前端依賴

| 程式庫 | 版本 | 來源 | 用途 |
|--------|------|------|------|
| jQuery | WP Core | WordPress 內建 | DOM 操作 |
| CryptoJS | 4.1.1 | CDN | TOTP HMAC-SHA1 |
| Select2 | - | 本地 | 多選下拉選單 |
| jQuery UI | - | 本地 | 日期選擇器 (保留) |
| DataTables | - | 本地 | 表格功能 (保留) |

### 7.3 JavaScript 全域變數

透過 `wp_localize_script()` 傳遞:

```javascript
var mxp_password_manager_obj = {
    ajax_url: "https://example.com/wp-admin/admin-ajax.php",
    nonce: "abc123...",
    user_maps: {
        1: "王小明",
        2: "李四",
        3: "張三"
    }
};
```

### 7.4 TOTP 顯示元件

```
┌─────────────────────────────────────────┐
│ 2FA Token: [JBSWY3DPEHPK3PXP]           │
│                                          │
│ OTP: 123456                              │
│ ████████████████░░░░  23 秒後更新        │
└─────────────────────────────────────────┘
```

- 每秒更新倒數計時
- 剩餘 10 秒內進度條變紅
- 每 30 秒自動產生新驗證碼

---

## 8. 安全性設計

### 8.1 安全措施總覽

| 威脅 | 防護措施 |
|------|---------|
| CSRF | WordPress Nonce 驗證 |
| SQL Injection | `$wpdb->prepare()` 預處理語句 |
| XSS | `sanitize_text_field()` 輸入過濾 |
| 未授權存取 | 使用者授權清單檢查 + 自訂 capability |
| 敏感資料外洩 | AES-256-GCM 加密儲存 |
| 操作追蹤 | 完整稽核日誌 |

### 8.2 內建加密機制

#### 8.2.1 加密演算法

- **演算法**: AES-256-GCM (Galois/Counter Mode)
- **金鑰長度**: 256 bits (32 bytes)
- **IV 長度**: 96 bits (12 bytes)
- **Tag 長度**: 128 bits (16 bytes)

#### 8.2.2 加密實作

```php
public static function encrypt(string $plaintext): string {
    $key = self::get_key();
    $iv = random_bytes(12); // 96-bit IV

    $ciphertext = openssl_encrypt(
        $plaintext,
        self::$cipher,
        $key,
        OPENSSL_RAW_DATA,
        $iv,
        $tag
    );

    // 格式: IV + Tag + Ciphertext (Base64)
    return base64_encode($iv . $tag . $ciphertext);
}

public static function decrypt(string $encrypted): string {
    $key = self::get_key();
    $data = base64_decode($encrypted);

    $iv = substr($data, 0, 12);
    $tag = substr($data, 12, 16);
    $ciphertext = substr($data, 28);

    return openssl_decrypt(
        $ciphertext,
        self::$cipher,
        $key,
        OPENSSL_RAW_DATA,
        $iv,
        $tag
    );
}
```

#### 8.2.3 金鑰設定方式

**方式一: wp-config.php 常數 (推薦)**

```php
// 在 wp-config.php 中加入
define('MXP_ENCRYPTION_KEY', 'your-32-byte-encryption-key-here');
```

**方式二: 環境變數**

```bash
export MXP_ENCRYPTION_KEY="your-32-byte-encryption-key-here"
```

**方式三: 資料庫 (自動產生)**

透過網路設定頁面自動產生並儲存於 `wp_sitemeta` 表。

### 8.3 存取控制

```
使用者請求
    │
    ▼
┌─────────────────────┐
│ Nonce 驗證          │───[失敗]──▶ 拒絕存取
└──────────┬──────────┘
           │[成功]
           ▼
┌─────────────────────┐
│ 有 mxp_view_all_   │───[是]──▶ 允許存取所有服務
│ services 權限?      │
└──────────┬──────────┘
           │[否]
           ▼
┌─────────────────────┐
│ 在授權清單中?       │───[否]──▶ 僅顯示授權服務
└──────────┬──────────┘
           │[是]
           ▼
       允許存取
```

### 8.4 稽核日誌

每次操作記錄以下資訊:

- 操作時間
- 操作使用者 ID 與名稱
- 操作類型 (查看/新增/移除/更新)
- 修改欄位名稱
- 原始值與新值

---

## 9. Hooks 與 Filters 參考

### 9.1 Actions (動作鉤子)

| Hook 名稱 | 觸發時機 | 參數 |
|-----------|---------|------|
| `mxp_service_created` | 服務建立後 | `$service_id`, `$service_data` |
| `mxp_service_updated` | 服務更新後 | `$service_id`, `$changed_fields`, `$old_values` |
| `mxp_service_deleted` | 服務刪除後 | `$service_id` |
| `mxp_service_viewed` | 服務被查看時 | `$service_id`, `$user_id` |
| `mxp_service_archived` | 服務歸檔後 (新增) | `$service_id`, `$user_id` |
| `mxp_service_restored` | 服務恢復後 (新增) | `$service_id`, `$user_id`, `$restore_to` |
| `mxp_service_status_changed` | 服務狀態變更後 (新增) | `$service_id`, `$old_status`, `$new_status` |
| `mxp_auth_granted` | 授權新增後 | `$service_id`, `$user_id` |
| `mxp_auth_revoked` | 授權移除後 | `$service_id`, `$user_id` |
| `mxp_audit_logged` | 稽核記錄後 | `$log_id`, `$log_data` |
| `mxp_before_encrypt` | 加密前 | `$field_name`, `$plaintext` |
| `mxp_after_decrypt` | 解密後 | `$field_name`, `$plaintext` |
| `mxp_notification_sent` | 通知發送後 | `$user_id`, `$type`, `$result` |
| `mxp_key_rotated` | 金鑰輪替後 | `$timestamp` |
| `mxp_category_created` | 分類建立後 (新增) | `$category_id`, `$category_data` |
| `mxp_category_updated` | 分類更新後 (新增) | `$category_id`, `$changed_fields` |
| `mxp_category_deleted` | 分類刪除後 (新增) | `$category_id` |
| `mxp_tag_created` | 標籤建立後 (新增) | `$tag_id`, `$tag_data` |
| `mxp_tag_deleted` | 標籤刪除後 (新增) | `$tag_id` |
| `mxp_batch_action_completed` | 批次操作完成後 (新增) | `$action_type`, `$service_ids`, `$user_id` |

### 9.2 Filters (過濾器)

| Filter 名稱 | 用途 | 參數 | 回傳 |
|-------------|------|------|------|
| `mxp_encrypt_fields` | 自訂加密欄位 | `$fields` | `array` |
| `mxp_service_data` | 過濾服務資料 | `$data`, `$service_id` | `array` |
| `mxp_can_view_service` | 自訂存取權限 | `$can_view`, `$service_id`, `$user_id` | `bool` |
| `mxp_can_edit_service` | 自訂編輯權限 | `$can_edit`, `$service_id`, `$user_id` | `bool` |
| `mxp_can_archive_service` | 自訂歸檔權限 (新增) | `$can_archive`, `$service_id`, `$user_id` | `bool` |
| `mxp_audit_log_data` | 過濾稽核資料 | `$log_data` | `array` |
| `mxp_notification_message` | 自訂通知內容 | `$message`, `$type`, `$data` | `string` |
| `mxp_notification_subject` | 自訂通知標題 | `$subject`, `$type` | `string` |
| `mxp_notification_recipients` | 過濾收件人 | `$recipients`, `$service_id`, `$type` | `array` |
| `mxp_encryption_method` | 自訂加密方法 | `$method` | `string` |
| `mxp_user_capabilities` | 過濾使用者權限 | `$caps`, `$user_id` | `array` |
| `mxp_admin_menu_capability` | 選單權限 | `$capability` | `string` |
| `mxp_settings_sections` | 自訂設定區塊 | `$sections` | `array` |
| `mxp_search_query` | 過濾搜尋查詢 (新增) | `$query`, `$search_params` | `array` |
| `mxp_search_results` | 過濾搜尋結果 (新增) | `$results`, `$search_params` | `array` |
| `mxp_default_categories` | 自訂預設分類 (新增) | `$categories` | `array` |
| `mxp_available_status` | 自訂可用狀態 (新增) | `$statuses` | `array` |
| `mxp_archive_retention_days` | 歸檔保留天數 (新增) | `$days` | `int` |

### 9.3 使用範例

#### 9.3.1 自訂加密欄位

```php
add_filter('mxp_encrypt_fields', function($fields) {
    // 新增自訂欄位到加密清單
    $fields[] = 'custom_secret';
    return $fields;
});
```

#### 9.3.2 自訂存取權限

```php
add_filter('mxp_can_view_service', function($can_view, $service_id, $user_id) {
    // 特定使用者可查看所有服務
    if ($user_id === 123) {
        return true;
    }
    return $can_view;
}, 10, 3);
```

#### 9.3.3 自訂通知內容

```php
add_filter('mxp_notification_message', function($message, $type, $data) {
    if ($type === 'auth_granted') {
        $message .= "\n\n請遵守公司安全政策使用此帳號。";
    }
    return $message;
}, 10, 3);
```

#### 9.3.4 監聽服務更新事件

```php
add_action('mxp_service_updated', function($service_id, $changed_fields, $old_values) {
    // 記錄到外部系統
    external_log_service("Service {$service_id} updated", $changed_fields);
}, 10, 3);
```

---

## 10. 使用者偏好設定

### 10.1 設定位置

使用者可在個人設定頁面 (`/wp-admin/profile.php`) 管理通知偏好。

### 10.2 設定介面

```
┌─────────────────────────────────────────────────────────────────┐
│                     帳號管理通知設定                             │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  接收通知方式:                                                   │
│  ○ Email (HTML 格式)                                            │
│  ○ Email (純文字格式)                                           │
│  ○ 不接收通知                                                   │
│                                                                 │
│  通知類型選擇:                                                   │
│  ☑ 授權變更通知 (新增/移除授權)                                  │
│  ☑ 密碼變更通知                                                 │
│  ☐ 一般服務更新通知                                             │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### 10.3 儲存方式

使用者偏好儲存於 `usermeta` 表:

| Meta Key | 說明 | 可選值 |
|----------|------|--------|
| `mxp_notification_format` | Email 格式 | `html`, `text`, `none` |
| `mxp_notify_auth_change` | 授權變更通知 | `1`, `0` |
| `mxp_notify_password_change` | 密碼變更通知 | `1`, `0` |
| `mxp_notify_service_update` | 服務更新通知 | `1`, `0` |

### 10.4 預設值

| 設定 | 預設值 |
|------|--------|
| 通知格式 | `html` |
| 授權變更通知 | 啟用 |
| 密碼變更通知 | 啟用 |
| 服務更新通知 | 停用 |

---

## 11. 部署與依賴

### 11.1 系統需求

| 項目 | 最低需求 |
|------|---------|
| WordPress | 5.0+ (Multisite) |
| PHP | 7.4+ |
| PHP 擴充 | OpenSSL |
| MySQL | 5.7+ / MariaDB 10.3+ |
| 瀏覽器 | ES6+ 支援 |

### 11.2 PHP 擴充需求

| 擴充 | 用途 | 必要性 |
|------|------|--------|
| OpenSSL | AES-256-GCM 加密 | 必要 |
| JSON | API 資料處理 | 必要 |
| mbstring | 多位元組字串處理 | 建議 |

### 11.3 安裝步驟

1. 上傳外掛目錄至 `/wp-content/plugins/`
2. 於 WordPress Multisite 子站台啟用外掛
3. 外掛自動建立資料表
4. 設定加密金鑰 (選擇以下方式之一):
   - **推薦**: 在 `wp-config.php` 中定義 `MXP_ENCRYPTION_KEY` 常數
   - 或設定環境變數 `MXP_ENCRYPTION_KEY`
   - 或透過網路設定頁面自動產生 (資料庫儲存)
5. (選用) 於網路設定頁面調整其他設定

### 11.4 wp-config.php 設定範例

```php
/**
 * MXP Password Manager 加密金鑰
 * 必須為 32 位元組 (256 bits)
 * 可使用以下命令產生: openssl rand -base64 32
 */
define('MXP_ENCRYPTION_KEY', 'base64-encoded-32-byte-key-here==');
```

### 11.5 檔案權限

| 路徑 | 權限 |
|------|------|
| `/mxp-password-manager/` | 755 |
| `*.php` | 644 |
| `/assets/` | 755 |
| `/assets/**/*` | 644 |

---

## 12. 版本歷史

| 版本 | 日期 | 變更說明 |
|------|------|---------|
| 1.0.0 | - | 初始版本 |
| 1.0.1 | - | 小修正 |
| 2.0.0 | 2026-01-06 | 重大更新：移除外部依賴、內建加密模組、Email 通知、Hooks 機制 |
| 2.1.0 | 2026-01-06 | 介面優化：歸檔管理、分類標籤系統、進階搜尋篩選 |

### 2.1.0 變更摘要 (UI/UX 優化版)

**資料庫變更：**
- **新增**: `to_service_categories` 資料表 - 服務分類管理
- **新增**: `to_service_tags` 資料表 - 標籤系統
- **新增**: `to_service_tag_map` 資料表 - 服務與標籤多對多關聯
- **變更**: `to_service_list` 新增欄位：
  - `category_id` - 分類外鍵
  - `status` - 服務狀態 (active/archived/suspended)
  - `priority` - 重要程度 (1-5)
  - `last_used` - 最後使用時間
  - `created_time` - 建立時間

**介面變更：**
- **新增**: 三欄式主介面佈局 (側邊導航 + 服務列表)
- **新增**: 進階篩選面板 (狀態、分類、標籤、重要程度、時間範圍)
- **新增**: 歸檔管理介面 (批次恢復、批次刪除、自動清理設定)
- **新增**: 分類與標籤管理介面 (拖曳排序、顏色自訂)
- **新增**: 批次操作工具列 (批次歸檔、變更分類、新增標籤)
- **優化**: 服務卡片顯示 (分類圖示、標籤、重要程度星級)
- **優化**: 服務詳情頁面 (分區卡片式佈局)

**API 變更：**
- **新增**: `wp_ajax_to_search_services` - 進階搜尋與篩選
- **新增**: `wp_ajax_to_archive_service` - 歸檔服務
- **新增**: `wp_ajax_to_restore_service` - 恢復歸檔服務
- **新增**: `wp_ajax_to_batch_action` - 批次操作
- **新增**: `wp_ajax_to_manage_categories` - 分類管理
- **新增**: `wp_ajax_to_manage_tags` - 標籤管理

**Hooks 變更：**
- **新增**: `mxp_service_archived`, `mxp_service_restored` - 歸檔/恢復事件
- **新增**: `mxp_category_created`, `mxp_category_updated`, `mxp_category_deleted` - 分類事件
- **新增**: `mxp_tag_created`, `mxp_tag_deleted` - 標籤事件
- **新增**: `mxp_search_query`, `mxp_search_results` - 搜尋過濾器
- **新增**: `mxp_can_archive_service` - 歸檔權限過濾器

### 2.0.0 變更摘要

- **移除**: MxpDevTools 外部加密依賴
- **移除**: Slack 整合
- **新增**: 內建 AES-256-GCM 加密模組 (`Mxp_Encryption`)
- **新增**: Email 通知系統 (`Mxp_Notification`)，支援 HTML + 純文字
- **新增**: 網路層級設定頁面 (`Mxp_Settings`)
- **新增**: 使用者通知偏好設定
- **新增**: 完整的 Hooks 與 Filters 擴充機制
- **新增**: 自訂權限 (capabilities) 系統

---

## 附錄 A: 程式碼統計

| 檔案 | 行數 | 說明 |
|------|------|------|
| `mxp-password-manager.php` | ~700 | 主程式邏輯 |
| `update.php` | ~50 | 版本遷移 |
| `includes/class-mxp-encryption.php` | ~150 | 加密模組 |
| `includes/class-mxp-notification.php` | ~200 | 通知模組 |
| `includes/class-mxp-settings.php` | ~300 | 設定頁面 |
| `includes/class-mxp-hooks.php` | ~100 | Hooks 管理 |
| `assets/js/main.js` | ~510 | 前端 JavaScript |
| **總計** | **~2,000** | 自訂程式碼 |

## 附錄 B: 第三方程式庫

| 程式庫 | 授權 | 用途 |
|--------|------|------|
| Select2 | MIT | 多選下拉選單 |
| DataTables | MIT | 表格功能 |
| jQuery UI | MIT | UI 元件 |
| CryptoJS | MIT | 前端 TOTP 運算 |

---

**文件結束**
