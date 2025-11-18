<?php
/**
 * Generation history view
 *
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/admin/partials
 * @since      1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get generations from database
global $wpdb;
$table_name = $wpdb->prefix . 'waisg_generations';

// Pagination
$page = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
$per_page = 20;
$offset = ( $page - 1 ) * $per_page;

// Filters
$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';
$date_filter = isset( $_GET['date_range'] ) ? sanitize_text_field( $_GET['date_range'] ) : '';

// Build query
$where_clause = 'WHERE 1=1';
$where_params = array();

if ( $status_filter ) {
	$where_clause .= ' AND status = %s';
	$where_params[] = $status_filter;
}

if ( $date_filter === 'today' ) {
	$where_clause .= ' AND DATE(created_at) = CURDATE()';
} elseif ( $date_filter === 'week' ) {
	$where_clause .= ' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
} elseif ( $date_filter === 'month' ) {
	$where_clause .= ' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
}

// Get total count
$total_query = "SELECT COUNT(*) FROM $table_name $where_clause";
if ( ! empty( $where_params ) ) {
	$total_query = $wpdb->prepare( $total_query, $where_params );
}
$total_items = $wpdb->get_var( $total_query );

// Get generations
$query = "SELECT * FROM $table_name $where_clause ORDER BY created_at DESC LIMIT %d OFFSET %d";
$query_params = array_merge( $where_params, array( $per_page, $offset ) );
$generations = $wpdb->get_results( $wpdb->prepare( $query, $query_params ) );

// Calculate total pages
$total_pages = ceil( $total_items / $per_page );
?>

<div class="wrap waisg-history-page">
	<h1 class="wp-heading-inline"><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-ai-site-generator-generate' ) ); ?>" class="page-title-action">
		<?php esc_html_e( 'Generate New', 'wp-ai-site-generator' ); ?>
	</a>

	<hr class="wp-header-end">

	<!-- Filters -->
	<div class="tablenav top">
		<div class="alignleft actions">
			<select name="status" id="filter-by-status">
				<option value=""><?php esc_html_e( 'All Statuses', 'wp-ai-site-generator' ); ?></option>
				<option value="completed" <?php selected( $status_filter, 'completed' ); ?>>
					<?php esc_html_e( 'Completed', 'wp-ai-site-generator' ); ?>
				</option>
				<option value="processing" <?php selected( $status_filter, 'processing' ); ?>>
					<?php esc_html_e( 'Processing', 'wp-ai-site-generator' ); ?>
				</option>
				<option value="failed" <?php selected( $status_filter, 'failed' ); ?>>
					<?php esc_html_e( 'Failed', 'wp-ai-site-generator' ); ?>
				</option>
				<option value="cancelled" <?php selected( $status_filter, 'cancelled' ); ?>>
					<?php esc_html_e( 'Cancelled', 'wp-ai-site-generator' ); ?>
				</option>
			</select>

			<select name="date_range" id="filter-by-date">
				<option value=""><?php esc_html_e( 'All Time', 'wp-ai-site-generator' ); ?></option>
				<option value="today" <?php selected( $date_filter, 'today' ); ?>>
					<?php esc_html_e( 'Today', 'wp-ai-site-generator' ); ?>
				</option>
				<option value="week" <?php selected( $date_filter, 'week' ); ?>>
					<?php esc_html_e( 'Last 7 Days', 'wp-ai-site-generator' ); ?>
				</option>
				<option value="month" <?php selected( $date_filter, 'month' ); ?>>
					<?php esc_html_e( 'Last 30 Days', 'wp-ai-site-generator' ); ?>
				</option>
			</select>

			<button type="button" class="button" id="apply-filters">
				<?php esc_html_e( 'Filter', 'wp-ai-site-generator' ); ?>
			</button>

			<button type="button" class="button" id="clear-filters">
				<?php esc_html_e( 'Clear', 'wp-ai-site-generator' ); ?>
			</button>
		</div>

		<div class="alignleft actions">
			<button type="button" class="button" id="bulk-delete">
				<?php esc_html_e( 'Delete Selected', 'wp-ai-site-generator' ); ?>
			</button>

			<button type="button" class="button" id="export-history">
				<?php esc_html_e( 'Export CSV', 'wp-ai-site-generator' ); ?>
			</button>
		</div>

		<?php if ( $total_pages > 1 ) : ?>
			<div class="tablenav-pages">
				<span class="displaying-num">
					<?php
					printf(
						/* translators: %s: Number of items */
						_n( '%s item', '%s items', $total_items, 'wp-ai-site-generator' ),
						number_format_i18n( $total_items )
					);
					?>
				</span>

				<?php
				echo paginate_links( array(
					'base' => add_query_arg( 'paged', '%#%' ),
					'format' => '',
					'prev_text' => '&laquo;',
					'next_text' => '&raquo;',
					'total' => $total_pages,
					'current' => $page,
				) );
				?>
			</div>
		<?php endif; ?>
	</div>

	<!-- History Table -->
	<table class="wp-list-table widefat fixed striped waisg-history-table">
		<thead>
			<tr>
				<td class="manage-column column-cb check-column">
					<input type="checkbox" id="select-all" />
				</td>
				<th scope="col" class="manage-column column-id">
					<?php esc_html_e( 'ID', 'wp-ai-site-generator' ); ?>
				</th>
				<th scope="col" class="manage-column column-prompt">
					<?php esc_html_e( 'Prompt', 'wp-ai-site-generator' ); ?>
				</th>
				<th scope="col" class="manage-column column-provider">
					<?php esc_html_e( 'Provider', 'wp-ai-site-generator' ); ?>
				</th>
				<th scope="col" class="manage-column column-status">
					<?php esc_html_e( 'Status', 'wp-ai-site-generator' ); ?>
				</th>
				<th scope="col" class="manage-column column-pages">
					<?php esc_html_e( 'Pages', 'wp-ai-site-generator' ); ?>
				</th>
				<th scope="col" class="manage-column column-date">
					<?php esc_html_e( 'Date', 'wp-ai-site-generator' ); ?>
				</th>
				<th scope="col" class="manage-column column-actions">
					<?php esc_html_e( 'Actions', 'wp-ai-site-generator' ); ?>
				</th>
			</tr>
		</thead>
		<tbody>
			<?php if ( $generations ) : ?>
				<?php foreach ( $generations as $generation ) : ?>
					<?php
					$metadata = json_decode( $generation->metadata, true );
					$pages_count = is_array( $metadata ) && isset( $metadata['pages_count'] ) ? $metadata['pages_count'] : 0;
					$provider_display = ucfirst( $generation->provider );
					?>
					<tr class="generation-row" data-generation-id="<?php echo esc_attr( $generation->id ); ?>">
						<th scope="row" class="check-column">
							<input type="checkbox" name="generation[]" value="<?php echo esc_attr( $generation->id ); ?>" />
						</th>
						<td class="column-id">
							<strong>#<?php echo esc_html( $generation->id ); ?></strong>
						</td>
						<td class="column-prompt">
							<div class="prompt-preview">
								<?php echo esc_html( wp_trim_words( $generation->prompt, 15 ) ); ?>
							</div>
							<button type="button" class="button-link view-full-prompt" data-prompt="<?php echo esc_attr( $generation->prompt ); ?>">
								<?php esc_html_e( 'View full prompt', 'wp-ai-site-generator' ); ?>
							</button>
						</td>
						<td class="column-provider">
							<span class="provider-badge provider-<?php echo esc_attr( $generation->provider ); ?>">
								<?php echo esc_html( $provider_display ); ?>
							</span>
						</td>
						<td class="column-status">
							<?php
							$status_class = 'status-' . $generation->status;
							$status_text = ucfirst( $generation->status );
							?>
							<span class="status-badge <?php echo esc_attr( $status_class ); ?>">
								<?php echo esc_html( $status_text ); ?>
							</span>
							<?php if ( $generation->status === 'processing' ) : ?>
								<div class="progress-bar">
									<div class="progress-fill" style="width: <?php echo esc_attr( $metadata['progress'] ?? 0 ); ?>%"></div>
								</div>
							<?php endif; ?>
						</td>
						<td class="column-pages">
							<?php if ( $pages_count > 0 ) : ?>
								<span class="pages-count"><?php echo esc_html( $pages_count ); ?></span>
							<?php else : ?>
								<span class="dashicons dashicons-minus"></span>
							<?php endif; ?>
						</td>
						<td class="column-date">
							<span title="<?php echo esc_attr( $generation->created_at ); ?>">
								<?php echo esc_html( human_time_diff( strtotime( $generation->created_at ), current_time( 'timestamp' ) ) ); ?>
								<?php esc_html_e( 'ago', 'wp-ai-site-generator' ); ?>
							</span>
						</td>
						<td class="column-actions">
							<div class="row-actions">
								<?php if ( $generation->status === 'completed' ) : ?>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-ai-site-generator-generate&action=view&id=' . $generation->id ) ); ?>"
									   class="button button-small"
									   title="<?php esc_attr_e( 'View', 'wp-ai-site-generator' ); ?>">
										<span class="dashicons dashicons-visibility"></span>
									</a>
									<button type="button"
											class="button button-small regenerate-btn"
											data-generation-id="<?php echo esc_attr( $generation->id ); ?>"
											title="<?php esc_attr_e( 'Regenerate', 'wp-ai-site-generator' ); ?>">
										<span class="dashicons dashicons-update"></span>
									</button>
									<button type="button"
											class="button button-small duplicate-btn"
											data-generation-id="<?php echo esc_attr( $generation->id ); ?>"
											title="<?php esc_attr_e( 'Duplicate', 'wp-ai-site-generator' ); ?>">
										<span class="dashicons dashicons-admin-page"></span>
									</button>
								<?php elseif ( $generation->status === 'processing' ) : ?>
									<button type="button"
											class="button button-small cancel-btn"
											data-generation-id="<?php echo esc_attr( $generation->id ); ?>"
											title="<?php esc_attr_e( 'Cancel', 'wp-ai-site-generator' ); ?>">
										<span class="dashicons dashicons-no"></span>
									</button>
								<?php elseif ( $generation->status === 'failed' ) : ?>
									<button type="button"
											class="button button-small retry-btn"
											data-generation-id="<?php echo esc_attr( $generation->id ); ?>"
											title="<?php esc_attr_e( 'Retry', 'wp-ai-site-generator' ); ?>">
										<span class="dashicons dashicons-controls-repeat"></span>
									</button>
									<button type="button"
											class="button button-small view-error-btn"
											data-error="<?php echo esc_attr( $metadata['error'] ?? 'Unknown error' ); ?>"
											title="<?php esc_attr_e( 'View Error', 'wp-ai-site-generator' ); ?>">
										<span class="dashicons dashicons-warning"></span>
									</button>
								<?php endif; ?>
								<button type="button"
										class="button button-small delete-btn"
										data-generation-id="<?php echo esc_attr( $generation->id ); ?>"
										title="<?php esc_attr_e( 'Delete', 'wp-ai-site-generator' ); ?>">
									<span class="dashicons dashicons-trash"></span>
								</button>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php else : ?>
				<tr>
					<td colspan="8" class="no-items">
						<div class="waisg-empty-state">
							<span class="dashicons dashicons-clock"></span>
							<h3><?php esc_html_e( 'No generations found', 'wp-ai-site-generator' ); ?></h3>
							<p><?php esc_html_e( 'Start generating your first AI-powered site!', 'wp-ai-site-generator' ); ?></p>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-ai-site-generator-generate' ) ); ?>" class="button button-primary">
								<?php esc_html_e( 'Generate Site', 'wp-ai-site-generator' ); ?>
							</a>
						</div>
					</td>
				</tr>
			<?php endif; ?>
		</tbody>
	</table>

	<!-- Bottom pagination -->
	<?php if ( $total_pages > 1 ) : ?>
		<div class="tablenav bottom">
			<div class="tablenav-pages">
				<?php
				echo paginate_links( array(
					'base' => add_query_arg( 'paged', '%#%' ),
					'format' => '',
					'prev_text' => '&laquo;',
					'next_text' => '&raquo;',
					'total' => $total_pages,
					'current' => $page,
				) );
				?>
			</div>
		</div>
	<?php endif; ?>
