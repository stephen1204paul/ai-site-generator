<?php
/**
 * Templates library view
 *
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/admin/partials
 * @since      1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get templates from database
global $wpdb;
$table_name = $wpdb->prefix . 'waisg_templates';

// Categories
$categories = array(
	'business' => __( 'Business', 'wp-ai-site-generator' ),
	'ecommerce' => __( 'E-commerce', 'wp-ai-site-generator' ),
	'portfolio' => __( 'Portfolio', 'wp-ai-site-generator' ),
	'blog' => __( 'Blog', 'wp-ai-site-generator' ),
	'landing' => __( 'Landing Page', 'wp-ai-site-generator' ),
	'nonprofit' => __( 'Non-profit', 'wp-ai-site-generator' ),
	'education' => __( 'Education', 'wp-ai-site-generator' ),
	'restaurant' => __( 'Restaurant', 'wp-ai-site-generator' ),
	'health' => __( 'Health & Medical', 'wp-ai-site-generator' ),
	'custom' => __( 'Custom', 'wp-ai-site-generator' ),
);

// Get selected category
$selected_category = isset( $_GET['category'] ) ? sanitize_text_field( $_GET['category'] ) : '';

// Build query
$where_clause = '';
if ( $selected_category && $selected_category !== 'all' ) {
	$where_clause = $wpdb->prepare( ' WHERE category = %s', $selected_category );
}

$templates = $wpdb->get_results( "SELECT * FROM $table_name $where_clause ORDER BY is_featured DESC, created_at DESC" );
?>

<div class="wrap waisg-templates-page">
	<h1 class="wp-heading-inline"><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<a href="#" class="page-title-action" id="create-template">
		<?php esc_html_e( 'Create Template', 'wp-ai-site-generator' ); ?>
	</a>

	<a href="#" class="page-title-action" id="import-template">
		<?php esc_html_e( 'Import Template', 'wp-ai-site-generator' ); ?>
	</a>

	<hr class="wp-header-end">

	<!-- Template Categories -->
	<div class="waisg-template-filters">
		<ul class="subsubsub">
			<li>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-ai-site-generator-templates' ) ); ?>"
				   class="<?php echo empty( $selected_category ) ? 'current' : ''; ?>">
					<?php esc_html_e( 'All', 'wp-ai-site-generator' ); ?>
					<span class="count">(<?php echo count( $templates ); ?>)</span>
				</a> |
			</li>
			<?php foreach ( $categories as $key => $label ) : ?>
				<?php
				$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table_name WHERE category = %s", $key ) );
				?>
				<li>
					<a href="<?php echo esc_url( add_query_arg( 'category', $key, admin_url( 'admin.php?page=wp-ai-site-generator-templates' ) ) ); ?>"
					   class="<?php echo $selected_category === $key ? 'current' : ''; ?>">
						<?php echo esc_html( $label ); ?>
						<span class="count">(<?php echo esc_html( $count ); ?>)</span>
					</a>
					<?php echo $key !== 'custom' ? ' |' : ''; ?>
				</li>
			<?php endforeach; ?>
		</ul>

		<div class="waisg-template-search">
			<input type="search"
				   id="template-search"
				   placeholder="<?php esc_attr_e( 'Search templates...', 'wp-ai-site-generator' ); ?>"
				   class="regular-text" />
		</div>
	</div>

	<!-- Templates Grid -->
	<div class="waisg-templates-grid">
		<?php if ( $templates ) : ?>
			<?php foreach ( $templates as $template ) : ?>
				<?php
				$metadata = json_decode( $template->metadata, true );
				$preview_url = isset( $metadata['preview_url'] ) ? $metadata['preview_url'] : '';
				$thumbnail = isset( $metadata['thumbnail'] ) ? $metadata['thumbnail'] : WAISG_PLUGIN_URL . 'admin/images/template-placeholder.jpg';
				?>
				<div class="waisg-template-card" data-template-id="<?php echo esc_attr( $template->id ); ?>">
					<?php if ( $template->is_featured ) : ?>
						<div class="waisg-template-badge featured">
							<span class="dashicons dashicons-star-filled"></span>
							<?php esc_html_e( 'Featured', 'wp-ai-site-generator' ); ?>
						</div>
					<?php endif; ?>

					<?php if ( $template->is_premium ) : ?>
						<div class="waisg-template-badge premium">
							<span class="dashicons dashicons-awards"></span>
							<?php esc_html_e( 'Premium', 'wp-ai-site-generator' ); ?>
						</div>
					<?php endif; ?>

					<div class="waisg-template-thumbnail">
						<img src="<?php echo esc_url( $thumbnail ); ?>"
							 alt="<?php echo esc_attr( $template->name ); ?>"
							 onerror="this.src='<?php echo esc_url( WAISG_PLUGIN_URL . 'admin/images/template-placeholder.jpg' ); ?>'" />

						<div class="waisg-template-overlay">
							<div class="waisg-template-actions">
								<?php if ( $preview_url ) : ?>
									<a href="<?php echo esc_url( $preview_url ); ?>"
									   target="_blank"
									   class="button button-secondary"
									   title="<?php esc_attr_e( 'Preview', 'wp-ai-site-generator' ); ?>">
										<span class="dashicons dashicons-visibility"></span>
										<?php esc_html_e( 'Preview', 'wp-ai-site-generator' ); ?>
									</a>
								<?php endif; ?>

								<button type="button"
										class="button button-primary use-template"
										data-template-id="<?php echo esc_attr( $template->id ); ?>">
									<span class="dashicons dashicons-admin-appearance"></span>
									<?php esc_html_e( 'Use Template', 'wp-ai-site-generator' ); ?>
								</button>
							</div>
						</div>
					</div>

					<div class="waisg-template-info">
						<h3><?php echo esc_html( $template->name ); ?></h3>

						<p class="waisg-template-description">
							<?php echo esc_html( wp_trim_words( $template->description, 15 ) ); ?>
						</p>

						<div class="waisg-template-meta">
							<span class="waisg-template-category">
								<span class="dashicons dashicons-category"></span>
								<?php echo esc_html( $categories[ $template->category ] ?? $template->category ); ?>
							</span>

							<?php if ( isset( $metadata['pages_count'] ) ) : ?>
								<span class="waisg-template-pages">
									<span class="dashicons dashicons-admin-page"></span>
									<?php
									printf(
										/* translators: %d: Number of pages */
										_n( '%d Page', '%d Pages', $metadata['pages_count'], 'wp-ai-site-generator' ),
										$metadata['pages_count']
									);
									?>
								</span>
							<?php endif; ?>
						</div>

						<div class="waisg-template-footer">
							<div class="waisg-template-actions-bottom">
								<button type="button"
										class="button-link edit-template"
										data-template-id="<?php echo esc_attr( $template->id ); ?>">
									<span class="dashicons dashicons-edit"></span>
									<?php esc_html_e( 'Edit', 'wp-ai-site-generator' ); ?>
								</button>

								<button type="button"
										class="button-link duplicate-template"
										data-template-id="<?php echo esc_attr( $template->id ); ?>">
									<span class="dashicons dashicons-admin-page"></span>
									<?php esc_html_e( 'Duplicate', 'wp-ai-site-generator' ); ?>
								</button>

								<button type="button"
										class="button-link export-template"
										data-template-id="<?php echo esc_attr( $template->id ); ?>">
									<span class="dashicons dashicons-download"></span>
									<?php esc_html_e( 'Export', 'wp-ai-site-generator' ); ?>
								</button>

								<?php if ( ! $template->is_default ) : ?>
									<button type="button"
											class="button-link delete-template"
											data-template-id="<?php echo esc_attr( $template->id ); ?>">
										<span class="dashicons dashicons-trash"></span>
										<?php esc_html_e( 'Delete', 'wp-ai-site-generator' ); ?>
									</button>
								<?php endif; ?>
							</div>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		<?php else : ?>
			<div class="waisg-empty-state">
				<span class="dashicons dashicons-layout"></span>
				<h3><?php esc_html_e( 'No templates found', 'wp-ai-site-generator' ); ?></h3>
				<p><?php esc_html_e( 'Create your first template or import existing ones.', 'wp-ai-site-generator' ); ?></p>
				<button type="button" class="button button-primary" id="create-first-template">
					<?php esc_html_e( 'Create Your First Template', 'wp-ai-site-generator' ); ?>
				</button>
			</div>
		<?php endif; ?>
	</div>
