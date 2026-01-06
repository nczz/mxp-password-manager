# MXP Password Manager

WordPress Multisite 企業帳號密碼集中管理外掛

[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Version](https://img.shields.io/badge/Version-2.1.0-orange.svg)](https://github.com/user/mxp-password-manager)

## 功能特色

- **AES-256-GCM 加密** - 敏感資料使用業界標準加密演算法保護
- **使用者授權管理** - 精細的存取控制，按服務授權使用者
- **即時 TOTP 驗證碼** - 前端即時產生雙因素認證碼
- **操作稽核日誌** - 完整記錄所有檢視與修改操作
- **Email 通知系統** - 支援 HTML 與純文字格式的自動通知
- **分類與標籤系統** - 靈活的服務組織與篩選機制
- **Hooks 擴充機制** - 完整的 Actions 與 Filters 供開發者擴充

## 系統需求

| 項目 | 最低需求 |
|------|---------|
| WordPress | 5.0+ (Multisite) |
| PHP | 7.4+ |
| PHP 擴充 | OpenSSL |
| MySQL | 5.7+ / MariaDB 10.3+ |
| 瀏覽器 | Chrome, Firefox, Safari, Edge (現代版本) |

## 安裝方式

### 1. 上傳外掛

將 `mxp-password-manager` 目錄上傳至 WordPress 的 `/wp-content/plugins/` 目錄。

### 2. 啟用外掛

在 WordPress Multisite 的子站台中啟用外掛。

> **注意**: 此外掛僅在子站台 (blog_id != 1) 運作，主站台不會啟用。

### 3. 設定加密金鑰

選擇以下其中一種方式設定加密金鑰：

#### 方式一：wp-config.php 常數 (推薦)

```php
// 在 wp-config.php 中加入
// 使用命令產生金鑰: openssl rand -base64 32
define('MXP_ENCRYPTION_KEY', 'your-base64-encoded-32-byte-key==');
```

#### 方式二：環境變數

```bash
export MXP_ENCRYPTION_KEY="your-base64-encoded-32-byte-key=="
```

#### 方式三：資料庫自動產生

透過「網路管理 > 設定 > 帳號管理設定」頁面自動產生金鑰。

## 目錄結構

```
mxp-password-manager/
├── mxp-password-manager.php       # 主程式入口
├── update.php                      # 版本遷移系統
├── includes/
│   ├── class-mxp-encryption.php   # AES-256-GCM 加密模組
│   ├── class-mxp-notification.php # Email 通知模組
│   ├── class-mxp-settings.php     # 網路層級設定頁面
│   └── class-mxp-hooks.php        # Hooks 管理
├── templates/
│   └── dashboard.php              # 儀表板範本
└── assets/
    ├── css/main.css               # 主樣式表
    ├── js/main.js                 # 前端邏輯 (含 TOTP)
    ├── vendor/                    # 第三方程式庫
    │   ├── select2/
    │   └── cryptojs/
    └── templates/emails/          # Email 範本
        ├── html/                  # HTML 格式
        └── text/                  # 純文字格式
```

## 資料庫結構

外掛會自動建立以下資料表：

| 資料表 | 用途 |
|--------|------|
| `{prefix}to_service_list` | 服務帳號資料 |
| `{prefix}to_service_categories` | 服務分類 |
| `{prefix}to_service_tags` | 服務標籤 |
| `{prefix}to_service_tag_map` | 服務與標籤對應 |
| `{prefix}to_auth_list` | 使用者授權清單 |
| `{prefix}to_audit_log` | 操作稽核日誌 |

### 加密欄位

以下欄位使用 AES-256-GCM 加密儲存：

- `account` - 登入帳號
- `password` - 登入密碼
- `2fa_token` - TOTP 金鑰
- `note` - 備註內容

## 使用說明

### 新增服務

1. 進入「帳號管理」選單
2. 點擊「新增服務」按鈕
3. 填寫服務資訊（名稱、帳號、密碼等）
4. 選擇授權人員
5. 儲存服務

### 管理授權

每個服務可獨立設定授權人員，只有被授權的使用者才能檢視該服務的敏感資訊。

### TOTP 驗證碼

若服務設定了 2FA Token，系統會自動在前端產生即時驗證碼，每 30 秒更新一次。

### 稽核日誌

所有操作（檢視、新增、修改、刪除）都會被記錄，可在服務詳情頁面查看完整歷史。

## 權限系統

| 權限 | 說明 | 預設授予 |
|------|------|---------|
| `mxp_manage_encryption` | 管理加密設定 | Super Admin |
| `mxp_rotate_encryption_key` | 執行金鑰輪替 | Super Admin |
| `mxp_view_all_services` | 查看所有服務 | Super Admin |
| `mxp_manage_notifications` | 管理通知設定 | Super Admin |

## Hooks 參考

### Actions

```php
// 服務事件
do_action('mxp_service_created', $service_id, $service_data);
do_action('mxp_service_updated', $service_id, $changed_fields, $old_values);
do_action('mxp_service_viewed', $service_id, $user_id);
do_action('mxp_service_archived', $service_id, $user_id);
do_action('mxp_service_deleted', $service_id);

// 授權事件
do_action('mxp_auth_granted', $service_id, $user_id);
do_action('mxp_auth_revoked', $service_id, $user_id);

// 通知事件
do_action('mxp_notification_sent', $user_id, $type, $result);

// 加密事件
do_action('mxp_key_rotated', $timestamp);
```

### Filters

```php
// 自訂加密欄位
add_filter('mxp_encrypt_fields', function($fields) {
    $fields[] = 'custom_secret';
    return $fields;
});

// 自訂存取權限
add_filter('mxp_can_view_service', function($can_view, $service_id, $user_id) {
    // 自訂邏輯
    return $can_view;
}, 10, 3);

// 自訂通知內容
add_filter('mxp_notification_message', function($message, $type, $data) {
    // 自訂通知內容
    return $message;
}, 10, 3);

// 過濾搜尋結果
add_filter('mxp_search_results', function($results, $params) {
    // 自訂過濾
    return $results;
}, 10, 2);
```

## AJAX API

| 端點 | 說明 |
|------|------|
| `to_get_service` | 取得服務詳細資料 |
| `to_update_service_info` | 更新服務資料 |
| `to_add_new_account_service` | 新增服務 |
| `to_search_services` | 搜尋與篩選服務 |
| `to_archive_service` | 歸檔服務 |
| `to_restore_service` | 恢復歸檔服務 |
| `to_batch_action` | 批次操作 |
| `to_manage_categories` | 分類管理 |
| `to_manage_tags` | 標籤管理 |
| `to_delete_service` | 刪除服務 |

所有 AJAX 請求需包含 WordPress Nonce 驗證。

## 安全性措施

| 威脅 | 防護措施 |
|------|---------|
| CSRF | WordPress Nonce 驗證 |
| SQL Injection | `$wpdb->prepare()` 預處理語句 |
| XSS | `sanitize_text_field()` 輸入過濾 |
| 未授權存取 | 授權清單檢查 + 自訂 Capability |
| 敏感資料外洩 | AES-256-GCM 加密儲存 |
| 操作追蹤 | 完整稽核日誌 |

## 通知類型

| 類型 | 說明 |
|------|------|
| `auth_granted` | 新增授權通知 |
| `auth_revoked` | 移除授權通知 |
| `service_created` | 服務建立通知 |
| `service_updated` | 服務更新通知 |
| `password_changed` | 密碼變更通知 |

使用者可在個人設定頁面自訂通知偏好。

## 常見問題

### Q: 外掛在主站台無法使用？

A: 此外掛設計為僅在 Multisite 子站台運作，主站台 (blog_id = 1) 會自動停用。

### Q: 如何產生加密金鑰？

A: 使用以下命令產生 Base64 編碼的 32 位元組金鑰：

```bash
openssl rand -base64 32
```

### Q: 金鑰輪替如何進行？

A: 透過「網路管理 > 設定 > 帳號管理設定」頁面執行金鑰輪替，系統會自動重新加密所有資料。

### Q: 如何備份加密資料？

A: 備份資料庫時，加密資料會保持加密狀態。還原時需確保使用相同的加密金鑰。

## 版本歷史

### 2.1.0 (2026-01-06)

- 新增：分類與標籤系統
- 新增：進階搜尋與篩選功能
- 新增：服務歸檔管理
- 新增：批次操作功能
- 優化：三欄式介面佈局
- 優化：服務卡片顯示樣式

### 2.0.0 (2026-01-06)

- 新增：內建 AES-256-GCM 加密模組
- 新增：Email 通知系統 (HTML + 純文字)
- 新增：網路層級設定頁面
- 新增：使用者通知偏好設定
- 新增：完整 Hooks 與 Filters 擴充機制
- 移除：外部加密依賴

### 1.0.0

- 初始版本

## 授權條款

本外掛採用 [GPL v2 or later](https://www.gnu.org/licenses/gpl-2.0.html) 授權。

## 作者

**Chun**

## 貢獻指南

歡迎提交 Issue 與 Pull Request。

1. Fork 此專案
2. 建立功能分支 (`git checkout -b feature/amazing-feature`)
3. 提交變更 (`git commit -m 'Add amazing feature'`)
4. 推送到分支 (`git push origin feature/amazing-feature`)
5. 開啟 Pull Request

## 相關文件

- [軟體設計文件 (SDD)](./SDD.md) - 詳細技術規格
- [開發指南 (CLAUDE.md)](./CLAUDE.md) - 開發者參考
