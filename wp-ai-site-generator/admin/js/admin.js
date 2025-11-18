/**
 * Admin JavaScript for WP AI Site Generator
 *
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/admin
 * @since      1.0.0
 */

(function($) {
    'use strict';

    /**
     * Main admin object
     */
    const WPAISiteGenerator = {

        /**
         * Initialize the admin interface
         */
        init: function() {
            this.bindEvents();
            this.initializeComponents();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Generation form submit
            $(document).on('submit', '#waisg-generation-form', this.handleGenerationSubmit);

            // Cancel generation
            $(document).on('click', '.waisg-cancel-generation', this.handleCancelGeneration);

            // Provider settings toggle
            $(document).on('change', '.waisg-provider-toggle', this.handleProviderToggle);

            // Tab navigation
            $(document).on('click', '.waisg-tab-link', this.handleTabClick);

            // Template selection
            $(document).on('click', '.waisg-template-select', this.handleTemplateSelect);

            // Settings save
            $(document).on('submit', '#waisg-settings-form', this.handleSettingsSave);

            // Copy to clipboard
            $(document).on('click', '.waisg-copy-btn', this.handleCopyToClipboard);
        },

        /**
         * Initialize UI components
         */
        initializeComponents: function() {
            // Initialize Select2
            if ($.fn.select2) {
                $('.waisg-select2').select2({
                    width: '100%'
                });
            }

            // Initialize color picker
            if ($.fn.wpColorPicker) {
                $('.waisg-color-picker').wpColorPicker();
            }

            // Initialize tooltips
            this.initTooltips();

            // Check for active generation
            this.checkActiveGeneration();
        },

        /**
         * Handle generation form submission
         */
        handleGenerationSubmit: function(e) {
            e.preventDefault();

            const $form = $(this);
            const $submitBtn = $form.find('button[type="submit"]');
            const originalText = $submitBtn.text();

            // Get form data
            const formData = new FormData(this);
            formData.append('action', 'waisg_generate_site');
            formData.append('nonce', waisg_admin.nonce);

            // Disable form and show loading
            $submitBtn.prop('disabled', true).text(waisg_admin.strings.generating);
            WPAISiteGenerator.showProgress();

            // Send AJAX request
            $.ajax({
                url: waisg_admin.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        // Start monitoring generation progress
                        WPAISiteGenerator.monitorGeneration(response.data.generation_id);
                        WPAISiteGenerator.showNotice('success', response.data.message);
                    } else {
                        WPAISiteGenerator.showNotice('error', response.data);
                        $submitBtn.prop('disabled', false).text(originalText);
                        WPAISiteGenerator.hideProgress();
                    }
                },
                error: function(xhr, status, error) {
                    WPAISiteGenerator.showNotice('error', waisg_admin.strings.error);
                    $submitBtn.prop('disabled', false).text(originalText);
                    WPAISiteGenerator.hideProgress();
                }
            });
        },

        /**
         * Monitor generation progress
         */
        monitorGeneration: function(generationId) {
            const intervalId = setInterval(function() {
                $.ajax({
                    url: waisg_admin.ajax_url,
                    type: 'GET',
                    data: {
                        action: 'waisg_get_generation_status',
                        generation_id: generationId,
                        nonce: waisg_admin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            const generation = response.data;

                            // Update progress bar
                            WPAISiteGenerator.updateProgress(generation.progress, generation.status);

                            // Check if completed
                            if (generation.status === 'completed') {
                                clearInterval(intervalId);
                                WPAISiteGenerator.handleGenerationComplete(generation);
                            } else if (generation.status === 'failed' || generation.status === 'cancelled') {
                                clearInterval(intervalId);
                                WPAISiteGenerator.handleGenerationFailed(generation);
                            }
                        }
                    },
                    error: function() {
                        clearInterval(intervalId);
                        WPAISiteGenerator.showNotice('error', 'Failed to get generation status');
                    }
                });
            }, 2000); // Poll every 2 seconds

            // Store interval ID for cancellation
            $('#waisg-generation-form').data('interval-id', intervalId);
        },

        /**
         * Handle generation completion
         */
        handleGenerationComplete: function(generation) {
            this.hideProgress();
            this.showNotice('success', waisg_admin.strings.generation_complete);

            // Reset form
            $('#waisg-generation-form')[0].reset();
            $('#waisg-generation-form button[type="submit"]')
                .prop('disabled', false)
                .text('Generate Site');

            // Redirect to result page if configured
            if (generation.result && generation.result.redirect_url) {
                setTimeout(function() {
                    window.location.href = generation.result.redirect_url;
                }, 2000);
            }
        },

        /**
         * Handle generation failure
         */
        handleGenerationFailed: function(generation) {
            this.hideProgress();

            const message = generation.error_message || 'Generation failed';
            this.showNotice('error', message);

            $('#waisg-generation-form button[type="submit"]')
                .prop('disabled', false)
                .text('Generate Site');
        },

        /**
         * Handle generation cancellation
         */
        handleCancelGeneration: function(e) {
            e.preventDefault();

            if (!confirm(waisg_admin.strings.confirm_cancel)) {
                return;
            }

            const generationId = $(this).data('generation-id');
            const intervalId = $('#waisg-generation-form').data('interval-id');

            // Clear monitoring interval
            if (intervalId) {
                clearInterval(intervalId);
            }

            $.ajax({
                url: waisg_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'waisg_cancel_generation',
                    generation_id: generationId,
                    nonce: waisg_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        WPAISiteGenerator.showNotice('info', response.data);
                        WPAISiteGenerator.hideProgress();
                    }
                }
            });
        },

        /**
         * Handle provider toggle
         */
        handleProviderToggle: function() {
            const $toggle = $(this);
            const provider = $toggle.data('provider');
            const enabled = $toggle.is(':checked');
            const $settings = $toggle.closest('.waisg-provider-card').find('.waisg-provider-settings');

            if (enabled) {
                $settings.slideDown();
            } else {
                $settings.slideUp();
            }
        },

        /**
         * Handle tab navigation
         */
        handleTabClick: function(e) {
            e.preventDefault();

            const $tab = $(this);
            const target = $tab.attr('href');

            // Update active tab
            $('.waisg-tab-link').removeClass('active');
            $tab.addClass('active');

            // Show target content
            $('.waisg-tab-content').hide();
            $(target).show();
        },

        /**
         * Handle template selection
         */
        handleTemplateSelect: function(e) {
            e.preventDefault();

            const $btn = $(this);
            const templateId = $btn.data('template-id');

            // Load template data
            $.ajax({
                url: waisg_admin.rest_url + 'templates/' + templateId,
                type: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', waisg_admin.rest_nonce);
                },
                success: function(template) {
                    // Populate form with template data
                    if (template.prompt_template) {
                        $('#waisg-prompt').val(template.prompt_template);
                    }
                    if (template.configuration) {
                        // Apply configuration settings
                        WPAISiteGenerator.applyTemplateConfiguration(template.configuration);
                    }
                    WPAISiteGenerator.showNotice('success', 'Template loaded');
                }
            });
        },

        /**
         * Apply template configuration
         */
        applyTemplateConfiguration: function(config) {
            if (typeof config === 'string') {
                config = JSON.parse(config);
            }

            // Apply configuration to form fields
            $.each(config, function(key, value) {
                const $field = $('[name="options[' + key + ']"]');
                if ($field.length) {
                    if ($field.is(':checkbox')) {
                        $field.prop('checked', value);
                    } else {
                        $field.val(value);
                    }
                }
            });
        },

        /**
         * Handle settings save
         */
        handleSettingsSave: function(e) {
            e.preventDefault();

            const $form = $(this);
            const $submitBtn = $form.find('button[type="submit"]');
            const originalText = $submitBtn.text();

            $submitBtn.prop('disabled', true).text(waisg_admin.strings.saving);

            $.ajax({
                url: $form.attr('action'),
                type: 'POST',
                data: $form.serialize(),
                success: function(response) {
                    WPAISiteGenerator.showNotice('success', waisg_admin.strings.settings_saved);
                    $submitBtn.prop('disabled', false).text(originalText);
                },
                error: function() {
                    WPAISiteGenerator.showNotice('error', waisg_admin.strings.error);
                    $submitBtn.prop('disabled', false).text(originalText);
                }
            });
        },

        /**
         * Handle copy to clipboard
         */
        handleCopyToClipboard: function(e) {
            e.preventDefault();

            const $btn = $(this);
            const text = $btn.data('copy-text');

            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(function() {
                    WPAISiteGenerator.showNotice('success', 'Copied to clipboard');
                });
            } else {
                // Fallback for older browsers
                const $temp = $('<textarea>');
                $('body').append($temp);
                $temp.val(text).select();
                document.execCommand('copy');
                $temp.remove();
                WPAISiteGenerator.showNotice('success', 'Copied to clipboard');
            }
        },

        /**
         * Check for active generation on page load
         */
        checkActiveGeneration: function() {
            const activeGenerationId = $('#waisg-active-generation').data('generation-id');
            if (activeGenerationId) {
                this.monitorGeneration(activeGenerationId);
            }
        },

        /**
         * Show progress bar
         */
        showProgress: function() {
            const $progressBar = $('#waisg-progress-container');
            if ($progressBar.length === 0) {
                const progressHtml = `
                    <div id="waisg-progress-container" class="waisg-progress-container">
                        <div class="waisg-progress-bar">
                            <div class="waisg-progress-fill" style="width: 0%">0%</div>
                        </div>
                        <div class="waisg-progress-message"></div>
                        <button class="waisg-btn waisg-btn-danger waisg-cancel-generation">Cancel</button>
                    </div>
                `;
                $('#waisg-generation-form').after(progressHtml);
            }
            $progressBar.show();
        },

        /**
         * Hide progress bar
         */
        hideProgress: function() {
            $('#waisg-progress-container').hide();
        },

        /**
         * Update progress bar
         */
        updateProgress: function(progress, status) {
            const $progressFill = $('.waisg-progress-fill');
            const $progressMessage = $('.waisg-progress-message');

            $progressFill.css('width', progress + '%').text(progress + '%');

            // Update status message
            let message = '';
            switch (status) {
                case 'analyzing':
                    message = waisg_admin.strings.analyzing;
                    break;
                case 'generating':
                    message = waisg_admin.strings.generating_content;
                    break;
                case 'optimizing':
                    message = waisg_admin.strings.optimizing;
                    break;
                case 'finalizing':
                    message = waisg_admin.strings.finalizing;
                    break;
                default:
                    message = waisg_admin.strings.processing;
            }
            $progressMessage.text(message);
        },

        /**
         * Show admin notice
         */
        showNotice: function(type, message) {
            const $notice = $(`
                <div class="notice notice-${type} is-dismissible">
                    <p>${message}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">Dismiss this notice.</span>
                    </button>
                </div>
            `);

            $('.wrap h1').after($notice);

            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);

            // Handle manual dismiss
            $notice.on('click', '.notice-dismiss', function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            });
        },

        /**
         * Initialize tooltips
         */
        initTooltips: function() {
            $('.waisg-tooltip').each(function() {
                const $el = $(this);
                const content = $el.data('tooltip');

                $el.attr('title', content);
            });
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        WPAISiteGenerator.init();
    });

})(jQuery);