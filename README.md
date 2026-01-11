# MXP Password Manager

WordPress 企業帳號密碼集中管理外掛（支援單站與 Multisite）

[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Version](https://img.shields.io/badge/Version-3.5.1-orange.svg)](https://github.com/nczz/mxp-password-manager)

## 功能特色

- **AES-256-GCM 加密** - 敏感資料使用業界標準加密演算法保護
- **使用者授權管理** - 精細的存取控制，按服務授權使用者
- **即時 TOTP 驗證碼** - 前端即時產生雙因素認證碼
- **操作稽核日誌** - 完整記錄所有檢視與修改操作
- **Email 通知系統** - 支援 HTML 與純文字格式的自動通知
- **分類與標籤系統** - 靈活的服務組織與篩選機制
- **Hooks 擴充機制** - 完整的 Actions 與 Filters 供開發者擴充
- **多站台中控管理** (v3.0.0) - 跨站台服務帳號集中管理與共享
- **GitHub 自動更新** (v3.2.0) - 開箱即用的自動更新機制，無需任何設定即可檢查和安裝更新

## 系統需求

| 項目 | 最低需求 |
|------|---------|
| WordPress | 5.0+ (單站或 Multisite) |
| PHP | 7.4+ |
| PHP 擴充 | OpenSSL |
| MySQL | 5.7+ / MariaDB 10.3+ |
| 瀏覽器 | Chrome, Firefox, Safari, Edge (現代版本) |

## 安裝方式

### 1. 上傳外掛

將 `mxp-password-manager` 目錄上傳至 WordPress 的 `/wp-content/plugins/` 目錄。

### 2. 啟用外掛

在 WordPress 管理後台的「外掛」頁面啟用外掛。

> **支援環境**: 此外掛同時支援 WordPress 單站安裝與 Multisite 網路。

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

- **單站安裝**: 透過「設定 > 帳號管理設定」頁面自動產生金鑰
- **Multisite**: 透過「網路管理 > 設定 > 帳號管理設定」頁面自動產生金鑰

## 目錄結構

```
mxp-password-manager/
├── mxp-password-manager.php          # 主程式入口
├── update.php                         # 版本遷移系統
├── includes/
│   ├── class-mxp-pm-encryption.php    # AES-256-GCM 加密模組 (Mxp_Pm_Encryption)
│   ├── class-mxp-pm-notification.php  # Email 通知模組 (Mxp_Pm_Notification)
│   ├── class-mxp-pm-settings.php      # 網路層級設定頁面 (Mxp_Pm_Settings)
│   ├── class-mxp-pm-hooks.php         # Hooks 管理 (Mxp_Pm_Hooks)
│   ├── class-mxp-pm-multisite.php     # 多站台中控模組 (Mxp_Pm_Multisite)
│   ├── class-mxp-pm-github-updater-config.php  # GitHub 更新配置 (MXP_GitHub_Updater_Config)
│   └── class-mxp-pm-updater.php       # GitHub 自動更新主類 (Mxp_Pm_Updater)
├── templates/
│   └── dashboard.php                 # 儀表板範本
└── assets/
    ├── css/main.css                  # 主樣式表
    ├── js/main.js                    # 前端邏輯 (含 TOTP)
    ├── icon-128x128.svg              # 外掛圖標 (v3.4.0)
    ├── vendor/                       # 第三方程式庫
    │   ├── select2/
    │   └── cryptojs/
    └── templates/emails/             # Email 範本
        ├── html/                     # HTML 格式
        └── text/                     # 純文字格式
```

## 資料庫結構

外掛會自動建立以下資料表：

| 資料表 | 用途 |
|--------|------|
| `{prefix}mxp_pm_service_list` | 服務帳號資料 |
| `{prefix}mxp_pm_service_categories` | 服務分類 |
| `{prefix}mxp_pm_service_tags` | 服務標籤 |
| `{prefix}mxp_pm_service_tag_map` | 服務與標籤對應 |
| `{prefix}mxp_pm_auth_list` | 使用者授權清單 |
| `{prefix}mxp_pm_audit_log` | 操作稽核日誌 |
| `{prefix}mxp_pm_site_access` | 站台存取控制 (v3.0.0) |
| `{prefix}mxp_pm_central_admins` | 中控管理員 (v3.0.0) |

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

## 自動更新

### 開箱即用

此外掛內建 GitHub 自動更新機制，安裝後**無需任何設定**即可自動檢查和更新：

1. WordPress 會定期檢查 GitHub Releases
2. 發現新版本時，在外掛頁面顯示「更新」按鈕
3. 點擊「更新」按鈕自動下載並安裝新版本
4. 更新完成後自動執行資料庫遷移（如有）

### API 限制說明

- **無 Token**：60 次/小時（對於自動更新檢查已足夠）
- **有 Token**：5,000 次/小時（適用於需要頻繁檢查的場景）

> **提示**：Token 是可選的，不影響基本的更新功能。

### GitHub Releases 準備

要發布更新版本，需在 GitHub 創建 Release：

1. 使用語義化版本標籤：`v3.3.0`, `v3.3.1`
2. 執行 `git tag v3.3.1` 並推送到 GitHub
3. 在 GitHub 創建 Release：
   - 選擇 tag `v3.3.1`
   - 添加外掛 ZIP 文件（包含完整代碼）
   - 撰寫更新日誌（Markdown 格式）
   - 點擊「Publish release」

## 權限系統

| 權限 | 說明 | 預設授予 |
|------|------|---------|
| `manage_encryption` | 管理加密設定 | Super Admin |
| `rotate_encryption_key` | 執行金鑰輪替 | Super Admin |
| `view_all_services` | 查看所有服務 | Super Admin |
| `manage_notifications` | 管理通知設定 | Super Admin |

## Hooks 參考

### Actions

```php
// 服務事件
do_action('mxp_pm_service_created', $service_id, $service_data);
do_action('mxp_pm_service_updated', $service_id, $changed_fields, $old_values);
do_action('mxp_pm_service_viewed', $service_id, $user_id);
do_action('mxp_pm_service_archived', $service_id, $user_id);
do_action('mxp_pm_service_restored', $service_id, $user_id, $restore_to);
do_action('mxp_pm_service_deleted', $service_id);

// 授權事件
do_action('mxp_pm_auth_granted', $service_id, $user_id);
do_action('mxp_pm_auth_revoked', $service_id, $user_id);

// 通知事件
do_action('mxp_pm_notification_sent', $user_id, $type, $result);

// 加密事件
do_action('mxp_pm_key_rotated', $timestamp);

// 分類與標籤事件
do_action('mxp_pm_category_created', $category_id, $category_data);
do_action('mxp_pm_category_updated', $category_id, $updates);
do_action('mxp_pm_category_deleted', $category_id);
do_action('mxp_pm_tag_created', $tag_id, $tag_data);
do_action('mxp_pm_tag_deleted', $tag_id);

// 中控管理事件 (Multisite)
do_action('mxp_pm_site_access_granted', $service_id, $blog_id, $access_level);
do_action('mxp_pm_site_access_revoked', $service_id, $blog_id);
do_action('mxp_pm_central_admin_added', $user_id, $permission_level);
do_action('mxp_pm_central_admin_removed', $user_id);

// 批次操作事件
do_action('mxp_pm_batch_action_completed', $action_type, $service_ids, $user_id);
```

### Filters

```php
// 自訂加密欄位
add_filter('mxp_pm_encrypt_fields', function($fields) {
    $fields[] = 'custom_secret';
    return $fields;
});

// 自訂存取權限
add_filter('mxp_pm_can_view_service', function($can_view, $service_id, $user_id) {
    // 自訂邏輯
    return $can_view;
}, 10, 3);

// 自訂編輯權限
add_filter('mxp_pm_can_edit_service', function($can_edit, $service_id, $user_id) {
    // 自訂邏輯
    return $can_edit;
}, 10, 3);

// 自訂歸檔權限
add_filter('mxp_pm_can_archive_service', function($can_archive, $service_id, $user_id) {
    // 自訂邏輯
    return $can_archive;
}, 10, 3);

// 自訂通知內容
add_filter('mxp_pm_notification_message', function($message, $type, $data) {
    // 自訂通知內容
    return $message;
}, 10, 3);

// 自訂通知主題
add_filter('mxp_pm_notification_subject', function($subject, $type) {
    // 自訂主題
    return $subject;
}, 10, 2);

// 自訂通知收件者
add_filter('mxp_pm_notification_recipients', function($users, $service_id, $type) {
    // 自訂收件者清單
    return $users;
}, 10, 3);

// 過濾搜尋結果
add_filter('mxp_pm_search_results', function($results, $params) {
    // 自訂過濾
    return $results;
}, 10, 2);

// 自訂搜尋查詢
add_filter('mxp_pm_search_query', function($query_parts, $params) {
    // 自訂 SQL 查詢
    return $query_parts;
}, 10, 2);

// 自訂預設分類
add_filter('mxp_pm_default_categories', function($categories) {
    // 自訂預設分類
    return $categories;
});

// Multisite: 自訂可建立全域服務的權限
add_filter('mxp_pm_can_create_global_service', function($can_create, $user_id, $blog_id) {
    // 自訂邏輯
    return $can_create;
}, 10, 3);

// Multisite: 自訂可授權的使用者清單
add_filter('mxp_pm_available_auth_users', function($users, $service_id, $requesting_user_id) {
    // 自訂可用使用者
    return $users;
}, 10, 3);

// 自訂管理選單權限
add_filter('mxp_pm_admin_menu_capability', function($capability) {
    // 自訂存取管理頁面的最低權限
    return $capability;
});

// 自訂狀態選項
add_filter('mxp_pm_status_options', function($options) {
    // 自訂服務狀態選項
    return $options;
});

// 自訂優先度選項
add_filter('mxp_pm_priority_options', function($options) {
    // 自訂服務優先度選項
    return $options;
});
```

## AJAX API

| 端點 | 說明 |
|------|------|
| `mxp_pm_get_service` | 取得服務詳細資料 |
| `mxp_pm_update_service_info` | 更新服務資料 |
| `mxp_pm_add_new_account_service` | 新增服務 |
| `mxp_pm_search_services` | 搜尋與篩選服務 |
| `mxp_pm_archive_service` | 歸檔服務 |
| `mxp_pm_restore_service` | 恢復歸檔服務 |
| `mxp_pm_delete_service` | 刪除服務 |
| `mxp_pm_batch_action` | 批次操作 |
| `mxp_pm_manage_categories` | 分類管理 |
| `mxp_pm_manage_tags` | 標籤管理 |
| `mxp_pm_manage_site_access` | 站台存取控制 (Multisite) |
| `mxp_pm_get_network_users` | 取得網路使用者 (Multisite) |
| `mxp_pm_check_updates` | 檢查 GitHub 更新 |
| `mxp_pm_dismiss_update_notice` | 關閉更新通知 |

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

### Q: 外掛支援哪些 WordPress 環境？

A: 此外掛同時支援 WordPress 單站安裝與 Multisite 網路環境。

### Q: 如何產生加密金鑰？

A: 使用以下命令產生 Base64 編碼的 32 位元組金鑰：

```bash
openssl rand -base64 32
```

### Q: 金鑰輪替如何進行？

A: 透過「帳號管理設定」頁面執行金鑰輪替（單站在「設定」選單，Multisite 在「網路管理 > 設定」），系統會自動重新加密所有資料。

### Q: 如何備份加密資料？

A: 備份資料庫時，加密資料會保持加密狀態。還原時需確保使用相同的加密金鑰。

## 版本歷史

### 3.5.1 (2026-01-11)

**重大改進**
- Email 通知信 UI/UX 重大改版
  - 修正 email 樣式顯示問題，支援各種 email 客戶端（Gmail、Outlook、Apple Mail）
  - 將所有 CSS 改為內聯樣式，提升相容性
  - 添加 Outlook VML 支援，確保按鈕正常顯示

**功能改進**
- 修正通知信變更欄位問題
  - 只有實際變更的欄位才會加入通知
  - 避免發送全欄位，明確標示實際變更項目
- 變更欄位中文化
  - 變更欄位名稱從資料庫欄位轉換為中文顯示
  - 提升使用者體驗，更清楚標示變更項目

**UI/UX 優化**
- 採用現代化配色方案與視覺樣式
  - 主色調改為 Indigo (#4f46e5)，更具科技感
  - Logo bar 使用漸層效果 (#4f46e5 → #818cf8)
  - 成功色：薄荷綠 (#f0fdf4 + #16a34a)
  - 警告色：琥珀色 (#fffbeb + #d97706)
  - 危險色：柔和紅 (#fef2f2 + #dc2626)
- 視覺細節優化
  - 卡片圓角增加到 16px，陰影更柔和
  - 按鈕圓角 8px，添加紫色陰影效果
  - 資訊框圓角 8px，更柔和的背景色
  - 時間戳記增加邊框，視覺更突出
- 優化欄位間距與排版
  - 增加表格行距，提升可讀性
  - 標籤字體加粗，與數值區分更明顯
  - 響應式設計：手機版自動堆疊

**變更**
- 修改檔案：
  - mxp-password-manager.php
  - assets/templates/emails/html/*.php（所有 HTML email 範本）

### 3.4.0 (2026-01-10)

**新功能**
- 新增外掛圖標支持
  - 添加 `icons` 欄位到 plugins_api 響應中
  - 創建自訂外掛圖標 (SVG 格式)
  - WordPress 外掛列表和更新詳情頁面將顯示鎖定圖標
  - 使用 GitHub raw URL 提供圖標資源

**變更**
- 新增檔案: assets/icon-128x128.svg
- 修改檔案: includes/class-mxp-pm-updater.php

### 3.3.6 (2026-01-10)

**Bug 修復**
- 修正通知信中的連結錯誤
  - 將所有通知信範本中的 page 參數從 `to_account_manager` 改為 `mxp-password-manager`
  - 影響檔案: assets/templates/emails/ 下所有 HTML 與純文字範本（共 8 個檔案）

### 3.3.5 (2026-01-10)

**Bug 修復**
- 修正通知設定中的「寄件者名稱」與「寄件者 Email」欄位
  - 移除錯誤的 Select2 class (`mxp-select mxp-select2-users`)
  - 恢復為純文字輸入欄位 (`regular-text` class)
  - 刪除重複的「寄件者 Email」欄位
  - 影響檔案: includes/class-mxp-pm-settings.php

### 3.3.4 (2026-01-10)

**改進**
- 移除「更新設定」分頁（現在使用 GitHub 自動更新，無需手動設定）
- 更新文件，移除過時的「更新設定（可選）」說明

**變更**
- 移除設定頁面中的「更新設定」分頁導航連結
- 移除空的 `render_updates_tab()` 方法
- 更新 README.md 中的自動更新說明

### 3.3.3 (2026-01-09)

**Bug 修復**

- 修復翻譯載入時機警告（WordPress 6.7.0+）
  - 在 Mxp_Pm_Updater 中添加延遲 getter 方法
  - 確保 plugin data 只在真正需要時才載入
  - 避免在 plugins_loaded 鉤子期間觸發翻譯
  - 影響檔案: includes/class-mxp-pm-updater.php

- 修復更新通知關閉按鈕的 AJAX 請求失敗問題（403 錯誤）
  - 添加權限檢查（current_user_can('update_plugins')）
  - 改進 nonce 驗證，使用自定義錯誤回應
  - 添加錯誤處理和調試日誌
  - 影響檔案: includes/class-mxp-pm-updater.php

**變更**
- 新增 get_plugin_name(), get_plugin_author(), get_plugin_description(), get_plugin_homepage() 方法
- 更新所有訪問 config plugin data 的地方使用延遲 getter

### 3.3.2 (2026-01-09)

**改進**
- 移除「更新設定」分頁（現在使用 GitHub 自動更新，無需手動設定）
- 優化權限設定頁面的使用者選擇器
  - 將普通 select 改為 Select2 UI
  - 與帳號密碼管理頁面的使用者選擇器保持一致
  - 提升使用者體驗和操作直觀性

**變更**
- 移除 settings 頁面中的 updates tab
- 移除 handle_settings_save() 中的 updates case 處理
- 移除 render_updates_tab() 方法
- 權限設定中的使用者選擇器改用 mxp-select mxp-select2-users class

### 3.3.1 (2026-01-09)

**Bug 修復**

- 修復資料表前綴 `{{$prefix}` 語法錯誤
  - 修正 SQL 查詢中的雙大括號為正確的單大括號
  - 修復分類與標籤管理功能的資料庫互動問題
  - 影響檔案: mxp-password-manager.php, class-mxp-pm-multisite.php, class-mxp-pm-notification.php

- 修復翻譯載入時機警告（WordPress 6.7.0+）
  - 延遲載入 plugin data 避免早期觸發翻譯
  - 在 MXP_GitHub_Updater_Config 中實現延遲載入
  - 添加 magic methods 保持向後兼容性

- 修復設定選項讀寫路徑不一致問題
  - 修正 `mxp_pm_delete_data_on_uninstall` 選項名稱
  - 修正權限選項重複前綴問題
    - `mxp_pm_view_all_services_users` → `view_all_services_users`
    - `mxp_pm_manage_encryption_users` → `manage_encryption_users`
  - 確保所有設定選項讀寫路徑一致
  - 更新所有 capability 檢查使用正確的選項名稱

**資料庫變更**
無（此版本為 bug 修復版本，無需資料庫遷移）

### 3.2.0 (2026-01-08)

- 新增：GitHub 自動更新系統
  - 從 GitHub Releases 自動檢查和下載更新
  - 開箱即用，無需任何設定
  - 完整的 WordPress 更新系統集成
  - 支援自動更新和手動檢查
  - 完整的錯誤處理和 Rate Limiting 機制
  - 支援 GitHub Token 配置提高 API 限制
  - 可選擇是否接收 Beta 版本更新
  - 可配置更新檢查間隔
  - 自動清除緩存和更新通知
  - 與現有資料庫遷移系統無縫集成

### 3.0.0 (2026-01-06)

- 新增：多站台中控管理功能
  - 服務範圍設定（全域共享/站台專屬）
  - 中控管理員角色（檢視者/編輯者/管理員）
  - 跨站台使用者授權
  - 站台存取控制
- 新增：新資料表 `to_site_access` 和 `to_central_admins`
- 新增：中控設定與管理員管理介面
- 新增：服務清單顯示範圍徽章
- 新增：多個 Multisite 相關 Hooks
- 優化：支援單站和 Multisite 兩種安裝環境

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
