/**
 * MXP Password Manager - Main JavaScript
 *
 * @package MXP_Password_Manager
 * @since 2.1.0
 */

(function($) {
    'use strict';

    // Global namespace
    window.MXP = window.MXP || {};

    /**
     * TOTP Generator (RFC 4226/6238)
     */
    MXP.TOTP = {
        /**
         * Base32 alphabet
         */
        BASE32_CHARS: 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567',

        /**
         * Decode Base32 string to byte array
         * @param {string} input - Base32 encoded string
         * @returns {Uint8Array}
         */
        base32Decode: function(input) {
            input = input.replace(/\s/g, '').toUpperCase();
            input = input.replace(/=+$/, '');

            var bits = '';
            for (var i = 0; i < input.length; i++) {
                var val = this.BASE32_CHARS.indexOf(input.charAt(i));
                if (val === -1) continue;
                bits += ('00000' + val.toString(2)).slice(-5);
            }

            var bytes = new Uint8Array(Math.floor(bits.length / 8));
            for (var i = 0; i < bytes.length; i++) {
                bytes[i] = parseInt(bits.substr(i * 8, 8), 2);
            }

            return bytes;
        },

        /**
         * Generate TOTP code
         * @param {string} secret - Base32 encoded secret
         * @param {number} timeStep - Time step in seconds (default: 30)
         * @param {number} digits - Number of digits (default: 6)
         * @returns {string}
         */
        generate: function(secret, timeStep, digits) {
            timeStep = timeStep || 30;
            digits = digits || 6;

            // Get current time counter
            var counter = Math.floor(Date.now() / 1000 / timeStep);

            // Convert counter to byte array (8 bytes, big-endian)
            var counterBytes = new Uint8Array(8);
            for (var i = 7; i >= 0; i--) {
                counterBytes[i] = counter & 0xff;
                counter = Math.floor(counter / 256);
            }

            // Decode secret
            var key = this.base32Decode(secret);

            // HMAC-SHA1
            var hmac = CryptoJS.HmacSHA1(
                CryptoJS.lib.WordArray.create(counterBytes),
                CryptoJS.lib.WordArray.create(key)
            );

            // Convert to byte array
            var hmacBytes = [];
            var hmacWords = hmac.words;
            for (var i = 0; i < hmacWords.length; i++) {
                hmacBytes.push((hmacWords[i] >> 24) & 0xff);
                hmacBytes.push((hmacWords[i] >> 16) & 0xff);
                hmacBytes.push((hmacWords[i] >> 8) & 0xff);
                hmacBytes.push(hmacWords[i] & 0xff);
            }

            // Dynamic truncation
            var offset = hmacBytes[hmacBytes.length - 1] & 0xf;
            var code = (
                ((hmacBytes[offset] & 0x7f) << 24) |
                ((hmacBytes[offset + 1] & 0xff) << 16) |
                ((hmacBytes[offset + 2] & 0xff) << 8) |
                (hmacBytes[offset + 3] & 0xff)
            ) % Math.pow(10, digits);

            // Pad with zeros
            return ('000000' + code).slice(-digits);
        },

        /**
         * Get remaining seconds until next code
         * @param {number} timeStep - Time step in seconds (default: 30)
         * @returns {number}
         */
        getRemainingSeconds: function(timeStep) {
            timeStep = timeStep || 30;
            return timeStep - (Math.floor(Date.now() / 1000) % timeStep);
        },

        /**
         * Store for active TOTP intervals
         */
        _intervals: {},

        /**
         * Start TOTP display for a service
         * @param {string} sid - Service ID
         * @param {string} secret - Base32 encoded secret
         */
        startDisplay: function(sid, secret) {
            var self = this;

            // Clear existing interval
            if (this._intervals[sid]) {
                clearInterval(this._intervals[sid]);
            }

            // Update function
            var update = function() {
                var code = self.generate(secret);
                var remaining = self.getRemainingSeconds();
                var progress = (remaining / 30) * 100;

                $('#mxp-totp-' + sid).text(code.replace(/(.{3})/g, '$1 ').trim());
                $('#mxp-totp-' + sid).closest('.mxp-totp-container').find('.mxp-totp-seconds').text(remaining);
                $('#mxp-totp-' + sid).closest('.mxp-totp-container').find('.mxp-totp-countdown-progress')
                    .attr('stroke-dasharray', progress + ', 100');
            };

            // Initial update
            update();

            // Start interval
            this._intervals[sid] = setInterval(update, 1000);
        },

        /**
         * Stop TOTP display for a service
         * @param {string} sid - Service ID
         */
        stopDisplay: function(sid) {
            if (this._intervals[sid]) {
                clearInterval(this._intervals[sid]);
                delete this._intervals[sid];
            }
        },

        /**
         * Stop all TOTP displays
         */
        stopAll: function() {
            for (var sid in this._intervals) {
                this.stopDisplay(sid);
            }
        }
    };

    /**
     * Toast Notifications
     */
    MXP.Toast = {
        /**
         * Container element
         */
        $container: null,

        /**
         * Initialize container
         */
        init: function() {
            if (!this.$container) {
                this.$container = $('<div class="mxp-toast-container"></div>').appendTo('body');
            }
        },

        /**
         * Show toast
         * @param {string} message - Message to display
         * @param {string} type - Toast type (success, error, warning, info)
         * @param {number} duration - Duration in ms (default: 3000)
         */
        show: function(message, type, duration) {
            this.init();

            type = type || 'info';
            duration = duration || 3000;

            var icons = {
                success: 'dashicons-yes-alt',
                error: 'dashicons-dismiss',
                warning: 'dashicons-warning',
                info: 'dashicons-info'
            };

            var $toast = $(
                '<div class="mxp-toast mxp-toast-' + type + '">' +
                    '<span class="dashicons ' + icons[type] + ' mxp-toast-icon"></span>' +
                    '<span class="mxp-toast-message">' + message + '</span>' +
                    '<button type="button" class="mxp-toast-close">&times;</button>' +
                '</div>'
            );

            this.$container.append($toast);

            // Auto dismiss
            setTimeout(function() {
                $toast.addClass('mxp-toast-out');
                setTimeout(function() {
                    $toast.remove();
                }, 300);
            }, duration);

            // Manual close
            $toast.find('.mxp-toast-close').on('click', function() {
                $toast.addClass('mxp-toast-out');
                setTimeout(function() {
                    $toast.remove();
                }, 300);
            });
        },

        /**
         * Shorthand methods
         */
        success: function(message) { this.show(message, 'success'); },
        error: function(message) { this.show(message, 'error', 5000); },
        warning: function(message) { this.show(message, 'warning', 4000); },
        info: function(message) { this.show(message, 'info'); }
    };

    /**
     * Clipboard Helper
     */
    MXP.Clipboard = {
        /**
         * Copy text to clipboard
         * @param {string} text - Text to copy
         * @param {jQuery} $button - Button element for feedback
         */
        copy: function(text, $button) {
            navigator.clipboard.writeText(text).then(function() {
                if ($button) {
                    $button.addClass('copied');
                    setTimeout(function() {
                        $button.removeClass('copied');
                    }, 1500);
                }
                MXP.Toast.success('已複製到剪貼簿');
            }).catch(function() {
                // Fallback for older browsers
                var $temp = $('<textarea>').val(text).appendTo('body').select();
                document.execCommand('copy');
                $temp.remove();

                if ($button) {
                    $button.addClass('copied');
                    setTimeout(function() {
                        $button.removeClass('copied');
                    }, 1500);
                }
                MXP.Toast.success('已複製到剪貼簿');
            });
        }
    };

    /**
     * Password Generator
     */
    MXP.PasswordGenerator = {
        /**
         * Generate random password
         * @param {number} length - Password length (default: 16)
         * @param {object} options - Options
         * @returns {string}
         */
        generate: function(length, options) {
            length = length || 16;
            options = $.extend({
                uppercase: true,
                lowercase: true,
                numbers: true,
                symbols: true
            }, options);

            var chars = '';
            if (options.uppercase) chars += 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            if (options.lowercase) chars += 'abcdefghijklmnopqrstuvwxyz';
            if (options.numbers) chars += '0123456789';
            if (options.symbols) chars += '!@#$%^&*()_+-=[]{}|;:,.<>?';

            var password = '';
            var array = new Uint32Array(length);
            crypto.getRandomValues(array);

            for (var i = 0; i < length; i++) {
                password += chars[array[i] % chars.length];
            }

            return password;
        }
    };

    /**
     * Confirmation Dialog
     */
    MXP.Confirm = {
        /**
         * Show confirmation dialog
         * @param {string} title - Dialog title
         * @param {string} message - Dialog message
         * @param {function} onConfirm - Callback on confirm
         * @param {function} onCancel - Callback on cancel
         */
        show: function(title, message, onConfirm, onCancel) {
            var $dialog = $('#mxp-confirm-dialog');
            $('#mxp-confirm-title').text(title);
            $('#mxp-confirm-message').html(message);

            $dialog.show();

            // Confirm button
            $dialog.find('.mxp-confirm-ok').off('click').on('click', function() {
                $dialog.hide();
                if (typeof onConfirm === 'function') {
                    onConfirm();
                }
            });

            // Cancel button
            $dialog.find('.mxp-confirm-cancel, .mxp-modal-overlay').off('click').on('click', function() {
                $dialog.hide();
                if (typeof onCancel === 'function') {
                    onCancel();
                }
            });
        }
    };

    /**
     * Main Application
     */
    MXP.App = {
        /**
         * Current state
         */
        state: {
            currentPage: 1,
            currentStatus: 'active',
            currentService: null,
            batchMode: false,
            filters: {
                search: '',
                status: 'active',
                category: '',
                tags: [],
                priority: ''
            }
        },

        /**
         * Templates
         */
        templates: {},

        /**
         * Initialize application
         */
        init: function() {
            this.cacheTemplates();
            this.bindEvents();
            this.initSelect2();
            this.loadServices();
            this.loadStats();
        },

        /**
         * Cache WordPress-style templates
         */
        cacheTemplates: function() {
            // Use WordPress template syntax: <# #> for code, {{ }} for output
            this.templates.serviceItem = wp.template('mxp-service-item');
            this.templates.serviceDetail = wp.template('mxp-service-detail');
        },

        /**
         * Initialize Select2
         */
        initSelect2: function() {
            $('.mxp-select2-tags').select2({
                placeholder: '選擇標籤...',
                allowClear: true,
                width: '100%',
                dropdownParent: $('body')
            });

            $('.mxp-select2-users').select2({
                placeholder: '選擇使用者...',
                allowClear: true,
                width: '100%',
                dropdownParent: $('body')
            });
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            var self = this;

            // Tab switching
            $(document).on('click', '.mxp-tab', function() {
                var status = $(this).data('status');
                self.state.filters.status = status;
                self.state.currentPage = 1;
                $('.mxp-tab').removeClass('active');
                $(this).addClass('active');
                self.loadServices();
            });

            // Search input (debounced)
            var searchTimer;
            $('#mxp-search').on('input', function() {
                clearTimeout(searchTimer);
                var value = $(this).val();
                searchTimer = setTimeout(function() {
                    self.state.filters.search = value;
                    self.state.currentPage = 1;
                    self.loadServices();
                }, 300);
            });

            // Filter controls
            $('#mxp-filter-status').on('change', function() {
                self.state.filters.status = $(this).val();
            });
            $('#mxp-filter-category').on('change', function() {
                self.state.filters.category = $(this).val();
            });
            $('#mxp-filter-tags').on('change', function() {
                self.state.filters.tags = $(this).val() || [];
            });
            $('#mxp-filter-priority').on('change', function() {
                self.state.filters.priority = $(this).val();
            });

            // Apply filter
            $('#mxp-apply-filter').on('click', function() {
                self.state.currentPage = 1;
                self.loadServices();
            });

            // Clear filter
            $('#mxp-clear-filter').on('click', function() {
                self.state.filters = {
                    search: '',
                    status: 'active',
                    category: '',
                    tags: [],
                    priority: ''
                };
                $('#mxp-search').val('');
                $('#mxp-filter-status').val('');
                $('#mxp-filter-category').val('');
                $('#mxp-filter-tags').val([]).trigger('change');
                $('#mxp-filter-priority').val('');
                $('.mxp-tab').removeClass('active');
                $('.mxp-tab[data-status="active"]').addClass('active');
                self.state.currentPage = 1;
                self.loadServices();
            });

            // Service item click
            $(document).on('click', '.mxp-service-item', function(e) {
                if ($(e.target).is('.mxp-batch-checkbox')) return;
                var sid = $(this).data('sid');
                $('.mxp-service-item').removeClass('active');
                $(this).addClass('active');
                self.loadServiceDetail(sid);
            });

            // Add new service
            $('#mxp-add-service').on('click', function() {
                self.openServiceModal();
            });

            // Manage categories
            $('#mxp-manage-categories').on('click', function() {
                self.loadCategories();
                $('#mxp-categories-modal').show();
            });

            // Manage tags
            $('#mxp-manage-tags').on('click', function() {
                self.loadTags();
                $('#mxp-tags-modal').show();
            });

            // Modal close
            $(document).on('click', '.mxp-modal-close, .mxp-modal-cancel, .mxp-modal-overlay', function() {
                $(this).closest('.mxp-modal').hide();
            });

            // Service form submit
            $('#mxp-service-form').on('submit', function(e) {
                e.preventDefault();
                self.saveService($(this));
            });

            // Toggle password visibility
            $(document).on('click', '.mxp-toggle-password', function() {
                var $input = $(this).siblings('input');
                var type = $input.attr('type') === 'password' ? 'text' : 'password';
                $input.attr('type', type);
                $(this).find('.dashicons')
                    .toggleClass('dashicons-visibility dashicons-hidden');
            });

            // Generate password
            $(document).on('click', '.mxp-generate-password', function() {
                var password = MXP.PasswordGenerator.generate(16);
                $('#mxp-form-password').val(password).attr('type', 'text');
                $(this).siblings('.mxp-toggle-password').find('.dashicons')
                    .removeClass('dashicons-visibility').addClass('dashicons-hidden');
            });

            // Reveal sensitive field
            $(document).on('click', '.mxp-reveal-btn', function() {
                var $container = $(this).closest('.mxp-sensitive');
                $container.find('.mxp-masked').toggle();
                $container.find('.mxp-revealed').toggle();
                $(this).find('.dashicons')
                    .toggleClass('dashicons-visibility dashicons-hidden');
            });

            // Copy button
            $(document).on('click', '.mxp-copy-btn:not(.mxp-copy-totp)', function() {
                var text = $(this).data('copy');
                MXP.Clipboard.copy(text, $(this));
            });

            // Copy TOTP
            $(document).on('click', '.mxp-copy-totp', function() {
                var sid = $(this).data('sid');
                var code = $('#mxp-totp-' + sid).text().replace(/\s/g, '');
                MXP.Clipboard.copy(code, $(this));
            });

            // Edit service
            $(document).on('click', '.mxp-edit-service', function() {
                var sid = $(this).data('sid');
                self.openServiceModal(sid);
            });

            // Archive service
            $(document).on('click', '.mxp-archive-service', function() {
                var sid = $(this).data('sid');
                MXP.Confirm.show('歸檔服務', '確定要將此服務歸檔嗎？', function() {
                    self.archiveService(sid);
                });
            });

            // Restore service
            $(document).on('click', '.mxp-restore-service', function() {
                var sid = $(this).data('sid');
                MXP.Confirm.show('還原服務', '確定要還原此服務嗎？', function() {
                    self.restoreService(sid);
                });
            });

            // Delete service
            $(document).on('click', '.mxp-delete-service', function() {
                var sid = $(this).data('sid');
                MXP.Confirm.show('刪除服務', '確定要永久刪除此服務嗎？此操作無法復原。', function() {
                    self.deleteService(sid);
                });
            });

            // View audit log
            $(document).on('click', '.mxp-view-audit-log', function() {
                var $section = $('.mxp-audit-log-section');
                if ($section.length) {
                    $section.slideToggle();
                    $(this).text($section.is(':visible') ? '隱藏日誌' : '查看日誌');
                } else {
                    MXP.Toast.info('此服務尚無操作日誌');
                }
            });

            // Toggle sensitive value in audit log
            $(document).on('click', '.mxp-toggle-log-value', function() {
                var $container = $(this).closest('.mxp-sensitive-log');
                var $masked = $container.find('.mxp-masked-value');
                var $real = $container.find('.mxp-real-value');
                var $icon = $(this).find('.dashicons');

                if ($masked.is(':visible')) {
                    $masked.hide();
                    $real.show();
                    $icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
                } else {
                    $masked.show();
                    $real.hide();
                    $icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
                }
            });

            // Batch mode toggle
            $('#mxp-batch-mode').on('click', function() {
                self.state.batchMode = !self.state.batchMode;
                self.toggleBatchMode();
            });

            // Select all
            $('#mxp-select-all').on('change', function() {
                var checked = $(this).prop('checked');
                $('.mxp-batch-checkbox').prop('checked', checked);
            });

            // Batch execute
            $('#mxp-batch-execute').on('click', function() {
                self.executeBatchAction();
            });

            // Batch cancel
            $('#mxp-batch-cancel').on('click', function() {
                self.state.batchMode = false;
                self.toggleBatchMode();
            });

            // Pagination
            $(document).on('click', '.mxp-pagination button', function() {
                var page = $(this).data('page');
                if (page) {
                    self.state.currentPage = page;
                    self.loadServices();
                }
            });

            // Category form
            $('#mxp-category-form').on('submit', function(e) {
                e.preventDefault();
                self.saveCategory($(this));
            });

            // Tag form
            $('#mxp-tag-form').on('submit', function(e) {
                e.preventDefault();
                self.saveTag($(this));
            });

            // Delete category
            $(document).on('click', '.mxp-delete-category', function() {
                var cid = $(this).data('cid');
                MXP.Confirm.show('刪除分類', '確定要刪除此分類嗎？', function() {
                    self.deleteCategory(cid);
                });
            });

            // Delete tag
            $(document).on('click', '.mxp-delete-tag', function() {
                var tid = $(this).data('tid');
                MXP.Confirm.show('刪除標籤', '確定要刪除此標籤嗎？', function() {
                    self.deleteTag(tid);
                });
            });
        },

        /**
         * Load services list
         */
        loadServices: function() {
            var self = this;

            $('#mxp-service-list').html(
                '<div class="mxp-loading">' +
                    '<span class="spinner is-active"></span>' +
                    '載入中...' +
                '</div>'
            );

            $.ajax({
                url: mxp_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'to_search_services',
                    to_nonce: mxp_ajax.nonce,
                    search: self.state.filters.search,
                    status: self.state.filters.status,
                    category_id: self.state.filters.category,
                    tags: self.state.filters.tags,
                    priority: self.state.filters.priority,
                    page: self.state.currentPage,
                    per_page: 20
                },
                success: function(response) {
                    if (response.success && response.data && response.data.data) {
                        self.renderServiceList(response.data.data.services);
                        self.renderPagination(response.data.data.pagination);
                    } else if (response.success && response.data && response.data.services) {
                        // Fallback for direct structure
                        self.renderServiceList(response.data.services);
                        self.renderPagination(response.data.pagination);
                    } else {
                        self.renderEmptyState(response.data?.message || '載入失敗');
                    }
                },
                error: function() {
                    self.renderEmptyState('載入失敗，請重試');
                }
            });
        },

        /**
         * Render service list
         */
        renderServiceList: function(services) {
            var self = this;
            var $list = $('#mxp-service-list');

            console.log('MXP Debug - renderServiceList services:', services);

            if (!services || services.length === 0) {
                this.renderEmptyState('沒有找到符合條件的服務');
                return;
            }

            var html = '';
            services.forEach(function(service) {
                console.log('MXP Debug - rendering service:', service);
                // wp.template automatically assigns the passed object as 'data'
                html += self.templates.serviceItem(service);
            });

            console.log('MXP Debug - rendered HTML:', html);
            $list.html(html);

            // Show batch checkboxes if in batch mode
            if (this.state.batchMode) {
                $('.mxp-batch-checkbox').show();
            }
        },

        /**
         * Render empty state
         */
        renderEmptyState: function(message) {
            $('#mxp-service-list').html(
                '<div class="mxp-empty-state">' +
                    '<span class="dashicons dashicons-database"></span>' +
                    '<p>' + message + '</p>' +
                '</div>'
            );
        },

        /**
         * Render pagination
         */
        renderPagination: function(pagination) {
            var $pagination = $('#mxp-pagination');

            if (!pagination || pagination.total_pages <= 1) {
                $pagination.empty();
                return;
            }

            var html = '';
            var current = pagination.current_page;
            var total = pagination.total_pages;

            // Previous button
            if (current > 1) {
                html += '<button type="button" data-page="' + (current - 1) + '">&laquo;</button>';
            }

            // Page numbers
            for (var i = 1; i <= total; i++) {
                if (i === 1 || i === total || (i >= current - 2 && i <= current + 2)) {
                    html += '<button type="button" data-page="' + i + '"' +
                        (i === current ? ' class="active"' : '') + '>' + i + '</button>';
                } else if (i === current - 3 || i === current + 3) {
                    html += '<button type="button" disabled>...</button>';
                }
            }

            // Next button
            if (current < total) {
                html += '<button type="button" data-page="' + (current + 1) + '">&raquo;</button>';
            }

            $pagination.html(html);
        },

        /**
         * Load service detail
         */
        loadServiceDetail: function(sid) {
            var self = this;

            // Stop existing TOTP
            MXP.TOTP.stopAll();

            $.ajax({
                url: mxp_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'to_get_service',
                    to_nonce: mxp_ajax.nonce,
                    sid: sid
                },
                success: function(response) {
                    if (response.success && response.data) {
                        // Handle nested data structure: response.data.data or response.data
                        var serviceData = response.data.data || response.data;

                        console.log('MXP Debug - loadServiceDetail response:', response);
                        console.log('MXP Debug - serviceData:', serviceData);

                        self.state.currentService = serviceData;
                        self.renderServiceDetail(serviceData);

                        // Start TOTP if available
                        if (serviceData.has_2fa && serviceData['2fa_token']) {
                            MXP.TOTP.startDisplay(sid, serviceData['2fa_token']);
                        }
                    } else {
                        MXP.Toast.error(response.data?.message || '載入服務詳情失敗');
                    }
                },
                error: function() {
                    MXP.Toast.error('載入服務詳情失敗');
                }
            });
        },

        /**
         * Render service detail
         */
        renderServiceDetail: function(service) {
            // wp.template automatically assigns the passed object as 'data'
            console.log('MXP Debug - renderServiceDetail input:', service);
            var html = this.templates.serviceDetail(service);
            console.log('MXP Debug - renderServiceDetail HTML:', html);
            $('#mxp-detail-panel').html(html);
        },

        /**
         * Load statistics
         */
        loadStats: function() {
            $.ajax({
                url: mxp_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'to_search_services',
                    to_nonce: mxp_ajax.nonce,
                    status: '',
                    per_page: 1
                },
                success: function(response) {
                    if (response.success && response.data.stats) {
                        $('#mxp-stat-total').text(response.data.stats.total || 0);
                        $('#mxp-stat-active').text(response.data.stats.active || 0);
                        $('#mxp-stat-archived').text(response.data.stats.archived || 0);
                    }
                }
            });
        },

        /**
         * Open service modal (add/edit)
         */
        openServiceModal: function(sid) {
            var self = this;
            var $modal = $('#mxp-service-modal');
            var $form = $('#mxp-service-form');
            var $allowEditRow = $('#mxp-form-allow-edit-row');
            var $allowEditCheckbox = $('#mxp-form-allow_authorized_edit');
            var $authUsersSelect = $('#mxp-form-auth_users');

            // Reset form
            $form[0].reset();
            $('#mxp-form-sid').val('');

            // Reset allow_authorized_edit checkbox to checked (default)
            $allowEditCheckbox.prop('checked', true).prop('disabled', false);
            $allowEditRow.show();

            // Clear stored creator ID
            self.state.editingServiceCreatorId = null;

            // Show modal first so Select2 can calculate width properly
            $modal.show();

            // Destroy and reinitialize Select2 after modal is visible
            self.initModalSelect2();

            // Clear Select2 values after reinitialization
            $('#mxp-form-tags').val([]).trigger('change');
            $authUsersSelect.val([]).trigger('change');

            if (sid) {
                // Edit mode
                $('#mxp-modal-title').text('編輯服務');
                $form.find('[name="action"]').val('to_update_service_info');

                // Load service data
                $.ajax({
                    url: mxp_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'to_get_service',
                        to_nonce: mxp_ajax.nonce,
                        sid: sid
                    },
                    success: function(response) {
                        if (response.success && response.data) {
                            // Handle nested data structure
                            var data = response.data.data || response.data;
                            console.log('MXP Debug - Edit form data:', data);

                            $('#mxp-form-sid').val(data.sid);
                            $('#mxp-form-service_name').val(data.service_name);
                            $('#mxp-form-service_url').val(data.service_url || data.login_url);
                            $('#mxp-form-category').val(data.category_id);
                            $('#mxp-form-priority').val(data.priority);

                            // Tags: extract tag IDs from tags array
                            var tagIds = [];
                            if (data.tags && data.tags.length) {
                                tagIds = data.tags.map(function(tag) {
                                    return tag.tid;
                                });
                            }
                            $('#mxp-form-tags').val(tagIds).trigger('change');

                            $('#mxp-form-account').val(data.account);
                            $('#mxp-form-password').val(data.password);
                            $('#mxp-form-2fa_token').val(data['2fa_token']);
                            $('#mxp-form-note').val(data.note);

                            // Store creator ID for protection (v3.1.0)
                            self.state.editingServiceCreatorId = data.created_by ? parseInt(data.created_by) : null;

                            // Auth users: use auth_list (array of user IDs)
                            var authUserIds = data.auth_list || data.auth_user_ids || [];
                            $authUsersSelect.val(authUserIds).trigger('change');

                            // Disable the creator option in Select2 to prevent removal
                            if (self.state.editingServiceCreatorId) {
                                self.protectCreatorInAuthList(self.state.editingServiceCreatorId);
                            }

                            // Handle allow_authorized_edit checkbox (v3.1.0)
                            var allowEdit = data.allow_authorized_edit !== undefined ? parseInt(data.allow_authorized_edit) : 1;
                            $allowEditCheckbox.prop('checked', allowEdit === 1);

                            // Only creator can modify this option
                            if (data.is_creator) {
                                $allowEditCheckbox.prop('disabled', false);
                                $allowEditRow.show();
                            } else {
                                // Non-creator cannot see or modify this option
                                $allowEditCheckbox.prop('disabled', true);
                                $allowEditRow.hide();
                            }
                        }
                    }
                });
            } else {
                // Add mode - user is the creator, so they can set this option
                $('#mxp-modal-title').text('新增服務');
                $form.find('[name="action"]').val('to_add_new_account_service');
                $allowEditCheckbox.prop('checked', true).prop('disabled', false);
                $allowEditRow.show();

                // Pre-select current user as authorized user (v3.1.0)
                var currentUserId = mxp_password_manager_obj.current_user_id;
                if (currentUserId) {
                    $authUsersSelect.val([currentUserId.toString()]).trigger('change');
                }
            }
        },

        /**
         * Protect creator from being removed in auth list (v3.1.0)
         */
        protectCreatorInAuthList: function(creatorId) {
            var $select = $('#mxp-form-auth_users');

            // Lock the creator option
            $select.find('option[value="' + creatorId + '"]').prop('disabled', true);

            // Listen for unselect events and prevent removing creator
            $select.on('select2:unselecting', function(e) {
                if (parseInt(e.params.args.data.id) === creatorId) {
                    e.preventDefault();
                    MXP.Toast.warning('無法移除建立者的授權');
                }
            });
        },

        /**
         * Initialize Select2 in modal (called when modal opens)
         */
        initModalSelect2: function() {
            var $tagsSelect = $('#mxp-form-tags');
            var $usersSelect = $('#mxp-form-auth_users');

            // Remove any existing event listeners (v3.1.0)
            $usersSelect.off('select2:unselecting');

            // Re-enable all options (reset from previous edit)
            $usersSelect.find('option').prop('disabled', false);

            // Destroy existing Select2 instances if any
            if ($tagsSelect.hasClass('select2-hidden-accessible')) {
                $tagsSelect.select2('destroy');
            }
            if ($usersSelect.hasClass('select2-hidden-accessible')) {
                $usersSelect.select2('destroy');
            }

            // Reinitialize Select2
            $tagsSelect.select2({
                placeholder: '選擇標籤...',
                allowClear: true,
                width: '100%',
                dropdownParent: $('#mxp-service-modal .mxp-modal-content')
            });

            $usersSelect.select2({
                placeholder: '選擇使用者...',
                allowClear: true,
                width: '100%',
                dropdownParent: $('#mxp-service-modal .mxp-modal-content')
            });
        },

        /**
         * Save service
         */
        saveService: function($form) {
            var self = this;
            var formData = $form.serialize();

            // Handle allow_authorized_edit checkbox - ensure it's sent even when unchecked
            var $allowEditCheckbox = $('#mxp-form-allow_authorized_edit');
            if (!$allowEditCheckbox.prop('disabled')) {
                // Remove any existing allow_authorized_edit from formData
                formData = formData.replace(/&?allow_authorized_edit=[^&]*/g, '');
                // Add the correct value
                var allowEditValue = $allowEditCheckbox.prop('checked') ? 1 : 0;
                formData += '&allow_authorized_edit=' + allowEditValue;
            }

            $.ajax({
                url: mxp_ajax.ajax_url,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        MXP.Toast.success(response.data.message || '儲存成功');
                        $('#mxp-service-modal').hide();
                        self.loadServices();
                        self.loadStats();

                        if (response.data.sid) {
                            self.loadServiceDetail(response.data.sid);
                        }
                    } else {
                        MXP.Toast.error(response.data.message || '儲存失敗');
                    }
                },
                error: function() {
                    MXP.Toast.error('儲存失敗，請重試');
                }
            });
        },

        /**
         * Archive service
         */
        archiveService: function(sid) {
            var self = this;

            $.ajax({
                url: mxp_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'to_archive_service',
                    to_nonce: mxp_ajax.nonce,
                    sid: sid
                },
                success: function(response) {
                    if (response.success) {
                        MXP.Toast.success('服務已歸檔');
                        self.loadServices();
                        self.loadStats();
                        self.clearDetail();
                    } else {
                        MXP.Toast.error(response.data.message || '歸檔失敗');
                    }
                },
                error: function() {
                    MXP.Toast.error('歸檔失敗，請重試');
                }
            });
        },

        /**
         * Restore service
         */
        restoreService: function(sid) {
            var self = this;

            $.ajax({
                url: mxp_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'to_restore_service',
                    to_nonce: mxp_ajax.nonce,
                    sid: sid
                },
                success: function(response) {
                    if (response.success) {
                        MXP.Toast.success('服務已還原');
                        self.loadServices();
                        self.loadStats();
                        self.clearDetail();
                    } else {
                        MXP.Toast.error(response.data.message || '還原失敗');
                    }
                },
                error: function() {
                    MXP.Toast.error('還原失敗，請重試');
                }
            });
        },

        /**
         * Delete service
         */
        deleteService: function(sid) {
            var self = this;

            $.ajax({
                url: mxp_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'to_delete_service',
                    to_nonce: mxp_ajax.nonce,
                    sid: sid
                },
                success: function(response) {
                    if (response.success) {
                        MXP.Toast.success('服務已刪除');
                        self.loadServices();
                        self.loadStats();
                        self.clearDetail();
                    } else {
                        MXP.Toast.error(response.data.message || '刪除失敗');
                    }
                },
                error: function() {
                    MXP.Toast.error('刪除失敗，請重試');
                }
            });
        },

        /**
         * Clear detail panel
         */
        clearDetail: function() {
            MXP.TOTP.stopAll();
            this.state.currentService = null;
            $('#mxp-detail-panel').html(
                '<div class="mxp-detail-placeholder">' +
                    '<span class="dashicons dashicons-lock"></span>' +
                    '<p>選擇左側服務以查看詳細資訊</p>' +
                '</div>'
            );
        },

        /**
         * Toggle batch mode
         */
        toggleBatchMode: function() {
            var $bar = $('.mxp-batch-bar');
            var $checkboxes = $('.mxp-batch-checkbox');

            if (this.state.batchMode) {
                $bar.show();
                $checkboxes.show();
                $('#mxp-batch-mode').text('取消批次');
            } else {
                $bar.hide();
                $checkboxes.hide().prop('checked', false);
                $('#mxp-select-all').prop('checked', false);
                $('#mxp-batch-mode').text('批次操作');
            }
        },

        /**
         * Execute batch action
         */
        executeBatchAction: function() {
            var self = this;
            var action = $('#mxp-batch-action').val();
            var sids = [];

            $('.mxp-batch-checkbox:checked').each(function() {
                sids.push($(this).val());
            });

            if (!action) {
                MXP.Toast.warning('請選擇操作');
                return;
            }

            if (sids.length === 0) {
                MXP.Toast.warning('請選擇至少一個服務');
                return;
            }

            var actionLabels = {
                archive: '歸檔',
                restore: '還原',
                delete: '刪除'
            };

            MXP.Confirm.show(
                '批次' + (actionLabels[action] || '操作'),
                '確定要對選取的 ' + sids.length + ' 個服務執行此操作嗎？',
                function() {
                    $.ajax({
                        url: mxp_ajax.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'to_batch_action',
                            to_nonce: mxp_ajax.nonce,
                            batch_action: action,
                            sids: sids
                        },
                        success: function(response) {
                            if (response.success) {
                                MXP.Toast.success(response.data.message || '操作完成');
                                self.state.batchMode = false;
                                self.toggleBatchMode();
                                self.loadServices();
                                self.loadStats();
                                self.clearDetail();
                            } else {
                                MXP.Toast.error(response.data.message || '操作失敗');
                            }
                        },
                        error: function() {
                            MXP.Toast.error('操作失敗，請重試');
                        }
                    });
                }
            );
        },

        /**
         * Save category
         */
        saveCategory: function($form) {
            var self = this;

            $.ajax({
                url: mxp_ajax.ajax_url,
                type: 'POST',
                data: $form.serialize(),
                success: function(response) {
                    if (response.success) {
                        MXP.Toast.success('分類已儲存');
                        $form[0].reset();
                        self.loadCategories();
                    } else {
                        MXP.Toast.error(response.data.message || '儲存失敗');
                    }
                },
                error: function() {
                    MXP.Toast.error('儲存失敗，請重試');
                }
            });
        },

        /**
         * Save tag
         */
        saveTag: function($form) {
            var self = this;

            $.ajax({
                url: mxp_ajax.ajax_url,
                type: 'POST',
                data: $form.serialize(),
                success: function(response) {
                    if (response.success) {
                        MXP.Toast.success('標籤已儲存');
                        $form[0].reset();
                        self.loadTags();
                    } else {
                        MXP.Toast.error(response.data.message || '儲存失敗');
                    }
                },
                error: function() {
                    MXP.Toast.error('儲存失敗，請重試');
                }
            });
        },

        /**
         * Load categories for management modal
         */
        loadCategories: function() {
            var self = this;

            $.ajax({
                url: mxp_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'to_manage_categories',
                    to_nonce: mxp_ajax.nonce,
                    operation: 'list'
                },
                success: function(response) {
                    if (response.success && response.data.categories) {
                        var html = '';
                        response.data.categories.forEach(function(cat) {
                            html += '<div class="mxp-category-item" data-cid="' + cat.cid + '">' +
                                '<span class="dashicons ' + (cat.category_icon || 'dashicons-category') + '"></span>' +
                                '<span>' + cat.category_name + '</span>' +
                                '<button type="button" class="button button-small mxp-delete-category" data-cid="' + cat.cid + '">刪除</button>' +
                            '</div>';
                        });
                        $('#mxp-categories-list').html(html || '<p>尚無分類</p>');

                        // Update the category filter dropdown
                        var $select = $('#mxp-filter-category, #mxp-form-category');
                        $select.find('option:not(:first)').remove();
                        response.data.categories.forEach(function(cat) {
                            $select.append('<option value="' + cat.cid + '">' + cat.category_name + '</option>');
                        });
                    }
                }
            });
        },

        /**
         * Load tags for management modal
         */
        loadTags: function() {
            var self = this;

            $.ajax({
                url: mxp_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'to_manage_tags',
                    to_nonce: mxp_ajax.nonce,
                    operation: 'list'
                },
                success: function(response) {
                    if (response.success && response.data.tags) {
                        var html = '';
                        response.data.tags.forEach(function(tag) {
                            html += '<div class="mxp-tag-item" data-tid="' + tag.tid + '">' +
                                '<span class="mxp-tag-color" style="background-color: ' + (tag.tag_color || '#6c757d') + '"></span>' +
                                '<span>' + tag.tag_name + '</span>' +
                                '<button type="button" class="button button-small mxp-delete-tag" data-tid="' + tag.tid + '">刪除</button>' +
                            '</div>';
                        });
                        $('#mxp-tags-list').html(html || '<p>尚無標籤</p>');

                        // Update the tags filter dropdown
                        var $select = $('#mxp-filter-tags, #mxp-form-tags');
                        $select.find('option').remove();
                        response.data.tags.forEach(function(tag) {
                            $select.append('<option value="' + tag.tid + '">' + tag.tag_name + '</option>');
                        });
                        $select.trigger('change');
                    }
                }
            });
        },

        /**
         * Delete category
         */
        deleteCategory: function(cid) {
            var self = this;

            $.ajax({
                url: mxp_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'to_manage_categories',
                    to_nonce: mxp_ajax.nonce,
                    operation: 'delete',
                    cid: cid
                },
                success: function(response) {
                    if (response.success) {
                        MXP.Toast.success('分類已刪除');
                        self.loadCategories();
                    } else {
                        MXP.Toast.error(response.data.message || '刪除失敗');
                    }
                }
            });
        },

        /**
         * Delete tag
         */
        deleteTag: function(tid) {
            var self = this;

            $.ajax({
                url: mxp_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'to_manage_tags',
                    to_nonce: mxp_ajax.nonce,
                    operation: 'delete',
                    tid: tid
                },
                success: function(response) {
                    if (response.success) {
                        MXP.Toast.success('標籤已刪除');
                        self.loadTags();
                    } else {
                        MXP.Toast.error(response.data.message || '刪除失敗');
                    }
                }
            });
        }
    };

    // Initialize when DOM ready
    $(document).ready(function() {
        // Only initialize if on the password manager page
        if ($('.mxp-password-manager').length) {
            MXP.App.init();
        }
    });

})(jQuery);
