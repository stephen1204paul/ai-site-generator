<?php
/**
 * Admin Marketplace Page
 *
 * @package WP_AI_Site_Generator
 * @since 1.0.0
 */

// Security check
if ( ! defined( 'WPINC' ) ) {
    die;
}

// Get marketplace instance
$marketplace = new \WP_AI_Site_Generator\Includes\Template_Marketplace();

// Handle actions
$action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : '';
$tab    = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'browse';

// Get user capabilities
$can_submit   = current_user_can( 'edit_posts' );
$can_moderate = current_user_can( 'manage_options' );

// Enqueue marketplace scripts and styles
wp_enqueue_script( 'wp-ai-marketplace', plugin_dir_url( dirname( __FILE__ ) ) . 'js/marketplace-ui.js', array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n' ), '1.0.0', true );
wp_enqueue_style( 'wp-ai-marketplace', plugin_dir_url( dirname( __FILE__ ) ) . 'css/marketplace.css', array( 'wp-components' ), '1.0.0' );

// Localize script
wp_localize_script( 'wp-ai-marketplace', 'wpAiMarketplace', array(
    'apiUrl'     => rest_url( 'wp-ai-site-generator/v1/marketplace' ),
    'nonce'      => wp_create_nonce( 'wp_rest' ),
    'canSubmit'  => $can_submit,
    'canModerate'=> $can_moderate,
    'userId'     => get_current_user_id(),
    'siteUrl'    => site_url(),
    'i18n'       => array(
        'browse'      => __( 'Browse Templates', 'wp-ai-site-generator' ),
        'featured'    => __( 'Featured', 'wp-ai-site-generator' ),
        'trending'    => __( 'Trending', 'wp-ai-site-generator' ),
        'myTemplates' => __( 'My Templates', 'wp-ai-site-generator' ),
        'favorites'   => __( 'Favorites', 'wp-ai-site-generator' ),
        'submit'      => __( 'Submit Template', 'wp-ai-site-generator' ),
        'search'      => __( 'Search templates...', 'wp-ai-site-generator' ),
        'install'     => __( 'Install', 'wp-ai-site-generator' ),
        'preview'     => __( 'Preview', 'wp-ai-site-generator' ),
        'rate'        => __( 'Rate', 'wp-ai-site-generator' ),
        'review'      => __( 'Write Review', 'wp-ai-site-generator' ),
        'noTemplates' => __( 'No templates found', 'wp-ai-site-generator' ),
        'loading'     => __( 'Loading templates...', 'wp-ai-site-generator' ),
        'error'       => __( 'Error loading templates', 'wp-ai-site-generator' ),
    ),
) );
?>

<div class="wrap wp-ai-site-generator-marketplace">
    <h1 class="wp-heading-inline">
        <?php esc_html_e( 'Template Marketplace', 'wp-ai-site-generator' ); ?>
    </h1>

    <?php if ( $can_submit ) : ?>
        <a href="#" class="page-title-action" id="add-new-template">
            <?php esc_html_e( 'Submit New Template', 'wp-ai-site-generator' ); ?>
        </a>
    <?php endif; ?>

    <?php if ( $can_moderate ) : ?>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-ai-marketplace-moderation' ) ); ?>" class="page-title-action">
            <?php esc_html_e( 'Moderation Queue', 'wp-ai-site-generator' ); ?>
        </a>
    <?php endif; ?>

    <hr class="wp-header-end">

    <!-- Notices will be displayed here -->
    <div id="marketplace-notices"></div>

    <!-- Navigation Tabs -->
    <nav class="nav-tab-wrapper wp-clearfix" aria-label="<?php esc_attr_e( 'Secondary menu', 'wp-ai-site-generator' ); ?>">
        <a href="?page=wp-ai-marketplace&tab=browse" class="nav-tab <?php echo $tab === 'browse' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e( 'Browse', 'wp-ai-site-generator' ); ?>
        </a>
        <a href="?page=wp-ai-marketplace&tab=featured" class="nav-tab <?php echo $tab === 'featured' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e( 'Featured', 'wp-ai-site-generator' ); ?>
        </a>
        <a href="?page=wp-ai-marketplace&tab=trending" class="nav-tab <?php echo $tab === 'trending' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e( 'Trending', 'wp-ai-site-generator' ); ?>
        </a>
        <a href="?page=wp-ai-marketplace&tab=my-templates" class="nav-tab <?php echo $tab === 'my-templates' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e( 'My Templates', 'wp-ai-site-generator' ); ?>
        </a>
        <a href="?page=wp-ai-marketplace&tab=collections" class="nav-tab <?php echo $tab === 'collections' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e( 'Collections', 'wp-ai-site-generator' ); ?>
        </a>
    </nav>

    <div class="marketplace-content-wrapper">
        <?php
        switch ( $tab ) {
            case 'browse':
                include 'marketplace-browse.php';
                break;

            case 'featured':
                include 'marketplace-featured.php';
                break;

            case 'trending':
                include 'marketplace-trending.php';
                break;

            case 'my-templates':
                include 'marketplace-my-templates.php';
                break;

            case 'collections':
                include 'marketplace-collections.php';
                break;

            default:
                include 'marketplace-browse.php';
                break;
        }
        ?>
    </div>

    <!-- React App Mount Point -->
    <div id="wp-ai-marketplace-app"></div>

    <!-- Template Preview Modal -->
    <div id="template-preview-modal" class="marketplace-modal" style="display: none;">
        <div class="modal-content">
            <span class="modal-close">&times;</span>
            <div class="modal-header">
                <h2 id="preview-title"></h2>
            </div>
            <div class="modal-body">
                <div class="preview-screenshots"></div>
                <div class="preview-details">
                    <div class="detail-section">
                        <h3><?php esc_html_e( 'Description', 'wp-ai-site-generator' ); ?></h3>
                        <p id="preview-description"></p>
                    </div>
                    <div class="detail-section">
                        <h3><?php esc_html_e( 'Features', 'wp-ai-site-generator' ); ?></h3>
                        <ul id="preview-features"></ul>
                    </div>
                    <div class="detail-section">
                        <h3><?php esc_html_e( 'Details', 'wp-ai-site-generator' ); ?></h3>
                        <table class="preview-meta">
                            <tr>
                                <th><?php esc_html_e( 'Author', 'wp-ai-site-generator' ); ?></th>
                                <td id="preview-author"></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'Version', 'wp-ai-site-generator' ); ?></th>
                                <td id="preview-version"></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'License', 'wp-ai-site-generator' ); ?></th>
                                <td id="preview-license"></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'Downloads', 'wp-ai-site-generator' ); ?></th>
                                <td id="preview-downloads"></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'Rating', 'wp-ai-site-generator' ); ?></th>
                                <td id="preview-rating"></td>
                            </tr>
                        </table>
                    </div>
                </div>
                <div class="preview-reviews">
                    <h3><?php esc_html_e( 'Reviews', 'wp-ai-site-generator' ); ?></h3>
                    <div id="preview-reviews-list"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="button button-secondary modal-close-btn">
                    <?php esc_html_e( 'Close', 'wp-ai-site-generator' ); ?>
                </button>
                <button type="button" class="button button-primary" id="install-template-btn">
                    <?php esc_html_e( 'Install Template', 'wp-ai-site-generator' ); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Template Submission Modal -->
    <div id="template-submission-modal" class="marketplace-modal" style="display: none;">
        <div class="modal-content">
            <span class="modal-close">&times;</span>
            <div class="modal-header">
                <h2><?php esc_html_e( 'Submit New Template', 'wp-ai-site-generator' ); ?></h2>
            </div>
            <form id="template-submission-form" method="post" enctype="multipart/form-data">
                <?php wp_nonce_field( 'submit_template', 'template_nonce' ); ?>
                <div class="modal-body">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="template-title"><?php esc_html_e( 'Template Title', 'wp-ai-site-generator' ); ?> <span class="required">*</span></label>
                            </th>
                            <td>
                                <input type="text" id="template-title" name="title" class="regular-text" required />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="template-description"><?php esc_html_e( 'Description', 'wp-ai-site-generator' ); ?> <span class="required">*</span></label>
                            </th>
                            <td>
                                <textarea id="template-description" name="description" rows="4" cols="50" required></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="template-content"><?php esc_html_e( 'Template Content/Prompts', 'wp-ai-site-generator' ); ?> <span class="required">*</span></label>
                            </th>
                            <td>
                                <textarea id="template-content" name="content" rows="10" cols="50" required></textarea>
                                <p class="description"><?php esc_html_e( 'Enter your template configuration and prompts in JSON format', 'wp-ai-site-generator' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="template-category"><?php esc_html_e( 'Category', 'wp-ai-site-generator' ); ?></label>
                            </th>
                            <td>
                                <select id="template-category" name="category">
                                    <option value=""><?php esc_html_e( 'Select Category', 'wp-ai-site-generator' ); ?></option>
                                    <?php
                                    $categories = $marketplace->get_categories();
                                    foreach ( $categories as $category ) {
                                        echo '<option value="' . esc_attr( $category['slug'] ) . '">' . esc_html( $category['name'] ) . '</option>';
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="template-industry"><?php esc_html_e( 'Industry', 'wp-ai-site-generator' ); ?></label>
                            </th>
                            <td>
                                <select id="template-industry" name="industry">
                                    <option value=""><?php esc_html_e( 'Select Industry', 'wp-ai-site-generator' ); ?></option>
                                    <?php
                                    $industries = $marketplace->get_industries();
                                    foreach ( $industries as $industry ) {
                                        echo '<option value="' . esc_attr( $industry['slug'] ) . '">' . esc_html( $industry['name'] ) . '</option>';
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="template-tags"><?php esc_html_e( 'Tags', 'wp-ai-site-generator' ); ?></label>
                            </th>
                            <td>
                                <input type="text" id="template-tags" name="tags" class="regular-text" />
                                <p class="description"><?php esc_html_e( 'Comma-separated tags', 'wp-ai-site-generator' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="template-license"><?php esc_html_e( 'License', 'wp-ai-site-generator' ); ?></label>
                            </th>
                            <td>
                                <select id="template-license" name="license">
                                    <option value="free"><?php esc_html_e( 'Free', 'wp-ai-site-generator' ); ?></option>
                                    <option value="gpl"><?php esc_html_e( 'GPL', 'wp-ai-site-generator' ); ?></option>
                                    <option value="mit"><?php esc_html_e( 'MIT', 'wp-ai-site-generator' ); ?></option>
                                    <option value="premium"><?php esc_html_e( 'Premium', 'wp-ai-site-generator' ); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="template-visibility"><?php esc_html_e( 'Visibility', 'wp-ai-site-generator' ); ?></label>
                            </th>
                            <td>
                                <select id="template-visibility" name="visibility">
                                    <option value="public"><?php esc_html_e( 'Public', 'wp-ai-site-generator' ); ?></option>
                                    <option value="private"><?php esc_html_e( 'Private', 'wp-ai-site-generator' ); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="template-demo-url"><?php esc_html_e( 'Demo URL', 'wp-ai-site-generator' ); ?></label>
                            </th>
                            <td>
                                <input type="url" id="template-demo-url" name="demo_url" class="regular-text" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="template-screenshots"><?php esc_html_e( 'Screenshots', 'wp-ai-site-generator' ); ?></label>
                            </th>
                            <td>
                                <input type="file" id="template-screenshots" name="screenshots[]" accept="image/*" multiple />
                                <p class="description"><?php esc_html_e( 'Upload screenshots of your template (max 5)', 'wp-ai-site-generator' ); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                <div class="modal-footer">
                    <button type="button" class="button button-secondary modal-close-btn">
                        <?php esc_html_e( 'Cancel', 'wp-ai-site-generator' ); ?>
                    </button>
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e( 'Submit Template', 'wp-ai-site-generator' ); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Template Comparison Tool -->
    <div id="template-comparison-modal" class="marketplace-modal" style="display: none;">
        <div class="modal-content modal-wide">
            <span class="modal-close">&times;</span>
            <div class="modal-header">
                <h2><?php esc_html_e( 'Compare Templates', 'wp-ai-site-generator' ); ?></h2>
            </div>
            <div class="modal-body">
                <div class="comparison-grid" id="comparison-grid">
                    <!-- Comparison items will be added dynamically -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="button button-secondary" id="clear-comparison">
                    <?php esc_html_e( 'Clear All', 'wp-ai-site-generator' ); ?>
                </button>
                <button type="button" class="button button-secondary modal-close-btn">
                    <?php esc_html_e( 'Close', 'wp-ai-site-generator' ); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Inline JavaScript for basic functionality -->
<script type="text/javascript">
jQuery(document).ready(function($) {
    // Modal handling
    $('.modal-close, .modal-close-btn').on('click', function() {
        $(this).closest('.marketplace-modal').hide();
    });

    $('#add-new-template').on('click', function(e) {
        e.preventDefault();
        $('#template-submission-modal').show();
    });

    // Template submission form
    $('#template-submission-form').on('submit', function(e) {
        e.preventDefault();

        var formData = new FormData(this);

        $.ajax({
            url: wpAiMarketplace.apiUrl + '/templates',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: {
                'X-WP-Nonce': wpAiMarketplace.nonce
            },
            success: function(response) {
                $('#template-submission-modal').hide();
                $('#template-submission-form')[0].reset();

                // Show success notice
                var notice = $('<div class="notice notice-success is-dismissible"><p>' +
                    response.message + '</p></div>');
                $('#marketplace-notices').append(notice);

                // Refresh the page after 2 seconds
                setTimeout(function() {
                    window.location.reload();
                }, 2000);
            },
            error: function(xhr) {
                var response = xhr.responseJSON;
                var message = response && response.message ?
                    response.message :
                    '<?php esc_html_e( 'An error occurred while submitting the template.', 'wp-ai-site-generator' ); ?>';

                var notice = $('<div class="notice notice-error is-dismissible"><p>' +
                    message + '</p></div>');
                $('#marketplace-notices').append(notice);
            }
        });
    });

    // Template preview
    window.previewTemplate = function(templateId) {
        $.ajax({
            url: wpAiMarketplace.apiUrl + '/templates/' + templateId,
            method: 'GET',
            headers: {
                'X-WP-Nonce': wpAiMarketplace.nonce
            },
            success: function(template) {
                $('#preview-title').text(template.title);
                $('#preview-description').text(template.description);
                $('#preview-author').text(template.author.name);
                $('#preview-version').text(template.version);
                $('#preview-license').text(template.license);
                $('#preview-downloads').text(template.downloads);
                $('#preview-rating').html(template.rating.toFixed(1) + ' / 5.0 (' +
                    template.rating_count + ' reviews)');

                // Show screenshots
                if (template.screenshots && template.screenshots.length > 0) {
                    var screenshotsHtml = '';
                    template.screenshots.forEach(function(screenshot) {
                        screenshotsHtml += '<img src="' + screenshot + '" alt="" />';
                    });
                    $('.preview-screenshots').html(screenshotsHtml);
                }

                // Set install button data
                $('#install-template-btn').data('template-id', templateId);

                $('#template-preview-modal').show();
            },
            error: function() {
                alert('<?php esc_html_e( 'Failed to load template preview.', 'wp-ai-site-generator' ); ?>');
            }
        });
    };

    // Install template
    $('#install-template-btn').on('click', function() {
        var templateId = $(this).data('template-id');
        var $button = $(this);

        $button.prop('disabled', true).text('<?php esc_html_e( 'Installing...', 'wp-ai-site-generator' ); ?>');

        $.ajax({
            url: wpAiMarketplace.apiUrl + '/templates/' + templateId + '/install',
            method: 'POST',
            headers: {
                'X-WP-Nonce': wpAiMarketplace.nonce
            },
            success: function(response) {
                $('#template-preview-modal').hide();

                var notice = $('<div class="notice notice-success is-dismissible"><p>' +
                    response.message + '</p></div>');
                $('#marketplace-notices').append(notice);

                // Update UI to show template as installed
                $('[data-template-id="' + templateId + '"]')
                    .find('.install-btn')
                    .text('<?php esc_html_e( 'Installed', 'wp-ai-site-generator' ); ?>')
                    .prop('disabled', true);
            },
            error: function(xhr) {
                var response = xhr.responseJSON;
                var message = response && response.message ?
                    response.message :
                    '<?php esc_html_e( 'Failed to install template.', 'wp-ai-site-generator' ); ?>';

                alert(message);
            },
            complete: function() {
                $button.prop('disabled', false).text('<?php esc_html_e( 'Install Template', 'wp-ai-site-generator' ); ?>');
            }
        });
    });

    // Template comparison
    var comparisonTemplates = [];

    window.addToComparison = function(templateId) {
        if (comparisonTemplates.length >= 3) {
            alert('<?php esc_html_e( 'You can compare up to 3 templates at a time.', 'wp-ai-site-generator' ); ?>');
            return;
        }

        if (comparisonTemplates.indexOf(templateId) === -1) {
            comparisonTemplates.push(templateId);
            updateComparisonButton();
        }
    };

    window.showComparison = function() {
        if (comparisonTemplates.length < 2) {
            alert('<?php esc_html_e( 'Please select at least 2 templates to compare.', 'wp-ai-site-generator' ); ?>');
            return;
        }

        // Load and display comparison
        $('#comparison-grid').empty();

        comparisonTemplates.forEach(function(templateId) {
            $.ajax({
                url: wpAiMarketplace.apiUrl + '/templates/' + templateId,
                method: 'GET',
                headers: {
                    'X-WP-Nonce': wpAiMarketplace.nonce
                },
                success: function(template) {
                    var comparisonItem = $('<div class="comparison-item">' +
                        '<h3>' + template.title + '</h3>' +
                        '<dl>' +
                        '<dt><?php esc_html_e( 'Author', 'wp-ai-site-generator' ); ?></dt>' +
                        '<dd>' + template.author.name + '</dd>' +
                        '<dt><?php esc_html_e( 'Rating', 'wp-ai-site-generator' ); ?></dt>' +
                        '<dd>' + template.rating.toFixed(1) + ' / 5.0</dd>' +
                        '<dt><?php esc_html_e( 'Downloads', 'wp-ai-site-generator' ); ?></dt>' +
                        '<dd>' + template.downloads + '</dd>' +
                        '<dt><?php esc_html_e( 'License', 'wp-ai-site-generator' ); ?></dt>' +
                        '<dd>' + template.license + '</dd>' +
                        '</dl>' +
                        '<button class="button button-primary install-comparison-btn" data-template-id="' +
                        template.id + '"><?php esc_html_e( 'Install', 'wp-ai-site-generator' ); ?></button>' +
                        '</div>');

                    $('#comparison-grid').append(comparisonItem);
                }
            });
        });

        $('#template-comparison-modal').show();
    };

    $('#clear-comparison').on('click', function() {
        comparisonTemplates = [];
        $('#comparison-grid').empty();
        updateComparisonButton();
    });

    function updateComparisonButton() {
        var count = comparisonTemplates.length;
        if (count > 0) {
            $('#compare-templates-btn').text('<?php esc_html_e( 'Compare', 'wp-ai-site-generator' ); ?> (' + count + ')').show();
        } else {
            $('#compare-templates-btn').hide();
        }
    }

    // Initialize React app if container exists
    if (document.getElementById('wp-ai-marketplace-app')) {
        // React app initialization will be handled by marketplace-ui.jsx
    }
});
</script>

<style type="text/css">
/* Basic marketplace styles */
.wp-ai-site-generator-marketplace {
    max-width: 1200px;
}

.marketplace-content-wrapper {
    margin-top: 20px;
}

.marketplace-modal {
    position: fixed;
    z-index: 100000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    overflow: auto;
}

.modal-content {
    background-color: #fefefe;
    margin: 5% auto;
    padding: 0;
    border: 1px solid #888;
    width: 80%;
    max-width: 800px;
    border-radius: 4px;
}

.modal-content.modal-wide {
    max-width: 1200px;
}

.modal-header,
.modal-footer {
    padding: 15px 20px;
    background: #f1f1f1;
}

.modal-header {
    border-bottom: 1px solid #ddd;
}

.modal-footer {
    border-top: 1px solid #ddd;
    text-align: right;
}

.modal-body {
    padding: 20px;
    max-height: 60vh;
    overflow-y: auto;
}

.modal-close {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.modal-close:hover,
.modal-close:focus {
    color: #000;
}

.comparison-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.comparison-item {
    border: 1px solid #ddd;
    padding: 15px;
    border-radius: 4px;
}

.comparison-item h3 {
    margin-top: 0;
}

.comparison-item dl {
    margin: 10px 0;
}

.comparison-item dt {
    font-weight: bold;
    margin-top: 10px;
}

.comparison-item dd {
    margin-left: 0;
    margin-bottom: 10px;
}

.preview-screenshots {
    display: flex;
    gap: 10px;
    overflow-x: auto;
    margin-bottom: 20px;
}

.preview-screenshots img {
    max-width: 200px;
    height: auto;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.preview-meta {
    width: 100%;
    border-collapse: collapse;
}

.preview-meta th {
    text-align: left;
    padding: 5px 10px;
    background: #f5f5f5;
    font-weight: normal;
    width: 30%;
}

.preview-meta td {
    padding: 5px 10px;
}

.detail-section {
    margin-bottom: 20px;
}

.detail-section h3 {
    margin-bottom: 10px;
    color: #23282d;
}

.required {
    color: #dc3232;
}

#marketplace-notices {
    margin-top: 10px;
}

#marketplace-notices .notice {
    margin-bottom: 10px;
}
</style>