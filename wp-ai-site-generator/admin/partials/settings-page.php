<?php
/**
 * Main settings page view
 *
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/admin/partials
 * @since      1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get current settings
$settings = get_option( 'waisg_settings', array() );
$features = get_option( 'waisg_features', array() );
$generation_defaults = get_option( 'waisg_generation_defaults', array() );
$usage_limits = get_option( 'waisg_usage_limits', array() );
?>

<div class="wrap waisg-settings-page">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<div class="waisg-settings-container">
		<nav class="nav-tab-wrapper wp-clearfix">
			<a href="#general" class="nav-tab nav-tab-active" data-tab="general">
				<?php esc_html_e( 'General', 'wp-ai-site-generator' ); ?>
			</a>
			<a href="#generation" class="nav-tab" data-tab="generation">
				<?php esc_html_e( 'Generation Defaults', 'wp-ai-site-generator' ); ?>
			</a>
			<a href="#features" class="nav-tab" data-tab="features">
				<?php esc_html_e( 'Features', 'wp-ai-site-generator' ); ?>
			</a>
			<a href="#usage" class="nav-tab" data-tab="usage">
				<?php esc_html_e( 'Usage Limits', 'wp-ai-site-generator' ); ?>
			</a>
			<a href="#advanced" class="nav-tab" data-tab="advanced">
				<?php esc_html_e( 'Advanced', 'wp-ai-site-generator' ); ?>
			</a>
		</nav>

		<form method="post" action="options.php" class="waisg-settings-form">
			<?php settings_fields( 'waisg_settings_group' ); ?>

			<!-- General Settings Tab -->
			<div id="general" class="tab-content active">
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="enable_ai_assistant">
								<?php esc_html_e( 'Enable AI Assistant', 'wp-ai-site-generator' ); ?>
							</label>
						</th>
						<td>
							<label class="waisg-switch">
								<input type="checkbox"
									   id="enable_ai_assistant"
									   name="waisg_settings[enable_ai_assistant]"
									   value="1"
									   <?php checked( ! empty( $settings['enable_ai_assistant'] ) ); ?> />
								<span class="waisg-slider round"></span>
							</label>
							<p class="description">
								<?php esc_html_e( 'Enable the AI-powered chat assistant for real-time help.', 'wp-ai-site-generator' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="auto_save_drafts">
								<?php esc_html_e( 'Auto-save Drafts', 'wp-ai-site-generator' ); ?>
							</label>
						</th>
						<td>
							<label class="waisg-switch">
								<input type="checkbox"
									   id="auto_save_drafts"
									   name="waisg_settings[auto_save_drafts]"
									   value="1"
									   <?php checked( ! empty( $settings['auto_save_drafts'] ) ); ?> />
								<span class="waisg-slider round"></span>
							</label>
							<p class="description">
								<?php esc_html_e( 'Automatically save generated content as drafts.', 'wp-ai-site-generator' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="default_language">
								<?php esc_html_e( 'Default Language', 'wp-ai-site-generator' ); ?>
							</label>
						</th>
						<td>
							<select id="default_language" name="waisg_settings[default_language]" class="waisg-select2">
								<option value="en" <?php selected( $settings['default_language'] ?? 'en', 'en' ); ?>>English</option>
								<option value="es" <?php selected( $settings['default_language'] ?? '', 'es' ); ?>>Español</option>
								<option value="fr" <?php selected( $settings['default_language'] ?? '', 'fr' ); ?>>Français</option>
								<option value="de" <?php selected( $settings['default_language'] ?? '', 'de' ); ?>>Deutsch</option>
								<option value="it" <?php selected( $settings['default_language'] ?? '', 'it' ); ?>>Italiano</option>
								<option value="pt" <?php selected( $settings['default_language'] ?? '', 'pt' ); ?>>Português</option>
								<option value="nl" <?php selected( $settings['default_language'] ?? '', 'nl' ); ?>>Nederlands</option>
								<option value="ru" <?php selected( $settings['default_language'] ?? '', 'ru' ); ?>>Русский</option>
								<option value="ja" <?php selected( $settings['default_language'] ?? '', 'ja' ); ?>>日本語</option>
								<option value="zh" <?php selected( $settings['default_language'] ?? '', 'zh' ); ?>>中文</option>
							</select>
							<p class="description">
								<?php esc_html_e( 'Default language for generated content.', 'wp-ai-site-generator' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="content_tone">
								<?php esc_html_e( 'Content Tone', 'wp-ai-site-generator' ); ?>
							</label>
						</th>
						<td>
							<select id="content_tone" name="waisg_settings[content_tone]" class="waisg-select2">
								<option value="professional" <?php selected( $settings['content_tone'] ?? 'professional', 'professional' ); ?>>
									<?php esc_html_e( 'Professional', 'wp-ai-site-generator' ); ?>
								</option>
								<option value="casual" <?php selected( $settings['content_tone'] ?? '', 'casual' ); ?>>
									<?php esc_html_e( 'Casual', 'wp-ai-site-generator' ); ?>
								</option>
								<option value="friendly" <?php selected( $settings['content_tone'] ?? '', 'friendly' ); ?>>
									<?php esc_html_e( 'Friendly', 'wp-ai-site-generator' ); ?>
								</option>
								<option value="formal" <?php selected( $settings['content_tone'] ?? '', 'formal' ); ?>>
									<?php esc_html_e( 'Formal', 'wp-ai-site-generator' ); ?>
								</option>
								<option value="creative" <?php selected( $settings['content_tone'] ?? '', 'creative' ); ?>>
									<?php esc_html_e( 'Creative', 'wp-ai-site-generator' ); ?>
								</option>
							</select>
							<p class="description">
								<?php esc_html_e( 'Default tone for generated content.', 'wp-ai-site-generator' ); ?>
							</p>
						</td>
					</tr>
				</table>
			</div>

			<!-- Generation Defaults Tab -->
			<div id="generation" class="tab-content">
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="default_pages_count">
								<?php esc_html_e( 'Default Pages Count', 'wp-ai-site-generator' ); ?>
							</label>
						</th>
						<td>
							<input type="number"
								   id="default_pages_count"
								   name="waisg_generation_defaults[pages_count]"
								   value="<?php echo esc_attr( $generation_defaults['pages_count'] ?? '5' ); ?>"
								   min="1"
								   max="50"
								   class="small-text" />
							<p class="description">
								<?php esc_html_e( 'Default number of pages to generate.', 'wp-ai-site-generator' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="default_content_length">
								<?php esc_html_e( 'Default Content Length', 'wp-ai-site-generator' ); ?>
							</label>
						</th>
						<td>
							<select id="default_content_length" name="waisg_generation_defaults[content_length]">
								<option value="short" <?php selected( $generation_defaults['content_length'] ?? 'medium', 'short' ); ?>>
									<?php esc_html_e( 'Short (300-500 words)', 'wp-ai-site-generator' ); ?>
								</option>
								<option value="medium" <?php selected( $generation_defaults['content_length'] ?? 'medium', 'medium' ); ?>>
									<?php esc_html_e( 'Medium (500-1000 words)', 'wp-ai-site-generator' ); ?>
								</option>
								<option value="long" <?php selected( $generation_defaults['content_length'] ?? '', 'long' ); ?>>
									<?php esc_html_e( 'Long (1000-2000 words)', 'wp-ai-site-generator' ); ?>
								</option>
								<option value="extra-long" <?php selected( $generation_defaults['content_length'] ?? '', 'extra-long' ); ?>>
									<?php esc_html_e( 'Extra Long (2000+ words)', 'wp-ai-site-generator' ); ?>
								</option>
							</select>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="include_images">
								<?php esc_html_e( 'Include Images', 'wp-ai-site-generator' ); ?>
							</label>
						</th>
						<td>
							<label class="waisg-switch">
								<input type="checkbox"
									   id="include_images"
									   name="waisg_generation_defaults[include_images]"
									   value="1"
									   <?php checked( ! empty( $generation_defaults['include_images'] ) ); ?> />
								<span class="waisg-slider round"></span>
							</label>
							<p class="description">
								<?php esc_html_e( 'Generate and include images in content.', 'wp-ai-site-generator' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="include_seo">
								<?php esc_html_e( 'Include SEO Metadata', 'wp-ai-site-generator' ); ?>
							</label>
						</th>
						<td>
							<label class="waisg-switch">
								<input type="checkbox"
									   id="include_seo"
									   name="waisg_generation_defaults[include_seo]"
									   value="1"
									   <?php checked( ! empty( $generation_defaults['include_seo'] ) ); ?> />
								<span class="waisg-slider round"></span>
							</label>
							<p class="description">
								<?php esc_html_e( 'Generate SEO metadata (title, description, keywords).', 'wp-ai-site-generator' ); ?>
							</p>
						</td>
					</tr>
				</table>
			</div>

			<!-- Features Tab -->
			<div id="features" class="tab-content">
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="enable_chat_interface">
								<?php esc_html_e( 'Chat Interface', 'wp-ai-site-generator' ); ?>
							</label>
						</th>
						<td>
							<label class="waisg-switch">
								<input type="checkbox"
									   id="enable_chat_interface"
									   name="waisg_features[chat_interface]"
									   value="1"
									   <?php checked( ! empty( $features['chat_interface'] ) ); ?> />
								<span class="waisg-slider round"></span>
							</label>
							<p class="description">
								<?php esc_html_e( 'Enable conversational AI chat for content generation.', 'wp-ai-site-generator' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="enable_section_regeneration">
								<?php esc_html_e( 'Section Regeneration', 'wp-ai-site-generator' ); ?>
							</label>
						</th>
						<td>
							<label class="waisg-switch">
								<input type="checkbox"
									   id="enable_section_regeneration"
									   name="waisg_features[section_regeneration]"
									   value="1"
									   <?php checked( ! empty( $features['section_regeneration'] ) ); ?> />
								<span class="waisg-slider round"></span>
							</label>
							<p class="description">
								<?php esc_html_e( 'Allow regeneration of individual sections.', 'wp-ai-site-generator' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="enable_bulk_generation">
								<?php esc_html_e( 'Bulk Generation', 'wp-ai-site-generator' ); ?>
							</label>
						</th>
						<td>
							<label class="waisg-switch">
								<input type="checkbox"
									   id="enable_bulk_generation"
									   name="waisg_features[bulk_generation]"
									   value="1"
									   <?php checked( ! empty( $features['bulk_generation'] ) ); ?> />
								<span class="waisg-slider round"></span>
							</label>
							<p class="description">
								<?php esc_html_e( 'Enable generation of multiple sites at once.', 'wp-ai-site-generator' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="enable_template_customization">
								<?php esc_html_e( 'Template Customization', 'wp-ai-site-generator' ); ?>
							</label>
						</th>
						<td>
							<label class="waisg-switch">
								<input type="checkbox"
									   id="enable_template_customization"
									   name="waisg_features[template_customization]"
									   value="1"
									   <?php checked( ! empty( $features['template_customization'] ) ); ?> />
								<span class="waisg-slider round"></span>
							</label>
							<p class="description">
								<?php esc_html_e( 'Allow creation and customization of templates.', 'wp-ai-site-generator' ); ?>
							</p>
						</td>
					</tr>
				</table>
			</div>

			<!-- Usage Limits Tab -->
			<div id="usage" class="tab-content">
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="daily_generation_limit">
								<?php esc_html_e( 'Daily Generation Limit', 'wp-ai-site-generator' ); ?>
							</label>
						</th>
						<td>
							<input type="number"
								   id="daily_generation_limit"
								   name="waisg_usage_limits[daily_limit]"
								   value="<?php echo esc_attr( $usage_limits['daily_limit'] ?? '10' ); ?>"
								   min="0"
								   class="small-text" />
							<p class="description">
								<?php esc_html_e( 'Maximum generations per day (0 = unlimited).', 'wp-ai-site-generator' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="monthly_generation_limit">
								<?php esc_html_e( 'Monthly Generation Limit', 'wp-ai-site-generator' ); ?>
							</label>
						</th>
						<td>
							<input type="number"
								   id="monthly_generation_limit"
								   name="waisg_usage_limits[monthly_limit]"
								   value="<?php echo esc_attr( $usage_limits['monthly_limit'] ?? '100' ); ?>"
								   min="0"
								   class="small-text" />
							<p class="description">
								<?php esc_html_e( 'Maximum generations per month (0 = unlimited).', 'wp-ai-site-generator' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="max_tokens_per_request">
								<?php esc_html_e( 'Max Tokens per Request', 'wp-ai-site-generator' ); ?>
							</label>
						</th>
						<td>
							<input type="number"
								   id="max_tokens_per_request"
								   name="waisg_usage_limits[max_tokens]"
								   value="<?php echo esc_attr( $usage_limits['max_tokens'] ?? '4000' ); ?>"
								   min="100"
								   max="32000"
								   class="regular-text" />
							<p class="description">
								<?php esc_html_e( 'Maximum tokens per AI request.', 'wp-ai-site-generator' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="rate_limit_per_minute">
								<?php esc_html_e( 'Rate Limit (per minute)', 'wp-ai-site-generator' ); ?>
							</label>
						</th>
						<td>
							<input type="number"
								   id="rate_limit_per_minute"
								   name="waisg_usage_limits[rate_limit]"
								   value="<?php echo esc_attr( $usage_limits['rate_limit'] ?? '10' ); ?>"
								   min="1"
								   max="100"
								   class="small-text" />
							<p class="description">
								<?php esc_html_e( 'Maximum API requests per minute.', 'wp-ai-site-generator' ); ?>
							</p>
						</td>
					</tr>
				</table>
			</div>

			<!-- Advanced Tab -->
			<div id="advanced" class="tab-content">
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="enable_debug_mode">
								<?php esc_html_e( 'Debug Mode', 'wp-ai-site-generator' ); ?>
							</label>
						</th>
						<td>
							<label class="waisg-switch">
								<input type="checkbox"
									   id="enable_debug_mode"
									   name="waisg_settings[debug_mode]"
									   value="1"
									   <?php checked( ! empty( $settings['debug_mode'] ) ); ?> />
								<span class="waisg-slider round"></span>
							</label>
							<p class="description">
								<?php esc_html_e( 'Enable debug logging for troubleshooting.', 'wp-ai-site-generator' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="cache_duration">
								<?php esc_html_e( 'Cache Duration', 'wp-ai-site-generator' ); ?>
							</label>
						</th>
						<td>
							<input type="number"
								   id="cache_duration"
								   name="waisg_settings[cache_duration]"
								   value="<?php echo esc_attr( $settings['cache_duration'] ?? '3600' ); ?>"
								   min="0"
								   class="regular-text" />
							<span><?php esc_html_e( 'seconds', 'wp-ai-site-generator' ); ?></span>
							<p class="description">
								<?php esc_html_e( 'How long to cache AI responses (0 = no cache).', 'wp-ai-site-generator' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="api_timeout">
								<?php esc_html_e( 'API Timeout', 'wp-ai-site-generator' ); ?>
							</label>
						</th>
						<td>
							<input type="number"
								   id="api_timeout"
								   name="waisg_settings[api_timeout]"
								   value="<?php echo esc_attr( $settings['api_timeout'] ?? '60' ); ?>"
								   min="10"
								   max="300"
								   class="small-text" />
							<span><?php esc_html_e( 'seconds', 'wp-ai-site-generator' ); ?></span>
							<p class="description">
								<?php esc_html_e( 'Maximum time to wait for API responses.', 'wp-ai-site-generator' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="cleanup_old_generations">
								<?php esc_html_e( 'Auto-cleanup Old Generations', 'wp-ai-site-generator' ); ?>
							</label>
						</th>
						<td>
							<label class="waisg-switch">
								<input type="checkbox"
									   id="cleanup_old_generations"
									   name="waisg_settings[cleanup_old_generations]"
									   value="1"
									   <?php checked( ! empty( $settings['cleanup_old_generations'] ) ); ?> />
								<span class="waisg-slider round"></span>
							</label>
							<p class="description">
								<?php esc_html_e( 'Automatically remove generations older than 30 days.', 'wp-ai-site-generator' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<?php esc_html_e( 'Export/Import Settings', 'wp-ai-site-generator' ); ?>
						</th>
						<td>
							<button type="button" class="button button-secondary" id="export-settings">
								<?php esc_html_e( 'Export Settings', 'wp-ai-site-generator' ); ?>
							</button>
							<button type="button" class="button button-secondary" id="import-settings">
								<?php esc_html_e( 'Import Settings', 'wp-ai-site-generator' ); ?>
							</button>
							<input type="file" id="import-file" style="display: none;" accept=".json" />
							<p class="description">
								<?php esc_html_e( 'Export or import plugin settings for backup or migration.', 'wp-ai-site-generator' ); ?>
							</p>
						</td>
					</tr>
				</table>
			</div>

			<?php submit_button(); ?>
		</form>
	</div>
</div>

<script>
jQuery(document).ready(function($) {
	// Tab switching
	$('.nav-tab').on('click', function(e) {
		e.preventDefault();
		var tab = $(this).data('tab');

		$('.nav-tab').removeClass('nav-tab-active');
		$(this).addClass('nav-tab-active');

		$('.tab-content').removeClass('active');
		$('#' + tab).addClass('active');
	});

	// Initialize Select2
	if ($.fn.select2) {
		$('.waisg-select2').select2({
			width: '25em'
		});
	}

	// Export settings
	$('#export-settings').on('click', function() {
		// Trigger AJAX to get settings JSON
		$.post(waisg_admin.ajax_url, {
			action: 'waisg_export_settings',
			nonce: waisg_admin.nonce
		}, function(response) {
			if (response.success) {
				var dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(response.data));
				var downloadAnchorNode = document.createElement('a');
				downloadAnchorNode.setAttribute("href", dataStr);
				downloadAnchorNode.setAttribute("download", "waisg-settings-" + Date.now() + ".json");
				document.body.appendChild(downloadAnchorNode);
				downloadAnchorNode.click();
				downloadAnchorNode.remove();
			}
		});
	});

	// Import settings
	$('#import-settings').on('click', function() {
		$('#import-file').click();
	});

	$('#import-file').on('change', function(e) {
		var file = e.target.files[0];
		if (file) {
			var reader = new FileReader();
			reader.onload = function(e) {
				var settings = JSON.parse(e.target.result);

				if (confirm('<?php esc_html_e( 'This will override all current settings. Continue?', 'wp-ai-site-generator' ); ?>')) {
					$.post(waisg_admin.ajax_url, {
						action: 'waisg_import_settings',
						nonce: waisg_admin.nonce,
						settings: JSON.stringify(settings)
					}, function(response) {
						if (response.success) {
							location.reload();
						} else {
							alert(response.data);
						}
					});
				}
			};
			reader.readAsText(file);
		}
	});
});
</script>