/**
 * Provider Configuration React Component
 *
 * @package WPAISiteGenerator
 * @since   1.0.0
 */

import { useState, useEffect } from '@wordpress/element';
import {
	Button,
	TextControl,
	SelectControl,
	ToggleControl,
	RangeControl,
	Spinner,
	Notice,
	Card,
	CardBody,
	CardHeader,
	CardFooter,
	__experimentalHStack as HStack,
	__experimentalVStack as VStack,
	__experimentalInputControl as InputControl,
	Icon,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { check, close, warning } from '@wordpress/icons';

const ProviderConfig = () => {
	const [providers, setProviders] = useState({
		openai: {
			enabled: false,
			api_key: '',
			model: 'gpt-3.5-turbo',
			organization: '',
			max_tokens: 4000,
		},
		anthropic: {
			enabled: false,
			api_key: '',
			model: 'claude-3-opus-20240229',
			max_tokens: 4000,
		},
		google: {
			enabled: false,
			api_key: '',
			model: 'gemini-pro',
			max_tokens: 4000,
		},
		custom: {
			enabled: false,
			endpoint: '',
			api_key: '',
			model: '',
		},
	});

	const [globalSettings, setGlobalSettings] = useState({
		default_provider: '',
		fallback_provider: '',
		retry_attempts: 3,
		log_api_errors: false,
	});

	const [testResults, setTestResults] = useState({});
	const [isTesting, setIsTesting] = useState({});
	const [isSaving, setIsSaving] = useState(false);
	const [saveNotice, setSaveNotice] = useState(null);
	const [showApiKeys, setShowApiKeys] = useState({});

	// Load settings on mount
	useEffect(() => {
		loadProviderSettings();
	}, []);

	const loadProviderSettings = async () => {
		try {
			const response = await apiFetch({
				path: '/waisg/v1/providers/settings',
			});

			if (response.providers) {
				setProviders(response.providers);
			}
			if (response.global) {
				setGlobalSettings(response.global);
			}
		} catch (error) {
			console.error('Failed to load provider settings:', error);
		}
	};

	const saveSettings = async () => {
		setIsSaving(true);
		setSaveNotice(null);

		try {
			await apiFetch({
				path: '/waisg/v1/providers/settings',
				method: 'POST',
				data: {
					providers,
					global: globalSettings,
				},
			});

			setSaveNotice({
				status: 'success',
				message: __('Settings saved successfully!', 'wp-ai-site-generator'),
			});
		} catch (error) {
			setSaveNotice({
				status: 'error',
				message: error.message || __('Failed to save settings.', 'wp-ai-site-generator'),
			});
		} finally {
			setIsSaving(false);
		}
	};

	const testConnection = async (providerKey) => {
		setIsTesting({ ...isTesting, [providerKey]: true });
		setTestResults({ ...testResults, [providerKey]: null });

		try {
			const response = await apiFetch({
				path: '/waisg/v1/providers/test',
				method: 'POST',
				data: {
					provider: providerKey,
					...providers[providerKey],
				},
			});

			setTestResults({
				...testResults,
				[providerKey]: {
					success: true,
					message: response.message || __('Connection successful!', 'wp-ai-site-generator'),
					details: response.details,
				},
			});
		} catch (error) {
			setTestResults({
				...testResults,
				[providerKey]: {
					success: false,
					message: error.message || __('Connection failed!', 'wp-ai-site-generator'),
				},
			});
		} finally {
			setIsTesting({ ...isTesting, [providerKey]: false });
		}
	};

	const toggleApiKeyVisibility = (provider) => {
		setShowApiKeys({
			...showApiKeys,
			[provider]: !showApiKeys[provider],
		});
	};

	const updateProvider = (providerKey, field, value) => {
		setProviders({
			...providers,
			[providerKey]: {
				...providers[providerKey],
				[field]: value,
			},
		});
	};

	const providerInfo = {
		openai: {
			name: 'OpenAI',
			icon: '🤖',
			description: __('Access GPT-4 and GPT-3.5 models', 'wp-ai-site-generator'),
			models: [
				{ value: 'gpt-4-turbo-preview', label: 'GPT-4 Turbo (Latest)' },
				{ value: 'gpt-4', label: 'GPT-4' },
				{ value: 'gpt-3.5-turbo', label: 'GPT-3.5 Turbo' },
				{ value: 'gpt-3.5-turbo-16k', label: 'GPT-3.5 Turbo 16K' },
			],
			apiKeyUrl: 'https://platform.openai.com/api-keys',
		},
		anthropic: {
			name: 'Anthropic Claude',
			icon: '🧠',
			description: __('Access Claude 3 models', 'wp-ai-site-generator'),
			models: [
				{ value: 'claude-3-opus-20240229', label: 'Claude 3 Opus (Most Capable)' },
				{ value: 'claude-3-sonnet-20240229', label: 'Claude 3 Sonnet (Balanced)' },
				{ value: 'claude-3-haiku-20240307', label: 'Claude 3 Haiku (Fastest)' },
			],
			apiKeyUrl: 'https://console.anthropic.com/api',
		},
		google: {
			name: 'Google Gemini',
			icon: '✨',
			description: __('Access Gemini Pro models', 'wp-ai-site-generator'),
			models: [
				{ value: 'gemini-pro', label: 'Gemini Pro' },
				{ value: 'gemini-pro-vision', label: 'Gemini Pro Vision' },
			],
			apiKeyUrl: 'https://makersuite.google.com/app/apikey',
		},
		custom: {
			name: 'Custom / Local AI',
			icon: '⚙️',
			description: __('Connect to local or custom AI models', 'wp-ai-site-generator'),
		},
	};

	const renderProviderCard = (providerKey) => {
		const provider = providers[providerKey];
		const info = providerInfo[providerKey];
		const testResult = testResults[providerKey];
		const isTestingProvider = isTesting[providerKey];
		const showKey = showApiKeys[providerKey];

		return (
			<Card key={providerKey} className={`waisg-provider-card ${provider.enabled ? 'is-enabled' : ''}`}>
				<CardHeader>
					<HStack alignment="space-between">
						<HStack>
							<span className="waisg-provider-icon">{info.icon}</span>
							<VStack spacing={1}>
								<h3>{info.name}</h3>
								<small>{info.description}</small>
							</VStack>
						</HStack>
						<ToggleControl
							checked={provider.enabled}
							onChange={(enabled) => updateProvider(providerKey, 'enabled', enabled)}
						/>
					</HStack>
				</CardHeader>

				{provider.enabled && (
					<CardBody>
						<VStack spacing={4}>
							{/* API Key */}
							<div className="waisg-provider-field">
								<HStack>
									<TextControl
										label={__('API Key', 'wp-ai-site-generator')}
										value={provider.api_key}
										onChange={(api_key) => updateProvider(providerKey, 'api_key', api_key)}
										type={showKey ? 'text' : 'password'}
										placeholder={providerKey === 'openai' ? 'sk-...' : ''}
										help={
											info.apiKeyUrl && (
												<a href={info.apiKeyUrl} target="_blank" rel="noopener noreferrer">
													{__('Get your API key', 'wp-ai-site-generator')}
												</a>
											)
										}
									/>
									<Button
										variant="secondary"
										onClick={() => toggleApiKeyVisibility(providerKey)}
										icon={showKey ? 'hidden' : 'visibility'}
										label={showKey ? __('Hide', 'wp-ai-site-generator') : __('Show', 'wp-ai-site-generator')}
									/>
								</HStack>
							</div>

							{/* Model Selection */}
							{info.models && (
								<SelectControl
									label={__('Default Model', 'wp-ai-site-generator')}
									value={provider.model}
									onChange={(model) => updateProvider(providerKey, 'model', model)}
									options={info.models}
								/>
							)}

							{/* OpenAI Organization */}
							{providerKey === 'openai' && (
								<TextControl
									label={__('Organization ID (Optional)', 'wp-ai-site-generator')}
									value={provider.organization}
									onChange={(organization) => updateProvider(providerKey, 'organization', organization)}
									placeholder="org-..."
									help={__('Required only for organization accounts', 'wp-ai-site-generator')}
								/>
							)}

							{/* Custom Endpoint */}
							{providerKey === 'custom' && (
								<>
									<TextControl
										label={__('API Endpoint', 'wp-ai-site-generator')}
										value={provider.endpoint}
										onChange={(endpoint) => updateProvider(providerKey, 'endpoint', endpoint)}
										type="url"
										placeholder="http://localhost:11434/api/generate"
										help={__('Full URL to your local AI API endpoint', 'wp-ai-site-generator')}
									/>
									<TextControl
										label={__('Model Name', 'wp-ai-site-generator')}
										value={provider.model}
										onChange={(model) => updateProvider(providerKey, 'model', model)}
										placeholder="llama2:13b"
									/>
								</>
							)}

							{/* Max Tokens */}
							{providerKey !== 'custom' && (
								<RangeControl
									label={__('Max Tokens per Request', 'wp-ai-site-generator')}
									value={provider.max_tokens}
									onChange={(max_tokens) => updateProvider(providerKey, 'max_tokens', max_tokens)}
									min={100}
									max={providerKey === 'anthropic' ? 200000 : 128000}
									step={100}
									help={__('Maximum tokens to use per request (affects cost and response length)', 'wp-ai-site-generator')}
								/>
							)}

							{/* Test Connection */}
							<HStack>
								<Button
									variant="secondary"
									onClick={() => testConnection(providerKey)}
									disabled={!provider.api_key || isTestingProvider}
									icon={isTestingProvider ? undefined : 'admin-plugins'}
								>
									{isTestingProvider ? (
										<>
											<Spinner />
											{__('Testing...', 'wp-ai-site-generator')}
										</>
									) : (
										__('Test Connection', 'wp-ai-site-generator')
									)}
								</Button>
							</HStack>

							{/* Test Result */}
							{testResult && (
								<Notice
									status={testResult.success ? 'success' : 'error'}
									isDismissible={false}
								>
									<strong>{testResult.message}</strong>
									{testResult.details && (
										<p>{testResult.details}</p>
									)}
								</Notice>
							)}
						</VStack>
					</CardBody>
				)}
			</Card>
		);
	};

	return (
		<div className="waisg-provider-config">
			<VStack spacing={6}>
				{/* Provider Cards */}
				<div className="waisg-providers-grid">
					{Object.keys(providers).map(providerKey => renderProviderCard(providerKey))}
				</div>

				{/* Global Settings */}
				<Card>
					<CardHeader>
						<h2>{__('Global Settings', 'wp-ai-site-generator')}</h2>
					</CardHeader>
					<CardBody>
						<VStack spacing={4}>
							<SelectControl
								label={__('Default Provider', 'wp-ai-site-generator')}
								value={globalSettings.default_provider}
								onChange={(default_provider) =>
									setGlobalSettings({ ...globalSettings, default_provider })
								}
								options={[
									{ value: '', label: __('Auto-select based on availability', 'wp-ai-site-generator') },
									...Object.keys(providers).map(key => ({
										value: key,
										label: providerInfo[key].name,
									})),
								]}
							/>

							<SelectControl
								label={__('Fallback Provider', 'wp-ai-site-generator')}
								value={globalSettings.fallback_provider}
								onChange={(fallback_provider) =>
									setGlobalSettings({ ...globalSettings, fallback_provider })
								}
								options={[
									{ value: '', label: __('None', 'wp-ai-site-generator') },
									...Object.keys(providers).map(key => ({
										value: key,
										label: providerInfo[key].name,
									})),
								]}
								help={__('Use this provider if the default provider fails', 'wp-ai-site-generator')}
							/>

							<RangeControl
								label={__('Retry Attempts', 'wp-ai-site-generator')}
								value={globalSettings.retry_attempts}
								onChange={(retry_attempts) =>
									setGlobalSettings({ ...globalSettings, retry_attempts })
								}
								min={0}
								max={10}
								help={__('Number of times to retry failed API calls', 'wp-ai-site-generator')}
							/>

							<ToggleControl
								label={__('Log API Errors', 'wp-ai-site-generator')}
								checked={globalSettings.log_api_errors}
								onChange={(log_api_errors) =>
									setGlobalSettings({ ...globalSettings, log_api_errors })
								}
								help={__('Log API errors for debugging purposes', 'wp-ai-site-generator')}
							/>
						</VStack>
					</CardBody>
				</Card>

				{/* Save Notice */}
				{saveNotice && (
					<Notice
						status={saveNotice.status}
						onRemove={() => setSaveNotice(null)}
					>
						{saveNotice.message}
					</Notice>
				)}

				{/* Save Button */}
				<CardFooter>
					<Button
						variant="primary"
						onClick={saveSettings}
						disabled={isSaving}
						icon={isSaving ? undefined : 'saved'}
					>
						{isSaving ? (
							<>
								<Spinner />
								{__('Saving...', 'wp-ai-site-generator')}
							</>
						) : (
							__('Save Settings', 'wp-ai-site-generator')
						)}
					</Button>
				</CardFooter>
			</VStack>
		</div>
	);
};

export default ProviderConfig;