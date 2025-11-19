<?php
/**
 * Funnel Dashboard
 *
 * Analytics and overview dashboard for sales funnels
 *
 * @package WP_AI_Site_Generator
 * @subpackage Admin
 */

use WP_AI_Site_Generator\Includes\Sales_Funnel;
use WP_AI_Site_Generator\Database\Funnel_DB_Handler;

if (!defined('ABSPATH')) {
    exit;
}

// Get funnel data
$sales_funnel = new Sales_Funnel();
$db_handler = new Funnel_DB_Handler();

// Get all funnels
$funnels = get_posts([
    'post_type' => 'wp_ai_funnel',
    'posts_per_page' => -1,
    'post_status' => 'any',
]);

// Get date range from query params
$date_range = $_GET['date_range'] ?? '30days';
$selected_funnel = isset($_GET['funnel_id']) ? intval($_GET['funnel_id']) : 0;

// Calculate overall statistics
$total_funnels = count($funnels);
$active_funnels = 0;
$total_visitors = 0;
$total_conversions = 0;
$total_revenue = 0;

foreach ($funnels as $funnel) {
    $status = get_post_meta($funnel->ID, '_funnel_status', true);
    if ($status === 'active') {
        $active_funnels++;
    }

    $metrics = $db_handler->get_funnel_metrics($funnel->ID, $date_range);
    $total_visitors += $metrics['total_visitors'] ?? 0;
    $total_conversions += $metrics['conversions'] ?? 0;
    $total_revenue += $metrics['revenue'] ?? 0;
}

$overall_conversion_rate = $total_visitors > 0 ? ($total_conversions / $total_visitors) * 100 : 0;

// Get selected funnel analytics
$funnel_analytics = null;
if ($selected_funnel) {
    $funnel_analytics = $sales_funnel->get_funnel_analytics($selected_funnel, $date_range);
}
?>

