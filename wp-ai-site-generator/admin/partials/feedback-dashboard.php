<?php
/**
 * Admin Feedback Dashboard
 *
 * @package WP_AI_Site_Generator
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get feedback handler instance
$feedback_handler = new \WP_AI_Site_Generator\Includes\User_Feedback();
$feedback_learner = new \WP_AI_Site_Generator\Includes\Feedback_Learner();
$db_handler = new \WP_AI_Site_Generator\Database\Feedback_DB_Handler();

// Get period from request
$period = isset($_GET['period']) ? sanitize_text_field($_GET['period']) : '30days';
$view = isset($_GET['view']) ? sanitize_text_field($_GET['view']) : 'overview';

// Get insights and statistics
$insights = $db_handler->get_aggregated_insights($period, 'all');
$analyzed_insights = $feedback_learner->generate_insights(['insights' => $insights]);
$top_prompts = $feedback_learner->get_top_rated_prompts();

// Calculate summary statistics
$total_feedback = $insights['overall_stats']['total_feedback'] ?? 0;
$avg_rating = $insights['overall_stats']['avg_rating'] ?? 0;
$positive_ratio = 0;
if (isset($insights['quick_feedback']['total']) && $insights['quick_feedback']['total'] > 0) {
    $positive_ratio = ($insights['quick_feedback']['positive'] / $insights['quick_feedback']['total']) * 100;
}

?>
<div class="wrap wp-ai-feedback-dashboard">
    <h1 class="wp-heading-inline">
        <?php _e('Feedback Dashboard', 'wp-ai-site-generator'); ?>
    </h1>

    <!-- Period Selector -->
    <div class="tablenav top">
        <div class="alignleft actions">
            <label for="period-selector" class="screen-reader-text">
                <?php _e('Select time period', 'wp-ai-site-generator'); ?>
            </label>
            <select id="period-selector" name="period">
                <option value="7days" <?php selected($period, '7days'); ?>>
                    <?php _e('Last 7 Days', 'wp-ai-site-generator'); ?>
                </option>
                <option value="30days" <?php selected($period, '30days'); ?>>
                    <?php _e('Last 30 Days', 'wp-ai-site-generator'); ?>
                </option>
                <option value="90days" <?php selected($period, '90days'); ?>>
                    <?php _e('Last 90 Days', 'wp-ai-site-generator'); ?>
                </option>
                <option value="1year" <?php selected($period, '1year'); ?>>
                    <?php _e('Last Year', 'wp-ai-site-generator'); ?>
                </option>
            </select>
            <button class="button" onclick="updatePeriod()">
                <?php _e('Apply', 'wp-ai-site-generator'); ?>
            </button>
        </div>

        <div class="alignright">
            <button class="button" onclick="exportFeedback('csv')">
                <?php _e('Export CSV', 'wp-ai-site-generator'); ?>
            </button>
            <button class="button" onclick="exportFeedback('json')">
                <?php _e('Export JSON', 'wp-ai-site-generator'); ?>
            </button>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <nav class="nav-tab-wrapper wp-clearfix">
        <a href="?page=wp-ai-feedback&view=overview"
           class="nav-tab <?php echo $view === 'overview' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Overview', 'wp-ai-site-generator'); ?>
        </a>
        <a href="?page=wp-ai-feedback&view=ratings"
           class="nav-tab <?php echo $view === 'ratings' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Ratings', 'wp-ai-site-generator'); ?>
        </a>
        <a href="?page=wp-ai-feedback&view=issues"
           class="nav-tab <?php echo $view === 'issues' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Issues', 'wp-ai-site-generator'); ?>
        </a>
        <a href="?page=wp-ai-feedback&view=suggestions"
           class="nav-tab <?php echo $view === 'suggestions' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Suggestions', 'wp-ai-site-generator'); ?>
        </a>
        <a href="?page=wp-ai-feedback&view=insights"
           class="nav-tab <?php echo $view === 'insights' ? 'nav-tab-active' : ''; ?>">
            <?php _e('AI Insights', 'wp-ai-site-generator'); ?>
        </a>
        <a href="?page=wp-ai-feedback&view=ab-tests"
           class="nav-tab <?php echo $view === 'ab-tests' ? 'nav-tab-active' : ''; ?>">
            <?php _e('A/B Tests', 'wp-ai-site-generator'); ?>
        </a>
    </nav>

    <div class="tab-content">
        <?php
        switch ($view) {
            case 'overview':
                include 'feedback-dashboard-overview.php';
                break;
            case 'ratings':
                include 'feedback-dashboard-ratings.php';
                break;
            case 'issues':
                include 'feedback-dashboard-issues.php';
                break;
            case 'suggestions':
                include 'feedback-dashboard-suggestions.php';
                break;
            case 'insights':
                include 'feedback-dashboard-insights.php';
                break;
            case 'ab-tests':
                include 'feedback-dashboard-ab-tests.php';
                break;
            default:
                include 'feedback-dashboard-overview.php';
        }
        ?>
    </div>
</div>

<!-- Overview Tab Content (inline for main dashboard) -->
<?php if ($view === 'overview'): ?>
<div class="feedback-overview">
    <!-- Summary Cards -->
    <div class="summary-cards">
        <div class="card">
            <h3><?php _e('Total Feedback', 'wp-ai-site-generator'); ?></h3>
            <div class="card-value"><?php echo number_format($total_feedback); ?></div>
            <div class="card-description">
                <?php _e('Feedback submissions received', 'wp-ai-site-generator'); ?>
            </div>
        </div>

        <div class="card">
            <h3><?php _e('Average Rating', 'wp-ai-site-generator'); ?></h3>
            <div class="card-value">
                <?php echo number_format($avg_rating, 1); ?>/5
                <span class="stars">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <span class="star <?php echo $i <= round($avg_rating) ? 'filled' : ''; ?>">★</span>
                    <?php endfor; ?>
                </span>
            </div>
            <div class="card-description">
                <?php _e('Overall content rating', 'wp-ai-site-generator'); ?>
            </div>
        </div>

        <div class="card">
            <h3><?php _e('Satisfaction Rate', 'wp-ai-site-generator'); ?></h3>
            <div class="card-value"><?php echo number_format($positive_ratio, 0); ?>%</div>
            <div class="card-description">
                <?php _e('Positive quick feedback', 'wp-ai-site-generator'); ?>
            </div>
        </div>

        <div class="card">
            <h3><?php _e('Issues Reported', 'wp-ai-site-generator'); ?></h3>
            <div class="card-value">
                <?php echo count($insights['common_issues'] ?? []); ?>
            </div>
            <div class="card-description">
                <?php _e('Active issues to address', 'wp-ai-site-generator'); ?>
            </div>
        </div>
    </div>

    <!-- Rating Distribution Chart -->
    <div class="section rating-distribution">
        <h2><?php _e('Rating Distribution', 'wp-ai-site-generator'); ?></h2>
        <div class="chart-container">
            <canvas id="rating-distribution-chart"></canvas>
        </div>
    </div>

    <!-- Rating by Dimension -->
    <div class="section rating-dimensions">
        <h2><?php _e('Ratings by Dimension', 'wp-ai-site-generator'); ?></h2>
        <table class="widefat">
            <thead>
                <tr>
                    <th><?php _e('Dimension', 'wp-ai-site-generator'); ?></th>
                    <th><?php _e('Average Rating', 'wp-ai-site-generator'); ?></th>
                    <th><?php _e('Visual', 'wp-ai-site-generator'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                $dimensions = ['overall', 'quality', 'accuracy', 'relevance', 'usefulness'];
                foreach ($dimensions as $dimension):
                    $rating = $insights['average_ratings'][$dimension] ?? 0;
                ?>
                <tr>
                    <td><?php echo ucfirst($dimension); ?></td>
                    <td><?php echo number_format($rating, 2); ?></td>
                    <td>
                        <div class="rating-bar">
                            <div class="rating-fill" style="width: <?php echo ($rating / 5 * 100); ?>%"></div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Top Performing Prompts -->
    <?php if (!empty($top_prompts)): ?>
    <div class="section top-prompts">
        <h2><?php _e('Top Performing Prompts', 'wp-ai-site-generator'); ?></h2>
        <table class="widefat">
            <thead>
                <tr>
                    <th><?php _e('Prompt Template', 'wp-ai-site-generator'); ?></th>
                    <th><?php _e('Avg Rating', 'wp-ai-site-generator'); ?></th>
                    <th><?php _e('Usage Count', 'wp-ai-site-generator'); ?></th>
                    <th><?php _e('Actions', 'wp-ai-site-generator'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_slice($top_prompts, 0, 5) as $prompt): ?>
                <tr>
                    <td class="prompt-template">
                        <?php echo esc_html(substr($prompt['prompt_template'], 0, 100) . '...'); ?>
                    </td>
                    <td>
                        <?php echo number_format($prompt['avg_rating'], 2); ?>
                        <span class="stars-mini">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <span class="star <?php echo $i <= round($prompt['avg_rating']) ? 'filled' : ''; ?>">★</span>
                            <?php endfor; ?>
                        </span>
                    </td>
                    <td><?php echo $prompt['usage_count']; ?></td>
                    <td>
                        <button class="button button-small" onclick="viewPromptDetails('<?php echo esc_attr($prompt['prompt_template']); ?>')">
                            <?php _e('View Details', 'wp-ai-site-generator'); ?>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Common Issues -->
    <?php if (!empty($insights['common_issues'])): ?>
    <div class="section common-issues">
        <h2><?php _e('Common Issues', 'wp-ai-site-generator'); ?></h2>
        <table class="widefat">
            <thead>
                <tr>
                    <th><?php _e('Issue Type', 'wp-ai-site-generator'); ?></th>
                    <th><?php _e('Frequency', 'wp-ai-site-generator'); ?></th>
                    <th><?php _e('Avg Severity', 'wp-ai-site-generator'); ?></th>
                    <th><?php _e('Status', 'wp-ai-site-generator'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($insights['common_issues'] as $issue): ?>
                <tr>
                    <td><?php echo esc_html($issue['issue_type']); ?></td>
                    <td><?php echo $issue['count']; ?></td>
                    <td>
                        <?php
                        $severity_map = [0 => 'Low', 1 => 'Medium', 2 => 'High', 3 => 'Critical'];
                        echo $severity_map[round($issue['avg_severity'])] ?? 'Unknown';
                        ?>
                    </td>
                    <td>
                        <span class="status-badge status-open">
                            <?php _e('Open', 'wp-ai-site-generator'); ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Key Findings and Recommendations -->
    <?php if (!empty($analyzed_insights)): ?>
    <div class="section insights-summary">
        <h2><?php _e('AI-Generated Insights', 'wp-ai-site-generator'); ?></h2>

        <?php if (!empty($analyzed_insights['key_findings'])): ?>
        <div class="insights-box">
            <h3><?php _e('Key Findings', 'wp-ai-site-generator'); ?></h3>
            <ul>
                <?php foreach ($analyzed_insights['key_findings'] as $finding): ?>
                <li><?php echo esc_html($finding); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if (!empty($analyzed_insights['recommendations'])): ?>
        <div class="insights-box">
            <h3><?php _e('Recommendations', 'wp-ai-site-generator'); ?></h3>
            <ul>
                <?php foreach ($analyzed_insights['recommendations'] as $recommendation): ?>
                <li>
                    <?php
                    if (is_array($recommendation)) {
                        echo esc_html($recommendation['message'] ?? '');
                    } else {
                        echo esc_html($recommendation);
                    }
                    ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if (!empty($analyzed_insights['action_items'])): ?>
        <div class="insights-box">
            <h3><?php _e('Action Items', 'wp-ai-site-generator'); ?></h3>
            <ul>
                <?php foreach ($analyzed_insights['action_items'] as $action): ?>
                <li><?php echo esc_html($action); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Trend Analysis -->
    <div class="section trend-analysis">
        <h2><?php _e('Trend Analysis', 'wp-ai-site-generator'); ?></h2>
        <div class="trend-charts">
            <div class="chart-container">
                <h3><?php _e('Rating Trend', 'wp-ai-site-generator'); ?></h3>
                <canvas id="rating-trend-chart"></canvas>
            </div>
            <div class="chart-container">
                <h3><?php _e('Feedback Volume', 'wp-ai-site-generator'); ?></h3>
                <canvas id="volume-trend-chart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for charts and interactions -->
<script>
jQuery(document).ready(function($) {
    // Initialize rating distribution chart
    if (document.getElementById('rating-distribution-chart')) {
        const ctx = document.getElementById('rating-distribution-chart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['1 Star', '2 Stars', '3 Stars', '4 Stars', '5 Stars'],
                datasets: [{
                    label: 'Number of Ratings',
                    data: [
                        <?php
                        // Get rating distribution data
                        global $wpdb;
                        $distribution = $wpdb->get_results(
                            "SELECT rating, COUNT(*) as count
                            FROM {$wpdb->prefix}ai_feedback_ratings
                            WHERE dimension = 'overall'
                            GROUP BY rating
                            ORDER BY rating",
                            ARRAY_A
                        );

                        $rating_counts = array_fill(1, 5, 0);
                        foreach ($distribution as $item) {
                            $rating_counts[$item['rating']] = $item['count'];
                        }
                        echo implode(',', $rating_counts);
                        ?>
                    ],
                    backgroundColor: [
                        '#dc3545',
                        '#ffc107',
                        '#17a2b8',
                        '#28a745',
                        '#007bff'
                    ]
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                },
                responsive: true,
                maintainAspectRatio: false
            }
        });
    }

    // Period selector
    window.updatePeriod = function() {
        const period = $('#period-selector').val();
        window.location.href = '?page=wp-ai-feedback&period=' + period + '&view=<?php echo $view; ?>';
    };

    // Export functionality
    window.exportFeedback = function(format) {
        const period = $('#period-selector').val();

        $.ajax({
            url: wpaiSettings.apiUrl + 'feedback/export',
            method: 'GET',
            data: {
                format: format,
                period: period
            },
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', wpaiSettings.nonce);
            },
            success: function(response) {
                if (response.download_url) {
                    window.location.href = response.download_url;
                }
            },
            error: function(xhr, status, error) {
                alert('Export failed: ' + error);
            }
        });
    };

    // View prompt details
    window.viewPromptDetails = function(promptTemplate) {
        // Open modal or navigate to detailed view
        console.log('View details for:', promptTemplate);
    };

    // Initialize trend charts
    if (document.getElementById('rating-trend-chart')) {
        // Fetch and render rating trend data
        $.ajax({
            url: wpaiSettings.apiUrl + 'feedback/trends',
            method: 'GET',
            data: {
                period: '<?php echo $period; ?>',
                metric: 'rating'
            },
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', wpaiSettings.nonce);
            },
            success: function(response) {
                if (response.data) {
                    renderTrendChart('rating-trend-chart', response.data);
                }
            }
        });
    }

    if (document.getElementById('volume-trend-chart')) {
        // Fetch and render volume trend data
        $.ajax({
            url: wpaiSettings.apiUrl + 'feedback/trends',
            method: 'GET',
            data: {
                period: '<?php echo $period; ?>',
                metric: 'volume'
            },
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', wpaiSettings.nonce);
            },
            success: function(response) {
                if (response.data) {
                    renderTrendChart('volume-trend-chart', response.data);
                }
            }
        });
    }

    function renderTrendChart(canvasId, data) {
        const ctx = document.getElementById(canvasId).getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.map(d => d.date),
                datasets: [{
                    label: 'Trend',
                    data: data.map(d => d.value),
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
});
</script>

<style>
.wp-ai-feedback-dashboard {
    max-width: 1200px;
}

.summary-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.card h3 {
    margin: 0 0 10px 0;
    color: #666;
    font-size: 14px;
    font-weight: normal;
    text-transform: uppercase;
}

.card-value {
    font-size: 32px;
    font-weight: bold;
    color: #333;
    margin: 10px 0;
}

.card-description {
    color: #666;
    font-size: 13px;
}

.stars {
    display: inline-block;
    margin-left: 10px;
}

.star {
    color: #ddd;
    font-size: 20px;
}

.star.filled {
    color: #ffb900;
}

.section {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 20px;
    margin: 20px 0;
}

.chart-container {
    position: relative;
    height: 300px;
    margin: 20px 0;
}

.rating-bar {
    width: 200px;
    height: 20px;
    background: #f0f0f0;
    border-radius: 10px;
    overflow: hidden;
}

.rating-fill {
    height: 100%;
    background: linear-gradient(90deg, #28a745, #ffc107);
    transition: width 0.3s ease;
}

.trend-charts {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.insights-box {
    background: #f9f9f9;
    border-left: 4px solid #0073aa;
    padding: 15px;
    margin: 15px 0;
}

.insights-box h3 {
    margin-top: 0;
}

.status-badge {
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 11px;
    text-transform: uppercase;
    font-weight: bold;
}

.status-open {
    background: #fff3cd;
    color: #856404;
}

.prompt-template {
    max-width: 400px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.stars-mini .star {
    font-size: 14px;
}

@media (max-width: 768px) {
    .summary-cards {
        grid-template-columns: 1fr;
    }

    .trend-charts {
        grid-template-columns: 1fr;
    }
}
</style>
<?php endif; ?>