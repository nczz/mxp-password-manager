# Version 3.5.1 Release Notes

## 🎨 Major UI/UX Redesign: Email Notification System

### ✨ New Features

- **Modern email template design with improved visual hierarchy**
  - Redesigned all email notification templates (HTML format)
  - Adopted modern card-style layout with enhanced shadows
  - Gradient color effects for better visual appeal

### 🎨 Design Improvements

- **New color scheme**
  - Primary color changed to Indigo (#4f46e5) for a more modern, tech-focused look
  - Logo bar uses gradient effect (#4f46e5 → #818cf8)
  - Success state: Mint green (#f0fdf4 + #16a34a)
  - Warning state: Amber (#fffbeb + #d97706)
  - Danger state: Soft red (#fef2f2 + #dc2626)

- **Visual enhancements**
  - Card border radius increased to 16px with softer shadows
  - Button radius increased to 8px with purple shadow effects
  - Info box border radius increased to 8px with softer background colors
  - Timestamp badge now includes border for better visibility
  - Improved font color contrast for better readability

- **Responsive improvements**
  - Mobile-optimized spacing and layout
  - Better table spacing (16px row padding)
  - Label-value separation with font weight and color contrast
  - Mobile stack view for better readability on small screens

### 🔧 Bug Fixes

- **Fixed email style display issues**
  - Converted all CSS to inline styles for better email client compatibility
  - Added Outlook VML support for proper button rendering
  - Now works correctly in Gmail, Outlook, Apple Mail, and other major clients

- **Fixed notification field change tracking**
  - Only actual changed fields are now included in notifications
  - Previously all submitted fields were sent, making it unclear what actually changed
  - Users now see precise information about what was modified

- **Added Chinese translation for changed fields**
  - Database field names now converted to Chinese display names
  - Examples: `account` → 帳號, `password` → 密碼, `service_name` → 服務名稱
  - Improves user experience with clear, localized field labels

### 📝 Changed Files

- `mxp-password-manager.php`
  - Updated MXP_PM_VERSION constant to 3.5.1
  - Fixed field change tracking logic (line 1020-1030)
  - Added field label mapping for Chinese translation

- `assets/templates/emails/html/base.php`
  - Complete redesign with inline styles
  - Modern color scheme and gradient effects
  - Enhanced card design with rounded corners and shadows
  - Responsive design improvements

- `assets/templates/emails/html/auth_granted.php`
  - Updated to new design system
  - Success color theme (mint green)
  - Improved visual hierarchy

- `assets/templates/emails/html/auth_revoked.php`
  - Updated to new design system
  - Danger color theme (soft red)
  - Better user feedback

- `assets/templates/emails/html/password_changed.php`
  - Updated to new design system
  - Warning color theme (amber)
  - Clear change indication

- `assets/templates/emails/html/service_created.php`
  - Updated to new design system
  - Success color theme (mint green)
  - Enhanced visual presentation

- `assets/templates/emails/html/service_updated.php`
  - Updated to new design system
  - Warning color theme (amber)
  - Clear changed field display with Chinese labels

- `README.md`
  - Updated version badge to 3.5.1
  - Added v3.5.1 version history entry
  - Updated icon version references

- `CLAUDE.md`
  - Updated icon version references

- `SDD.md`
  - Updated icon version references

### 🎯 Rationale

Email notifications are a critical communication channel for this password manager. The previous implementation had several issues:

1. **Style Display Problems**: Many email clients (Gmail, Outlook) ignored CSS in `<style>` tags, resulting in plain, unstyled emails
2. **Unclear Change Notifications**: Users couldn't tell what actually changed when a service was updated
3. **Outdated Design**: The visual design didn't match modern UI/UX standards

This major update addresses all these issues:
- **Better Compatibility**: Inline styles ensure consistent appearance across all email clients
- **Clearer Communication**: Only actual changes are shown with localized field names
- **Modern Aesthetics**: New color scheme and design principles align with contemporary UI trends
- **Improved Usability**: Better spacing, contrast, and visual hierarchy make notifications easier to read and understand

### 🚀 Upgrade Instructions
1. Update to this version via GitHub automatic update or manual installation
2. No configuration required - all changes are backward compatible
3. Email templates will immediately use new design for all future notifications
4. Existing settings and data remain unchanged

### ⚠️ Important Notes
- **Backwards Compatible**: All existing functionality remains unchanged
- **No Breaking Changes**: Existing data and settings are preserved
- **Email Client Support**: Tested on Gmail, Outlook, Apple Mail, Yahoo Mail
- **Chinese Localization**: Field names now display in Chinese for better user experience

---

# Version 3.4.0 Release Notes

## ✨ New Feature: Plugin Icon Support

### 📝 Changes

- **Added plugin icon for WordPress admin display**
  - Added `icons` field to plugins_api response
  - Created custom lock icon (SVG format, 128x128)
  - WordPress plugin list and update details page will now display lock icon
  - Using GitHub raw URL to serve icon assets

### 🎨 Design

- Created a modern lock icon design with WordPress blue gradient
- SVG format ensures crisp rendering at any size
- Matches WordPress admin UI color scheme

### 📝 Changed Files

- `includes/class-mxp-pm-updater.php`
  - Added `icons` field to filter_plugin_info response
- `assets/icon-128x128.svg` (new file)
  - Custom lock icon with gradient design

### 🎯 Rationale

WordPress plugins from official repository automatically display icons, but custom GitHub-hosted plugins need to provide icon URLs via `plugins_api` hook. This enhancement adds visual polish and makes plugin more recognizable in WordPress admin interface.

---

# Version 3.3.6 Release Notes

## 🐛 Bug Fix: Correct Email Notification Links

### 📝 Changes

- **Fixed broken links in notification emails**
  - Changed page parameter from `to_account_manager` to `mxp-password-manager` in all email templates
  - Links now correctly point to plugin's dashboard page
  - Affected all 8 email templates (HTML and plain text versions)

### 🎯 Rationale

The notification email templates were using an incorrect page slug (`to_account_manager`) for dashboard links, causing 404 errors when users clicked on links. This fix updates all templates to use the correct page slug (`mxp-password-manager`) that matches the registered admin menu.

### 📝 Changed Files

- `assets/templates/emails/text/auth_granted.php`
- `assets/templates/emails/text/password_changed.php`
- `assets/templates/emails/text/service_updated.php`
- `assets/templates/emails/text/service_created.php`
- `assets/templates/emails/html/auth_granted.php`
- `assets/templates/emails/html/password_changed.php`
- `assets/templates/emails/html/service_updated.php`
- `assets/templates/emails/html/service_created.php`

---

# Version 3.3.5 Release Notes

## 🐛 Bug Fix: Correct Notification Settings Input Fields

### 📝 Changes

- **Fixed "From Name" and "From Email" fields in notification settings**
  - Removed incorrect Select2 class (`mxp-select mxp-select2-users`)
  - Changed back to plain text input fields (`regular-text` class)
  - Removed duplicate "From Email" field
  - These fields should be simple string inputs, not dropdown selectors

### 🎯 Rationale

The notification sender name and email fields are meant to be simple text inputs for configuring email sender information. They were incorrectly styled with Select2 classes, which caused them to appear as dropdown selectors rather than text input fields. This fix restores correct UI behavior.

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
  - Simplifies settings UI for better user experience

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

GitHub auto-update system (introduced in v3.2.0) is designed to work out-of-the-box with no configuration required. The "Update Settings" tab was left over from initial implementation and contained no meaningful settings since all update behavior is automatic.

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
This update brings a powerful automatic update system that makes plugin maintenance easier for all users. The system is designed to be simple, secure, and reliable.

---

## Manual Release Creation Instructions

Since `gh` CLI is not available in this environment, please create the GitHub Release manually:

1. Go to: https://github.com/nczz/mxp-password-manager/releases/new
2. Select tag: `v3.2.0`
3. Title: `Version 3.2.0`
4. Description: Copy content from this file (RELEASE_NOTES.md)
5. Attach ZIP file: `mxp-password-manager-3.2.0.zip` (already in project root)
6. Check "Set as the latest release"
7. Click "Publish release"

The release notes above provide comprehensive information about this version.
