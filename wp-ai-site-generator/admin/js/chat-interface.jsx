/**
 * React Chat Interface Component
 *
 * @package WPAISiteGenerator
 * @since   1.0.0
 */

import { useState, useEffect, useRef, useCallback } from '@wordpress/element';
import {
	Button,
	TextareaControl,
	SelectControl,
	RangeControl,
	CheckboxControl,
	Spinner,
	Notice,
	Panel,
	PanelBody,
	PanelRow,
	Card,
	CardBody,
	CardHeader,
	CardFooter,
	__experimentalHStack as HStack,
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { format } from '@wordpress/date';

const ChatInterface = () => {
	const [messages, setMessages] = useState([]);
	const [inputMessage, setInputMessage] = useState('');
	const [isLoading, setIsLoading] = useState(false);
	const [error, setError] = useState(null);
	const [settings, setSettings] = useState({
		provider: 'auto',
		model: 'default',
		temperature: 0.7,
		contextWindow: 10,
		streamResponse: true,
		autoGenerate: false,
	});
	const [conversationHistory, setConversationHistory] = useState([]);
	const [savedPrompts, setSavedPrompts] = useState([]);
	const messagesEndRef = useRef(null);
	const abortControllerRef = useRef(null);

	// Load saved data on mount
	useEffect(() => {
		loadConversationHistory();
		loadSavedPrompts();
		loadSettings();
	}, []);

	// Scroll to bottom when messages change
	useEffect(() => {
		scrollToBottom();
	}, [messages]);

	const scrollToBottom = () => {
		messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
	};

	const loadConversationHistory = () => {
		const history = localStorage.getItem('waisg_chat_history');
		if (history) {
			try {
				setConversationHistory(JSON.parse(history));
			} catch (e) {
				console.error('Failed to load chat history:', e);
			}
		}
	};

	const loadSavedPrompts = () => {
		const prompts = localStorage.getItem('waisg_saved_prompts');
		if (prompts) {
			try {
				setSavedPrompts(JSON.parse(prompts));
			} catch (e) {
				console.error('Failed to load saved prompts:', e);
			}
		}
	};

	const loadSettings = () => {
		const savedSettings = localStorage.getItem('waisg_chat_settings');
		if (savedSettings) {
			try {
				setSettings({ ...settings, ...JSON.parse(savedSettings) });
			} catch (e) {
				console.error('Failed to load settings:', e);
			}
		}
	};

	const saveSettings = useCallback((newSettings) => {
		setSettings(newSettings);
		localStorage.setItem('waisg_chat_settings', JSON.stringify(newSettings));
	}, []);

	const saveToHistory = (role, content) => {
		const newMessage = {
			role,
			content,
			timestamp: new Date().toISOString(),
		};

		const updatedHistory = [...conversationHistory, newMessage].slice(-100);
		setConversationHistory(updatedHistory);
		localStorage.setItem('waisg_chat_history', JSON.stringify(updatedHistory));
	};

	const sendMessage = async () => {
		if (!inputMessage.trim() || isLoading) return;

		const userMessage = {
			id: Date.now(),
			role: 'user',
			content: inputMessage.trim(),
			timestamp: new Date().toISOString(),
		};

		setMessages(prev => [...prev, userMessage]);
		setInputMessage('');
		setIsLoading(true);
		setError(null);
		saveToHistory('user', userMessage.content);

		// Create AbortController for cancelling requests
		abortControllerRef.current = new AbortController();

		try {
			const response = await apiFetch({
				path: '/waisg/v1/chat/message',
				method: 'POST',
				data: {
					message: userMessage.content,
					settings,
					context: getConversationContext(),
				},
				signal: abortControllerRef.current.signal,
			});

			if (response.success) {
				const assistantMessage = {
					id: Date.now() + 1,
					role: 'assistant',
					content: response.data.response,
					timestamp: new Date().toISOString(),
				};

				setMessages(prev => [...prev, assistantMessage]);
				saveToHistory('assistant', assistantMessage.content);

				// Handle auto-generation if enabled
				if (settings.autoGenerate && response.data.generation_data) {
					handleAutoGeneration(response.data.generation_data);
				}
			} else {
				throw new Error(response.message || __('Failed to get response', 'wp-ai-site-generator'));
			}
		} catch (error) {
			if (error.name !== 'AbortError') {
				console.error('Chat error:', error);
				setError(error.message);

				const errorMessage = {
					id: Date.now() + 1,
					role: 'assistant',
					content: __('Sorry, I encountered an error. Please try again.', 'wp-ai-site-generator'),
					timestamp: new Date().toISOString(),
					isError: true,
				};

				setMessages(prev => [...prev, errorMessage]);
			}
		} finally {
			setIsLoading(false);
			abortControllerRef.current = null;
		}
	};

	const getConversationContext = () => {
		const contextWindow = settings.contextWindow === 'all'
			? messages
			: messages.slice(-parseInt(settings.contextWindow));

		return contextWindow.map(msg => ({
			role: msg.role,
			content: msg.content,
		}));
	};

	const handleAutoGeneration = (generationData) => {
		// Redirect to generation page with pre-filled data
		const params = new URLSearchParams({
			prompt: generationData.prompt,
			auto_start: '1',
		});

		window.location.href = `${window.waisgAdmin.adminUrl}admin.php?page=wp-ai-site-generator-generate&${params}`;
	};

	const clearChat = () => {
		if (window.confirm(__('Are you sure you want to clear the chat?', 'wp-ai-site-generator'))) {
			setMessages([]);
			setConversationHistory([]);
			localStorage.removeItem('waisg_chat_history');
		}
	};

	const exportChat = () => {
		const exportData = {
			messages,
			settings,
			exported_at: new Date().toISOString(),
		};

		const blob = new Blob([JSON.stringify(exportData, null, 2)], { type: 'application/json' });
		const url = URL.createObjectURL(blob);
		const a = document.createElement('a');
		a.href = url;
		a.download = `chat-export-${Date.now()}.json`;
		document.body.appendChild(a);
		a.click();
		document.body.removeChild(a);
		URL.revokeObjectURL(url);
	};

	const cancelRequest = () => {
		if (abortControllerRef.current) {
			abortControllerRef.current.abort();
			setIsLoading(false);
		}
	};

	const saveCurrentPrompt = () => {
		if (!inputMessage.trim()) return;

		const prompt = {
			id: Date.now(),
			content: inputMessage.trim(),
			created_at: new Date().toISOString(),
		};

		const updatedPrompts = [...savedPrompts, prompt];
		setSavedPrompts(updatedPrompts);
		localStorage.setItem('waisg_saved_prompts', JSON.stringify(updatedPrompts));
	};

	const useSavedPrompt = (prompt) => {
		setInputMessage(prompt.content);
	};

	const deleteSavedPrompt = (promptId) => {
		const updatedPrompts = savedPrompts.filter(p => p.id !== promptId);
		setSavedPrompts(updatedPrompts);
		localStorage.setItem('waisg_saved_prompts', JSON.stringify(updatedPrompts));
	};

	const quickPrompts = [
		{ label: __('Law Firm Website', 'wp-ai-site-generator'), prompt: 'Create a professional website for a law firm' },
		{ label: __('E-commerce Site', 'wp-ai-site-generator'), prompt: 'Generate an e-commerce site for selling handmade crafts' },
		{ label: __('Photography Portfolio', 'wp-ai-site-generator'), prompt: 'Build a portfolio website for a photographer' },
		{ label: __('Health Blog', 'wp-ai-site-generator'), prompt: 'Create a blog about healthy living and nutrition' },
		{ label: __('Restaurant Website', 'wp-ai-site-generator'), prompt: 'Generate a restaurant website with menu pages' },
		{ label: __('Tech Startup Site', 'wp-ai-site-generator'), prompt: 'Build a corporate website for a tech startup' },
	];

	return (
		<div className="waisg-chat-interface">
			<Card className="waisg-chat-card">
				<CardHeader className="waisg-chat-header">
					<HStack alignment="space-between">
						<h2>{__('AI Chat Assistant', 'wp-ai-site-generator')}</h2>
						<HStack>
							<Button
								variant="secondary"
								onClick={clearChat}
								disabled={messages.length === 0}
								icon="trash"
							>
								{__('Clear', 'wp-ai-site-generator')}
							</Button>
							<Button
								variant="secondary"
								onClick={exportChat}
								disabled={messages.length === 0}
								icon="download"
							>
								{__('Export', 'wp-ai-site-generator')}
							</Button>
						</HStack>
					</HStack>
				</CardHeader>

				<CardBody className="waisg-chat-body">
					<div className="waisg-chat-messages">
						{messages.length === 0 ? (
							<div className="waisg-chat-welcome">
								<h3>{__('Welcome to AI Site Generator Chat!', 'wp-ai-site-generator')}</h3>
								<p>{__('I can help you with:', 'wp-ai-site-generator')}</p>
								<ul>
									<li>{__('Creating new websites and pages', 'wp-ai-site-generator')}</li>
									<li>{__('Generating content for specific industries', 'wp-ai-site-generator')}</li>
									<li>{__('Customizing templates and designs', 'wp-ai-site-generator')}</li>
									<li>{__('SEO optimization suggestions', 'wp-ai-site-generator')}</li>
								</ul>

								<div className="waisg-quick-prompts">
									<h4>{__('Quick Prompts:', 'wp-ai-site-generator')}</h4>
									<HStack wrap>
										{quickPrompts.map((item, index) => (
											<Button
												key={index}
												variant="secondary"
												onClick={() => setInputMessage(item.prompt)}
											>
												{item.label}
											</Button>
										))}
									</HStack>
								</div>
							</div>
						) : (
							messages.map(message => (
								<div
									key={message.id}
									className={`waisg-chat-message waisg-chat-message--${message.role} ${message.isError ? 'is-error' : ''}`}
								>
									<div className="waisg-chat-message__avatar">
										{message.role === 'user' ? '👤' : '🤖'}
									</div>
									<div className="waisg-chat-message__content">
										<div className="waisg-chat-message__text">
											{message.content}
										</div>
										<div className="waisg-chat-message__time">
											{format('g:i a', message.timestamp)}
										</div>
									</div>
								</div>
							))
						)}

						{isLoading && (
							<div className="waisg-chat-message waisg-chat-message--assistant">
								<div className="waisg-chat-message__avatar">🤖</div>
								<div className="waisg-chat-message__content">
									<Spinner />
									<Button
										variant="link"
										onClick={cancelRequest}
										className="waisg-cancel-button"
									>
										{__('Cancel', 'wp-ai-site-generator')}
									</Button>
								</div>
							</div>
						)}

						<div ref={messagesEndRef} />
					</div>

					{error && (
						<Notice status="error" onRemove={() => setError(null)}>
							{error}
						</Notice>
					)}
				</CardBody>

				<CardFooter className="waisg-chat-footer">
					<VStack spacing={3}>
						<TextareaControl
							value={inputMessage}
							onChange={setInputMessage}
							placeholder={__('Type your message here... (Shift+Enter for new line)', 'wp-ai-site-generator')}
							rows={3}
							onKeyDown={(e) => {
								if (e.key === 'Enter' && !e.shiftKey) {
									e.preventDefault();
									sendMessage();
								}
							}}
						/>

						<HStack alignment="space-between" expanded>
							<HStack>
								<CheckboxControl
									label={__('Stream Response', 'wp-ai-site-generator')}
									checked={settings.streamResponse}
									onChange={(streamResponse) => saveSettings({ ...settings, streamResponse })}
								/>
								<CheckboxControl
									label={__('Auto-generate', 'wp-ai-site-generator')}
									checked={settings.autoGenerate}
									onChange={(autoGenerate) => saveSettings({ ...settings, autoGenerate })}
								/>
							</HStack>

							<HStack>
								{inputMessage.trim() && (
									<Button
										variant="secondary"
										onClick={saveCurrentPrompt}
										icon="star-empty"
									>
										{__('Save Prompt', 'wp-ai-site-generator')}
									</Button>
								)}
								<Button
									variant="primary"
									onClick={sendMessage}
									disabled={!inputMessage.trim() || isLoading}
									icon="arrow-right-alt"
								>
									{__('Send', 'wp-ai-site-generator')}
								</Button>
							</HStack>
						</HStack>
					</VStack>
				</CardFooter>
			</Card>

			<div className="waisg-chat-sidebar">
				<Panel>
					<PanelBody title={__('Chat Settings', 'wp-ai-site-generator')} initialOpen>
						<PanelRow>
							<SelectControl
								label={__('AI Provider', 'wp-ai-site-generator')}
								value={settings.provider}
								onChange={(provider) => saveSettings({ ...settings, provider })}
								options={[
									{ value: 'auto', label: __('Auto-select', 'wp-ai-site-generator') },
									{ value: 'openai', label: 'OpenAI' },
									{ value: 'anthropic', label: 'Anthropic Claude' },
									{ value: 'google', label: 'Google Gemini' },
								]}
							/>
						</PanelRow>

						<PanelRow>
							<SelectControl
								label={__('Model', 'wp-ai-site-generator')}
								value={settings.model}
								onChange={(model) => saveSettings({ ...settings, model })}
								options={[
									{ value: 'default', label: __('Default', 'wp-ai-site-generator') },
									{ value: 'gpt-4', label: 'GPT-4' },
									{ value: 'gpt-3.5-turbo', label: 'GPT-3.5 Turbo' },
									{ value: 'claude-3', label: 'Claude 3' },
									{ value: 'gemini-pro', label: 'Gemini Pro' },
								]}
							/>
						</PanelRow>

						<PanelRow>
							<RangeControl
								label={__('Creativity', 'wp-ai-site-generator')}
								value={settings.temperature}
								onChange={(temperature) => saveSettings({ ...settings, temperature })}
								min={0}
								max={1}
								step={0.1}
							/>
						</PanelRow>

						<PanelRow>
							<SelectControl
								label={__('Context Window', 'wp-ai-site-generator')}
								value={settings.contextWindow}
								onChange={(contextWindow) => saveSettings({ ...settings, contextWindow })}
								options={[
									{ value: '5', label: __('Last 5 messages', 'wp-ai-site-generator') },
									{ value: '10', label: __('Last 10 messages', 'wp-ai-site-generator') },
									{ value: '20', label: __('Last 20 messages', 'wp-ai-site-generator') },
									{ value: 'all', label: __('All messages', 'wp-ai-site-generator') },
								]}
							/>
						</PanelRow>
					</PanelBody>

					<PanelBody title={__('Saved Prompts', 'wp-ai-site-generator')} initialOpen={false}>
						{savedPrompts.length === 0 ? (
							<p>{__('No saved prompts yet.', 'wp-ai-site-generator')}</p>
						) : (
							<VStack spacing={2}>
								{savedPrompts.map(prompt => (
									<Card key={prompt.id} size="small">
										<CardBody>
											<p>{prompt.content}</p>
											<HStack>
												<Button
													variant="link"
													onClick={() => useSavedPrompt(prompt)}
												>
													{__('Use', 'wp-ai-site-generator')}
												</Button>
												<Button
													variant="link"
													isDestructive
													onClick={() => deleteSavedPrompt(prompt.id)}
												>
													{__('Delete', 'wp-ai-site-generator')}
												</Button>
											</HStack>
										</CardBody>
									</Card>
								))}
							</VStack>
						)}
					</PanelBody>

					<PanelBody title={__('Recent Conversations', 'wp-ai-site-generator')} initialOpen={false}>
						{conversationHistory.length === 0 ? (
							<p>{__('No previous conversations.', 'wp-ai-site-generator')}</p>
						) : (
							<VStack spacing={2}>
								{conversationHistory.slice(-10).reverse().map((msg, index) => (
									<Card key={index} size="small">
										<CardBody>
											<small>{format('M j, g:i a', msg.timestamp)}</small>
											<p>{msg.content.substring(0, 100)}...</p>
										</CardBody>
									</Card>
								))}
							</VStack>
						)}
					</PanelBody>
				</Panel>
			</div>
		</div>
	);
};

export default ChatInterface;