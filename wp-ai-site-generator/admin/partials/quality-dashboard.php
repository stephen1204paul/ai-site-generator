<?php
/**
 * Quality Dashboard Admin Page
 *
 * Displays comprehensive quality metrics and monitoring dashboard
 *
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/admin/partials
 * @since      1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPAISiteGenerator\Includes\Quality_Metrics;

$metrics_tracker = new Quality_Metrics();

// Get date range from request or default to last 30 days
$date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( $_GET['date_from'] ) : date( 'Y-m-d', strtotime( '-30 days' ) );
$date_to = isset( $_GET['date_to'] ) ? sanitize_text_field( $_GET['date_to'] ) : date( 'Y-m-d' );

// Get filter values
$provider = isset( $_GET['provider'] ) ? sanitize_text_field( $_GET['provider'] ) : '';
$content_type = isset( $_GET['content_type'] ) ? sanitize_text_field( $_GET['content_type'] ) : '';

// Prepare filters
$filters = array(
	'date_from'    => $date_from,
	'date_to'      => $date_to . ' 23:59:59',
	'provider'     => $provider,
	'content_type' => $content_type,
);

// Get metrics data
$recent_metrics = $metrics_tracker->get_metrics( array_merge( $filters, array( 'limit' => 10 ) ) );
$trends = $metrics_tracker->get_score_trends( 'day', $filters );
$provider_comparison = $metrics_tracker->get_provider_comparison( array( 'from' => $date_from, 'to' => $date_to . ' 23:59:59' ) );
$validation_breakdown = $metrics_tracker->get_validation_breakdown( $filters );
$cost_by_quality = $metrics_tracker->get_cost_by_quality( $filters );
$recommendations = $metrics_tracker->get_recommendations();

// Calculate overview stats
$total_generations = $recent_metrics['total'];
$avg_quality_score = 0;
$high_quality_count = 0;
$low_quality_count = 0;

if ( ! empty( $trends ) ) {
	$total_score = 0;
	$count = 0;
	foreach ( $trends as $trend ) {
		$total_score += $trend->avg_score * $trend->generation_count;
		$count += $trend->generation_count;
		$high_quality_count += $trend->high_quality_count;
		$low_quality_count += $trend->low_quality_count;
	}
	if ( $count > 0 ) {
		$avg_quality_score = $total_score / $count;
	}
}

?>

<div class="wrap waisg-quality-dashboard">
	<h1 class="wp-heading-inline">
		<?php esc_html_e( 'Quality Monitoring Dashboard', 'wp-ai-site-generator' ); ?>
	</h1>

	<div class="waisg-dashboard-filters">
		<form method="get" action="" class="filter-form">
			<input type="hidden" name="page" value="<?php echo esc_attr( $_GET['page'] ); ?>">

			<div class="filter-group">
				<label for="date_from"><?php esc_html_e( 'Date From:', 'wp-ai-site-generator' ); ?></label>
				<input type="date" id="date_from" name="date_from" value="<?php echo esc_attr( $date_from ); ?>">
			</div>

			<div class="filter-group">
				<label for="date_to"><?php esc_html_e( 'Date To:', 'wp-ai-site-generator' ); ?></label>
				<input type="date" id="date_to" name="date_to" value="<?php echo esc_attr( $date_to ); ?>">
			</div>

			<div class="filter-group">
				<label for="provider"><?php esc_html_e( 'Provider:', 'wp-ai-site-generator' ); ?></label>
				<select id="provider" name="provider">
					<option value=""><?php esc_html_e( 'All Providers', 'wp-ai-site-generator' ); ?></option>
					<option value="openai" <?php selected( $provider, 'openai' ); ?>>OpenAI</option>
					<option value="anthropic" <?php selected( $provider, 'anthropic' ); ?>>Anthropic</option>
					<option value="google" <?php selected( $provider, 'google' ); ?>>Google AI</option>
					<option value="cohere" <?php selected( $provider, 'cohere' ); ?>>Cohere</option>
				</select>
			</div>

			<div class="filter-group">
				<label for="content_type"><?php esc_html_e( 'Content Type:', 'wp-ai-site-generator' ); ?></label>
				<select id="content_type" name="content_type">
					<option value=""><?php esc_html_e( 'All Types', 'wp-ai-site-generator' ); ?></option>
					<option value="page" <?php selected( $content_type, 'page' ); ?>>Page</option>
					<option value="post" <?php selected( $content_type, 'post' ); ?>>Post</option>
					<option value="product" <?php selected( $content_type, 'product' ); ?>>Product</option>
					<option value="section" <?php selected( $content_type, 'section' ); ?>>Section</option>
				</select>
			</div>

			<div class="filter-actions">
				<button type="submit" class="button button-primary">
					<?php esc_html_e( 'Apply Filters', 'wp-ai-site-generator' ); ?>
				</button>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $_GET['page'] ) ); ?>" class="button">
					<?php esc_html_e( 'Reset', 'wp-ai-site-generator' ); ?>
				</a>
				<button type="button" class="button export-report" data-format="csv">
					<span class="dashicons dashicons-download"></span>
					<?php esc_html_e( 'Export CSV', 'wp-ai-site-generator' ); ?>
				</button>
			</div>
		</form>
	</div>

	<!-- Overview Cards -->
	<div class="waisg-overview-cards">
		<div class="metric-card">
			<div class="metric-icon">
				<span class="dashicons dashicons-chart-line"></span>
			</div>
			<div class="metric-content">
				<h3><?php esc_html_e( 'Total Generations', 'wp-ai-site-generator' ); ?></h3>
				<div class="metric-value"><?php echo esc_html( number_format( $total_generations ) ); ?></div>
				<div class="metric-change">
					<?php esc_html_e( 'In selected period', 'wp-ai-site-generator' ); ?>
				</div>
			</div>
		</div>

		<div class="metric-card">
			<div class="metric-icon quality-score" data-score="<?php echo esc_attr( round( $avg_quality_score ) ); ?>">
				<span class="dashicons dashicons-awards"></span>
			</div>
			<div class="metric-content">
				<h3><?php esc_html_e( 'Average Quality Score', 'wp-ai-site-generator' ); ?></h3>
				<div class="metric-value"><?php echo esc_html( round( $avg_quality_score, 1 ) ); ?>%</div>
				<div class="metric-change quality-indicator" data-score="<?php echo esc_attr( round( $avg_quality_score ) ); ?>">
					<?php
					if ( $avg_quality_score >= 80 ) {
						esc_html_e( 'Excellent', 'wp-ai-site-generator' );
					} elseif ( $avg_quality_score >= 60 ) {
						esc_html_e( 'Good', 'wp-ai-site-generator' );
					} elseif ( $avg_quality_score >= 40 ) {
						esc_html_e( 'Fair', 'wp-ai-site-generator' );
					} else {
						esc_html_e( 'Needs Improvement', 'wp-ai-site-generator' );
					}
					?>
				</div>
			</div>
		</div>

		<div class="metric-card">
			<div class="metric-icon success">
				<span class="dashicons dashicons-yes-alt"></span>
			</div>
			<div class="metric-content">
				<h3><?php esc_html_e( 'High Quality', 'wp-ai-site-generator' ); ?></h3>
				<div class="metric-value"><?php echo esc_html( number_format( $high_quality_count ) ); ?></div>
				<div class="metric-change">
					<?php
					if ( $total_generations > 0 ) {
						echo esc_html( round( ( $high_quality_count / $total_generations ) * 100, 1 ) ) . '% ';
					}
					esc_html_e( 'Score ≥ 80', 'wp-ai-site-generator' );
					?>
				</div>
			</div>
		</div>

		<div class="metric-card">
			<div class="metric-icon warning">
				<span class="dashicons dashicons-warning"></span>
			</div>
			<div class="metric-content">
				<h3><?php esc_html_e( 'Low Quality', 'wp-ai-site-generator' ); ?></h3>
				<div class="metric-value"><?php echo esc_html( number_format( $low_quality_count ) ); ?></div>
				<div class="metric-change">
					<?php
					if ( $total_generations > 0 ) {
						echo esc_html( round( ( $low_quality_count / $total_generations ) * 100, 1 ) ) . '% ';
					}
					esc_html_e( 'Score < 50', 'wp-ai-site-generator' );
					?>
				</div>
			</div>
		</div>
	</div>

	<!-- Recommendations -->
	<?php if ( ! empty( $recommendations ) ) : ?>
	<div class="waisg-recommendations">
		<h2><?php esc_html_e( 'Recommendations', 'wp-ai-site-generator' ); ?></h2>
		<div class="recommendations-list">
			<?php foreach ( $recommendations as $recommendation ) : ?>
			<div class="recommendation-item <?php echo esc_attr( $recommendation['type'] ); ?>">
				<div class="recommendation-icon">
					<span class="dashicons dashicons-<?php echo 'critical' === $recommendation['type'] ? 'warning' : 'info'; ?>"></span>
				</div>
				<div class="recommendation-content">
					<h4><?php echo esc_html( $recommendation['title'] ); ?></h4>
					<p><?php echo esc_html( $recommendation['message'] ); ?></p>
				</div>
			</div>
			<?php endforeach; ?>
		</div>
	</div>
	<?php endif; ?>

	<!-- Main Dashboard Content -->
	<div class="waisg-dashboard-grid">

		<!-- Quality Trends Chart -->
		<div class="dashboard-section quality-trends">
			<h2><?php esc_html_e( 'Quality Score Trends', 'wp-ai-site-generator' ); ?></h2>
			<div id="quality-trends-chart" class="chart-container">
				<!-- React component will render here -->
			</div>
		</div>

		<!-- Provider Comparison -->
		<div class="dashboard-section provider-comparison">
			<h2><?php esc_html_e( 'Provider Performance', 'wp-ai-site-generator' ); ?></h2>
			<?php if ( ! empty( $provider_comparison ) ) : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Provider', 'wp-ai-site-generator' ); ?></th>
						<th><?php esc_html_e( 'Generations', 'wp-ai-site-generator' ); ?></th>
						<th><?php esc_html_e( 'Avg Score', 'wp-ai-site-generator' ); ?></th>
						<th><?php esc_html_e( 'Quality Distribution', 'wp-ai-site-generator' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $provider_comparison as $provider_data ) : ?>
					<tr>
						<td><strong><?php echo esc_html( ucfirst( $provider_data->provider ) ); ?></strong></td>
						<td><?php echo esc_html( $provider_data->total_generations ); ?></td>
						<td>
							<span class="quality-score" data-score="<?php echo esc_attr( round( $provider_data->avg_score ) ); ?>">
								<?php echo esc_html( round( $provider_data->avg_score, 1 ) ); ?>%
							</span>
						</td>
						<td>
							<div class="quality-distribution">
								<?php
								$total = $provider_data->high_quality_count + $provider_data->medium_quality_count + $provider_data->low_quality_count;
								if ( $total > 0 ) :
									$high_pct = ( $provider_data->high_quality_count / $total ) * 100;
									$medium_pct = ( $provider_data->medium_quality_count / $total ) * 100;
									$low_pct = ( $provider_data->low_quality_count / $total ) * 100;
								?>
								<div class="distribution-bar">
									<div class="bar-segment high" style="width: <?php echo esc_attr( $high_pct ); ?>%;" title="High: <?php echo esc_attr( round( $high_pct, 1 ) ); ?>%"></div>
									<div class="bar-segment medium" style="width: <?php echo esc_attr( $medium_pct ); ?>%;" title="Medium: <?php echo esc_attr( round( $medium_pct, 1 ) ); ?>%"></div>
									<div class="bar-segment low" style="width: <?php echo esc_attr( $low_pct ); ?>%;" title="Low: <?php echo esc_attr( round( $low_pct, 1 ) ); ?>%"></div>
								</div>
								<?php endif; ?>
							</div>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php else : ?>
			<p><?php esc_html_e( 'No provider data available for the selected period.', 'wp-ai-site-generator' ); ?></p>
			<?php endif; ?>
		</div>

		<!-- Validation Breakdown -->
		<div class="dashboard-section validation-breakdown">
			<h2><?php esc_html_e( 'Validation Results', 'wp-ai-site-generator' ); ?></h2>
			<?php if ( $validation_breakdown['total_validations'] > 0 ) : ?>
			<div class="validation-stats">
				<div class="stat-row">
					<span class="stat-label"><?php esc_html_e( 'Total Validations:', 'wp-ai-site-generator' ); ?></span>
					<span class="stat-value"><?php echo esc_html( $validation_breakdown['total_validations'] ); ?></span>
				</div>
				<div class="stat-row success">
					<span class="stat-label"><?php esc_html_e( 'Passed:', 'wp-ai-site-generator' ); ?></span>
					<span class="stat-value">
						<?php echo esc_html( $validation_breakdown['passed'] ); ?>
						(<?php echo esc_html( round( ( $validation_breakdown['passed'] / $validation_breakdown['total_validations'] ) * 100, 1 ) ); ?>%)
					</span>
				</div>
				<div class="stat-row error">
					<span class="stat-label"><?php esc_html_e( 'Failed:', 'wp-ai-site-generator' ); ?></span>
					<span class="stat-value">
						<?php echo esc_html( $validation_breakdown['failed'] ); ?>
						(<?php echo esc_html( round( ( $validation_breakdown['failed'] / $validation_breakdown['total_validations'] ) * 100, 1 ) ); ?>%)
					</span>
				</div>
			</div>

			<?php if ( ! empty( $validation_breakdown['common_issues'] ) ) : ?>
			<h3><?php esc_html_e( 'Common Issues', 'wp-ai-site-generator' ); ?></h3>
			<ul class="common-issues">
				<?php foreach ( $validation_breakdown['common_issues'] as $issue => $count ) : ?>
				<li>
					<span class="issue-name"><?php echo esc_html( ucwords( str_replace( '_', ' ', $issue ) ) ); ?>:</span>
					<span class="issue-count"><?php echo esc_html( $count ); ?> occurrences</span>
				</li>
				<?php endforeach; ?>
			</ul>
			<?php endif; ?>
			<?php else : ?>
			<p><?php esc_html_e( 'No validation data available for the selected period.', 'wp-ai-site-generator' ); ?></p>
			<?php endif; ?>
		</div>

		<!-- Cost Analysis -->
		<div class="dashboard-section cost-analysis">
			<h2><?php esc_html_e( 'Cost by Quality Score', 'wp-ai-site-generator' ); ?></h2>
			<?php if ( ! empty( $cost_by_quality ) ) : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Quality Tier', 'wp-ai-site-generator' ); ?></th>
						<th><?php esc_html_e( 'Generations', 'wp-ai-site-generator' ); ?></th>
						<th><?php esc_html_e( 'Avg Tokens', 'wp-ai-site-generator' ); ?></th>
						<th><?php esc_html_e( 'Avg Cost', 'wp-ai-site-generator' ); ?></th>
						<th><?php esc_html_e( 'Total Cost', 'wp-ai-site-generator' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $cost_by_quality as $tier_data ) : ?>
					<tr>
						<td>
							<span class="quality-tier <?php echo sanitize_html_class( strtolower( explode( ' ', $tier_data->quality_tier )[0] ) ); ?>">
								<?php echo esc_html( $tier_data->quality_tier ); ?>
							</span>
						</td>
						<td><?php echo esc_html( $tier_data->generation_count ); ?></td>
						<td><?php echo esc_html( number_format( $tier_data->avg_tokens ) ); ?></td>
						<td>$<?php echo esc_html( number_format( $tier_data->avg_cost, 4 ) ); ?></td>
						<td><strong>$<?php echo esc_html( number_format( $tier_data->total_cost, 2 ) ); ?></strong></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php else : ?>
			<p><?php esc_html_e( 'No cost data available for the selected period.', 'wp-ai-site-generator' ); ?></p>
			<?php endif; ?>
		</div>

		<!-- Recent Generations -->
		<div class="dashboard-section recent-generations">
			<h2><?php esc_html_e( 'Recent Generations', 'wp-ai-site-generator' ); ?></h2>
			<?php if ( ! empty( $recent_metrics['results'] ) ) : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'ID', 'wp-ai-site-generator' ); ?></th>
						<th><?php esc_html_e( 'Quality Score', 'wp-ai-site-generator' ); ?></th>
						<th><?php esc_html_e( 'Provider', 'wp-ai-site-generator' ); ?></th>
						<th><?php esc_html_e( 'Content Type', 'wp-ai-site-generator' ); ?></th>
						<th><?php esc_html_e( 'Created', 'wp-ai-site-generator' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'wp-ai-site-generator' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $recent_metrics['results'] as $metric ) : ?>
					<tr>
						<td>#<?php echo esc_html( $metric->generation_id ); ?></td>
						<td>
							<div class="quality-score-badge" data-score="<?php echo esc_attr( round( $metric->quality_score ) ); ?>">
								<?php echo esc_html( round( $metric->quality_score, 1 ) ); ?>%
							</div>
						</td>
						<td><?php echo esc_html( ucfirst( $metric->provider ) ); ?></td>
						<td><?php echo esc_html( ucfirst( $metric->content_type ) ); ?></td>
						<td><?php echo esc_html( human_time_diff( strtotime( $metric->created_at ), current_time( 'timestamp' ) ) . ' ago' ); ?></td>
						<td>
							<a href="#" class="view-details" data-generation-id="<?php echo esc_attr( $metric->generation_id ); ?>">
								<?php esc_html_e( 'View Details', 'wp-ai-site-generator' ); ?>
							</a>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php else : ?>
			<p><?php esc_html_e( 'No generation data available for the selected period.', 'wp-ai-site-generator' ); ?></p>
			<?php endif; ?>
		</div>

	</div>

	<!-- React Component Mount Point -->
	<div id="waisg-quality-dashboard-root"
		data-filters='<?php echo esc_attr( wp_json_encode( $filters ) ); ?>'
		data-trends='<?php echo esc_attr( wp_json_encode( $trends ) ); ?>'
		data-providers='<?php echo esc_attr( wp_json_encode( $provider_comparison ) ); ?>'>
	</div>

</div>