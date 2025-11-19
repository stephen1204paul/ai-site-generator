/**
 * Quality Dashboard React Component
 *
 * Interactive dashboard for quality metrics visualization
 *
 * @package WPAISiteGenerator
 */

const { Component, Fragment } = wp.element;
const { __ } = wp.i18n;
const { Button, Card, CardBody, SelectControl, DatePicker, Spinner, Notice } = wp.components;
const { useState, useEffect } = wp.element;
const apiFetch = wp.apiFetch;

/**
 * Quality Dashboard Component
 */
const QualityDashboard = () => {
	const [loading, setLoading] = useState(false);
	const [error, setError] = useState(null);
	const [metrics, setMetrics] = useState({});
	const [trends, setTrends] = useState([]);
	const [providers, setProviders] = useState([]);
	const [filters, setFilters] = useState({
		dateFrom: '',
		dateTo: '',
		provider: '',
		contentType: '',
	});
	const [chartType, setChartType] = useState('line');
	const [refreshInterval, setRefreshInterval] = useState(null);

	// Load initial data
	useEffect(() => {
		loadDashboardData();
	}, [filters]);

	// Set up auto-refresh
	useEffect(() => {
		if (refreshInterval) {
			const interval = setInterval(() => {
				loadDashboardData();
			}, refreshInterval * 1000);

			return () => clearInterval(interval);
		}
	}, [refreshInterval, filters]);

	/**
	 * Load dashboard data from API
	 */
	const loadDashboardData = async () => {
		setLoading(true);
		setError(null);

		try {
			// Fetch metrics
			const metricsResponse = await apiFetch({
				path: '/waisg/v1/quality/metrics',
				method: 'POST',
				data: filters,
			});

			// Fetch trends
			const trendsResponse = await apiFetch({
				path: '/waisg/v1/quality/trends',
				method: 'POST',
				data: filters,
			});

			// Fetch provider comparison
			const providersResponse = await apiFetch({
				path: '/waisg/v1/quality/breakdown',
				method: 'POST',
				data: filters,
			});

			setMetrics(metricsResponse);
			setTrends(trendsResponse);
			setProviders(providersResponse);
		} catch (err) {
			setError(err.message || __('Failed to load dashboard data', 'wp-ai-site-generator'));
		} finally {
			setLoading(false);
		}
	};

	/**
	 * Export quality report
	 */
	const exportReport = async (format) => {
		setLoading(true);

		try {
			const response = await apiFetch({
				path: '/waisg/v1/quality/export',
				method: 'POST',
				data: {
					...filters,
					format: format,
				},
			});

			if (format === 'csv') {
				// Create download link for CSV
				const blob = new Blob([response.data], { type: 'text/csv' });
				const url = window.URL.createObjectURL(blob);
				const a = document.createElement('a');
				a.href = url;
				a.download = `quality-report-${Date.now()}.csv`;
				a.click();
				window.URL.revokeObjectURL(url);
			} else {
				// Handle JSON export
				const blob = new Blob([JSON.stringify(response.data, null, 2)], { type: 'application/json' });
				const url = window.URL.createObjectURL(blob);
				const a = document.createElement('a');
				a.href = url;
				a.download = `quality-report-${Date.now()}.json`;
				a.click();
				window.URL.revokeObjectURL(url);
			}
		} catch (err) {
			setError(err.message || __('Failed to export report', 'wp-ai-site-generator'));
		} finally {
			setLoading(false);
		}
	};

	/**
	 * Render quality score chart
	 */
	const renderQualityChart = () => {
		if (!trends || trends.length === 0) {
			return <p>{__('No trend data available', 'wp-ai-site-generator')}</p>;
		}

		// Prepare chart data
		const chartData = {
			labels: trends.map(t => t.period),
			datasets: [
				{
					label: __('Average Score', 'wp-ai-site-generator'),
					data: trends.map(t => t.avg_score),
					borderColor: 'rgb(75, 192, 192)',
					backgroundColor: 'rgba(75, 192, 192, 0.2)',
					tension: 0.1,
				},
				{
					label: __('Max Score', 'wp-ai-site-generator'),
					data: trends.map(t => t.max_score),
					borderColor: 'rgb(54, 162, 235)',
					backgroundColor: 'rgba(54, 162, 235, 0.2)',
					tension: 0.1,
				},
				{
					label: __('Min Score', 'wp-ai-site-generator'),
					data: trends.map(t => t.min_score),
					borderColor: 'rgb(255, 99, 132)',
					backgroundColor: 'rgba(255, 99, 132, 0.2)',
					tension: 0.1,
				},
			],
		};

		// Chart configuration
		const config = {
			type: chartType,
			data: chartData,
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: {
					legend: {
						display: true,
						position: 'top',
					},
					title: {
						display: true,
						text: __('Quality Score Trends', 'wp-ai-site-generator'),
					},
					tooltip: {
						mode: 'index',
						intersect: false,
					},
				},
				scales: {
					y: {
						beginAtZero: true,
						max: 100,
						title: {
							display: true,
							text: __('Quality Score (%)', 'wp-ai-site-generator'),
						},
					},
					x: {
						title: {
							display: true,
							text: __('Date', 'wp-ai-site-generator'),
						},
					},
				},
			},
		};

		return (
			<div className="chart-wrapper" style={{ height: '400px' }}>
				<canvas id="quality-trends-canvas"></canvas>
				<script dangerouslySetInnerHTML={{
					__html: `
						(function() {
							const ctx = document.getElementById('quality-trends-canvas');
							if (ctx && window.Chart) {
								new Chart(ctx, ${JSON.stringify(config)});
							}
						})();
					`
				}} />
			</div>
		);
	};

	/**
	 * Render score distribution histogram
	 */
	const renderScoreDistribution = () => {
		if (!metrics.distribution) {
			return null;
		}

		const distributionData = {
			labels: ['0-20', '20-40', '40-60', '60-80', '80-100'],
			datasets: [{
				label: __('Generations', 'wp-ai-site-generator'),
				data: metrics.distribution,
				backgroundColor: [
					'rgba(255, 99, 132, 0.5)',
					'rgba(255, 159, 64, 0.5)',
					'rgba(255, 205, 86, 0.5)',
					'rgba(75, 192, 192, 0.5)',
					'rgba(54, 162, 235, 0.5)',
				],
				borderColor: [
					'rgb(255, 99, 132)',
					'rgb(255, 159, 64)',
					'rgb(255, 205, 86)',
					'rgb(75, 192, 192)',
					'rgb(54, 162, 235)',
				],
				borderWidth: 1,
			}],
		};

		return (
			<Card>
				<CardBody>
					<h3>{__('Score Distribution', 'wp-ai-site-generator')}</h3>
					<div className="chart-wrapper" style={{ height: '300px' }}>
						<canvas id="score-distribution-canvas"></canvas>
						<script dangerouslySetInnerHTML={{
							__html: `
								(function() {
									const ctx = document.getElementById('score-distribution-canvas');
									if (ctx && window.Chart) {
										new Chart(ctx, {
											type: 'bar',
											data: ${JSON.stringify(distributionData)},
											options: {
												responsive: true,
												maintainAspectRatio: false,
												plugins: {
													legend: {
														display: false,
													},
													title: {
														display: true,
														text: '${__('Quality Score Distribution', 'wp-ai-site-generator')}',
													},
												},
												scales: {
													y: {
														beginAtZero: true,
														title: {
															display: true,
															text: '${__('Number of Generations', 'wp-ai-site-generator')}',
														},
													},
													x: {
														title: {
															display: true,
															text: '${__('Score Range', 'wp-ai-site-generator')}',
														},
													},
												},
											},
										});
									}
								})();
							`
						}} />
					</div>
				</CardBody>
			</Card>
		);
	};

	/**
	 * Render provider comparison chart
	 */
	const renderProviderComparison = () => {
		if (!providers || providers.length === 0) {
			return null;
		}

		const comparisonData = {
			labels: providers.map(p => p.provider),
			datasets: [{
				label: __('Average Quality Score', 'wp-ai-site-generator'),
				data: providers.map(p => p.avg_score),
				backgroundColor: [
					'rgba(255, 99, 132, 0.5)',
					'rgba(54, 162, 235, 0.5)',
					'rgba(255, 206, 86, 0.5)',
					'rgba(75, 192, 192, 0.5)',
				],
				borderColor: [
					'rgba(255, 99, 132, 1)',
					'rgba(54, 162, 235, 1)',
					'rgba(255, 206, 86, 1)',
					'rgba(75, 192, 192, 1)',
				],
				borderWidth: 1,
			}],
		};

		return (
			<Card>
				<CardBody>
					<h3>{__('Provider Performance Comparison', 'wp-ai-site-generator')}</h3>
					<div className="chart-wrapper" style={{ height: '300px' }}>
						<canvas id="provider-comparison-canvas"></canvas>
						<script dangerouslySetInnerHTML={{
							__html: `
								(function() {
									const ctx = document.getElementById('provider-comparison-canvas');
									if (ctx && window.Chart) {
										new Chart(ctx, {
											type: 'radar',
											data: ${JSON.stringify(comparisonData)},
											options: {
												responsive: true,
												maintainAspectRatio: false,
												scales: {
													r: {
														beginAtZero: true,
														max: 100,
													},
												},
											},
										});
									}
								})();
							`
						}} />
					</div>
				</CardBody>
			</Card>
		);
	};

	return (
		<div className="waisg-quality-dashboard-react">
			{error && (
				<Notice status="error" isDismissible={true} onRemove={() => setError(null)}>
					{error}
				</Notice>
			)}

			<div className="dashboard-toolbar">
				<div className="toolbar-filters">
					<SelectControl
						label={__('Provider', 'wp-ai-site-generator')}
						value={filters.provider}
						options={[
							{ value: '', label: __('All Providers', 'wp-ai-site-generator') },
							{ value: 'openai', label: 'OpenAI' },
							{ value: 'anthropic', label: 'Anthropic' },
							{ value: 'google', label: 'Google AI' },
							{ value: 'cohere', label: 'Cohere' },
						]}
						onChange={(provider) => setFilters({ ...filters, provider })}
					/>

					<SelectControl
						label={__('Content Type', 'wp-ai-site-generator')}
						value={filters.contentType}
						options={[
							{ value: '', label: __('All Types', 'wp-ai-site-generator') },
							{ value: 'page', label: __('Page', 'wp-ai-site-generator') },
							{ value: 'post', label: __('Post', 'wp-ai-site-generator') },
							{ value: 'product', label: __('Product', 'wp-ai-site-generator') },
							{ value: 'section', label: __('Section', 'wp-ai-site-generator') },
						]}
						onChange={(contentType) => setFilters({ ...filters, contentType })}
					/>

					<SelectControl
						label={__('Chart Type', 'wp-ai-site-generator')}
						value={chartType}
						options={[
							{ value: 'line', label: __('Line Chart', 'wp-ai-site-generator') },
							{ value: 'bar', label: __('Bar Chart', 'wp-ai-site-generator') },
							{ value: 'area', label: __('Area Chart', 'wp-ai-site-generator') },
						]}
						onChange={setChartType}
					/>

					<SelectControl
						label={__('Auto Refresh', 'wp-ai-site-generator')}
						value={refreshInterval}
						options={[
							{ value: null, label: __('Disabled', 'wp-ai-site-generator') },
							{ value: 30, label: __('30 seconds', 'wp-ai-site-generator') },
							{ value: 60, label: __('1 minute', 'wp-ai-site-generator') },
							{ value: 300, label: __('5 minutes', 'wp-ai-site-generator') },
						]}
						onChange={(value) => setRefreshInterval(value ? parseInt(value) : null)}
					/>
				</div>

				<div className="toolbar-actions">
					<Button
						isPrimary
						onClick={() => loadDashboardData()}
						disabled={loading}
					>
						{loading ? <Spinner /> : __('Refresh', 'wp-ai-site-generator')}
					</Button>

					<Button
						isSecondary
						onClick={() => exportReport('csv')}
						disabled={loading}
					>
						{__('Export CSV', 'wp-ai-site-generator')}
					</Button>

					<Button
						isSecondary
						onClick={() => exportReport('json')}
						disabled={loading}
					>
						{__('Export JSON', 'wp-ai-site-generator')}
					</Button>
				</div>
			</div>

			{loading && !metrics.total ? (
				<div className="loading-container">
					<Spinner />
					<p>{__('Loading dashboard data...', 'wp-ai-site-generator')}</p>
				</div>
			) : (
				<Fragment>
					{/* Render charts */}
					<div className="dashboard-charts">
						{renderQualityChart()}
						{renderScoreDistribution()}
						{renderProviderComparison()}
					</div>

					{/* Real-time metrics display */}
					{metrics.realtime && (
						<Card className="realtime-metrics">
							<CardBody>
								<h3>{__('Real-Time Metrics', 'wp-ai-site-generator')}</h3>
								<div className="metrics-grid">
									<div className="metric">
										<span className="metric-label">{__('Active Generations', 'wp-ai-site-generator')}</span>
										<span className="metric-value">{metrics.realtime.active}</span>
									</div>
									<div className="metric">
										<span className="metric-label">{__('Last Hour Average', 'wp-ai-site-generator')}</span>
										<span className="metric-value">{metrics.realtime.lastHourAvg}%</span>
									</div>
									<div className="metric">
										<span className="metric-label">{__('Today\'s Generations', 'wp-ai-site-generator')}</span>
										<span className="metric-value">{metrics.realtime.todayCount}</span>
									</div>
								</div>
							</CardBody>
						</Card>
					)}
				</Fragment>
			)}
		</div>
	);
};

// Initialize the component when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
	const rootElement = document.getElementById('waisg-quality-dashboard-root');

	if (rootElement && window.wp && window.wp.element) {
		const { render } = window.wp.element;

		// Get initial data from PHP
		const initialFilters = JSON.parse(rootElement.dataset.filters || '{}');
		const initialTrends = JSON.parse(rootElement.dataset.trends || '[]');
		const initialProviders = JSON.parse(rootElement.dataset.providers || '[]');

		// Render the component
		render(
			<QualityDashboard
				initialFilters={initialFilters}
				initialTrends={initialTrends}
				initialProviders={initialProviders}
			/>,
			rootElement
		);
	}
});

// Export for global access
window.QualityDashboard = QualityDashboard;