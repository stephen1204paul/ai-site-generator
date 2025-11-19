/**
 * Enhanced Chat Interface Component.
 *
 * Advanced chat interface with conversation branching, context visualization,
 * smart suggestions, history sidebar, and enhanced UX features.
 *
 * @package WPAISiteGenerator
 * @since 1.0.0
 */

import React, { useState, useEffect, useRef, useCallback } from 'react';
import {
	Panel,
	PanelBody,
	PanelRow,
	Button,
	ButtonGroup,
	TextareaControl,
	SelectControl,
	Modal,
	Notice,
	Spinner,
	SearchControl,
	Card,
	CardBody,
	CardHeader,
	CardFooter,
	DropdownMenu,
	MenuGroup,
	MenuItem,
	ToolbarGroup,
	ToolbarButton,
	Tooltip,
	TabPanel,
	__experimentalHStack as HStack,
	__experimentalVStack as VStack,
	__experimentalSpacer as Spacer,
	__experimentalText as Text,
	__experimentalHeading as Heading,
} from '@wordpress/components';
import {
	useState as useWordPressState,
	useEffect as useWordPressEffect,
} from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	comment,
	plusCircle,
	download,
	upload,
	redo,
	edit,
	code,
	image,
	formatListBullets,
	archive,
	search,
	closeSmall,
	chevronRight,
	chevronDown,
	copy,
} from '@wordpress/icons';
import apiFetch from '@wordpress/api-fetch';

// Syntax highlighting for code blocks
import Prism from 'prismjs';
import 'prismjs/themes/prism-tomorrow.css';
import 'prismjs/components/prism-javascript';
import 'prismjs/components/prism-css';
import 'prismjs/components/prism-markup';
import 'prismjs/components/prism-php';

/**
 * Enhanced Chat Interface Component.
 *
 * @since 1.0.0
 * @param {Object} props Component properties.
 * @returns {JSX.Element} Enhanced chat interface.
 */
