<?php
/**
 * MXP Password Manager - Dashboard Template
 *
 * Main UI template with 3-column layout
 *
 * @package MXP_Password_Manager
 * @since 2.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Get current user
$current_user_id = get_current_user_id();
$is_super_admin = is_multisite() ? is_super_admin($current_user_id) : current_user_can('manage_options');
$can_view_all = $is_super_admin || Mxp_Pm_Settings::user_can('mxp_pm_view_all_services', $current_user_id);

// Get table prefix
$prefix = mxp_pm_get_table_prefix();

// Get categories for filter
$categories = $wpdb->get_results("SELECT * FROM {$prefix}to_service_categories ORDER BY sort_order, category_name");

// Get tags for filter
$tags = $wpdb->get_results("SELECT * FROM {$prefix}to_service_tags ORDER BY tag_name");

// Get status options
$status_options = Mxp_Pm_Hooks::apply_filters('mxp_pm_status_options', []);

// Get priority options
$priority_options = Mxp_Pm_Hooks::apply_filters('mxp_pm_priority_options', []);
?>

<?php
// Check if user can add services
$can_add_service = $is_super_admin || current_user_can(mxp_pm_get_option('mxp_pm_add_service_capability', 'manage_options'));
?>
<div class="wrap mxp-password-manager">
    <h1>
        <?php esc_html_e('帳號密碼管理', 'mxp-password-manager'); ?>
        <?php if ($can_add_service): ?>
            <button type="button" class="page-title-action" id="mxp-add-service">新增服務</button>
            <button type="button" class="page-title-action" id="mxp-manage-categories">管理分類</button>
            <button type="button" class="page-title-action" id="mxp-manage-tags">管理標籤</button>
        <?php endif; ?>
    </h1>

    <!-- Three Column Layout -->
    <div class="mxp-dashboard-container">
        <!-- Left Column: Filter Panel -->
        <div class="mxp-column mxp-filter-column">
            <div class="mxp-panel">
                <h3>篩選條件</h3>

                <!-- Search Box -->
                <div class="mxp-filter-group">
                    <label for="mxp-search">搜尋</label>
                    <input type="text" id="mxp-search" class="regular-text" placeholder="輸入服務名稱或帳號...">
                </div>

                <!-- Status Filter -->
                <div class="mxp-filter-group">
                    <label for="mxp-filter-status">狀態</label>
                    <select id="mxp-filter-status" class="mxp-select">
                        <option value="">全部狀態</option>
                        <?php foreach ($status_options as $value => $label): ?>
                            <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Category Filter -->
                <div class="mxp-filter-group">
                    <label for="mxp-filter-category">分類</label>
                    <select id="mxp-filter-category" class="mxp-select">
                        <option value="">全部分類</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo esc_attr($cat->cid); ?>"><?php echo esc_html($cat->category_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Tags Filter -->
                <div class="mxp-filter-group">
                    <label for="mxp-filter-tags">標籤</label>
                    <select id="mxp-filter-tags" class="mxp-select mxp-select2-tags" multiple>
                        <?php foreach ($tags as $tag): ?>
                            <option value="<?php echo esc_attr($tag->tid); ?>"><?php echo esc_html($tag->tag_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Priority Filter -->
                <div class="mxp-filter-group">
                    <label for="mxp-filter-priority">優先度</label>
                    <select id="mxp-filter-priority" class="mxp-select">
                        <option value="">全部優先度</option>
                        <?php foreach ($priority_options as $value => $label): ?>
                            <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if (is_multisite()): ?>
                <!-- Scope Filter (Multisite only) -->
                <div class="mxp-filter-group">
                    <label for="mxp-filter-scope">服務範圍</label>
                    <select id="mxp-filter-scope" class="mxp-select">
                        <option value="">全部範圍</option>
                        <option value="global"><?php echo esc_html(Mxp_Pm_Multisite::get_scope_label('global')); ?></option>
                        <option value="site"><?php echo esc_html(Mxp_Pm_Multisite::get_scope_label('site')); ?></option>
                    </select>
                </div>
                <?php endif; ?>

                <!-- Filter Actions -->
                <div class="mxp-filter-actions">
                    <button type="button" id="mxp-apply-filter" class="button button-primary">套用篩選</button>
                    <button type="button" id="mxp-clear-filter" class="button">清除篩選</button>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="mxp-panel mxp-stats-panel">
                <h3>統計資訊</h3>
                <div class="mxp-stats">
                    <div class="mxp-stat-item">
                        <span class="mxp-stat-number" id="mxp-stat-total">-</span>
                        <span class="mxp-stat-label">總服務數</span>
                    </div>
                    <div class="mxp-stat-item">
                        <span class="mxp-stat-number" id="mxp-stat-active">-</span>
                        <span class="mxp-stat-label">使用中</span>
                    </div>
                    <div class="mxp-stat-item">
                        <span class="mxp-stat-number" id="mxp-stat-archived">-</span>
                        <span class="mxp-stat-label">已歸檔</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Middle Column: Service List -->
        <div class="mxp-column mxp-list-column">
            <div class="mxp-panel">
                <!-- List Header -->
                <div class="mxp-list-header">
                    <div class="mxp-list-tabs">
                        <button type="button" class="mxp-tab active" data-status="active">使用中</button>
                        <button type="button" class="mxp-tab" data-status="archived">已歸檔</button>
                        <button type="button" class="mxp-tab" data-status="">全部</button>
                    </div>
                    <div class="mxp-list-actions">
                        <button type="button" id="mxp-batch-mode" class="button button-small">批次操作</button>
                    </div>
                </div>

                <!-- Batch Action Bar (hidden by default) -->
                <div class="mxp-batch-bar" style="display: none;">
                    <label>
                        <input type="checkbox" id="mxp-select-all"> 全選
                    </label>
                    <select id="mxp-batch-action" class="mxp-select">
                        <option value="">選擇操作...</option>
                        <option value="archive">歸檔選取項目</option>
                        <option value="restore">還原選取項目</option>
                        <option value="delete">刪除選取項目</option>
                        <option value="change_category">變更分類</option>
                        <option value="add_tags">新增標籤</option>
                    </select>
                    <button type="button" id="mxp-batch-execute" class="button">執行</button>
                    <button type="button" id="mxp-batch-cancel" class="button">取消</button>
                </div>

                <!-- Service List -->
                <div class="mxp-service-list" id="mxp-service-list">
                    <div class="mxp-loading">
                        <span class="spinner is-active"></span>
                        載入中...
                    </div>
                </div>

                <!-- Pagination -->
                <div class="mxp-pagination" id="mxp-pagination"></div>
            </div>
        </div>

        <!-- Right Column: Service Details -->
        <div class="mxp-column mxp-detail-column">
            <div class="mxp-panel mxp-detail-panel" id="mxp-detail-panel">
                <div class="mxp-detail-placeholder">
                    <span class="dashicons dashicons-lock"></span>
                    <p>選擇左側服務以查看詳細資訊</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Service Detail Template -->
<script type="text/template" id="tmpl-mxp-service-detail">
    <div class="mxp-detail-header">
        <h2>{{data.service_name}}</h2>
        <div class="mxp-detail-badges">
            <div class="mxp-detail-status mxp-status-{{data.status}}">{{data.status_label}}</div>
            <div class="mxp-detail-scope mxp-scope-{{data.scope || 'global'}}">
                <# if (data.is_global) { #>
                    <span class="dashicons dashicons-admin-multisite"></span>
                <# } else { #>
                    <span class="dashicons dashicons-admin-home"></span>
                <# } #>
                {{data.scope_label}}
            </div>
        </div>
    </div>

    <div class="mxp-detail-body">
        <!-- Scope & Owner Info (Multisite) -->
        <# if (data.owner_blog_name) { #>
            <div class="mxp-detail-owner">
                <span class="dashicons dashicons-admin-site"></span>
                來源站台: <strong>{{data.owner_blog_name}}</strong>
            </div>
        <# } #>

        <!-- Category & Tags -->
        <div class="mxp-detail-meta">
            <# if (data.category_name) { #>
                <span class="mxp-category">
                    <span class="dashicons {{data.category_icon}}"></span>
                    {{data.category_name}}
                </span>
            <# } #>
            <# if (data.tags && data.tags.length) { #>
                <div class="mxp-tags">
                    <# _.each(data.tags, function(tag) { #>
                        <span class="mxp-tag" style="background-color: {{tag.tag_color}}">{{tag.tag_name}}</span>
                    <# }); #>
                </div>
            <# } #>
        </div>

        <!-- Service URL -->
        <div class="mxp-detail-field">
            <label>服務網址</label>
            <div class="mxp-field-value">
                <a href="{{data.service_url}}" target="_blank" rel="noopener">{{data.service_url}}</a>
                <button type="button" class="mxp-copy-btn" data-copy="{{data.service_url}}" title="複製">
                    <span class="dashicons dashicons-clipboard"></span>
                </button>
            </div>
        </div>

        <!-- Account -->
        <div class="mxp-detail-field">
            <label>帳號</label>
            <div class="mxp-field-value mxp-sensitive">
                <span class="mxp-masked">••••••••</span>
                <span class="mxp-revealed" style="display:none;">{{data.account}}</span>
                <button type="button" class="mxp-reveal-btn" title="顯示/隱藏">
                    <span class="dashicons dashicons-visibility"></span>
                </button>
                <button type="button" class="mxp-copy-btn" data-copy="{{data.account}}" title="複製">
                    <span class="dashicons dashicons-clipboard"></span>
                </button>
            </div>
        </div>

        <!-- Password -->
        <div class="mxp-detail-field">
            <label>密碼</label>
            <div class="mxp-field-value mxp-sensitive">
                <span class="mxp-masked">••••••••</span>
                <span class="mxp-revealed" style="display:none;">{{data.password}}</span>
                <button type="button" class="mxp-reveal-btn" title="顯示/隱藏">
                    <span class="dashicons dashicons-visibility"></span>
                </button>
                <button type="button" class="mxp-copy-btn" data-copy="{{data.password}}" title="複製">
                    <span class="dashicons dashicons-clipboard"></span>
                </button>
            </div>
        </div>

        <!-- TOTP -->
        <# if (data.has_2fa) { #>
            <div class="mxp-detail-field mxp-totp-field">
                <label>驗證碼 (TOTP)</label>
                <div class="mxp-totp-container">
                    <span class="mxp-totp-code" id="mxp-totp-{{data.sid}}">------</span>
                    <div class="mxp-totp-timer">
                        <svg class="mxp-totp-countdown" viewBox="0 0 36 36">
                            <path class="mxp-totp-countdown-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                            <path class="mxp-totp-countdown-progress" stroke-dasharray="100, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                        </svg>
                        <span class="mxp-totp-seconds">30</span>
                    </div>
                    <button type="button" class="mxp-copy-btn mxp-copy-totp" data-sid="{{data.sid}}" title="複製">
                        <span class="dashicons dashicons-clipboard"></span>
                    </button>
                </div>
            </div>
        <# } #>

        <!-- Recover Code -->
        <# if (data.recover_code) { #>
            <div class="mxp-detail-field">
                <label>復原碼</label>
                <div class="mxp-field-value mxp-sensitive">
                    <span class="mxp-masked">••••••••</span>
                    <pre class="mxp-revealed mxp-recover-code" style="display:none;">{{data.recover_code}}</pre>
                    <button type="button" class="mxp-reveal-btn" title="顯示/隱藏">
                        <span class="dashicons dashicons-visibility"></span>
                    </button>
                    <button type="button" class="mxp-copy-btn" data-copy="{{data.recover_code}}" title="複製">
                        <span class="dashicons dashicons-clipboard"></span>
                    </button>
                </div>
            </div>
        <# } #>

        <!-- Registration Email -->
        <# if (data.reg_email) { #>
            <div class="mxp-detail-field">
                <label>註冊信箱</label>
                <div class="mxp-field-value">
                    <a href="mailto:{{data.reg_email}}">{{data.reg_email}}</a>
                    <button type="button" class="mxp-copy-btn" data-copy="{{data.reg_email}}" title="複製">
                        <span class="dashicons dashicons-clipboard"></span>
                    </button>
                </div>
            </div>
        <# } #>

        <!-- Phone Numbers -->
        <# if (data.reg_phone || data.reg_phone2) { #>
            <div class="mxp-detail-field">
                <label>電話</label>
                <div class="mxp-field-value">
                    <# if (data.reg_phone) { #>
                        <span class="mxp-phone">
                            <a href="tel:{{data.reg_phone}}">{{data.reg_phone}}</a>
                            <button type="button" class="mxp-copy-btn" data-copy="{{data.reg_phone}}" title="複製">
                                <span class="dashicons dashicons-clipboard"></span>
                            </button>
                        </span>
                    <# } #>
                    <# if (data.reg_phone2) { #>
                        <span class="mxp-phone">
                            <a href="tel:{{data.reg_phone2}}">{{data.reg_phone2}}</a>
                            <button type="button" class="mxp-copy-btn" data-copy="{{data.reg_phone2}}" title="複製">
                                <span class="dashicons dashicons-clipboard"></span>
                            </button>
                        </span>
                    <# } #>
                </div>
            </div>
        <# } #>

        <!-- Notes -->
        <# if (data.note) { #>
            <div class="mxp-detail-field">
                <label>備註</label>
                <div class="mxp-field-value mxp-note">{{data.note}}</div>
            </div>
        <# } #>

        <!-- Timestamps -->
        <div class="mxp-detail-timestamps">
            <span title="建立時間">建立: {{data.created_time}}</span>
            <span title="最後更新">更新: {{data.updated_time}}</span>
            <# if (data.last_used) { #>
                <span title="最後使用">使用: {{data.last_used}}</span>
            <# } #>
        </div>
    </div>

    <!-- Creator Info -->
    <div class="mxp-detail-creator">
        <span class="dashicons dashicons-admin-users"></span>
        建立者: <strong>{{data.created_by_name}}</strong>
        <# if (data.is_creator) { #>
            <span class="mxp-creator-badge">（您）</span>
        <# } #>
        <# if (!data.allow_authorized_edit && !data.is_creator) { #>
            <span class="mxp-edit-restricted" title="建立者已限制編輯權限">
                <span class="dashicons dashicons-lock"></span>
            </span>
        <# } #>
    </div>

    <div class="mxp-detail-actions">
        <# if (data.can_edit) { #>
            <button type="button" class="button button-primary mxp-edit-service" data-sid="{{data.sid}}">編輯</button>
        <# } #>
        <# if (data.can_archive) { #>
            <# if (data.status === 'active') { #>
                <button type="button" class="button mxp-archive-service" data-sid="{{data.sid}}">歸檔</button>
            <# } else if (data.status === 'archived') { #>
                <button type="button" class="button mxp-restore-service" data-sid="{{data.sid}}">還原</button>
            <# } #>
            <button type="button" class="button mxp-delete-service" data-sid="{{data.sid}}">刪除</button>
        <# } #>
        <button type="button" class="button mxp-view-audit-log" data-sid="{{data.sid}}">查看日誌</button>
    </div>

    <!-- Audit Log Section -->
    <# if (data.audit_log && data.audit_log.length > 0) { #>
    <div class="mxp-audit-log-section" style="display: none;">
        <h3>操作日誌</h3>
        <div class="mxp-audit-log-list">
            <#
            var sensitiveFields = ['password', '2fa_token'];
            var fieldLabels = {
                'account': '帳號',
                'password': '密碼',
                '2fa_token': '2FA 金鑰',
                'note': '備註',
                'service_name': '服務名稱',
                'login_url': '登入網址',
                'reg_email': '註冊信箱',
                'priority': '優先級',
                'category_id': '分類',
                'status': '狀態',
                'scope': '範圍',
                'auth_list': '授權人員'
            };
            #>
            <# _.each(data.audit_log, function(log) { #>
            <#
            var isSensitive = sensitiveFields.indexOf(log.field_name) !== -1;
            var fieldLabel = fieldLabels[log.field_name] || log.field_name;
            #>
            <div class="mxp-audit-log-item">
                <div class="mxp-audit-log-action">
                    <strong>{{log.action}}</strong>
                    <# if (log.field_name) { #>
                        - {{fieldLabel}}
                    <# } #>
                </div>
                <div class="mxp-audit-log-meta">
                    <span class="mxp-audit-log-user">{{log.user_display}}</span>
                    <span class="mxp-audit-log-time">{{log.added_time}}</span>
                </div>
                <# if (log.old_value || log.new_value) { #>
                <div class="mxp-audit-log-values">
                    <# if (log.old_value) { #>
                        <# if (isSensitive) { #>
                            <span class="mxp-old-value mxp-sensitive-log">
                                舊: <span class="mxp-masked-value">••••••••</span>
                                <span class="mxp-real-value" style="display:none;">{{log.old_value}}</span>
                                <button type="button" class="mxp-toggle-log-value" title="顯示/隱藏"><span class="dashicons dashicons-visibility"></span></button>
                            </span>
                        <# } else { #>
                            <span class="mxp-old-value">舊: {{log.old_value}}</span>
                        <# } #>
                    <# } #>
                    <# if (log.new_value) { #>
                        <# if (isSensitive) { #>
                            <span class="mxp-new-value mxp-sensitive-log">
                                新: <span class="mxp-masked-value">••••••••</span>
                                <span class="mxp-real-value" style="display:none;">{{log.new_value}}</span>
                                <button type="button" class="mxp-toggle-log-value" title="顯示/隱藏"><span class="dashicons dashicons-visibility"></span></button>
                            </span>
                        <# } else { #>
                            <span class="mxp-new-value">新: {{log.new_value}}</span>
                        <# } #>
                    <# } #>
                </div>
                <# } #>
            </div>
            <# }); #>
        </div>
    </div>
    <# } #>
</script>

<!-- Service List Item Template -->
<script type="text/template" id="tmpl-mxp-service-item">
    <div class="mxp-service-item mxp-status-{{data.status}}" data-sid="{{data.sid}}">
        <input type="checkbox" class="mxp-batch-checkbox" value="{{data.sid}}" style="display: none;">
        <div class="mxp-service-icon">
            <# if (data.category_icon) { #>
                <span class="dashicons {{data.category_icon}}"></span>
            <# } else { #>
                <span class="dashicons dashicons-admin-network"></span>
            <# } #>
        </div>
        <div class="mxp-service-info">
            <div class="mxp-service-name">
                {{data.service_name}}
                <# if (data.is_global) { #>
                    <span class="mxp-scope-badge mxp-scope-global" title="{{data.scope_label}}">
                        <span class="dashicons dashicons-admin-multisite"></span>
                    </span>
                <# } else { #>
                    <span class="mxp-scope-badge mxp-scope-site" title="{{data.scope_label}}">
                        <span class="dashicons dashicons-admin-home"></span>
                    </span>
                <# } #>
            </div>
            <div class="mxp-service-meta">
                <# if (data.category_name) { #>
                    <span class="mxp-service-category">{{data.category_name}}</span>
                <# } #>
                <# if (data.has_2fa) { #>
                    <span class="mxp-service-2fa" title="有 TOTP 驗證碼">2FA</span>
                <# } #>
                <# if (data.owner_blog_name) { #>
                    <span class="mxp-service-blog" title="來源站台">{{data.owner_blog_name}}</span>
                <# } #>
                <# if (data.tags && data.tags.length > 0) { #>
                    <span class="mxp-service-tags">
                        <# _.each(data.tags, function(tag) { #>
                            <span class="mxp-service-tag" style="<# if (tag.tag_color) { #>background-color: {{tag.tag_color}};<# } #>">{{tag.tag_name}}</span>
                        <# }); #>
                    </span>
                <# } #>
            </div>
        </div>
        <div class="mxp-service-priority mxp-priority-{{data.priority}}" title="優先度: {{data.priority_label}}">
            <span class="dashicons dashicons-star-filled"></span>
        </div>
    </div>
</script>

<!-- Add/Edit Service Modal -->
<div id="mxp-service-modal" class="mxp-modal" style="display: none;">
    <div class="mxp-modal-overlay"></div>
    <div class="mxp-modal-content">
        <div class="mxp-modal-header">
            <h2 id="mxp-modal-title">新增服務</h2>
            <button type="button" class="mxp-modal-close">&times;</button>
        </div>
        <form id="mxp-service-form">
            <input type="hidden" name="sid" id="mxp-form-sid" value="">
            <input type="hidden" name="action" value="mxp_pm_add_new_account_service">
            <?php wp_nonce_field('mxp_pm_nonce', 'mxp_pm_nonce'); ?>

            <div class="mxp-modal-body">
                <div class="mxp-form-row">
                    <label for="mxp-form-service_name">服務名稱 <span class="required">*</span></label>
                    <input type="text" id="mxp-form-service_name" name="service_name" required>
                </div>

                <div class="mxp-form-row">
                    <label for="mxp-form-service_url">服務網址</label>
                    <input type="url" id="mxp-form-service_url" name="service_url" placeholder="https://">
                </div>

                <div class="mxp-form-row mxp-form-row-half">
                    <div>
                        <label for="mxp-form-category">分類</label>
                        <select id="mxp-form-category" name="category_id" class="mxp-select">
                            <option value="">無分類</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo esc_attr($cat->cid); ?>"><?php echo esc_html($cat->category_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="mxp-form-priority">優先度</label>
                        <select id="mxp-form-priority" name="priority" class="mxp-select">
                            <?php foreach ($priority_options as $value => $label): ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($value, 3); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="mxp-form-row">
                    <label for="mxp-form-tags">標籤</label>
                    <select id="mxp-form-tags" name="tags[]" class="mxp-select mxp-select2-tags" multiple>
                        <?php foreach ($tags as $tag): ?>
                            <option value="<?php echo esc_attr($tag->tid); ?>"><?php echo esc_html($tag->tag_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if (is_multisite()): ?>
                <!-- Scope Selection (Multisite only) -->
                <div class="mxp-form-row" id="mxp-form-scope-row">
                    <label>服務範圍</label>
                    <div class="mxp-scope-options">
                        <label class="mxp-scope-option">
                            <input type="radio" name="scope" value="global" <?php checked(Mxp_Pm_Multisite::get_default_scope(), 'global'); ?>>
                            <span class="mxp-scope-label">
                                <span class="dashicons dashicons-admin-multisite"></span>
                                <?php echo esc_html(Mxp_Pm_Multisite::get_scope_label('global')); ?>
                            </span>
                            <span class="mxp-scope-desc">所有站台皆可存取</span>
                        </label>
                        <label class="mxp-scope-option">
                            <input type="radio" name="scope" value="site" <?php checked(Mxp_Pm_Multisite::get_default_scope(), 'site'); ?>>
                            <span class="mxp-scope-label">
                                <span class="dashicons dashicons-admin-home"></span>
                                <?php echo esc_html(Mxp_Pm_Multisite::get_scope_label('site')); ?>
                            </span>
                            <span class="mxp-scope-desc">僅限此站台</span>
                        </label>
                    </div>
                    <?php if (!Mxp_Pm_Multisite::can_create_global()): ?>
                        <p class="description mxp-warning">您沒有建立全域共享服務的權限，只能選擇站台專屬。</p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <hr>

                <div class="mxp-form-row">
                    <label for="mxp-form-account">帳號 <span class="required">*</span></label>
                    <input type="text" id="mxp-form-account" name="account" autocomplete="off" required>
                </div>

                <div class="mxp-form-row">
                    <label for="mxp-form-password">密碼 <span class="required">*</span></label>
                    <div class="mxp-password-field">
                        <input type="password" id="mxp-form-password" name="password" autocomplete="new-password" required>
                        <button type="button" class="mxp-toggle-password" title="顯示/隱藏">
                            <span class="dashicons dashicons-visibility"></span>
                        </button>
                        <button type="button" class="mxp-generate-password" title="產生密碼">
                            <span class="dashicons dashicons-randomize"></span>
                        </button>
                    </div>
                </div>

                <div class="mxp-form-row">
                    <label for="mxp-form-2fa_token">2FA 金鑰 (TOTP Secret)</label>
                    <input type="text" id="mxp-form-2fa_token" name="2fa_token" placeholder="Base32 格式金鑰">
                    <p class="description">輸入 TOTP 驗證器的 Base32 金鑰，系統會自動產生驗證碼</p>
                </div>

                <div class="mxp-form-row">
                    <label for="mxp-form-recover_code">復原碼</label>
                    <textarea id="mxp-form-recover_code" name="recover_code" rows="2" placeholder="備用復原碼，每行一組"></textarea>
                </div>

                <div class="mxp-form-row">
                    <label for="mxp-form-reg_email">註冊信箱</label>
                    <input type="email" id="mxp-form-reg_email" name="reg_email" placeholder="example@email.com">
                </div>

                <div class="mxp-form-row mxp-form-row-half">
                    <div>
                        <label for="mxp-form-reg_phone">電話 1</label>
                        <input type="tel" id="mxp-form-reg_phone" name="reg_phone" placeholder="09xx-xxx-xxx">
                    </div>
                    <div>
                        <label for="mxp-form-reg_phone2">電話 2</label>
                        <input type="tel" id="mxp-form-reg_phone2" name="reg_phone2" placeholder="09xx-xxx-xxx">
                    </div>
                </div>

                <div class="mxp-form-row">
                    <label for="mxp-form-note">備註</label>
                    <textarea id="mxp-form-note" name="note" rows="3"></textarea>
                </div>

                <hr>

                <div class="mxp-form-row">
                    <label for="mxp-form-auth_users">授權使用者</label>
                    <select id="mxp-form-auth_users" name="auth_users[]" class="mxp-select mxp-select2-users" multiple>
                        <?php
                        // Get users for authorization
                        $user_args = is_multisite() ? ['blog_id' => get_current_blog_id(), 'number' => 100] : ['number' => 100];
                        $users = get_users($user_args);
                        foreach ($users as $user):
                            ?>
                            <option value="<?php echo esc_attr($user->ID); ?>"><?php echo esc_html($user->display_name); ?> (<?php echo esc_html($user->user_email); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">選擇可以存取此服務的使用者</p>
                </div>

                <div class="mxp-form-row mxp-creator-only-option" id="mxp-form-allow-edit-row">
                    <label class="mxp-checkbox-label">
                        <input type="checkbox" name="allow_authorized_edit" id="mxp-form-allow_authorized_edit" value="1" checked>
                        允許授權使用者編輯與歸檔此服務
                    </label>
                    <p class="description">取消勾選後，只有您（建立者）可以編輯或歸檔此服務</p>
                </div>
            </div>

            <div class="mxp-modal-footer">
                <button type="button" class="button mxp-modal-cancel">取消</button>
                <button type="submit" class="button button-primary">儲存</button>
            </div>
        </form>
    </div>
</div>

<!-- Manage Categories Modal -->
<div id="mxp-categories-modal" class="mxp-modal" style="display: none;">
    <div class="mxp-modal-overlay"></div>
    <div class="mxp-modal-content mxp-modal-small">
        <div class="mxp-modal-header">
            <h2>管理分類</h2>
            <button type="button" class="mxp-modal-close">&times;</button>
        </div>
        <div class="mxp-modal-body">
            <div class="mxp-categories-list" id="mxp-categories-list"></div>
            <form id="mxp-category-form" class="mxp-inline-form">
                <input type="hidden" name="action" value="mxp_pm_manage_categories">
                <input type="hidden" name="operation" value="add">
                <input type="hidden" name="cid" value="">
                <?php wp_nonce_field('mxp_pm_nonce', 'mxp_pm_nonce'); ?>
                <input type="text" name="category_name" placeholder="分類名稱" required>
                <select name="category_icon">
                    <option value="dashicons-category">預設</option>
                    <option value="dashicons-admin-users">使用者</option>
                    <option value="dashicons-cloud">雲端</option>
                    <option value="dashicons-database">資料庫</option>
                    <option value="dashicons-admin-site">網站</option>
                    <option value="dashicons-email">Email</option>
                    <option value="dashicons-money-alt">金融</option>
                    <option value="dashicons-shield">安全</option>
                </select>
                <button type="submit" class="button">新增</button>
            </form>
        </div>
    </div>
</div>

<!-- Manage Tags Modal -->
<div id="mxp-tags-modal" class="mxp-modal" style="display: none;">
    <div class="mxp-modal-overlay"></div>
    <div class="mxp-modal-content mxp-modal-small">
        <div class="mxp-modal-header">
            <h2>管理標籤</h2>
            <button type="button" class="mxp-modal-close">&times;</button>
        </div>
        <div class="mxp-modal-body">
            <div class="mxp-tags-list" id="mxp-tags-list"></div>
            <form id="mxp-tag-form" class="mxp-inline-form">
                <input type="hidden" name="action" value="mxp_pm_manage_tags">
                <input type="hidden" name="operation" value="add">
                <input type="hidden" name="tid" value="">
                <?php wp_nonce_field('mxp_pm_nonce', 'mxp_pm_nonce'); ?>
                <input type="text" name="tag_name" placeholder="標籤名稱" required>
                <input type="color" name="tag_color" value="#6c757d">
                <button type="submit" class="button">新增</button>
            </form>
        </div>
    </div>
</div>

<!-- Confirmation Dialog -->
<div id="mxp-confirm-dialog" class="mxp-modal" style="display: none;">
    <div class="mxp-modal-overlay"></div>
    <div class="mxp-modal-content mxp-modal-confirm">
        <div class="mxp-modal-header">
            <h2 id="mxp-confirm-title">確認</h2>
        </div>
        <div class="mxp-modal-body">
            <p id="mxp-confirm-message"></p>
        </div>
        <div class="mxp-modal-footer">
            <button type="button" class="button mxp-confirm-cancel">取消</button>
            <button type="button" class="button button-primary mxp-confirm-ok">確認</button>
        </div>
    </div>
</div>
