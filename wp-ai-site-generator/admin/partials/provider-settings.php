<?php
/**
 * Provider settings configuration view
 *
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/admin/partials
 * @since      1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get provider settings
$provider_settings = get_option( 'waisg_provider_settings', array() );
$providers = isset( $provider_settings['providers'] ) ? $provider_settings['providers'] : array();
?>

<div class="wrap waisg-providers-page">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<div class="waisg-providers-container">
		<div class="waisg-providers-intro">
			<p><?php esc_html_e( 'Configure your AI provider credentials and settings. You can enable multiple providers and switch between them.', 'wp-ai-site-generator' ); ?></p>
		</div>

		<form method="post" action="options.php" class="waisg-providers-form">
			<?php settings_fields( 'waisg_provider_settings_group' ); ?>

			<!-- OpenAI Provider -->
			<div class="waisg-provider-card">
				<div class="waisg-provider-header">
					<div class="waisg-provider-title">
						<img src="<?php echo esc_url( WAISG_PLUGIN_URL . 'admin/images/openai-logo.svg' ); ?>"
							 alt="OpenAI"
							 class="waisg-provider-logo"
							 onerror="this.style.display='none'" />
						<h2>OpenAI</h2>
						<span class="waisg-provider-badge"><?php esc_html_e( 'GPT-4, GPT-3.5', 'wp-ai-site-generator' ); ?></span>
					</div>
					<label class="waisg-switch">
						<input type="checkbox"
							   name="waisg_provider_settings[providers][openai][enabled]"
							   value="1"
							   <?php checked( ! empty( $providers['openai']['enabled'] ) ); ?>
							   class="waisg-provider-toggle"
							   data-provider="openai" />
						<span class="waisg-slider round"></span>
					</label>
				</div>

				<div class="waisg-provider-body" data-provider-body="openai">
					<div class="waisg-provider-field">
						<label for="openai_api_key">
							<?php esc_html_e( 'API Key', 'wp-ai-site-generator' ); ?>
							<span class="required">*</span>
						</label>
						<div class="waisg-input-group">
							<input type="password"
								   id="openai_api_key"
								   name="waisg_provider_settings[providers][openai][api_key]"
								   value="<?php echo esc_attr( $providers['openai']['api_key'] ?? '' ); ?>"
								   class="regular-text waisg-api-key-input"
								   placeholder="sk-..." />
							<button type="button" class="button waisg-toggle-visibility">
								<span class="dashicons dashicons-visibility"></span>
							</button>
							<button type="button" class="button waisg-test-connection" data-provider="openai">
								<?php esc_html_e( 'Test Connection', 'wp-ai-site-generator' ); ?>
							</button>
						</div>
						<p class="description">
							<?php
							printf(
								/* translators: %s: Link to OpenAI API keys page */
								__( 'Get your API key from <a href="%s" target="_blank">OpenAI Dashboard</a>', 'wp-ai-site-generator' ),
								'https://platform.openai.com/api-keys'
							);
							?>
						</p>
					</div>

					<div class="waisg-provider-field">
						<label for="openai_model">
							<?php esc_html_e( 'Default Model', 'wp-ai-site-generator' ); ?>
						</label>
						<select id="openai_model"
								name="waisg_provider_settings[providers][openai][model]"
								class="waisg-select">
							<option value="gpt-4-turbo-preview" <?php selected( $providers['openai']['model'] ?? '', 'gpt-4-turbo-preview' ); ?>>
								GPT-4 Turbo (Latest)
							</option>
							<option value="gpt-4" <?php selected( $providers['openai']['model'] ?? '', 'gpt-4' ); ?>>
								GPT-4
							</option>
							<option value="gpt-3.5-turbo" <?php selected( $providers['openai']['model'] ?? 'gpt-3.5-turbo', 'gpt-3.5-turbo' ); ?>>
								GPT-3.5 Turbo
							</option>
							<option value="gpt-3.5-turbo-16k" <?php selected( $providers['openai']['model'] ?? '', 'gpt-3.5-turbo-16k' ); ?>>
								GPT-3.5 Turbo 16K
							</option>
						</select>
					</div>

					<div class="waisg-provider-field">
						<label for="openai_organization">
							<?php esc_html_e( 'Organization ID', 'wp-ai-site-generator' ); ?>
							<span class="optional"><?php esc_html_e( '(Optional)', 'wp-ai-site-generator' ); ?></span>
						</label>
						<input type="text"
							   id="openai_organization"
							   name="waisg_provider_settings[providers][openai][organization]"
							   value="<?php echo esc_attr( $providers['openai']['organization'] ?? '' ); ?>"
							   class="regular-text"
							   placeholder="org-..." />
						<p class="description">
							<?php esc_html_e( 'Required only for organization accounts.', 'wp-ai-site-generator' ); ?>
						</p>
					</div>

					<div class="waisg-provider-field">
						<label for="openai_max_tokens">
							<?php esc_html_e( 'Max Tokens per Request', 'wp-ai-site-generator' ); ?>
						</label>
						<input type="number"
							   id="openai_max_tokens"
							   name="waisg_provider_settings[providers][openai][max_tokens]"
							   value="<?php echo esc_attr( $providers['openai']['max_tokens'] ?? '4000' ); ?>"
							   min="100"
							   max="128000"
							   class="small-text" />
						<p class="description">
							<?php esc_html_e( 'Maximum tokens to use per request (affects cost and response length).', 'wp-ai-site-generator' ); ?>
						</p>
					</div>

					<div class="waisg-provider-status" id="openai-status"></div>
				</div>
			</div>

			<!-- Anthropic Claude Provider -->
			<div class="waisg-provider-card">
				<div class="waisg-provider-header">
					<div class="waisg-provider-title">
						<img src="<?php echo esc_url( WAISG_PLUGIN_URL . 'admin/images/anthropic-logo.svg' ); ?>"
							 alt="Anthropic"
							 class="waisg-provider-logo"
							 onerror="this.style.display='none'" />
						<h2>Anthropic Claude</h2>
						<span class="waisg-provider-badge"><?php esc_html_e( 'Claude 3 Opus, Sonnet', 'wp-ai-site-generator' ); ?></span>
					</div>
					<label class="waisg-switch">
						<input type="checkbox"
							   name="waisg_provider_settings[providers][anthropic][enabled]"
							   value="1"
							   <?php checked( ! empty( $providers['anthropic']['enabled'] ) ); ?>
							   class="waisg-provider-toggle"
							   data-provider="anthropic" />
						<span class="waisg-slider round"></span>
					</label>
				</div>

				<div class="waisg-provider-body" data-provider-body="anthropic">
					<div class="waisg-provider-field">
						<label for="anthropic_api_key">
							<?php esc_html_e( 'API Key', 'wp-ai-site-generator' ); ?>
							<span class="required">*</span>
						</label>
						<div class="waisg-input-group">
							<input type="password"
								   id="anthropic_api_key"
								   name="waisg_provider_settings[providers][anthropic][api_key]"
								   value="<?php echo esc_attr( $providers['anthropic']['api_key'] ?? '' ); ?>"
								   class="regular-text waisg-api-key-input"
								   placeholder="sk-ant-..." />
							<button type="button" class="button waisg-toggle-visibility">
								<span class="dashicons dashicons-visibility"></span>
							</button>
							<button type="button" class="button waisg-test-connection" data-provider="anthropic">
								<?php esc_html_e( 'Test Connection', 'wp-ai-site-generator' ); ?>
							</button>
						</div>
						<p class="description">
							<?php
							printf(
								/* translators: %s: Link to Anthropic console */
								__( 'Get your API key from <a href="%s" target="_blank">Anthropic Console</a>', 'wp-ai-site-generator' ),
								'https://console.anthropic.com/api'
							);
							?>
						</p>
					</div>

					<div class="waisg-provider-field">
						<label for="anthropic_model">
							<?php esc_html_e( 'Default Model', 'wp-ai-site-generator' ); ?>
						</label>
						<select id="anthropic_model"
								name="waisg_provider_settings[providers][anthropic][model]"
								class="waisg-select">
							<option value="claude-3-opus-20240229" <?php selected( $providers['anthropic']['model'] ?? 'claude-3-opus-20240229', 'claude-3-opus-20240229' ); ?>>
								Claude 3 Opus (Most Capable)
							</option>
							<option value="claude-3-sonnet-20240229" <?php selected( $providers['anthropic']['model'] ?? '', 'claude-3-sonnet-20240229' ); ?>>
								Claude 3 Sonnet (Balanced)
							</option>
							<option value="claude-3-haiku-20240307" <?php selected( $providers['anthropic']['model'] ?? '', 'claude-3-haiku-20240307' ); ?>>
								Claude 3 Haiku (Fastest)
							</option>
						</select>
					</div>

					<div class="waisg-provider-field">
						<label for="anthropic_max_tokens">
							<?php esc_html_e( 'Max Tokens per Request', 'wp-ai-site-generator' ); ?>
						</label>
						<input type="number"
							   id="anthropic_max_tokens"
							   name="waisg_provider_settings[providers][anthropic][max_tokens]"
							   value="<?php echo esc_attr( $providers['anthropic']['max_tokens'] ?? '4000' ); ?>"
							   min="100"
							   max="200000"
							   class="small-text" />
					</div>

					<div class="waisg-provider-status" id="anthropic-status"></div>
				</div>
			</div>

			<!-- Google Gemini Provider -->
			<div class="waisg-provider-card">
				<div class="waisg-provider-header">
					<div class="waisg-provider-title">
						<img src="<?php echo esc_url( WAISG_PLUGIN_URL . 'admin/images/google-logo.svg' ); ?>"
							 alt="Google"
							 class="waisg-provider-logo"
							 onerror="this.style.display='none'" />
						<h2>Google Gemini</h2>
						<span class="waisg-provider-badge"><?php esc_html_e( 'Gemini Pro, Ultra', 'wp-ai-site-generator' ); ?></span>
					</div>
					<label class="waisg-switch">
						<input type="checkbox"
							   name="waisg_provider_settings[providers][google][enabled]"
							   value="1"
							   <?php checked( ! empty( $providers['google']['enabled'] ) ); ?>
							   class="waisg-provider-toggle"
							   data-provider="google" />
						<span class="waisg-slider round"></span>
					</label>
				</div>

				<div class="waisg-provider-body" data-provider-body="google">
					<div class="waisg-provider-field">
						<label for="google_api_key">
							<?php esc_html_e( 'API Key', 'wp-ai-site-generator' ); ?>
							<span class="required">*</span>
						</label>
						<div class="waisg-input-group">
							<input type="password"
								   id="google_api_key"
								   name="waisg_provider_settings[providers][google][api_key]"
								   value="<?php echo esc_attr( $providers['google']['api_key'] ?? '' ); ?>"
								   class="regular-text waisg-api-key-input" />
							<button type="button" class="button waisg-toggle-visibility">
								<span class="dashicons dashicons-visibility"></span>
							</button>
							<button type="button" class="button waisg-test-connection" data-provider="google">
								<?php esc_html_e( 'Test Connection', 'wp-ai-site-generator' ); ?>
							</button>
						</div>
						<p class="description">
							<?php
							printf(
								/* translators: %s: Link to Google AI Studio */
								__( 'Get your API key from <a href="%s" target="_blank">Google AI Studio</a>', 'wp-ai-site-generator' ),
								'https://makersuite.google.com/app/apikey'
							);
							?>
						</p>
					</div>

					<div class="waisg-provider-field">
						<label for="google_model">
							<?php esc_html_e( 'Default Model', 'wp-ai-site-generator' ); ?>
						</label>
						<select id="google_model"
								name="waisg_provider_settings[providers][google][model]"
								class="waisg-select">
							<option value="gemini-pro" <?php selected( $providers['google']['model'] ?? 'gemini-pro', 'gemini-pro' ); ?>>
								Gemini Pro
							</option>
							<option value="gemini-pro-vision" <?php selected( $providers['google']['model'] ?? '', 'gemini-pro-vision' ); ?>>
								Gemini Pro Vision
							</option>
						</select>
					</div>

					<div class="waisg-provider-status" id="google-status"></div>
				</div>
			</div>

			<!-- Local AI / Custom Provider -->
			<div class="waisg-provider-card">
				<div class="waisg-provider-header">
					<div class="waisg-provider-title">
						<span class="dashicons dashicons-admin-generic"></span>
						<h2><?php esc_html_e( 'Custom / Local AI', 'wp-ai-site-generator' ); ?></h2>
						<span class="waisg-provider-badge"><?php esc_html_e( 'LLaMA, Mistral, etc.', 'wp-ai-site-generator' ); ?></span>
					</div>
					<label class="waisg-switch">
						<input type="checkbox"
							   name="waisg_provider_settings[providers][custom][enabled]"
							   value="1"
							   <?php checked( ! empty( $providers['custom']['enabled'] ) ); ?>
							   class="waisg-provider-toggle"
							   data-provider="custom" />
						<span class="waisg-slider round"></span>
					</label>
				</div>

				<div class="waisg-provider-body" data-provider-body="custom">
					<div class="waisg-provider-field">
						<label for="custom_endpoint">
							<?php esc_html_e( 'API Endpoint', 'wp-ai-site-generator' ); ?>
							<span class="required">*</span>
						</label>
						<input type="url"
							   id="custom_endpoint"
							   name="waisg_provider_settings[providers][custom][endpoint]"
							   value="<?php echo esc_attr( $providers['custom']['endpoint'] ?? '' ); ?>"
							   class="regular-text"
							   placeholder="http://localhost:11434/api/generate" />
						<p class="description">
							<?php esc_html_e( 'Full URL to your local AI API endpoint.', 'wp-ai-site-generator' ); ?>
						</p>
					</div>

					<div class="waisg-provider-field">
						<label for="custom_model">
							<?php esc_html_e( 'Model Name', 'wp-ai-site-generator' ); ?>
						</label>
						<input type="text"
							   id="custom_model"
							   name="waisg_provider_settings[providers][custom][model]"
							   value="<?php echo esc_attr( $providers['custom']['model'] ?? '' ); ?>"
							   class="regular-text"
							   placeholder="llama2:13b" />
					</div>

					<div class="waisg-provider-field">
						<label for="custom_api_key">
							<?php esc_html_e( 'API Key', 'wp-ai-site-generator' ); ?>
							<span class="optional"><?php esc_html_e( '(If required)', 'wp-ai-site-generator' ); ?></span>
						</label>
						<input type="password"
							   id="custom_api_key"
							   name="waisg_provider_settings[providers][custom][api_key]"
							   value="<?php echo esc_attr( $providers['custom']['api_key'] ?? '' ); ?>"
							   class="regular-text waisg-api-key-input" />
					</div>

					<div class="waisg-provider-status" id="custom-status"></div>
				</div>
			</div>

			<!-- Global Provider Settings -->
			<div class="waisg-provider-card">
				<h2><?php esc_html_e( 'Global Settings', 'wp-ai-site-generator' ); ?></h2>

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="default_provider">
								<?php esc_html_e( 'Default Provider', 'wp-ai-site-generator' ); ?>
							</label>
						</th>
						<td>
							<select id="default_provider" name="waisg_provider_settings[default_provider]" class="waisg-select">
								<option value=""><?php esc_html_e( 'Auto-select based on availability', 'wp-ai-site-generator' ); ?></option>
								<option value="openai" <?php selected( $provider_settings['default_provider'] ?? '', 'openai' ); ?>>OpenAI</option>
								<option value="anthropic" <?php selected( $provider_settings['default_provider'] ?? '', 'anthropic' ); ?>>Anthropic</option>
								<option value="google" <?php selected( $provider_settings['default_provider'] ?? '', 'google' ); ?>>Google</option>
								<option value="custom" <?php selected( $provider_settings['default_provider'] ?? '', 'custom' ); ?>>Custom</option>
							</select>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="fallback_provider">
								<?php esc_html_e( 'Fallback Provider', 'wp-ai-site-generator' ); ?>
							</label>
						</th>
						<td>
							<select id="fallback_provider" name="waisg_provider_settings[fallback_provider]" class="waisg-select">
								<option value=""><?php esc_html_e( 'None', 'wp-ai-site-generator' ); ?></option>
								<option value="openai" <?php selected( $provider_settings['fallback_provider'] ?? '', 'openai' ); ?>>OpenAI</option>
								<option value="anthropic" <?php selected( $provider_settings['fallback_provider'] ?? '', 'anthropic' ); ?>>Anthropic</option>
								<option value="google" <?php selected( $provider_settings['fallback_provider'] ?? '', 'google' ); ?>>Google</option>
								<option value="custom" <?php selected( $provider_settings['fallback_provider'] ?? '', 'custom' ); ?>>Custom</option>
							</select>
							<p class="description">
								<?php esc_html_e( 'Use this provider if the default provider fails.', 'wp-ai-site-generator' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="retry_attempts">
								<?php esc_html_e( 'Retry Attempts', 'wp-ai-site-generator' ); ?>
							</label>
						</th>
						<td>
							<input type="number"
								   id="retry_attempts"
								   name="waisg_provider_settings[retry_attempts]"
								   value="<?php echo esc_attr( $provider_settings['retry_attempts'] ?? '3' ); ?>"
								   min="0"
								   max="10"
								   class="small-text" />
							<p class="description">
								<?php esc_html_e( 'Number of times to retry failed API calls.', 'wp-ai-site-generator' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="log_api_errors">
								<?php esc_html_e( 'Log API Errors', 'wp-ai-site-generator' ); ?>
							</label>
						</th>
						<td>
							<label class="waisg-switch">
								<input type="checkbox"
									   id="log_api_errors"
									   name="waisg_provider_settings[log_api_errors]"
									   value="1"
									   <?php checked( ! empty( $provider_settings['log_api_errors'] ) ); ?> />
								<span class="waisg-slider round"></span>
							</label>
							<p class="description">
								<?php esc_html_e( 'Log API errors for debugging purposes.', 'wp-ai-site-generator' ); ?>
							</p>
						</td>
					</tr>
				</table>
			</div>

			<?php submit_button(); ?>
		</form>
	</div>
</div>

<!-- Provider Configuration React Mount Point -->
<div id="waisg-provider-config-root"></div>

<script>
jQuery(document).ready(function($) {
	// Toggle provider body visibility based on enabled state
	$('.waisg-provider-toggle').each(function() {
		var provider = $(this).data('provider');
		var isEnabled = $(this).is(':checked');
		$('[data-provider-body="' + provider + '"]').toggle(isEnabled);
	});

	// Handle provider toggle
	$('.waisg-provider-toggle').on('change', function() {
		var provider = $(this).data('provider');
		var isEnabled = $(this).is(':checked');
		$('[data-provider-body="' + provider + '"]').slideToggle(300);
	});

	// Toggle API key visibility
	$('.waisg-toggle-visibility').on('click', function() {
		var input = $(this).siblings('.waisg-api-key-input');
		var icon = $(this).find('.dashicons');

		if (input.attr('type') === 'password') {
			input.attr('type', 'text');
			icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
		} else {
			input.attr('type', 'password');
			icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
		}
	});

	// Test connection
	$('.waisg-test-connection').on('click', function() {
		var button = $(this);
		var provider = button.data('provider');
		var statusDiv = $('#' + provider + '-status');

		// Get provider data
		var data = {
			provider: provider,
			api_key: $('#' + provider + '_api_key').val(),
			model: $('#' + provider + '_model').val()
		};

		if (provider === 'openai') {
			data.organization = $('#openai_organization').val();
		} else if (provider === 'custom') {
			data.endpoint = $('#custom_endpoint').val();
		}

		// Show loading
		button.prop('disabled', true).text('<?php esc_html_e( 'Testing...', 'wp-ai-site-generator' ); ?>');
		statusDiv.html('<div class="notice notice-info"><p><?php esc_html_e( 'Testing connection...', 'wp-ai-site-generator' ); ?></p></div>');

		// Make API call
		$.ajax({
			url: waisg_admin.rest_url + 'providers/test',
			method: 'POST',
			beforeSend: function(xhr) {
				xhr.setRequestHeader('X-WP-Nonce', waisg_admin.rest_nonce);
			},
			data: JSON.stringify(data),
			contentType: 'application/json',
			success: function(response) {
				if (response.success) {
					statusDiv.html(
						'<div class="notice notice-success">' +
						'<p><strong><?php esc_html_e( 'Connection successful!', 'wp-ai-site-generator' ); ?></strong></p>' +
						'<p>' + response.data.message + '</p>' +
						(response.data.details ? '<p class="description">' + response.data.details + '</p>' : '') +
						'</div>'
					);
				} else {
					statusDiv.html(
						'<div class="notice notice-error">' +
						'<p><strong><?php esc_html_e( 'Connection failed!', 'wp-ai-site-generator' ); ?></strong></p>' +
						'<p>' + response.data + '</p>' +
						'</div>'
					);
				}
			},
			error: function(xhr) {
				var message = xhr.responseJSON && xhr.responseJSON.message
					? xhr.responseJSON.message
					: '<?php esc_html_e( 'An error occurred while testing the connection.', 'wp-ai-site-generator' ); ?>';

				statusDiv.html(
					'<div class="notice notice-error">' +
					'<p><strong><?php esc_html_e( 'Connection failed!', 'wp-ai-site-generator' ); ?></strong></p>' +
					'<p>' + message + '</p>' +
					'</div>'
				);
			},
			complete: function() {
				button.prop('disabled', false).text('<?php esc_html_e( 'Test Connection', 'wp-ai-site-generator' ); ?>');
			}
		});
	});
});
</script>