const EnhancedChatInterface = ( props ) => {
	// State management
	const [conversations, setConversations] = useState([]);
	const [activeConversation, setActiveConversation] = useState(null);
	const [messages, setMessages] = useState([]);
	const [inputMessage, setInputMessage] = useState('');
	const [isLoading, setIsLoading] = useState(false);
	const [error, setError] = useState(null);
	const [suggestions, setSuggestions] = useState([]);
	const [showTemplates, setShowTemplates] = useState(false);
	const [templates, setTemplates] = useState([]);
	const [searchTerm, setSearchTerm] = useState('');
	const [showBranchDialog, setShowBranchDialog] = useState(false);
	const [branchPoint, setBranchPoint] = useState(null);
	const [context, setContext] = useState(null);
	const [showExportDialog, setShowExportDialog] = useState(false);
	const [showImportDialog, setShowImportDialog] = useState(false);
	const [selectedMessage, setSelectedMessage] = useState(null);
	const [editingMessage, setEditingMessage] = useState(null);
	const [showContextPanel, setShowContextPanel] = useState(true);
	const [showHistoryPanel, setShowHistoryPanel] = useState(true);
	const [activeTab, setActiveTab] = useState('chat');

	// Refs
	const messagesEndRef = useRef(null);
	const fileInputRef = useRef(null);
	const chatContainerRef = useRef(null);

	// API base URL
	const API_BASE = '/wp-ai-site-generator/v1/chat';

	/**
	 * Load conversations on mount.
	 */
	useEffect(() => {
		loadConversations();
		loadTemplates();
	}, []);

	/**
	 * Auto-scroll to bottom on new messages.
	 */
	useEffect(() => {
		scrollToBottom();
	}, [messages]);

	/**
	 * Load conversation messages when active conversation changes.
	 */
	useEffect(() => {
		if (activeConversation) {
			loadMessages(activeConversation.id);
			loadContext(activeConversation.id);
			loadSuggestions(activeConversation.id);
		}
	}, [activeConversation]);

	/**
	 * Scroll to bottom of messages.
	 */
	const scrollToBottom = () => {
		messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
	};

	/**
	 * Load conversations from API.
	 */
	const loadConversations = async () => {
		try {
			const response = await apiFetch({
				path: `${API_BASE}/conversations`,
			});
			setConversations(response.data || []);
		} catch (err) {
			console.error('Failed to load conversations:', err);
		}
	};

	/**
	 * Load messages for a conversation.
	 */
	const loadMessages = async (conversationId) => {
		try {
			setIsLoading(true);
			const response = await apiFetch({
				path: `${API_BASE}/conversations/${conversationId}/messages`,
			});
			setMessages(response.data || []);
		} catch (err) {
			setError(__('Failed to load messages', 'wp-ai-site-generator'));
		} finally {
			setIsLoading(false);
		}
	};

	/**
	 * Load conversation context.
	 */
	const loadContext = async (conversationId) => {
		try {
			const response = await apiFetch({
				path: `${API_BASE}/context/summarize`,
				method: 'POST',
				data: { conversation_id: conversationId },
			});
			setContext(response.data);
		} catch (err) {
			console.error('Failed to load context:', err);
		}
	};

	/**
	 * Load smart suggestions.
	 */
	const loadSuggestions = async (conversationId) => {
		try {
			const response = await apiFetch({
				path: `${API_BASE}/suggestions?conversation_id=${conversationId}`,
			});
			setSuggestions(response.data || []);
		} catch (err) {
			console.error('Failed to load suggestions:', err);
		}
	};

	/**
	 * Load conversation templates.
	 */
	const loadTemplates = async () => {
		try {
			const response = await apiFetch({
				path: `${API_BASE}/templates`,
			});
			setTemplates(response.data || []);
		} catch (err) {
			console.error('Failed to load templates:', err);
		}
	};

	/**
	 * Create new conversation.
	 */
	const createConversation = async (title, templateId = null) => {
		try {
			setIsLoading(true);
			const response = await apiFetch({
				path: `${API_BASE}/conversations`,
				method: 'POST',
				data: {
					title: title || __('New Conversation', 'wp-ai-site-generator'),
					template_id: templateId,
				},
			});
			const newConversation = response.data;
			setConversations([...conversations, newConversation]);
			setActiveConversation(newConversation);
			setShowTemplates(false);
		} catch (err) {
			setError(__('Failed to create conversation', 'wp-ai-site-generator'));
		} finally {
			setIsLoading(false);
		}
	};

	/**
	 * Send message in conversation.
	 */
	const sendMessage = async () => {
		if (!inputMessage.trim() || !activeConversation) return;

		const userMessage = {
			role: 'user',
			content: inputMessage,
			timestamp: new Date().toISOString(),
		};

		// Optimistically add message
		setMessages([...messages, userMessage]);
		setInputMessage('');
		setIsLoading(true);

		try {
			const response = await apiFetch({
				path: `${API_BASE}/conversations/${activeConversation.id}/messages`,
				method: 'POST',
				data: {
					content: userMessage.content,
					role: 'user',
				},
			});

			// Add AI response if available
			if (response.data.ai_response) {
				setMessages(prev => [...prev, response.data.ai_response]);
			}

			// Refresh suggestions
			loadSuggestions(activeConversation.id);
		} catch (err) {
			setError(__('Failed to send message', 'wp-ai-site-generator'));
		} finally {
			setIsLoading(false);
		}
	};

	/**
	 * Create conversation branch.
	 */
	const createBranch = async (messageId, title) => {
		try {
			setIsLoading(true);
			const response = await apiFetch({
				path: `${API_BASE}/branch`,
				method: 'POST',
				data: {
					conversation_id: activeConversation.id,
					branch_point_message_id: messageId,
					data: { title },
				},
			});
			const newBranch = response.data;
			setConversations([...conversations, newBranch]);
			setActiveConversation(newBranch);
			setShowBranchDialog(false);
		} catch (err) {
			setError(__('Failed to create branch', 'wp-ai-site-generator'));
		} finally {
			setIsLoading(false);
		}
	};

	/**
	 * Switch conversation topic.
	 */
	const switchTopic = async (newTopic) => {
		try {
			await apiFetch({
				path: `${API_BASE}/context/switch`,
				method: 'POST',
				data: {
					conversation_id: activeConversation.id,
					new_topic: newTopic,
				},
			});
			loadContext(activeConversation.id);
		} catch (err) {
			setError(__('Failed to switch topic', 'wp-ai-site-generator'));
		}
	};

	/**
	 * Export conversation.
	 */
	const exportConversation = async (format) => {
		try {
			const response = await apiFetch({
				path: `${API_BASE}/export`,
				method: 'POST',
				data: {
					conversation_id: activeConversation.id,
					format,
				},
			});

			// Create download link
			const blob = new Blob([response.data.content], {
				type: response.data.mime,
			});
			const url = URL.createObjectURL(blob);
			const a = document.createElement('a');
			a.href = url;
			a.download = response.filename;
			a.click();
			URL.revokeObjectURL(url);

			setShowExportDialog(false);
		} catch (err) {
			setError(__('Failed to export conversation', 'wp-ai-site-generator'));
		}
	};

	/**
	 * Import conversation.
	 */
	const importConversation = async (file) => {
		try {
			const content = await file.text();
			const response = await apiFetch({
				path: `${API_BASE}/import`,
				method: 'POST',
				data: {
					data: content,
					format: 'json',
				},
			});
			const imported = response.data;
			setConversations([...conversations, imported]);
			setActiveConversation(imported);
			setShowImportDialog(false);
		} catch (err) {
			setError(__('Failed to import conversation', 'wp-ai-site-generator'));
		}
	};

	/**
	 * Regenerate message.
	 */
	const regenerateMessage = async (messageIndex) => {
		const message = messages[messageIndex];
		if (message.role !== 'assistant') return;

		setIsLoading(true);
		try {
			// Remove current and subsequent messages
			const newMessages = messages.slice(0, messageIndex);
			setMessages(newMessages);

			// Resend last user message to regenerate
			const lastUserMessage = newMessages.findLast(m => m.role === 'user');
			if (lastUserMessage) {
				const response = await apiFetch({
					path: `${API_BASE}/conversations/${activeConversation.id}/messages`,
					method: 'POST',
					data: {
						content: lastUserMessage.content,
						role: 'user',
					},
				});

				if (response.data.ai_response) {
					setMessages([...newMessages, response.data.ai_response]);
				}
			}
		} catch (err) {
			setError(__('Failed to regenerate message', 'wp-ai-site-generator'));
		} finally {
			setIsLoading(false);
		}
	};

	/**
	 * Copy message to clipboard.
	 */
	const copyMessage = (content) => {
		navigator.clipboard.writeText(content).then(
			() => {
				// Could show a toast notification
			},
			() => {
				setError(__('Failed to copy message', 'wp-ai-site-generator'));
			}
		);
	};

	/**
	 * Render message content with formatting.
	 */
	const renderMessageContent = (content) => {
		// Parse markdown-like content
		let formatted = content;

		// Code blocks
		formatted = formatted.replace(
			/```(\w+)?\n([\s\S]*?)```/g,
			(match, lang, code) => {
				const language = lang || 'plaintext';
				const highlighted = Prism.highlight(
					code.trim(),
					Prism.languages[language] || Prism.languages.plaintext,
					language
				);
				return `<pre class="language-${language}"><code>${highlighted}</code></pre>`;
			}
		);

		// Inline code
		formatted = formatted.replace(
			/`([^`]+)`/g,
			'<code class="inline-code">$1</code>'
		);

		// Bold
		formatted = formatted.replace(
			/\*\*([^*]+)\*\*/g,
			'<strong>$1</strong>'
		);

		// Italic
		formatted = formatted.replace(
			/\*([^*]+)\*/g,
			'<em>$1</em>'
		);

		// Links
		formatted = formatted.replace(
			/\[([^\]]+)\]\(([^)]+)\)/g,
			'<a href="$2" target="_blank" rel="noopener">$1</a>'
		);

		// Lists
		formatted = formatted.replace(
			/^\* (.+)$/gm,
			'<li>$1</li>'
		);
		formatted = formatted.replace(
			/(<li>.*<\/li>)/s,
			'<ul>$1</ul>'
		);

		// Paragraphs
		formatted = formatted.split('\n\n').map(p => `<p>${p}</p>`).join('');

		return <div dangerouslySetInnerHTML={{ __html: formatted }} />;
	};

	/**
	 * Render conversation list item.
	 */
	const renderConversationItem = (conversation) => {
		const isActive = activeConversation?.id === conversation.id;
		const hasBranches = conversation.branches?.length > 0;

		return (
			<div
				key={conversation.id}
				className={`conversation-item ${isActive ? 'active' : ''}`}
				onClick={() => setActiveConversation(conversation)}
			>
				<HStack alignment="center" spacing={2}>
					{hasBranches && (
						<span className="branch-indicator">
							{chevronRight}
						</span>
					)}
					<VStack spacing={1}>
						<Text weight="semibold" size="small">
							{conversation.title}
						</Text>
						<Text variant="muted" size="small">
							{conversation.stats?.total_messages || 0} messages
						</Text>
					</VStack>
				</HStack>
			</div>
		);
	};

	/**
	 * Render message item.
	 */
	const renderMessage = (message, index) => {
		const isUser = message.role === 'user';
		const isEditing = editingMessage === index;

		return (
			<div
				key={index}
				className={`message-item ${message.role}`}
				onMouseEnter={() => setSelectedMessage(index)}
				onMouseLeave={() => setSelectedMessage(null)}
			>
				<Card size="small">
					<CardHeader>
						<HStack alignment="center" spacing={2}>
							<Text weight="semibold">
								{isUser ? __('You', 'wp-ai-site-generator') : __('Assistant', 'wp-ai-site-generator')}
							</Text>
							<Spacer />
							{selectedMessage === index && (
								<ButtonGroup>
									<Tooltip text={__('Copy', 'wp-ai-site-generator')}>
										<Button
											icon={copy}
											size="small"
											variant="tertiary"
											onClick={() => copyMessage(message.content)}
										/>
									</Tooltip>
									{message.role === 'assistant' && (
										<Tooltip text={__('Regenerate', 'wp-ai-site-generator')}>
											<Button
												icon={redo}
												size="small"
												variant="tertiary"
												onClick={() => regenerateMessage(index)}
											/>
										</Tooltip>
									)}
									<Tooltip text={__('Branch from here', 'wp-ai-site-generator')}>
										<Button
											icon={plusCircle}
											size="small"
											variant="tertiary"
											onClick={() => {
												setBranchPoint(message.id);
												setShowBranchDialog(true);
											}}
										/>
									</Tooltip>
								</ButtonGroup>
							)}
						</HStack>
					</CardHeader>
					<CardBody>
						{isEditing ? (
							<TextareaControl
								value={message.content}
								onChange={(value) => {
									const updated = [...messages];
									updated[index].content = value;
									setMessages(updated);
								}}
								onBlur={() => setEditingMessage(null)}
								autoFocus
							/>
						) : (
							renderMessageContent(message.content)
						)}
					</CardBody>
				</Card>
			</div>
		);
	};

	/**
	 * Render context panel.
	 */
	const renderContextPanel = () => {
		if (!context) return null;

		return (
			<Panel header={__('Context & Information', 'wp-ai-site-generator')}>
				<PanelBody title={__('Current Topic', 'wp-ai-site-generator')} initialOpen>
					<Text>{context.current_topic || __('General conversation', 'wp-ai-site-generator')}</Text>
					<Button
						variant="secondary"
						size="small"
						onClick={() => {
							const newTopic = prompt(__('Enter new topic:', 'wp-ai-site-generator'));
							if (newTopic) switchTopic(newTopic);
						}}
					>
						{__('Switch Topic', 'wp-ai-site-generator')}
					</Button>
				</PanelBody>

				<PanelBody title={__('Key Points', 'wp-ai-site-generator')}>
					{context.key_points?.map((point, index) => (
						<div key={index} className="key-point">
							<Text size="small">{point.content || point}</Text>
						</div>
					))}
				</PanelBody>

				<PanelBody title={__('Statistics', 'wp-ai-site-generator')}>
					<VStack spacing={2}>
						<Text size="small">
							{__('Total Tokens:', 'wp-ai-site-generator')} {context.token_count}
						</Text>
						<Text size="small">
							{__('Topics Covered:', 'wp-ai-site-generator')} {context.topics_covered}
						</Text>
						<Text size="small">
							{__('Compression Level:', 'wp-ai-site-generator')} {context.compression_level}
						</Text>
					</VStack>
				</PanelBody>
			</Panel>
		);
	};

	/**
	 * Render suggestions panel.
	 */
	const renderSuggestionsPanel = () => {
		if (!suggestions.length) return null;

		return (
			<Card size="small">
				<CardHeader>
					<Heading level={5}>{__('Suggestions', 'wp-ai-site-generator')}</Heading>
				</CardHeader>
				<CardBody>
					<VStack spacing={2}>
						{suggestions.map((suggestion, index) => (
							<Button
								key={index}
								variant="secondary"
								isBlock
								onClick={() => setInputMessage(suggestion)}
							>
								{suggestion}
							</Button>
						))}
					</VStack>
				</CardBody>
			</Card>
		);
	};

	/**
	 * Main render.
	 */
	return (
		<div className="wpaisg-enhanced-chat">
			<HStack alignment="stretch" spacing={0}>
				{/* Conversation History Sidebar */}
				{showHistoryPanel && (
					<div className="chat-sidebar">
						<Card>
							<CardHeader>
								<HStack alignment="center">
									<Heading level={4}>{__('Conversations', 'wp-ai-site-generator')}</Heading>
									<Spacer />
									<Button
										icon={plusCircle}
										size="small"
										variant="primary"
										onClick={() => setShowTemplates(true)}
									>
										{__('New', 'wp-ai-site-generator')}
									</Button>
								</HStack>
							</CardHeader>
							<CardBody>
								<SearchControl
									value={searchTerm}
									onChange={setSearchTerm}
									placeholder={__('Search conversations...', 'wp-ai-site-generator')}
								/>
								<div className="conversations-list">
									{conversations
										.filter(c =>
											c.title.toLowerCase().includes(searchTerm.toLowerCase())
										)
										.map(renderConversationItem)}
								</div>
							</CardBody>
							<CardFooter>
								<ButtonGroup>
									<Button
										icon={upload}
										size="small"
										variant="secondary"
										onClick={() => setShowImportDialog(true)}
									>
										{__('Import', 'wp-ai-site-generator')}
									</Button>
									<Button
										icon={archive}
										size="small"
										variant="secondary"
									>
										{__('Archive', 'wp-ai-site-generator')}
									</Button>
								</ButtonGroup>
							</CardFooter>
						</Card>
					</div>
				)}

				{/* Main Chat Area */}
				<div className="chat-main">
					<Card>
						<CardHeader>
							<HStack alignment="center">
								<Heading level={4}>
									{activeConversation?.title || __('Select a conversation', 'wp-ai-site-generator')}
								</Heading>
								<Spacer />
								<ButtonGroup>
									<Button
										icon={download}
										size="small"
										variant="secondary"
										onClick={() => setShowExportDialog(true)}
										disabled={!activeConversation}
									>
										{__('Export', 'wp-ai-site-generator')}
									</Button>
									<Button
										icon={showContextPanel ? chevronDown : chevronRight}
										size="small"
										variant="secondary"
										onClick={() => setShowContextPanel(!showContextPanel)}
									>
										{__('Context', 'wp-ai-site-generator')}
									</Button>
								</ButtonGroup>
							</HStack>
						</CardHeader>
						<CardBody>
							<div className="messages-container" ref={chatContainerRef}>
								{isLoading && !messages.length && (
									<div className="loading-placeholder">
										<Spinner />
										<Text>{__('Loading messages...', 'wp-ai-site-generator')}</Text>
									</div>
								)}
								{messages.map(renderMessage)}
								<div ref={messagesEndRef} />
							</div>
						</CardBody>
						<CardFooter>
							{renderSuggestionsPanel()}
							<HStack alignment="stretch" spacing={2}>
								<TextareaControl
									value={inputMessage}
									onChange={setInputMessage}
									placeholder={__('Type your message...', 'wp-ai-site-generator')}
									disabled={!activeConversation || isLoading}
									onKeyDown={(e) => {
										if (e.key === 'Enter' && !e.shiftKey) {
											e.preventDefault();
											sendMessage();
										}
									}}
								/>
								<VStack spacing={2}>
									<Button
										variant="primary"
										onClick={sendMessage}
										disabled={!activeConversation || !inputMessage.trim() || isLoading}
									>
										{isLoading ? <Spinner /> : __('Send', 'wp-ai-site-generator')}
									</Button>
									<DropdownMenu
										icon={formatListBullets}
										label={__('Quick Actions', 'wp-ai-site-generator')}
										popoverProps={{ position: 'top' }}
									>
										{({ onClose }) => (
											<>
												<MenuGroup>
													<MenuItem
														icon={code}
														onClick={() => {
															setInputMessage(inputMessage + '\n```\n\n```');
															onClose();
														}}
													>
														{__('Add Code Block', 'wp-ai-site-generator')}
													</MenuItem>
													<MenuItem
														icon={image}
														onClick={() => {
															// Handle image upload
															onClose();
														}}
													>
														{__('Attach Image', 'wp-ai-site-generator')}
													</MenuItem>
												</MenuGroup>
											</>
										)}
									</DropdownMenu>
								</VStack>
							</HStack>
						</CardFooter>
					</Card>
				</div>

				{/* Context Panel */}
				{showContextPanel && activeConversation && (
					<div className="context-sidebar">
						{renderContextPanel()}
					</div>
				)}
			</HStack>

			{/* Error Notice */}
			{error && (
				<Notice
					status="error"
					isDismissible
					onRemove={() => setError(null)}
				>
					{error}
				</Notice>
			)}

			{/* Templates Modal */}
			{showTemplates && (
				<Modal
					title={__('Start New Conversation', 'wp-ai-site-generator')}
					onRequestClose={() => setShowTemplates(false)}
				>
					<VStack spacing={4}>
						<Button
							variant="secondary"
							isBlock
							onClick={() => createConversation()}
						>
							{__('Blank Conversation', 'wp-ai-site-generator')}
						</Button>
						<hr />
						<Heading level={5}>{__('Templates', 'wp-ai-site-generator')}</Heading>
						{templates.map(template => (
							<Card key={template.id}>
								<CardBody>
									<VStack spacing={2}>
										<Heading level={6}>{template.name}</Heading>
										<Text>{template.description}</Text>
										<Button
											variant="primary"
											onClick={() => createConversation(template.name, template.id)}
										>
											{__('Use Template', 'wp-ai-site-generator')}
										</Button>
									</VStack>
								</CardBody>
							</Card>
						))}
					</VStack>
				</Modal>
			)}

			{/* Branch Dialog */}
			{showBranchDialog && (
				<Modal
					title={__('Create Conversation Branch', 'wp-ai-site-generator')}
					onRequestClose={() => setShowBranchDialog(false)}
				>
					<VStack spacing={4}>
						<Text>
							{__('This will create a new conversation branch from the selected message.', 'wp-ai-site-generator')}
						</Text>
						<TextareaControl
							label={__('Branch Title', 'wp-ai-site-generator')}
							placeholder={__('Enter a title for the branch...', 'wp-ai-site-generator')}
							onChange={(value) => setBranchTitle(value)}
						/>
						<ButtonGroup>
							<Button
								variant="primary"
								onClick={() => createBranch(branchPoint, branchTitle)}
							>
								{__('Create Branch', 'wp-ai-site-generator')}
							</Button>
							<Button
								variant="secondary"
								onClick={() => setShowBranchDialog(false)}
							>
								{__('Cancel', 'wp-ai-site-generator')}
							</Button>
						</ButtonGroup>
					</VStack>
				</Modal>
			)}

			{/* Export Dialog */}
			{showExportDialog && (
				<Modal
					title={__('Export Conversation', 'wp-ai-site-generator')}
					onRequestClose={() => setShowExportDialog(false)}
				>
					<VStack spacing={4}>
						<Text>
							{__('Choose export format:', 'wp-ai-site-generator')}
						</Text>
						<ButtonGroup>
							<Button
								variant="primary"
								onClick={() => exportConversation('json')}
							>
								{__('JSON', 'wp-ai-site-generator')}
							</Button>
							<Button
								variant="secondary"
								onClick={() => exportConversation('markdown')}
							>
								{__('Markdown', 'wp-ai-site-generator')}
							</Button>
							<Button
								variant="secondary"
								onClick={() => exportConversation('html')}
							>
								{__('HTML', 'wp-ai-site-generator')}
							</Button>
						</ButtonGroup>
					</VStack>
				</Modal>
			)}

			{/* Import Dialog */}
			{showImportDialog && (
				<Modal
					title={__('Import Conversation', 'wp-ai-site-generator')}
					onRequestClose={() => setShowImportDialog(false)}
				>
					<VStack spacing={4}>
						<Text>
							{__('Select a conversation file to import:', 'wp-ai-site-generator')}
						</Text>
						<input
							type="file"
							ref={fileInputRef}
							accept=".json"
							onChange={(e) => {
								const file = e.target.files[0];
								if (file) {
									importConversation(file);
								}
							}}
							style={{ display: 'none' }}
						/>
						<Button
							variant="primary"
							onClick={() => fileInputRef.current?.click()}
						>
							{__('Choose File', 'wp-ai-site-generator')}
						</Button>
					</VStack>
				</Modal>
			)}

			{/* Inline Styles */}
			<style jsx>{`
				.wpaisg-enhanced-chat {
					height: 100vh;
					overflow: hidden;
				}

				.chat-sidebar {
					width: 300px;
					border-right: 1px solid #e0e0e0;
					overflow-y: auto;
				}

				.context-sidebar {
					width: 320px;
					border-left: 1px solid #e0e0e0;
					overflow-y: auto;
					padding: 20px;
				}

				.chat-main {
					flex: 1;
					display: flex;
					flex-direction: column;
				}

				.conversations-list {
					max-height: 400px;
					overflow-y: auto;
				}

				.conversation-item {
					padding: 12px;
					cursor: pointer;
					border-radius: 4px;
					margin-bottom: 8px;
				}

				.conversation-item:hover {
					background: #f5f5f5;
				}

				.conversation-item.active {
					background: #e0f2ff;
				}

				.branch-indicator {
					color: #007cba;
				}

				.messages-container {
					flex: 1;
					overflow-y: auto;
					padding: 20px;
					max-height: 500px;
				}

				.message-item {
					margin-bottom: 16px;
				}

				.message-item.user .components-card {
					background: #e3f2fd;
					margin-left: 20%;
				}

				.message-item.assistant .components-card {
					background: #f5f5f5;
					margin-right: 20%;
				}

				.message-item pre {
					background: #1e1e1e;
					color: #fff;
					padding: 12px;
					border-radius: 4px;
					overflow-x: auto;
				}

				.message-item code.inline-code {
					background: #f0f0f0;
					padding: 2px 6px;
					border-radius: 3px;
					font-family: monospace;
				}

				.message-item a {
					color: #007cba;
					text-decoration: underline;
				}

				.loading-placeholder {
					display: flex;
					flex-direction: column;
					align-items: center;
					justify-content: center;
					padding: 40px;
				}

				.key-point {
					padding: 8px;
					background: #f9f9f9;
					border-radius: 4px;
					margin-bottom: 8px;
				}
			`}</style>
		</div>
	);
};

export default EnhancedChatInterface;