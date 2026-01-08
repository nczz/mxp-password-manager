# Plan: Unified MXP_PM Prefix Refactoring

## Objective
Unify all project prefixes to `mxp_pm_` (code, database, hooks, constants) and implement a comprehensive test suite to ensure stability.

## Current State Analysis

### Database Tables
- Current prefix: `to_` (e.g., `to_service_list`, `to_auth_list`)
- Desired prefix: `mxp_pm_` (e.g., `mxp_pm_service_list`, `mxp_pm_auth_list`)

### Classes
- Current prefix: `Mxp_` (e.g., `Mxp_AccountManager`, `Mxp_Encryption`)
- Desired prefix: `Mxp_Pm_` (e.g., `Mxp_Pm_AccountManager`, `Mxp_Pm_Encryption`)

### AJAX Actions
- Current prefix: `to_` (e.g., `wp_ajax_to_get_service`)
- Desired prefix: `mxp_pm_` (e.g., `wp_ajax_mxp_pm_get_service`)

### Constants
- Mixed usage: `MXP_PM_VERSION` (correct) vs `MXP_GITHUB_REPO` (needs update)
- Desired: `MXP_PM_` consistently

### Hooks
- Current: `mxp_service_created`, `mxp_auth_granted`
- Desired: `mxp_pm_service_created`, `mxp_pm_auth_granted`

### Options
- Current: `mxp_password_manager_version`, `mxp_github_repo`
- Desired: `mxp_pm_password_manager_version`, `mxp_pm_github_repo`

### Test Infrastructure
- **Status**: No existing test files found
- **Requirement**: Must establish test infrastructure before refactoring

## Execution Plan

### Phase 1: Test Infrastructure Setup (CRITICAL - Safety Net)

**Goal**: Establish a comprehensive test suite to prevent regressions.

#### 1.1 Composer Configuration
```json
{
  "require-dev": {
    "phpunit/phpunit": "^9.5",
    "10up/wp_mock": "^0.5.0"
  },
  "autoload-dev": {
    "psr-4": {
      "MXP_PM\\Tests\\": "tests/"
    }
  }
}
```

#### 1.2 PHPUnit Configuration
- Create `tests/bootstrap.php` to initialize WordPress environment
- Create `phpunit.xml.dist` for test configuration
- Configure test database (separate from production)

#### 1.3 Write Characterization Tests (Before Refactoring)
Create tests for existing behavior to establish baseline:

**Tests to Implement**:
1. `Mxp_Pm_Encryption_Test`
   - `test_encrypt_decrypt_success()`
   - `test_is_configured()`
   - `test_key_generation()`

2. `Mxp_Pm_AccountManager_Test`
   - `test_install_creates_tables()` (verify old table names first)
   - `test_add_audit_log()`
   - `test_user_can_access_service()`

3. `Mxp_Pm_Update_Test`
   - `test_update_version_tracking()`

**Success Criteria**: All tests pass with current code structure.

---

### Phase 2: Database & Data Migration

**Goal**: Rename tables and migrate data without data loss.

#### 2.1 Schema Definition Update
Update `Mxp_Pm_AccountManager::install()` to use `mxp_pm_` table prefix:
- Rename all `CREATE TABLE` statements
- Update all `$wpdb->prepare()` queries

#### 2.2 Migration Script Implementation
Implement `Mxp_Pm_Update::mxp_pm_update_to_v3_3_0()`:

**Migration Steps**:
```php
private static function mxp_pm_update_to_v3_3_0(): bool {
    global $wpdb;
    $prefix = mxp_pm_get_table_prefix();

    // Step 1: Rename tables (to_ -> mxp_pm_)
    $table_mappings = [
        'to_service_list' => 'mxp_pm_service_list',
        'to_auth_list' => 'mxp_pm_auth_list',
        'to_audit_log' => 'mxp_pm_audit_log',
        'to_service_categories' => 'mxp_pm_service_categories',
        'to_service_tags' => 'mxp_pm_service_tags',
        'to_service_tag_map' => 'mxp_pm_service_tag_map',
        'to_site_access' => 'mxp_pm_site_access',
        'to_central_admins' => 'mxp_pm_central_admins'
    ];

    foreach ($table_mappings as $old_table => $new_table) {
        $old_full = $prefix . $old_table;
        $new_full = $prefix . $new_table;

        // Check if old table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$old_full'");

        if ($table_exists) {
            $wpdb->query("RENAME TABLE `$old_full` TO `$new_full`");
        }
    }

    // Step 2: Migrate options (mxp_ -> mxp_pm_)
    $option_mappings = [
        'mxp_password_manager_version' => 'mxp_pm_password_manager_version',
        'mxp_github_repo' => 'mxp_pm_github_repo',
        'mxp_central_control_enabled' => 'mxp_pm_central_control_enabled',
        'mxp_default_service_scope' => 'mxp_pm_default_service_scope',
        // Add all other mxp_ options
    ];

    foreach ($option_mappings as $old_option => $new_option) {
        $value = mxp_pm_get_option($old_option);
        if ($value !== false) {
            mxp_pm_update_option($new_option, $value);
            mxp_pm_delete_option($old_option);
        }
    }

    // Step 3: Update version
    mxp_pm_update_option('mxp_pm_password_manager_version', '3.3.0');

    return true;
}
```

