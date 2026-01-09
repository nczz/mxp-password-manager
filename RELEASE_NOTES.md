# Version 3.3.5 Release Notes

## 🐛 Bug Fix: Correct Notification Settings Input Fields

### 📝 Changes

- **Fixed "From Name" and "From Email" fields in notification settings**
  - Removed incorrect Select2 class (`mxp-select mxp-select2-users`)
  - Changed back to plain text input fields (`regular-text` class)
  - Removed duplicate "From Email" field
  - These fields should be simple string inputs, not dropdown selectors

### 🎯 Rationale

The notification sender name and email fields are meant to be simple text inputs for configuring the email sender information. They were incorrectly styled with Select2 classes, which caused them to appear as dropdown selectors rather than text input fields. This fix restores the correct UI behavior.

### 📝 Changed Files

- `includes/class-mxp-pm-settings.php`
  - Line 255: Changed input class from `mxp-select mxp-select2-users` to `regular-text` for "From Name" field
  - Line 261: Changed input class from `mxp-select mxp-select2-users` to `regular-text` for "From Email" field
  - Removed duplicate "From Email" field (lines 264-269)

---

# Version 3.3.4 Release Notes

## 🧹 UI Cleanup: Remove Empty "Update Settings" Tab

### ✨ Changes

- **Removed "Update Settings" tab** from settings page
  - The tab had no configurable items (GitHub auto-update works out-of-the-box)
  - Simplifies the settings UI for better user experience

- **Updated documentation**
  - Removed outdated "Update Settings (Optional)" section from README.md
  - Updated auto-update documentation to reflect removal of manual settings

### 📝 Changed Files

- `includes/class-mxp-pm-settings.php`
  - Removed "更新設定" tab link from navigation (line 93)
  - Removed empty `render_updates_tab()` method
- `README.md`
  - Removed "Update Settings (Optional)" section
  - Updated version badge to 3.3.4
  - Added 3.3.4 version history entry
- `mxp-password-manager.php`
  - Updated version to 3.3.4 (plugin header)
  - Updated MXP_PM_VERSION constant to 3.3.4

### 🎯 Rationale

GitHub auto-update system (introduced in v3.2.0) is designed to work out-of-the-box with no configuration required. The "Update Settings" tab was left over from the initial implementation and contained no meaningful settings since all update behavior is automatic.

This cleanup:
- Reduces UI clutter
- Eliminates user confusion about "empty" settings
- Maintains full auto-update functionality (no features lost)

---

## Version 3.2.0 Release Notes

## 🎉 Major Feature: GitHub Auto-Update System

### ✨ New Features

- **GitHub Auto-Update System** - Out-of-the-box automatic updates from GitHub
  - Automatic update checks via GitHub Releases API
  - Seamless integration with WordPress built-in update system
  - **No configuration required** - works immediately after installation
  - Support for manual update check and notice dismissal
  - Full error handling and rate limiting mechanisms
  - Optional GitHub Token support for increased API limits (60/hr → 5,000/hr)
  - Beta version filtering (optional)
  - Configurable update check intervals (1-24 hours)
  - Automatic cache clearing after successful updates
  - Seamless integration with existing database migration system

### 🔧 Technical Improvements

- Added `Mxp_Updater` class - Main GitHub update handler
  - Uses `pre_set_site_transient_update_plugins` hook for update checks
  - Uses `plugins_api` hook for plugin information display
  - Uses `upgrader_process_complete` hook for cache clearing
  - AJAX endpoints: manual update check, notice dismissal
  - Admin notices for available updates

- Added `MXP_GitHub_Updater_Config` class - Configuration management
  - Flexible GitHub repository settings (default: nczz/mxp-password-manager)
  - Multiple token sources (wp-config constant > option > environment variable)
  - Configurable cache duration (default: 12 hours)
  - Beta version option

- Settings page enhancements
  - New "Update Settings" tab
  - Real-time update status display (current version, latest version, last check time)
  - "Check for Updates Now" button with AJAX
  - GitHub repository configuration (optional, uses default if empty)
  - GitHub Token configuration (optional, masked display)
  - Auto-update enable/disable toggle
  - Beta version inclusion toggle
  - Update check interval selector

### 📚 Documentation Updates

- Updated README.md
  - Added "Auto-Update" section with detailed usage instructions
  - Added API limits explanation (no Token vs. with Token)
  - Added GitHub Releases preparation guide
  - Updated features list and version history

- Updated CLAUDE.md
  - Added GitHub Auto-Update system documentation
  - Added new classes reference (Mxp_Updater, MXP_GitHub_Updater_Config)
  - Added development notes for updates

### 🎯 Key Features

#### Out-of-the-Box Usage
- **No Setup Required**: Plugin works immediately after installation
- WordPress automatically checks GitHub Releases periodically
- Update notifications appear in WordPress admin
- Click "Update" button to download and install new versions
- Automatic database migrations after updates (if needed)

#### API Limits
- **No Token**: 60 requests/hour (sufficient for automatic update checks)
- **With Token**: 5,000 requests/hour (for frequent checking scenarios)
- Token is **optional** and does not affect basic functionality

#### GitHub Releases Requirements
- Version tags: Use semantic versioning (v3.2.0, v3.3.0)
- Assets: Must include plugin ZIP file
- Release notes: Markdown format

### 📝 Changed Files
- `mxp-password-manager.php` - Updated plugin header, version 3.2.0, GitHub repo constants
- `includes/class-mxp-updater.php` - New GitHub update system
- `includes/class-mxp-github-updater-config.php` - New configuration management
- `includes/class-mxp-settings.php` - Added update settings tab
- `README.md` - Updated documentation
- `CLAUDE.md` - Updated development guide

### 🚀 Upgrade Instructions
1. Update to this version via GitHub automatic update or manual installation
2. No configuration required - auto-update works out of box
3. Optional: Configure GitHub repository in "Settings > Update Settings" if needed
4. Optional: Add GitHub Token for increased API limits (not required)

### ⚠️ Important Notes
- **Backwards Compatible**: All existing settings and data are preserved
- **No Breaking Changes**: Existing functionality remains unchanged
- **Default Repository**: nczz/mxp-password-manager (can be customized in settings)
- **Token is Optional**: The plugin works perfectly without GitHub Token

### 🙏 Acknowledgments
This update brings a powerful automatic update system that makes plugin maintenance
easier for all users. The system is designed to be simple, secure, and reliable.

---

## Manual Release Creation Instructions

Since `gh` CLI is not available in this environment, please create the GitHub Release manually:

1. Go to: https://github.com/nczz/mxp-password-manager/releases/new
2. Select tag: `v3.2.0`
3. Title: `Version 3.2.0`
4. Description: Copy the content from this file (RELEASE_NOTES.md)
5. Attach the ZIP file: `mxp-password-manager-3.2.0.zip` (already in project root)
6. Check "Set as the latest release"
7. Click "Publish release"

The release notes above provide comprehensive information about this version.