<div class="wrap wp-ai-funnel-dashboard">
    <h1 class="wp-heading-inline">
        <?php _e('Sales Funnel Dashboard', 'wp-ai-site-generator'); ?>
    </h1>

    <a href="<?php echo admin_url('admin.php?page=wp-ai-site-generator-funnel-builder'); ?>" class="page-title-action">
        <?php _e('Create New Funnel', 'wp-ai-site-generator'); ?>
    </a>

    <hr class="wp-header-end">

    <!-- Date Range Filter -->
    <div class="funnel-filters">
        <form method="get" action="">
            <input type="hidden" name="page" value="wp-ai-site-generator-funnel-dashboard">

            <label for="date-range"><?php _e('Date Range:', 'wp-ai-site-generator'); ?></label>
            <select name="date_range" id="date-range" onchange="this.form.submit()">
                <option value="today" <?php selected($date_range, 'today'); ?>><?php _e('Today', 'wp-ai-site-generator'); ?></option>
                <option value="7days" <?php selected($date_range, '7days'); ?>><?php _e('Last 7 Days', 'wp-ai-site-generator'); ?></option>
                <option value="30days" <?php selected($date_range, '30days'); ?>><?php _e('Last 30 Days', 'wp-ai-site-generator'); ?></option>
                <option value="90days" <?php selected($date_range, '90days'); ?>><?php _e('Last 90 Days', 'wp-ai-site-generator'); ?></option>
                <option value="all" <?php selected($date_range, 'all'); ?>><?php _e('All Time', 'wp-ai-site-generator'); ?></option>
            </select>

            <?php if ($funnels): ?>
                <label for="funnel-select"><?php _e('Funnel:', 'wp-ai-site-generator'); ?></label>
                <select name="funnel_id" id="funnel-select" onchange="this.form.submit()">
                    <option value="0"><?php _e('All Funnels', 'wp-ai-site-generator'); ?></option>
                    <?php foreach ($funnels as $funnel): ?>
                        <option value="<?php echo $funnel->ID; ?>" <?php selected($selected_funnel, $funnel->ID); ?>>
                            <?php echo esc_html($funnel->post_title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
        </form>
    </div>

    <!-- Overview Stats -->
    <div class="funnel-stats-grid">
        <div class="stat-card">
            <div class="stat-icon">📊</div>
            <div class="stat-content">
                <div class="stat-value"><?php echo number_format($total_funnels); ?></div>
                <div class="stat-label"><?php _e('Total Funnels', 'wp-ai-site-generator'); ?></div>
                <div class="stat-meta"><?php echo $active_funnels; ?> <?php _e('active', 'wp-ai-site-generator'); ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">👥</div>
            <div class="stat-content">
                <div class="stat-value"><?php echo number_format($total_visitors); ?></div>
                <div class="stat-label"><?php _e('Total Visitors', 'wp-ai-site-generator'); ?></div>
                <div class="stat-meta"><?php echo $date_range; ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">🎯</div>
            <div class="stat-content">
                <div class="stat-value"><?php echo number_format($total_conversions); ?></div>
                <div class="stat-label"><?php _e('Conversions', 'wp-ai-site-generator'); ?></div>
                <div class="stat-meta"><?php echo number_format($overall_conversion_rate, 2); ?>% <?php _e('rate', 'wp-ai-site-generator'); ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">💰</div>
            <div class="stat-content">
                <div class="stat-value">$<?php echo number_format($total_revenue, 2); ?></div>
                <div class="stat-label"><?php _e('Revenue', 'wp-ai-site-generator'); ?></div>
                <div class="stat-meta">
                    <?php
                    $aov = $total_conversions > 0 ? $total_revenue / $total_conversions : 0;
                    echo '$' . number_format($aov, 2) . ' ' . __('AOV', 'wp-ai-site-generator');
                    ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Funnel Performance Table -->
    <div class="funnel-performance">
        <h2><?php _e('Funnel Performance', 'wp-ai-site-generator'); ?></h2>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Funnel Name', 'wp-ai-site-generator'); ?></th>
                    <th><?php _e('Status', 'wp-ai-site-generator'); ?></th>
                    <th><?php _e('Template', 'wp-ai-site-generator'); ?></th>
                    <th><?php _e('Visitors', 'wp-ai-site-generator'); ?></th>
                    <th><?php _e('Conversions', 'wp-ai-site-generator'); ?></th>
                    <th><?php _e('Conv. Rate', 'wp-ai-site-generator'); ?></th>
                    <th><?php _e('Revenue', 'wp-ai-site-generator'); ?></th>
                    <th><?php _e('Actions', 'wp-ai-site-generator'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($funnels): ?>
                    <?php foreach ($funnels as $funnel):
                        $metrics = $db_handler->get_funnel_metrics($funnel->ID, $date_range);
                        $status = get_post_meta($funnel->ID, '_funnel_status', true) ?: 'draft';
                        $template = get_post_meta($funnel->ID, '_funnel_template', true) ?: 'custom';
                    ?>
                        <tr>
                            <td>
                                <strong>
                                    <a href="<?php echo admin_url('admin.php?page=wp-ai-site-generator-funnel-builder&funnel_id=' . $funnel->ID); ?>">
                                        <?php echo esc_html($funnel->post_title); ?>
                                    </a>
                                </strong>
                            </td>
                            <td>
                                <span class="funnel-status status-<?php echo esc_attr($status); ?>">
                                    <?php echo ucfirst($status); ?>
                                </span>
                            </td>
                            <td><?php echo ucwords(str_replace('_', ' ', $template)); ?></td>
                            <td><?php echo number_format($metrics['total_visitors']); ?></td>
                            <td><?php echo number_format($metrics['conversions']); ?></td>
                            <td><?php echo number_format($metrics['conversion_rate'], 2); ?>%</td>
                            <td>$<?php echo number_format($metrics['revenue'], 2); ?></td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=wp-ai-site-generator-funnel-builder&funnel_id=' . $funnel->ID); ?>" class="button button-small">
                                    <?php _e('Edit', 'wp-ai-site-generator'); ?>
                                </a>
                                <a href="<?php echo admin_url('admin.php?page=wp-ai-site-generator-funnel-dashboard&funnel_id=' . $funnel->ID); ?>" class="button button-small">
                                    <?php _e('Analytics', 'wp-ai-site-generator'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center;">
                            <?php _e('No funnels found. Create your first funnel to get started!', 'wp-ai-site-generator'); ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Selected Funnel Analytics -->
    <?php if ($funnel_analytics): ?>
        <div class="funnel-detailed-analytics">
            <h2><?php _e('Funnel Analytics', 'wp-ai-site-generator'); ?>: <?php echo esc_html(get_the_title($selected_funnel)); ?></h2>

            <!-- Funnel Visualization -->
            <div class="funnel-visualization">
                <h3><?php _e('Conversion Funnel', 'wp-ai-site-generator'); ?></h3>
                <div class="funnel-stages-flow">
                    <?php
                    $stages = $funnel_analytics['stages'] ?? [];
                    $max_views = max(array_column($stages, 'views'));
                    ?>

                    <?php foreach ($stages as $index => $stage): ?>
                        <div class="funnel-stage-block">
                            <div class="stage-name"><?php echo esc_html($stage['stage_id']); ?></div>
                            <div class="stage-bar-container">
                                <div class="stage-bar" style="width: <?php echo ($max_views > 0) ? ($stage['views'] / $max_views * 100) : 0; ?>%;">
                                    <span class="stage-count"><?php echo number_format($stage['views']); ?> visitors</span>
                                </div>
                            </div>
                            <div class="stage-metrics">
                                <span><?php echo number_format($stage['conversions']); ?> conversions</span>
                                <span><?php echo number_format($stage['conversion_rate'], 2); ?>% rate</span>
                            </div>

                            <?php if ($index < count($stages) - 1): ?>
                                <div class="stage-drop-off">
                                    <?php
                                    $drop_off = $stage['views'] - ($stages[$index + 1]['views'] ?? 0);
                                    $drop_off_rate = $stage['views'] > 0 ? ($drop_off / $stage['views'] * 100) : 0;
                                    ?>
                                    ↓ <?php echo number_format($drop_off_rate, 1); ?>% drop-off
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Drop-off Analysis -->
            <?php if (!empty($funnel_analytics['drop_off_points'])): ?>
                <div class="drop-off-analysis">
                    <h3><?php _e('Major Drop-off Points', 'wp-ai-site-generator'); ?></h3>
                    <div class="drop-off-list">
                        <?php foreach ($funnel_analytics['drop_off_points'] as $point): ?>
                            <div class="drop-off-item">
                                <span class="drop-off-location">
                                    <?php echo esc_html($point['between'][0]); ?> → <?php echo esc_html($point['between'][1]); ?>
                                </span>
                                <span class="drop-off-rate"><?php echo number_format($point['rate'], 1); ?>% loss</span>
                                <span class="drop-off-count">(<?php echo number_format($point['count']); ?> visitors)</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Recent Activity -->
    <div class="recent-activity">
        <h2><?php _e('Recent Conversions', 'wp-ai-site-generator'); ?></h2>

        <?php
        global $wpdb;
        $conversions_table = $wpdb->prefix . 'ai_funnel_conversions';

        $recent_conversions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$conversions_table}
                 WHERE funnel_id = %d OR %d = 0
                 ORDER BY created_at DESC
                 LIMIT 10",
                $selected_funnel,
                $selected_funnel
            )
        );
        ?>

        <?php if ($recent_conversions): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Date/Time', 'wp-ai-site-generator'); ?></th>
                        <th><?php _e('Funnel', 'wp-ai-site-generator'); ?></th>
                        <th><?php _e('Type', 'wp-ai-site-generator'); ?></th>
                        <th><?php _e('Email', 'wp-ai-site-generator'); ?></th>
                        <th><?php _e('Value', 'wp-ai-site-generator'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_conversions as $conversion): ?>
                        <tr>
                            <td><?php echo human_time_diff(strtotime($conversion->created_at)) . ' ago'; ?></td>
                            <td><?php echo esc_html(get_the_title($conversion->funnel_id)); ?></td>
                            <td><?php echo esc_html(ucwords(str_replace('_', ' ', $conversion->conversion_type))); ?></td>
                            <td><?php echo esc_html($conversion->customer_email ?: 'Anonymous'); ?></td>
                            <td>$<?php echo number_format($conversion->conversion_value, 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p><?php _e('No recent conversions found.', 'wp-ai-site-generator'); ?></p>
        <?php endif; ?>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
        <h2><?php _e('Quick Actions', 'wp-ai-site-generator'); ?></h2>
        <div class="action-buttons">
            <a href="<?php echo admin_url('admin.php?page=wp-ai-site-generator-funnel-builder'); ?>" class="button button-primary">
                <?php _e('Create New Funnel', 'wp-ai-site-generator'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=wp-ai-site-generator-lead-magnets'); ?>" class="button">
                <?php _e('Generate Lead Magnet', 'wp-ai-site-generator'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=wp-ai-site-generator-email-campaigns'); ?>" class="button">
                <?php _e('Create Email Campaign', 'wp-ai-site-generator'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=wp-ai-site-generator-ab-tests'); ?>" class="button">
                <?php _e('Setup A/B Test', 'wp-ai-site-generator'); ?>
            </a>
        </div>
    </div>
</div>

<style>
/* Dashboard Styles */
.wp-ai-funnel-dashboard {
    max-width: 1400px;
}

.funnel-filters {
    margin: 20px 0;
    padding: 15px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.funnel-filters form {
    display: flex;
    gap: 15px;
    align-items: center;
}

.funnel-filters select {
    min-width: 150px;
}

.funnel-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin: 30px 0;
}

.stat-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.stat-icon {
    font-size: 36px;
    opacity: 0.8;
}

.stat-content {
    flex: 1;
}

.stat-value {
    font-size: 28px;
    font-weight: bold;
    color: #333;
}

.stat-label {
    color: #666;
    margin-top: 5px;
}

.stat-meta {
    font-size: 12px;
    color: #999;
    margin-top: 5px;
}

.funnel-performance {
    background: #fff;
    padding: 20px;
    margin: 30px 0;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.funnel-status {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 500;
}

.funnel-status.status-active {
    background: #d4edda;
    color: #155724;
}

.funnel-status.status-paused {
    background: #fff3cd;
    color: #856404;
}

.funnel-status.status-draft {
    background: #e2e3e5;
    color: #383d41;
}

.funnel-detailed-analytics {
    background: #fff;
    padding: 20px;
    margin: 30px 0;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.funnel-visualization {
    margin: 20px 0;
}

.funnel-stages-flow {
    padding: 20px;
    background: #f9f9f9;
    border-radius: 4px;
}

.funnel-stage-block {
    margin-bottom: 20px;
    position: relative;
}

.stage-name {
    font-weight: bold;
    margin-bottom: 10px;
    color: #333;
}

.stage-bar-container {
    background: #e0e0e0;
    border-radius: 4px;
    height: 40px;
    position: relative;
    overflow: hidden;
}

.stage-bar {
    background: linear-gradient(90deg, #007cba, #005a87);
    height: 100%;
    display: flex;
    align-items: center;
    padding: 0 15px;
    color: white;
    transition: width 0.3s ease;
}

.stage-count {
    font-size: 14px;
    font-weight: 500;
}

.stage-metrics {
    display: flex;
    gap: 15px;
    margin-top: 5px;
    font-size: 12px;
    color: #666;
}

.stage-drop-off {
    position: absolute;
    right: 0;
    top: 50%;
    transform: translateY(-50%);
    background: #ff6b6b;
    color: white;
    padding: 5px 10px;
    border-radius: 3px;
    font-size: 12px;
}

.drop-off-analysis {
    margin: 30px 0;
    padding: 20px;
    background: #fff5f5;
    border: 1px solid #ffdddd;
    border-radius: 4px;
}

.drop-off-list {
    margin-top: 15px;
}

.drop-off-item {
    display: flex;
    justify-content: space-between;
    padding: 10px;
    background: white;
    margin-bottom: 10px;
    border-radius: 4px;
    border-left: 3px solid #ff6b6b;
}

.drop-off-location {
    font-weight: 500;
}

.drop-off-rate {
    color: #ff6b6b;
    font-weight: bold;
}

.recent-activity {
    background: #fff;
    padding: 20px;
    margin: 30px 0;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.quick-actions {
    background: #fff;
    padding: 20px;
    margin: 30px 0;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.action-buttons {
    display: flex;
    gap: 10px;
    margin-top: 15px;
    flex-wrap: wrap;
}
</style>