#### 2.3 Update Helper Functions
Modify `mxp_pm_get_table_prefix()` to return correct prefix (no change needed, it returns WordPress prefix).

---

### Phase 3: Codebase Refactoring

**Goal**: Apply unified prefix across all files systematically.

#### 3.1 File Renaming
```
includes/
  class-mxp-encryption.php → class-mxp-pm-encryption.php
  class-mxp-notification.php → class-mxp-pm-notification.php
  class-mxp-settings.php → class-mxp-pm-settings.php
  class-mxp-hooks.php → class-mxp-pm-hooks.php
  class-mxp-multisite.php → class-mxp-pm-multisite.php
  class-mxp-updater.php → class-mxp-pm-updater.php
  class-mxp-github-updater-config.php → class-mxp-pm-github-updater-config.php
```

#### 3.2 Class Renaming
- `Mxp_AccountManager` → `Mxp_Pm_AccountManager`
- `Mxp_Encryption` → `Mxp_Pm_Encryption`
- `Mxp_Notification` → `Mxp_Pm_Notification`
- `Mxp_Settings` → `Mxp_Pm_Settings`
- `Mxp_Hooks` → `Mxp_Pm_Hooks`
- `Mxp_Multisite` → `Mxp_Pm_Multisite`
- `Mxp_Updater` → `Mxp_Pm_Updater`
- `MXP_GitHub_Updater_Config` → `Mxp_Pm_GitHub_Updater_Config`

#### 3.3 Global Search & Replace (Execute Carefully)

**Constants**:
- `MXP_GITHUB_REPO` → `MXP_PM_GITHUB_REPO`
- `MXP_PM_VERSION` → `MXP_PM_VERSION` (already correct)

**Hooks**:
- `mxp_service_created` → `mxp_pm_service_created`
- `mxp_service_updated` → `mxp_pm_service_updated`
- `mxp_service_viewed` → `mxp_pm_service_viewed`
- `mxp_auth_granted` → `mxp_pm_auth_granted`
- `mxp_auth_revoked` → `mxp_pm_auth_revoked`
- `mxp_can_view_service` → `mxp_pm_can_view_service`
- `mxp_can_edit_service` → `mxp_pm_can_edit_service`
- `mxp_default_categories` → `mxp_pm_default_categories`
- `mxp_encrypt_fields` → `mxp_pm_encrypt_fields`
- `mxp_notification_message` → `mxp_pm_notification_message`
- `mxp_notification_recipients` → `mxp_pm_notification_recipients`
- `mxp_search_query` → `mxp_pm_search_query`
- `mxp_search_results` → `mxp_pm_search_results`
- `mxp_status_options` → `mxp_pm_status_options`
- `mxp_priority_options` → `mxp_pm_priority_options`
- `mxp_service_archived` → `mxp_pm_service_archived`
- `mxp_service_restored` → `mxp_pm_service_restored`
- `mxp_batch_action_completed` → `mxp_pm_batch_action_completed`
- `mxp_category_created` → `mxp_pm_category_created`
- `mxp_category_updated` → `mxp_pm_category_updated`
- `mxp_category_deleted` → `mxp_pm_category_deleted`
- `mxp_tag_created` → `mxp_pm_tag_created`
- `mxp_tag_deleted` → `mxp_pm_tag_deleted`
- `mxp_service_deleted` → `mxp_pm_service_deleted`
- `mxp_notification_sent` → `mxp_pm_notification_sent`
- `mxp_key_rotated` → `mxp_pm_key_rotated`
- `mxp_service_scope_changed` → `mxp_pm_service_scope_changed`

**AJAX Actions**:
- `wp_ajax_to_get_service` → `wp_ajax_mxp_pm_get_service`
- `wp_ajax_to_update_service_info` → `wp_ajax_mxp_pm_update_service_info`
- `wp_ajax_to_add_new_account_service` → `wp_ajax_mxp_pm_add_new_account_service`
- `wp_ajax_to_search_services` → `wp_ajax_mxp_pm_search_services`
- `wp_ajax_to_archive_service` → `wp_ajax_mxp_pm_archive_service`
- `wp_ajax_to_restore_service` → `wp_ajax_mxp_pm_restore_service`
- `wp_ajax_to_batch_action` → `wp_ajax_mxp_pm_batch_action`
- `wp_ajax_to_manage_categories` → `wp_ajax_mxp_pm_manage_categories`
- `wp_ajax_to_manage_tags` → `wp_ajax_mxp_pm_manage_tags`
- `wp_ajax_to_delete_service` → `wp_ajax_mxp_pm_delete_service`
- `wp_ajax_to_manage_site_access` → `wp_ajax_mxp_pm_manage_site_access`
- `wp_ajax_to_get_network_users` → `wp_ajax_mxp_pm_get_network_users`
- `wp_ajax_mxp_save_settings` → `wp_ajax_mxp_pm_save_settings`
- `network_admin_edit_mxp_save_settings` → `network_admin_edit_mxp_pm_save_settings`

