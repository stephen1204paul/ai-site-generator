<?php
/**
 * AI Chat Interface view
 *
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/admin/partials
 * @since      1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Check if chat interface is enabled
$features = get_option( 'waisg_features', array() );
if ( empty( $features['chat_interface'] ) ) {
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'AI Chat Assistant', 'wp-ai-site-generator' ); ?></h1>
		<div class="notice notice-info">
			<p>
				<?php
				printf(
					/* translators: %s: Link to settings page */
					__( 'Chat interface is currently disabled. <a href="%s">Enable it in settings</a> to start using the AI assistant.', 'wp-ai-site-generator' ),
					esc_url( admin_url( 'admin.php?page=wp-ai-site-generator-settings#features' ) )
				);
				?>
			</p>
		</div>
	</div>
	<?php
	return;
}
?>

<div class="wrap waisg-chat-page">
	<h1><?php esc_html_e( 'AI Chat Assistant', 'wp-ai-site-generator' ); ?></h1>

	<div class="waisg-chat-container">
		<div class="waisg-chat-main">
			<!-- Chat Header -->
			<div class="waisg-chat-header">
				<div class="waisg-chat-title">
					<span class="dashicons dashicons-format-chat"></span>
					<h2><?php esc_html_e( 'AI Site Generator Assistant', 'wp-ai-site-generator' ); ?></h2>
				</div>
				<div class="waisg-chat-actions">
					<button type="button" class="button button-secondary" id="clear-chat">
						<span class="dashicons dashicons-trash"></span>
						<?php esc_html_e( 'Clear Chat', 'wp-ai-site-generator' ); ?>
					</button>
					<button type="button" class="button button-secondary" id="export-chat">
						<span class="dashicons dashicons-download"></span>
						<?php esc_html_e( 'Export Chat', 'wp-ai-site-generator' ); ?>
					</button>
				</div>
			</div>

			<!-- Chat Messages Area -->
			<div id="waisg-chat-messages" class="waisg-chat-messages" role="log" aria-live="polite">
				<div class="waisg-chat-welcome">
					<h3><?php esc_html_e( 'Welcome to AI Site Generator Chat!', 'wp-ai-site-generator' ); ?></h3>
					<p><?php esc_html_e( 'I can help you with:', 'wp-ai-site-generator' ); ?></p>
					<ul>
						<li><?php esc_html_e( 'Creating new websites and pages', 'wp-ai-site-generator' ); ?></li>
						<li><?php esc_html_e( 'Generating content for specific industries', 'wp-ai-site-generator' ); ?></li>
						<li><?php esc_html_e( 'Customizing templates and designs', 'wp-ai-site-generator' ); ?></li>
						<li><?php esc_html_e( 'SEO optimization suggestions', 'wp-ai-site-generator' ); ?></li>
						<li><?php esc_html_e( 'Content improvement and editing', 'wp-ai-site-generator' ); ?></li>
					</ul>
					<div class="waisg-quick-prompts">
						<h4><?php esc_html_e( 'Quick Prompts:', 'wp-ai-site-generator' ); ?></h4>
						<div class="waisg-prompt-buttons">
							<button type="button" class="waisg-prompt-btn" data-prompt="Create a professional website for a law firm">
								<?php esc_html_e( 'Law Firm Website', 'wp-ai-site-generator' ); ?>
							</button>
							<button type="button" class="waisg-prompt-btn" data-prompt="Generate an e-commerce site for selling handmade crafts">
								<?php esc_html_e( 'E-commerce Site', 'wp-ai-site-generator' ); ?>
							</button>
							<button type="button" class="waisg-prompt-btn" data-prompt="Build a portfolio website for a photographer">
								<?php esc_html_e( 'Photography Portfolio', 'wp-ai-site-generator' ); ?>
							</button>
							<button type="button" class="waisg-prompt-btn" data-prompt="Create a blog about healthy living and nutrition">
								<?php esc_html_e( 'Health Blog', 'wp-ai-site-generator' ); ?>
							</button>
							<button type="button" class="waisg-prompt-btn" data-prompt="Generate a restaurant website with menu pages">
								<?php esc_html_e( 'Restaurant Website', 'wp-ai-site-generator' ); ?>
							</button>
							<button type="button" class="waisg-prompt-btn" data-prompt="Build a corporate website for a tech startup">
								<?php esc_html_e( 'Tech Startup Site', 'wp-ai-site-generator' ); ?>
							</button>
						</div>
					</div>
				</div>
			</div>

			<!-- Chat Input Area -->
			<div class="waisg-chat-input-container">
				<div class="waisg-chat-input-wrapper">
					<textarea
						id="waisg-chat-input"
						class="waisg-chat-input"
						placeholder="<?php esc_attr_e( 'Type your message here... (Shift+Enter for new line)', 'wp-ai-site-generator' ); ?>"
						rows="3"
						aria-label="<?php esc_attr_e( 'Chat message input', 'wp-ai-site-generator' ); ?>"></textarea>
					<div class="waisg-chat-input-actions">
						<div class="waisg-input-options">
							<label>
								<input type="checkbox" id="waisg-stream-response" checked />
								<?php esc_html_e( 'Stream Response', 'wp-ai-site-generator' ); ?>
							</label>
							<label>
								<input type="checkbox" id="waisg-auto-generate" />
								<?php esc_html_e( 'Auto-generate on approval', 'wp-ai-site-generator' ); ?>
							</label>
						</div>
						<button type="button"
								id="waisg-send-message"
								class="button button-primary"
								disabled>
							<span class="dashicons dashicons-arrow-right-alt"></span>
							<?php esc_html_e( 'Send', 'wp-ai-site-generator' ); ?>
						</button>
					</div>
				</div>
				<div class="waisg-typing-indicator" style="display: none;">
					<span></span>
					<span></span>
					<span></span>
				</div>
			</div>
		</div>

		<!-- Sidebar -->
		<div class="waisg-chat-sidebar">
			<!-- Chat Settings -->
			<div class="waisg-chat-panel">
				<h3><?php esc_html_e( 'Chat Settings', 'wp-ai-site-generator' ); ?></h3>

				<div class="waisg-chat-setting">
					<label for="waisg-chat-provider">
						<?php esc_html_e( 'AI Provider:', 'wp-ai-site-generator' ); ?>
					</label>
					<select id="waisg-chat-provider" class="waisg-select">
						<option value="auto"><?php esc_html_e( 'Auto-select', 'wp-ai-site-generator' ); ?></option>
						<option value="openai">OpenAI</option>
						<option value="anthropic">Anthropic Claude</option>
						<option value="google">Google Gemini</option>
					</select>
				</div>

				<div class="waisg-chat-setting">
					<label for="waisg-chat-model">
						<?php esc_html_e( 'Model:', 'wp-ai-site-generator' ); ?>
					</label>
					<select id="waisg-chat-model" class="waisg-select">
						<option value="default"><?php esc_html_e( 'Default', 'wp-ai-site-generator' ); ?></option>
						<option value="gpt-4">GPT-4</option>
						<option value="gpt-3.5-turbo">GPT-3.5 Turbo</option>
						<option value="claude-3">Claude 3</option>
						<option value="gemini-pro">Gemini Pro</option>
					</select>
				</div>

				<div class="waisg-chat-setting">
					<label for="waisg-chat-temperature">
						<?php esc_html_e( 'Creativity:', 'wp-ai-site-generator' ); ?>
						<span id="temperature-value">0.7</span>
					</label>
					<input type="range"
						   id="waisg-chat-temperature"
						   min="0"
						   max="1"
						   step="0.1"
						   value="0.7" />
				</div>

				<div class="waisg-chat-setting">
					<label for="waisg-chat-context">
						<?php esc_html_e( 'Context Window:', 'wp-ai-site-generator' ); ?>
					</label>
					<select id="waisg-chat-context" class="waisg-select">
						<option value="5"><?php esc_html_e( 'Last 5 messages', 'wp-ai-site-generator' ); ?></option>
						<option value="10" selected><?php esc_html_e( 'Last 10 messages', 'wp-ai-site-generator' ); ?></option>
						<option value="20"><?php esc_html_e( 'Last 20 messages', 'wp-ai-site-generator' ); ?></option>
						<option value="all"><?php esc_html_e( 'All messages', 'wp-ai-site-generator' ); ?></option>
					</select>
				</div>
			</div>

			<!-- Chat History -->
			<div class="waisg-chat-panel">
				<h3><?php esc_html_e( 'Recent Conversations', 'wp-ai-site-generator' ); ?></h3>
				<div id="waisg-chat-history" class="waisg-chat-history">
					<p class="description"><?php esc_html_e( 'No previous conversations', 'wp-ai-site-generator' ); ?></p>
				</div>
			</div>

			<!-- Saved Prompts -->
			<div class="waisg-chat-panel">
				<h3><?php esc_html_e( 'Saved Prompts', 'wp-ai-site-generator' ); ?></h3>
				<div id="waisg-saved-prompts" class="waisg-saved-prompts">
					<button type="button" class="button button-secondary button-small" id="save-current-prompt">
						<span class="dashicons dashicons-plus-alt2"></span>
						<?php esc_html_e( 'Save Current', 'wp-ai-site-generator' ); ?>
					</button>
					<div class="waisg-prompts-list">
						<!-- Saved prompts will be loaded here -->
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- Chat React Mount Point -->
<div id="waisg-chat-react-root"></div>