</div>

<!-- Prompt Modal -->
<div id="prompt-modal" class="waisg-modal" style="display: none;">
	<div class="waisg-modal-content">
		<div class="waisg-modal-header">
			<h3><?php esc_html_e( 'Full Prompt', 'wp-ai-site-generator' ); ?></h3>
			<button type="button" class="waisg-modal-close">&times;</button>
		</div>
		<div class="waisg-modal-body">
			<pre id="full-prompt-content"></pre>
		</div>
		<div class="waisg-modal-footer">
			<button type="button" class="button button-secondary copy-prompt">
				<?php esc_html_e( 'Copy to Clipboard', 'wp-ai-site-generator' ); ?>
			</button>
			<button type="button" class="button button-primary waisg-modal-close">
				<?php esc_html_e( 'Close', 'wp-ai-site-generator' ); ?>
			</button>
		</div>
	</div>
</div>

<!-- Error Modal -->
<div id="error-modal" class="waisg-modal" style="display: none;">
	<div class="waisg-modal-content">
		<div class="waisg-modal-header">
			<h3><?php esc_html_e( 'Generation Error', 'wp-ai-site-generator' ); ?></h3>
			<button type="button" class="waisg-modal-close">&times;</button>
		</div>
		<div class="waisg-modal-body">
			<div class="notice notice-error">
				<p id="error-content"></p>
			</div>
		</div>
		<div class="waisg-modal-footer">
			<button type="button" class="button button-primary waisg-modal-close">
				<?php esc_html_e( 'Close', 'wp-ai-site-generator' ); ?>
			</button>
		</div>
	</div>