**Nonce**:
- `to_account_manager_nonce` → `mxp_pm_nonce`

**Capability Names**:
- `mxp_manage_encryption` → `mxp_pm_manage_encryption`
- `mxp_rotate_encryption_key` → `mxp_pm_rotate_encryption_key`
- `mxp_view_all_services` → `mxp_pm_view_all_services`
- `mxp_manage_notifications` → `mxp_pm_manage_notifications`

**Option Names**:
- `mxp_password_manager_version` → `mxp_pm_password_manager_version`
- `mxp_github_repo` → `mxp_pm_github_repo`
- `mxp_central_control_enabled` → `mxp_pm_central_control_enabled`
- `mxp_default_service_scope` → `mxp_pm_default_service_scope`
- `mxp_view_all_services_users` → `mxp_pm_view_all_services_users`

#### 3.4 Frontend Updates (`assets/js/main.js`)
- Update AJAX action references to new prefixes
- Update nonce reference: `to_account_manager_nonce` → `mxp_pm_nonce`
- Update `mxp_ajax` object to `mxp_pm_ajax`

---

### Phase 4: Verification & Testing

**Goal**: Ensure everything works after refactoring.

#### 4.1 Automated Tests
- Run PHPUnit test suite
- Verify all tests pass with new class names
- Ensure migration script tests pass

#### 4.2 Manual Verification Checklist
- [ ] Plugin activation (creates new tables with correct prefix)
- [ ] Plugin upgrade from v3.2.0 (renames tables correctly)
- [ ] Create new service
- [ ] Update existing service
- [ ] View service (decrypt fields)
- [ ] Archive/restore service
- [ ] Delete archived service
- [ ] Category management
- [ ] Tag management
- [ ] Search services
- [ ] Batch operations
- [ ] Email notifications sent correctly
- [ ] GitHub auto-update still works
- [ ] TOTP generation works
- [ ] Multisite central admin functions
- [ ] Encryption key rotation

---

## Technical Requirements

### PHP Version
- **Minimum**: PHP 7.4+
- **Test Framework**: PHPUnit 9.5.x

### Development Tools
- Composer for dependency management
- WP_Mock for WordPress function mocking

### Database
- MySQL 5.7+ / MariaDB 10.3+
- Must support RENAME TABLE operation

---

## Risks & Mitigation

### Risk 1: Data Loss During Migration
**Mitigation**:
- Migration script will verify table existence before renaming
- Test migration on staging environment first
- Create backup table concept in tests

### Risk 2: Breaking Changes for Third-Party Integrations
**Mitigation**:
- Document all breaking changes in `RELEASE_NOTES.md`
- Provide migration guide for plugin developers
- Consider maintaining backward compatibility hooks (optional)

### Risk 3: Encryption Key Incompatibility
**Mitigation**:
- Encryption logic is not changing, only class/prefix names
- Test encryption/decryption before and after refactoring
- Ensure same encryption key works with new codebase

### Risk 4: WordPress Multisite Compatibility
**Mitigation**:
- Test on Multisite installation
- Verify `mxp_pm_get_table_prefix()` works correctly in both modes
- Ensure migration runs on network activation

---

## Breaking Changes Documentation

Update `RELEASE_NOTES.md` with:

```markdown
## Breaking Changes in v3.3.0

### Developer-Facing Changes
- **Class Names**: All classes renamed from `Mxp_*` to `Mxp_Pm_*`
- **Hooks**: All action/filter hooks renamed from `mxp_*` to `mxp_pm_*`
- **AJAX Actions**: Renamed from `to_*` to `mxp_pm_*`
- **Capabilities**: Renamed from `mxp_*` to `mxp_pm_*`
- **Options**: All plugin options renamed from `mxp_*` to `mxp_pm_*`
- **Nonce**: Changed from `to_account_manager_nonce` to `mxp_pm_nonce`

### Migration Required
Existing installations will automatically migrate to new table prefix and option names.

### For Plugin Developers
Update your custom integrations:
```php
// Old
add_action('mxp_service_created', 'my_callback');

// New
add_action('mxp_pm_service_created', 'my_callback');
```
```

---

## Success Criteria

1. All automated tests pass
2. Manual verification checklist complete
3. Zero data loss during migration
4. Plugin works in both single-site and Multisite mode
5. Documentation updated
6. Breaking changes clearly documented

---

## Rollback Plan

If critical issues arise post-deployment:
1. Deactivate v3.3.0 plugin
2. Reactivate v3.2.0
3. Data remains intact (tables not deleted, only renamed)
4. Database tables will be incompatible until manually renamed back

---

## Estimated Effort

- Phase 1 (Tests): 4-6 hours
- Phase 2 (Migration): 2-3 hours
- Phase 3 (Refactoring): 6-8 hours
- Phase 4 (Testing): 4-6 hours
- **Total**: ~16-23 hours

---

## Next Steps

1. Review and approve this plan
2. Run `/start-work` to begin implementation
3. Implementation will follow phases sequentially