<script>
jQuery(document).ready(function($) {
	var chatMessages = $('#waisg-chat-messages');
	var chatInput = $('#waisg-chat-input');
	var sendButton = $('#waisg-send-message');
	var typingIndicator = $('.waisg-typing-indicator');
	var isProcessing = false;

	// Enable/disable send button based on input
	chatInput.on('input', function() {
		sendButton.prop('disabled', $(this).val().trim().length === 0 || isProcessing);
	});

	// Handle Enter key (send) and Shift+Enter (new line)
	chatInput.on('keydown', function(e) {
		if (e.key === 'Enter' && !e.shiftKey) {
			e.preventDefault();
			if (!sendButton.prop('disabled')) {
				sendMessage();
			}
		}
	});

	// Send button click
	sendButton.on('click', sendMessage);

	// Quick prompt buttons
	$('.waisg-prompt-btn').on('click', function() {
		var prompt = $(this).data('prompt');
		chatInput.val(prompt);
		sendButton.prop('disabled', false);
		chatInput.focus();
	});

	// Clear chat
	$('#clear-chat').on('click', function() {
		if (confirm('<?php esc_html_e( 'Are you sure you want to clear the chat history?', 'wp-ai-site-generator' ); ?>')) {
			$('.waisg-chat-welcome').show();
			$('.waisg-chat-message').remove();
			// Clear from localStorage
			localStorage.removeItem('waisg_chat_history');
		}
	});

	// Temperature slider
	$('#waisg-chat-temperature').on('input', function() {
		$('#temperature-value').text($(this).val());
	});

	// Export chat
	$('#export-chat').on('click', function() {
		var messages = [];
		$('.waisg-chat-message').each(function() {
			messages.push({
				role: $(this).hasClass('user') ? 'user' : 'assistant',
				content: $(this).find('.message-content').text(),
				timestamp: $(this).find('.message-time').text()
			});
		});

		var dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(messages, null, 2));
		var downloadAnchorNode = document.createElement('a');
		downloadAnchorNode.setAttribute("href", dataStr);
		downloadAnchorNode.setAttribute("download", "chat-export-" + Date.now() + ".json");
		document.body.appendChild(downloadAnchorNode);
		downloadAnchorNode.click();
		downloadAnchorNode.remove();
	});

	function sendMessage() {
		var message = chatInput.val().trim();
		if (!message || isProcessing) return;

		isProcessing = true;
		sendButton.prop('disabled', true);

		// Hide welcome message
		$('.waisg-chat-welcome').hide();

		// Add user message
		addMessage('user', message);

		// Clear input
		chatInput.val('');

		// Show typing indicator
		typingIndicator.show();

		// Get settings
		var settings = {
			provider: $('#waisg-chat-provider').val(),
			model: $('#waisg-chat-model').val(),
			temperature: parseFloat($('#waisg-chat-temperature').val()),
			context_window: $('#waisg-chat-context').val(),
			stream: $('#waisg-stream-response').is(':checked')
		};

		// Send to API
		$.ajax({
			url: waisg_admin.rest_url + 'chat/message',
			method: 'POST',
			beforeSend: function(xhr) {
				xhr.setRequestHeader('X-WP-Nonce', waisg_admin.rest_nonce);
			},
			data: JSON.stringify({
				message: message,
				settings: settings,
				context: getConversationContext()
			}),
			contentType: 'application/json',
			success: function(response) {
				typingIndicator.hide();

				if (response.success) {
					addMessage('assistant', response.data.response);

					// If auto-generate is enabled and response contains generation data
					if ($('#waisg-auto-generate').is(':checked') && response.data.generation_data) {
						startGeneration(response.data.generation_data);
					}
				} else {
					addMessage('assistant', '<?php esc_html_e( 'Sorry, I encountered an error. Please try again.', 'wp-ai-site-generator' ); ?>', 'error');
				}
			},
			error: function(xhr) {
				typingIndicator.hide();
				var errorMsg = xhr.responseJSON && xhr.responseJSON.message
					? xhr.responseJSON.message
					: '<?php esc_html_e( 'Connection error. Please check your settings and try again.', 'wp-ai-site-generator' ); ?>';
				addMessage('assistant', errorMsg, 'error');
			},
			complete: function() {
				isProcessing = false;
				sendButton.prop('disabled', chatInput.val().trim().length === 0);
			}
		});
	}

	function addMessage(role, content, type) {
		var messageClass = 'waisg-chat-message ' + role;
		if (type) messageClass += ' ' + type;

		var timestamp = new Date().toLocaleTimeString();
		var avatar = role === 'user'
			? '<span class="dashicons dashicons-admin-users"></span>'
			: '<span class="dashicons dashicons-format-chat"></span>';

		var messageHtml =
			'<div class="' + messageClass + '">' +
				'<div class="message-avatar">' + avatar + '</div>' +
				'<div class="message-bubble">' +
					'<div class="message-content">' + escapeHtml(content) + '</div>' +
					'<div class="message-time">' + timestamp + '</div>' +
				'</div>' +
			'</div>';

		chatMessages.append(messageHtml);
		chatMessages.scrollTop(chatMessages[0].scrollHeight);

		// Save to localStorage
		saveToHistory(role, content, timestamp);
	}

	function getConversationContext() {
		var contextWindow = $('#waisg-chat-context').val();
		var messages = [];

		$('.waisg-chat-message').each(function() {
			messages.push({
				role: $(this).hasClass('user') ? 'user' : 'assistant',
				content: $(this).find('.message-content').text()
			});
		});

		if (contextWindow !== 'all' && messages.length > parseInt(contextWindow)) {
			messages = messages.slice(-parseInt(contextWindow));
		}

		return messages;
	}

	function saveToHistory(role, content, timestamp) {
		var history = JSON.parse(localStorage.getItem('waisg_chat_history') || '[]');
		history.push({
			role: role,
			content: content,
			timestamp: timestamp,
			date: new Date().toISOString()
		});

		// Keep only last 100 messages in localStorage
		if (history.length > 100) {
			history = history.slice(-100);
		}

		localStorage.setItem('waisg_chat_history', JSON.stringify(history));
	}

	function startGeneration(generationData) {
		// Redirect to generation page with pre-filled data
		var url = '<?php echo esc_url( admin_url( 'admin.php?page=wp-ai-site-generator-generate' ) ); ?>';
		url += '&prompt=' + encodeURIComponent(generationData.prompt);
		url += '&auto_start=1';

		window.location.href = url;
	}

	function escapeHtml(text) {
		var map = {
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#039;'
		};

		return text.replace(/[&<>"']/g, function(m) { return map[m]; });
	}

	// Load chat history on page load
	function loadChatHistory() {
		var history = JSON.parse(localStorage.getItem('waisg_chat_history') || '[]');

		if (history.length > 0) {
			$('.waisg-chat-welcome').hide();

			// Load last 20 messages
			var recentHistory = history.slice(-20);
			recentHistory.forEach(function(msg) {
				addMessage(msg.role, msg.content);
			});
		}
	}

	// Initialize
	loadChatHistory();
});
</script>