</div>

<!-- Create/Edit Template Modal -->
<div id="template-modal" class="waisg-modal" style="display: none;">
	<div class="waisg-modal-content large">
		<div class="waisg-modal-header">
			<h2 id="template-modal-title"><?php esc_html_e( 'Create Template', 'wp-ai-site-generator' ); ?></h2>
			<button type="button" class="waisg-modal-close">&times;</button>
		</div>
		<div class="waisg-modal-body">
			<form id="template-form">
				<input type="hidden" id="template-id" value="" />

				<div class="waisg-form-row">
					<label for="template-name">
						<?php esc_html_e( 'Template Name', 'wp-ai-site-generator' ); ?>
						<span class="required">*</span>
					</label>
					<input type="text"
						   id="template-name"
						   class="regular-text"
						   required />
				</div>

				<div class="waisg-form-row">
					<label for="template-category">
						<?php esc_html_e( 'Category', 'wp-ai-site-generator' ); ?>
						<span class="required">*</span>
					</label>
					<select id="template-category" required>
						<option value=""><?php esc_html_e( 'Select Category', 'wp-ai-site-generator' ); ?></option>
						<?php foreach ( $categories as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>">
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="waisg-form-row">
					<label for="template-description">
						<?php esc_html_e( 'Description', 'wp-ai-site-generator' ); ?>
					</label>
					<textarea id="template-description"
							  rows="3"
							  class="large-text"></textarea>
				</div>

				<div class="waisg-form-row">
					<label for="template-structure">
						<?php esc_html_e( 'Page Structure', 'wp-ai-site-generator' ); ?>
						<span class="required">*</span>
					</label>
					<div id="template-structure-editor">
						<div class="waisg-page-structure">
							<div class="waisg-page-item">
								<input type="text"
									   placeholder="<?php esc_attr_e( 'Home', 'wp-ai-site-generator' ); ?>"
									   value="Home"
									   class="page-title" />
								<textarea placeholder="<?php esc_attr_e( 'Page description...', 'wp-ai-site-generator' ); ?>"
										  class="page-description"></textarea>
								<button type="button" class="button-link remove-page">
									<span class="dashicons dashicons-trash"></span>
								</button>
							</div>
						</div>
						<button type="button" class="button button-secondary" id="add-page">
							<span class="dashicons dashicons-plus-alt2"></span>
							<?php esc_html_e( 'Add Page', 'wp-ai-site-generator' ); ?>
						</button>
					</div>
				</div>

				<div class="waisg-form-row">
					<label for="template-settings">
						<?php esc_html_e( 'Default Settings', 'wp-ai-site-generator' ); ?>
					</label>
					<div class="waisg-template-settings">
						<label>
							<input type="checkbox" id="template-include-images" checked />
							<?php esc_html_e( 'Include Images', 'wp-ai-site-generator' ); ?>
						</label>
						<label>
							<input type="checkbox" id="template-include-seo" checked />
							<?php esc_html_e( 'Include SEO Metadata', 'wp-ai-site-generator' ); ?>
						</label>
						<label>
							<input type="checkbox" id="template-include-menu" checked />
							<?php esc_html_e( 'Create Navigation Menu', 'wp-ai-site-generator' ); ?>
						</label>
					</div>
				</div>

				<div class="waisg-form-row">
					<label for="template-thumbnail">
						<?php esc_html_e( 'Thumbnail', 'wp-ai-site-generator' ); ?>
					</label>
					<div class="waisg-thumbnail-upload">
						<input type="hidden" id="template-thumbnail-url" />
						<div id="thumbnail-preview" class="thumbnail-preview"></div>
						<button type="button" class="button" id="upload-thumbnail">
							<?php esc_html_e( 'Upload Thumbnail', 'wp-ai-site-generator' ); ?>
						</button>
						<button type="button" class="button-link" id="remove-thumbnail" style="display: none;">
							<?php esc_html_e( 'Remove', 'wp-ai-site-generator' ); ?>
						</button>
					</div>
				</div>

				<div class="waisg-form-row">
					<label>
						<input type="checkbox" id="template-is-featured" />
						<?php esc_html_e( 'Mark as Featured', 'wp-ai-site-generator' ); ?>
					</label>
				</div>
			</form>
		</div>
		<div class="waisg-modal-footer">
			<button type="button" class="button button-secondary waisg-modal-close">
				<?php esc_html_e( 'Cancel', 'wp-ai-site-generator' ); ?>
			</button>
			<button type="button" class="button button-primary" id="save-template">
				<?php esc_html_e( 'Save Template', 'wp-ai-site-generator' ); ?>
			</button>
		</div>
	</div>
</div>

<!-- Import Template Modal -->
<div id="import-modal" class="waisg-modal" style="display: none;">
	<div class="waisg-modal-content">
		<div class="waisg-modal-header">
			<h2><?php esc_html_e( 'Import Template', 'wp-ai-site-generator' ); ?></h2>
			<button type="button" class="waisg-modal-close">&times;</button>
		</div>
		<div class="waisg-modal-body">
			<div class="waisg-import-options">
				<div class="waisg-import-option">
					<h3><?php esc_html_e( 'Upload Template File', 'wp-ai-site-generator' ); ?></h3>
					<p><?php esc_html_e( 'Import a template from a JSON file.', 'wp-ai-site-generator' ); ?></p>
					<input type="file" id="import-file" accept=".json" />
				</div>

				<div class="waisg-import-option">
					<h3><?php esc_html_e( 'Import from URL', 'wp-ai-site-generator' ); ?></h3>
					<p><?php esc_html_e( 'Import a template from a remote URL.', 'wp-ai-site-generator' ); ?></p>
					<input type="url"
						   id="import-url"
						   class="regular-text"
						   placeholder="https://example.com/template.json" />
				</div>

				<div class="waisg-import-option">
					<h3><?php esc_html_e( 'Browse Marketplace', 'wp-ai-site-generator' ); ?></h3>
					<p><?php esc_html_e( 'Browse and import templates from our marketplace.', 'wp-ai-site-generator' ); ?></p>
					<button type="button" class="button button-secondary" id="browse-marketplace">
						<?php esc_html_e( 'Browse Templates', 'wp-ai-site-generator' ); ?>
					</button>
				</div>
			</div>
		</div>
		<div class="waisg-modal-footer">
			<button type="button" class="button button-secondary waisg-modal-close">
				<?php esc_html_e( 'Cancel', 'wp-ai-site-generator' ); ?>
			</button>
			<button type="button" class="button button-primary" id="import-template-btn" disabled>
				<?php esc_html_e( 'Import', 'wp-ai-site-generator' ); ?>
			</button>
		</div>
	</div>
</div>

<script>
jQuery(document).ready(function($) {
	// Template search
	$('#template-search').on('input', function() {
		var searchTerm = $(this).val().toLowerCase();

		$('.waisg-template-card').each(function() {
			var title = $(this).find('h3').text().toLowerCase();
			var description = $(this).find('.waisg-template-description').text().toLowerCase();
			var category = $(this).find('.waisg-template-category').text().toLowerCase();

			if (title.includes(searchTerm) || description.includes(searchTerm) || category.includes(searchTerm)) {
				$(this).show();
			} else {
				$(this).hide();
			}
		});
	});

	// Create template
	$('#create-template, #create-first-template').on('click', function() {
		$('#template-modal-title').text('<?php esc_html_e( 'Create Template', 'wp-ai-site-generator' ); ?>');
		$('#template-form')[0].reset();
		$('#template-id').val('');
		$('#template-modal').fadeIn(300);
	});

	// Edit template
	$('.edit-template').on('click', function() {
		var templateId = $(this).data('template-id');
		$('#template-modal-title').text('<?php esc_html_e( 'Edit Template', 'wp-ai-site-generator' ); ?>');
		$('#template-id').val(templateId);

		// Load template data via AJAX
		$.ajax({
			url: waisg_admin.rest_url + 'templates/' + templateId,
			method: 'GET',
			beforeSend: function(xhr) {
				xhr.setRequestHeader('X-WP-Nonce', waisg_admin.rest_nonce);
			},
			success: function(template) {
				$('#template-name').val(template.name);
				$('#template-category').val(template.category);
				$('#template-description').val(template.description);
				$('#template-is-featured').prop('checked', template.is_featured);

				// Load page structure
				if (template.metadata && template.metadata.structure) {
					// Populate structure editor
				}

				$('#template-modal').fadeIn(300);
			}
		});
	});

	// Add page to structure
	$('#add-page').on('click', function() {
		var pageItem =
			'<div class="waisg-page-item">' +
				'<input type="text" placeholder="<?php esc_attr_e( 'Page Title', 'wp-ai-site-generator' ); ?>" class="page-title" />' +
				'<textarea placeholder="<?php esc_attr_e( 'Page description...', 'wp-ai-site-generator' ); ?>" class="page-description"></textarea>' +
				'<button type="button" class="button-link remove-page">' +
					'<span class="dashicons dashicons-trash"></span>' +
				'</button>' +
			'</div>';

		$('.waisg-page-structure').append(pageItem);
	});

	// Remove page from structure
	$(document).on('click', '.remove-page', function() {
		if ($('.waisg-page-item').length > 1) {
			$(this).closest('.waisg-page-item').remove();
		} else {
			alert('<?php esc_html_e( 'At least one page is required.', 'wp-ai-site-generator' ); ?>');
		}
	});

	// Save template
	$('#save-template').on('click', function() {
		var templateData = {
			id: $('#template-id').val(),
			name: $('#template-name').val(),
			category: $('#template-category').val(),
			description: $('#template-description').val(),
			is_featured: $('#template-is-featured').is(':checked'),
			metadata: {
				structure: [],
				settings: {
					include_images: $('#template-include-images').is(':checked'),
					include_seo: $('#template-include-seo').is(':checked'),
					include_menu: $('#template-include-menu').is(':checked')
				},
				thumbnail: $('#template-thumbnail-url').val()
			}
		};

		// Collect page structure
		$('.waisg-page-item').each(function() {
			templateData.metadata.structure.push({
				title: $(this).find('.page-title').val(),
				description: $(this).find('.page-description').val()
			});
		});

		var method = templateData.id ? 'PUT' : 'POST';
		var url = templateData.id
			? waisg_admin.rest_url + 'templates/' + templateData.id
			: waisg_admin.rest_url + 'templates';

		$.ajax({
			url: url,
			method: method,
			beforeSend: function(xhr) {
				xhr.setRequestHeader('X-WP-Nonce', waisg_admin.rest_nonce);
			},
			data: JSON.stringify(templateData),
			contentType: 'application/json',
			success: function() {
				location.reload();
			},
			error: function(xhr) {
				alert('<?php esc_html_e( 'Failed to save template.', 'wp-ai-site-generator' ); ?>');
			}
		});
	});

	// Use template
	$('.use-template').on('click', function() {
		var templateId = $(this).data('template-id');
		window.location.href = '<?php echo esc_url( admin_url( 'admin.php?page=wp-ai-site-generator-generate' ) ); ?>&template=' + templateId;
	});

	// Delete template
	$('.delete-template').on('click', function() {
		if (!confirm('<?php esc_html_e( 'Are you sure you want to delete this template?', 'wp-ai-site-generator' ); ?>')) {
			return;
		}

		var templateId = $(this).data('template-id');
		var card = $(this).closest('.waisg-template-card');

		$.ajax({
			url: waisg_admin.rest_url + 'templates/' + templateId,
			method: 'DELETE',
			beforeSend: function(xhr) {
				xhr.setRequestHeader('X-WP-Nonce', waisg_admin.rest_nonce);
			},
			success: function() {
				card.fadeOut(300, function() {
					$(this).remove();
				});
			},
			error: function() {
				alert('<?php esc_html_e( 'Failed to delete template.', 'wp-ai-site-generator' ); ?>');
			}
		});
	});

	// Duplicate template
	$('.duplicate-template').on('click', function() {
		var templateId = $(this).data('template-id');

		$.ajax({
			url: waisg_admin.rest_url + 'templates/' + templateId + '/duplicate',
			method: 'POST',
			beforeSend: function(xhr) {
				xhr.setRequestHeader('X-WP-Nonce', waisg_admin.rest_nonce);
			},
			success: function() {
				location.reload();
			},
			error: function() {
				alert('<?php esc_html_e( 'Failed to duplicate template.', 'wp-ai-site-generator' ); ?>');
			}
		});
	});

	// Export template
	$('.export-template').on('click', function() {
		var templateId = $(this).data('template-id');
		window.location.href = waisg_admin.rest_url + 'templates/' + templateId + '/export?_wpnonce=' + waisg_admin.rest_nonce;
	});

	// Import template modal
	$('#import-template').on('click', function() {
		$('#import-modal').fadeIn(300);
	});

	// Handle import file selection
	$('#import-file').on('change', function() {
		$('#import-template-btn').prop('disabled', !this.files.length);
	});

	// Handle import URL input
	$('#import-url').on('input', function() {
		$('#import-template-btn').prop('disabled', !$(this).val());
	});

	// Import template
	$('#import-template-btn').on('click', function() {
		var file = $('#import-file')[0].files[0];
		var url = $('#import-url').val();

		if (file) {
			var reader = new FileReader();
			reader.onload = function(e) {
				var templateData = JSON.parse(e.target.result);
				importTemplate(templateData);
			};
			reader.readAsText(file);
		} else if (url) {
			$.getJSON(url, function(templateData) {
				importTemplate(templateData);
			});
		}
	});

	function importTemplate(data) {
		$.ajax({
			url: waisg_admin.rest_url + 'templates/import',
			method: 'POST',
			beforeSend: function(xhr) {
				xhr.setRequestHeader('X-WP-Nonce', waisg_admin.rest_nonce);
			},
			data: JSON.stringify(data),
			contentType: 'application/json',
			success: function() {
				location.reload();
			},
			error: function() {
				alert('<?php esc_html_e( 'Failed to import template.', 'wp-ai-site-generator' ); ?>');
			}
		});
	}

	// Browse marketplace
	$('#browse-marketplace').on('click', function() {
		window.open('https://marketplace.waisg.com/templates', '_blank');
	});

	// Upload thumbnail
	$('#upload-thumbnail').on('click', function(e) {
		e.preventDefault();

		var frame = wp.media({
			title: '<?php esc_html_e( 'Select Thumbnail', 'wp-ai-site-generator' ); ?>',
			button: {
				text: '<?php esc_html_e( 'Use as Thumbnail', 'wp-ai-site-generator' ); ?>'
			},
			multiple: false
		});

		frame.on('select', function() {
			var attachment = frame.state().get('selection').first().toJSON();
			$('#template-thumbnail-url').val(attachment.url);
			$('#thumbnail-preview').html('<img src="' + attachment.url + '" />');
			$('#remove-thumbnail').show();
		});

		frame.open();
	});

	// Remove thumbnail
	$('#remove-thumbnail').on('click', function() {
		$('#template-thumbnail-url').val('');
		$('#thumbnail-preview').html('');
		$(this).hide();
	});

	// Modal close
	$('.waisg-modal-close').on('click', function() {
		$(this).closest('.waisg-modal').fadeOut(300);
	});

	// Close modal on ESC key
	$(document).on('keyup', function(e) {
		if (e.key === 'Escape') {
			$('.waisg-modal').fadeOut(300);
		}
	});
});
</script>