</div>

<script>
jQuery(document).ready(function($) {
	// Filter handling
	$('#apply-filters').on('click', function() {
		var status = $('#filter-by-status').val();
		var dateRange = $('#filter-by-date').val();
		var url = window.location.href.split('?')[0] + '?page=wp-ai-site-generator-history';

		if (status) {
			url += '&status=' + status;
		}
		if (dateRange) {
			url += '&date_range=' + dateRange;
		}

		window.location.href = url;
	});

	$('#clear-filters').on('click', function() {
		window.location.href = '<?php echo esc_url( admin_url( 'admin.php?page=wp-ai-site-generator-history' ) ); ?>';
	});

	// Select all checkbox
	$('#select-all').on('change', function() {
		$('input[name="generation[]"]').prop('checked', $(this).is(':checked'));
	});

	// View full prompt
	$('.view-full-prompt').on('click', function() {
		var prompt = $(this).data('prompt');
		$('#full-prompt-content').text(prompt);
		$('#prompt-modal').fadeIn(300);
	});

	// View error
	$('.view-error-btn').on('click', function() {
		var error = $(this).data('error');
		$('#error-content').text(error);
		$('#error-modal').fadeIn(300);
	});

	// Modal close
	$('.waisg-modal-close').on('click', function() {
		$(this).closest('.waisg-modal').fadeOut(300);
	});

	// Copy prompt
	$('.copy-prompt').on('click', function() {
		var prompt = $('#full-prompt-content').text();
		navigator.clipboard.writeText(prompt).then(function() {
			alert('<?php esc_html_e( 'Prompt copied to clipboard!', 'wp-ai-site-generator' ); ?>');
		});
	});

	// Delete generation
	$('.delete-btn').on('click', function() {
		if (!confirm('<?php esc_html_e( 'Are you sure you want to delete this generation?', 'wp-ai-site-generator' ); ?>')) {
			return;
		}

		var button = $(this);
		var generationId = button.data('generation-id');
		var row = button.closest('tr');

		button.prop('disabled', true);

		$.ajax({
			url: waisg_admin.rest_url + 'generations/' + generationId,
			method: 'DELETE',
			beforeSend: function(xhr) {
				xhr.setRequestHeader('X-WP-Nonce', waisg_admin.rest_nonce);
			},
			success: function() {
				row.fadeOut(300, function() {
					$(this).remove();
				});
			},
			error: function() {
				alert('<?php esc_html_e( 'Failed to delete generation.', 'wp-ai-site-generator' ); ?>');
				button.prop('disabled', false);
			}
		});
	});

	// Bulk delete
	$('#bulk-delete').on('click', function() {
		var selected = $('input[name="generation[]"]:checked').map(function() {
			return $(this).val();
		}).get();

		if (selected.length === 0) {
			alert('<?php esc_html_e( 'Please select at least one generation to delete.', 'wp-ai-site-generator' ); ?>');
			return;
		}

		if (!confirm('<?php esc_html_e( 'Are you sure you want to delete the selected generations?', 'wp-ai-site-generator' ); ?>')) {
			return;
		}

		$.ajax({
			url: waisg_admin.rest_url + 'generations/bulk-delete',
			method: 'POST',
			beforeSend: function(xhr) {
				xhr.setRequestHeader('X-WP-Nonce', waisg_admin.rest_nonce);
			},
			data: JSON.stringify({ ids: selected }),
			contentType: 'application/json',
			success: function() {
				location.reload();
			},
			error: function() {
				alert('<?php esc_html_e( 'Failed to delete generations.', 'wp-ai-site-generator' ); ?>');
			}
		});
	});

	// Regenerate
	$('.regenerate-btn').on('click', function() {
		var generationId = $(this).data('generation-id');
		window.location.href = '<?php echo esc_url( admin_url( 'admin.php?page=wp-ai-site-generator-generate' ) ); ?>&regenerate=' + generationId;
	});

	// Duplicate
	$('.duplicate-btn').on('click', function() {
		var generationId = $(this).data('generation-id');
		window.location.href = '<?php echo esc_url( admin_url( 'admin.php?page=wp-ai-site-generator-generate' ) ); ?>&duplicate=' + generationId;
	});

	// Retry failed generation
	$('.retry-btn').on('click', function() {
		var generationId = $(this).data('generation-id');
		window.location.href = '<?php echo esc_url( admin_url( 'admin.php?page=wp-ai-site-generator-generate' ) ); ?>&retry=' + generationId;
	});

	// Cancel processing generation
	$('.cancel-btn').on('click', function() {
		if (!confirm('<?php esc_html_e( 'Are you sure you want to cancel this generation?', 'wp-ai-site-generator' ); ?>')) {
			return;
		}

		var button = $(this);
		var generationId = button.data('generation-id');

		button.prop('disabled', true);

		$.ajax({
			url: waisg_admin.ajax_url,
			method: 'POST',
			data: {
				action: 'waisg_cancel_generation',
				nonce: waisg_admin.nonce,
				generation_id: generationId
			},
			success: function() {
				location.reload();
			},
			error: function() {
				alert('<?php esc_html_e( 'Failed to cancel generation.', 'wp-ai-site-generator' ); ?>');
				button.prop('disabled', false);
			}
		});
	});

	// Export history
	$('#export-history').on('click', function() {
		window.location.href = waisg_admin.rest_url + 'generations/export?_wpnonce=' + waisg_admin.rest_nonce;
	});

	// Auto-refresh for processing generations
	var processingRows = $('.generation-row').filter(function() {
		return $(this).find('.status-processing').length > 0;
	});

	if (processingRows.length > 0) {
		setInterval(function() {
			processingRows.each(function() {
				var row = $(this);
				var generationId = row.data('generation-id');

				$.ajax({
					url: waisg_admin.ajax_url,
					method: 'GET',
					data: {
						action: 'waisg_get_generation_status',
						nonce: waisg_admin.nonce,
						generation_id: generationId
					},
					success: function(response) {
						if (response.success && response.data.status !== 'processing') {
							location.reload();
						} else if (response.success) {
							// Update progress bar
							var progress = response.data.metadata ? response.data.metadata.progress : 0;
							row.find('.progress-fill').css('width', progress + '%');
						}
					}
				});
			});
		}, 5000); // Refresh every 5 seconds
	}
});
</script>