<?php
/**
 * Knowledge Base Admin Page
 *
 * @package AI_Site_Generator
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get knowledge base instance
$knowledge_base = AI_Site_Generator\Includes\Knowledge_Base::get_instance();
$categories     = $knowledge_base->get_categories();
$stats          = $knowledge_base->get_category_stats();
?>

<div class="wrap ai-site-generator-knowledge-base">
	<h1 class="wp-heading-inline">
		<?php esc_html_e( 'Knowledge Base', 'ai-site-generator' ); ?>
		<a href="#" class="page-title-action" id="kb-upload-btn">
			<?php esc_html_e( 'Upload Document', 'ai-site-generator' ); ?>
		</a>
		<a href="#" class="page-title-action" id="kb-bulk-upload-btn">
			<?php esc_html_e( 'Bulk Upload', 'ai-site-generator' ); ?>
		</a>
	</h1>

	<?php
	// Show admin notices
	if ( isset( $_GET['message'] ) ) {
		$message = sanitize_text_field( wp_unslash( $_GET['message'] ) );
		switch ( $message ) {
			case 'uploaded':
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Document uploaded successfully.', 'ai-site-generator' ) . '</p></div>';
				break;
			case 'deleted':
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Document deleted successfully.', 'ai-site-generator' ) . '</p></div>';
				break;
			case 'updated':
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Document updated successfully.', 'ai-site-generator' ) . '</p></div>';
				break;
		}
	}
	?>

	<!-- Statistics Overview -->
	<div class="kb-stats-overview">
		<div class="kb-stat-cards">
			<?php foreach ( $stats as $category_key => $stat ) : ?>
				<div class="kb-stat-card">
					<h3><?php echo esc_html( $stat['label'] ); ?></h3>
					<span class="stat-number"><?php echo esc_html( $stat['count'] ); ?></span>
					<span class="stat-label"><?php esc_html_e( 'documents', 'ai-site-generator' ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>
	</div>

	<!-- Tab Navigation -->
	<nav class="nav-tab-wrapper wp-clearfix">
		<a href="#library" class="nav-tab nav-tab-active" data-tab="library">
			<?php esc_html_e( 'Document Library', 'ai-site-generator' ); ?>
		</a>
		<a href="#search" class="nav-tab" data-tab="search">
			<?php esc_html_e( 'Search & Test', 'ai-site-generator' ); ?>
		</a>
		<a href="#categories" class="nav-tab" data-tab="categories">
			<?php esc_html_e( 'Categories', 'ai-site-generator' ); ?>
		</a>
		<a href="#settings" class="nav-tab" data-tab="settings">
			<?php esc_html_e( 'RAG Settings', 'ai-site-generator' ); ?>
		</a>
	</nav>

	<!-- Document Library Tab -->
	<div id="library-tab" class="tab-content active">
		<div class="kb-toolbar">
			<div class="kb-search-box">
				<input type="text" id="kb-search" placeholder="<?php esc_attr_e( 'Search documents...', 'ai-site-generator' ); ?>" />
				<button class="button" id="kb-search-btn"><?php esc_html_e( 'Search', 'ai-site-generator' ); ?></button>
			</div>
			<div class="kb-filters">
				<select id="kb-category-filter">
					<option value=""><?php esc_html_e( 'All Categories', 'ai-site-generator' ); ?></option>
					<?php foreach ( $categories as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>

		<div id="kb-documents-list" class="kb-documents-grid">
			<!-- Documents will be loaded here via JavaScript -->
			<div class="kb-loading">
				<span class="spinner is-active"></span>
				<span><?php esc_html_e( 'Loading documents...', 'ai-site-generator' ); ?></span>
			</div>
		</div>
	</div>

	<!-- Search & Test Tab -->
	<div id="search-tab" class="tab-content">
		<div class="kb-test-section">
			<h2><?php esc_html_e( 'Test RAG System', 'ai-site-generator' ); ?></h2>
			<p><?php esc_html_e( 'Test how the knowledge base retrieval works with different queries.', 'ai-site-generator' ); ?></p>

			<div class="kb-test-form">
				<textarea id="rag-test-query" rows="3" placeholder="<?php esc_attr_e( 'Enter a test query...', 'ai-site-generator' ); ?>"></textarea>
				<div class="kb-test-options">
					<label>
						<input type="radio" name="search-type" value="semantic" checked>
						<?php esc_html_e( 'Semantic Search', 'ai-site-generator' ); ?>
					</label>
					<label>
						<input type="radio" name="search-type" value="keyword">
						<?php esc_html_e( 'Keyword Search', 'ai-site-generator' ); ?>
					</label>
					<label>
						<input type="radio" name="search-type" value="hybrid">
						<?php esc_html_e( 'Hybrid Search', 'ai-site-generator' ); ?>
					</label>
				</div>
				<button class="button button-primary" id="test-rag-btn">
					<?php esc_html_e( 'Test Query', 'ai-site-generator' ); ?>
				</button>
			</div>

			<div id="rag-test-results" class="kb-test-results" style="display:none;">
				<!-- Test results will be displayed here -->
			</div>
		</div>
	</div>

	<!-- Categories Tab -->
	<div id="categories-tab" class="tab-content">
		<div class="kb-categories-overview">
			<h2><?php esc_html_e( 'Document Categories', 'ai-site-generator' ); ?></h2>
			<p><?php esc_html_e( 'Organize your knowledge base documents by category for better context retrieval.', 'ai-site-generator' ); ?></p>

			<div class="kb-categories-grid">
				<?php foreach ( $categories as $key => $label ) : ?>
					<div class="kb-category-card">
						<div class="category-icon">
							<?php
							$icons = array(
								'brand'       => 'dashicons-art',
								'products'    => 'dashicons-cart',
								'services'    => 'dashicons-hammer',
								'company'     => 'dashicons-building',
								'tone'        => 'dashicons-editor-quote',
								'audience'    => 'dashicons-groups',
								'seo'         => 'dashicons-search',
								'design'      => 'dashicons-admin-customizer',
								'competitors' => 'dashicons-chart-bar',
								'legal'       => 'dashicons-clipboard',
							);
							$icon = $icons[ $key ] ?? 'dashicons-media-document';
							?>
							<span class="dashicons <?php echo esc_attr( $icon ); ?>"></span>
						</div>
						<h3><?php echo esc_html( $label ); ?></h3>
						<p class="category-description">
							<?php
							$descriptions = array(
								'brand'       => __( 'Logo usage, color schemes, fonts, and brand voice guidelines', 'ai-site-generator' ),
								'products'    => __( 'Product features, specifications, pricing, and benefits', 'ai-site-generator' ),
								'services'    => __( 'Service offerings, processes, and deliverables', 'ai-site-generator' ),
								'company'     => __( 'Company history, mission, values, and team information', 'ai-site-generator' ),
								'tone'        => __( 'Writing style guides, tone of voice, and content examples', 'ai-site-generator' ),
								'audience'    => __( 'Target audience personas, demographics, and pain points', 'ai-site-generator' ),
								'seo'         => __( 'SEO keywords, meta descriptions, and optimization guidelines', 'ai-site-generator' ),
								'design'      => __( 'Design preferences, layout examples, and visual inspirations', 'ai-site-generator' ),
								'competitors' => __( 'Competitor analysis and differentiation points', 'ai-site-generator' ),
								'legal'       => __( 'Legal disclaimers, terms of service, and compliance requirements', 'ai-site-generator' ),
							);
							echo esc_html( $descriptions[ $key ] ?? '' );
							?>
						</p>
						<div class="category-stats">
							<span class="document-count">
								<?php echo esc_html( $stats[ $key ]['count'] ); ?>
								<?php esc_html_e( 'documents', 'ai-site-generator' ); ?>
							</span>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</div>

	<!-- RAG Settings Tab -->
	<div id="settings-tab" class="tab-content">
		<div class="kb-settings-section">
			<h2><?php esc_html_e( 'RAG Configuration', 'ai-site-generator' ); ?></h2>
			<form id="rag-settings-form" method="post" action="">
				<?php wp_nonce_field( 'kb_rag_settings', 'kb_rag_nonce' ); ?>

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="rag_enabled"><?php esc_html_e( 'Enable RAG', 'ai-site-generator' ); ?></label>
						</th>
						<td>
							<input type="checkbox" id="rag_enabled" name="rag_enabled" value="1" checked>
							<p class="description">
								<?php esc_html_e( 'Enable Retrieval Augmented Generation to use knowledge base context in content generation.', 'ai-site-generator' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="top_k"><?php esc_html_e( 'Top K Results', 'ai-site-generator' ); ?></label>
						</th>
						<td>
							<input type="number" id="top_k" name="top_k" value="5" min="1" max="20" class="small-text">
							<p class="description">
								<?php esc_html_e( 'Number of most relevant documents to retrieve for context.', 'ai-site-generator' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="min_relevance"><?php esc_html_e( 'Minimum Relevance Score', 'ai-site-generator' ); ?></label>
						</th>
						<td>
							<input type="number" id="min_relevance" name="min_relevance" value="0.7" min="0" max="1" step="0.1" class="small-text">
							<p class="description">
								<?php esc_html_e( 'Minimum similarity score (0-1) for document inclusion.', 'ai-site-generator' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="max_context"><?php esc_html_e( 'Max Context Length', 'ai-site-generator' ); ?></label>
						</th>
						<td>
							<input type="number" id="max_context" name="max_context" value="3000" min="500" max="8000" step="100" class="small-text">
							<p class="description">
								<?php esc_html_e( 'Maximum character length for retrieved context.', 'ai-site-generator' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="include_citations"><?php esc_html_e( 'Include Citations', 'ai-site-generator' ); ?></label>
						</th>
						<td>
							<input type="checkbox" id="include_citations" name="include_citations" value="1" checked>
							<p class="description">
								<?php esc_html_e( 'Include source citations in the generated context.', 'ai-site-generator' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Save Settings', 'ai-site-generator' ); ?>
					</button>
				</p>
			</form>
		</div>
	</div>

	<!-- Upload Modal -->
	<div id="kb-upload-modal" class="kb-modal" style="display:none;">
		<div class="kb-modal-content">
			<span class="kb-modal-close">&times;</span>
			<h2><?php esc_html_e( 'Upload Document', 'ai-site-generator' ); ?></h2>

			<form id="kb-upload-form" enctype="multipart/form-data">
				<?php wp_nonce_field( 'kb_upload', 'kb_upload_nonce' ); ?>

				<div class="kb-upload-area" id="kb-drop-area">
					<p class="kb-upload-message">
						<?php esc_html_e( 'Drag & drop files here or', 'ai-site-generator' ); ?>
						<label for="kb-file-input" class="kb-file-label">
							<?php esc_html_e( 'browse files', 'ai-site-generator' ); ?>
						</label>
					</p>
					<input type="file" id="kb-file-input" name="documents[]" multiple accept=".pdf,.docx,.txt,.md,.html" style="display:none;">
					<p class="kb-upload-info">
						<?php esc_html_e( 'Supported formats: PDF, DOCX, TXT, MD, HTML (Max 10MB)', 'ai-site-generator' ); ?>
					</p>
				</div>

				<div id="kb-file-list" class="kb-file-list" style="display:none;"></div>

				<div class="kb-upload-options">
					<label for="kb-upload-category"><?php esc_html_e( 'Category:', 'ai-site-generator' ); ?></label>
					<select id="kb-upload-category" name="category">
						<?php foreach ( $categories as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="kb-modal-actions">
					<button type="button" class="button" id="kb-cancel-upload">
						<?php esc_html_e( 'Cancel', 'ai-site-generator' ); ?>
					</button>
					<button type="submit" class="button button-primary" id="kb-submit-upload">
						<?php esc_html_e( 'Upload', 'ai-site-generator' ); ?>
					</button>
				</div>
			</form>

			<div id="kb-upload-progress" class="kb-upload-progress" style="display:none;">
				<div class="kb-progress-bar">
					<div class="kb-progress-fill"></div>
				</div>
				<p class="kb-progress-text"><?php esc_html_e( 'Uploading...', 'ai-site-generator' ); ?></p>
			</div>
		</div>
	</div>

	<!-- Document Preview Modal -->
	<div id="kb-preview-modal" class="kb-modal" style="display:none;">
		<div class="kb-modal-content kb-modal-large">
			<span class="kb-modal-close">&times;</span>
			<h2 id="kb-preview-title"><?php esc_html_e( 'Document Preview', 'ai-site-generator' ); ?></h2>

			<div class="kb-preview-meta">
				<span class="kb-meta-item">
					<strong><?php esc_html_e( 'Category:', 'ai-site-generator' ); ?></strong>
					<span id="kb-preview-category"></span>
				</span>
				<span class="kb-meta-item">
					<strong><?php esc_html_e( 'Size:', 'ai-site-generator' ); ?></strong>
					<span id="kb-preview-size"></span>
				</span>
				<span class="kb-meta-item">
					<strong><?php esc_html_e( 'Uploaded:', 'ai-site-generator' ); ?></strong>
					<span id="kb-preview-date"></span>
				</span>
				<span class="kb-meta-item">
					<strong><?php esc_html_e( 'Usage:', 'ai-site-generator' ); ?></strong>
					<span id="kb-preview-usage"></span>
				</span>
			</div>

			<div class="kb-preview-content" id="kb-preview-content">
				<!-- Document content will be loaded here -->
			</div>

			<div class="kb-modal-actions">
				<button type="button" class="button" id="kb-edit-document">
					<?php esc_html_e( 'Edit', 'ai-site-generator' ); ?>
				</button>
				<button type="button" class="button" id="kb-reprocess-document">
					<?php esc_html_e( 'Reprocess', 'ai-site-generator' ); ?>
				</button>
				<button type="button" class="button button-link-delete" id="kb-delete-document">
					<?php esc_html_e( 'Delete', 'ai-site-generator' ); ?>
				</button>
			</div>
		</div>
	</div>
</div>

<!-- React App Root (Alternative Implementation) -->
<div id="knowledge-base-ui-root" style="display:none;"></div>

<style>
/* Knowledge Base Styles */
.ai-site-generator-knowledge-base {
	margin-top: 20px;
}

.kb-stats-overview {
	margin: 20px 0;
}

.kb-stat-cards {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
	gap: 15px;
	margin-bottom: 20px;
}

.kb-stat-card {
	background: #fff;
	border: 1px solid #ddd;
	border-radius: 4px;
	padding: 15px;
	text-align: center;
}

.kb-stat-card h3 {
	margin: 0 0 10px;
	font-size: 14px;
	color: #666;
}

.stat-number {
	display: block;
	font-size: 32px;
	font-weight: bold;
	color: #2271b1;
}

.stat-label {
	display: block;
	font-size: 12px;
	color: #999;
}

.tab-content {
	display: none;
	background: #fff;
	padding: 20px;
	border: 1px solid #ddd;
	border-top: none;
}

.tab-content.active {
	display: block;
}

.kb-toolbar {
	display: flex;
	justify-content: space-between;
	margin-bottom: 20px;
	gap: 15px;
}

.kb-search-box {
	flex: 1;
	display: flex;
	gap: 10px;
}

.kb-search-box input {
	flex: 1;
}

.kb-documents-grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
	gap: 20px;
}

.kb-document-card {
	background: #f9f9f9;
	border: 1px solid #ddd;
	border-radius: 4px;
	padding: 15px;
	transition: box-shadow 0.2s;
}

.kb-document-card:hover {
	box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.kb-categories-grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
	gap: 20px;
	margin-top: 20px;
}

.kb-category-card {
	background: #fff;
	border: 1px solid #ddd;
	border-radius: 4px;
	padding: 20px;
	text-align: center;
	transition: transform 0.2s;
}

.kb-category-card:hover {
	transform: translateY(-2px);
	box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.category-icon {
	font-size: 48px;
	color: #2271b1;
	margin-bottom: 10px;
}

.category-description {
	font-size: 13px;
	color: #666;
	margin: 10px 0;
	min-height: 40px;
}

.document-count {
	display: inline-block;
	background: #f0f0f0;
	padding: 4px 8px;
	border-radius: 3px;
	font-size: 12px;
}

/* Modal Styles */
.kb-modal {
	position: fixed;
	z-index: 100000;
	left: 0;
	top: 0;
	width: 100%;
	height: 100%;
	background-color: rgba(0, 0, 0, 0.5);
}

.kb-modal-content {
	background-color: #fff;
	margin: 5% auto;
	padding: 30px;
	border-radius: 4px;
	width: 600px;
	max-width: 90%;
	position: relative;
}

.kb-modal-large {
	width: 900px;
}

.kb-modal-close {
	position: absolute;
	right: 20px;
	top: 20px;
	font-size: 28px;
	font-weight: bold;
	cursor: pointer;
	color: #999;
}

.kb-modal-close:hover {
	color: #333;
}

.kb-upload-area {
	border: 2px dashed #ddd;
	border-radius: 4px;
	padding: 40px;
	text-align: center;
	background: #f9f9f9;
	cursor: pointer;
	transition: all 0.3s;
}

.kb-upload-area.dragover {
	border-color: #2271b1;
	background: #f0f8ff;
}

.kb-file-label {
	color: #2271b1;
	text-decoration: underline;
	cursor: pointer;
}

.kb-file-list {
	margin: 20px 0;
	max-height: 200px;
	overflow-y: auto;
}

.kb-file-item {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 8px;
	background: #f5f5f5;
	border-radius: 3px;
	margin-bottom: 8px;
}

.kb-upload-progress {
	margin-top: 20px;
}

.kb-progress-bar {
	height: 20px;
	background: #f0f0f0;
	border-radius: 10px;
	overflow: hidden;
}

.kb-progress-fill {
	height: 100%;
	background: #2271b1;
	transition: width 0.3s;
}

.kb-modal-actions {
	display: flex;
	justify-content: flex-end;
	gap: 10px;
	margin-top: 20px;
	padding-top: 20px;
	border-top: 1px solid #ddd;
}

.kb-preview-meta {
	display: flex;
	gap: 20px;
	margin: 20px 0;
	padding: 15px;
	background: #f5f5f5;
	border-radius: 4px;
}

.kb-meta-item strong {
	margin-right: 5px;
}

.kb-preview-content {
	max-height: 400px;
	overflow-y: auto;
	padding: 20px;
	background: #fff;
	border: 1px solid #ddd;
	border-radius: 4px;
	white-space: pre-wrap;
	font-family: monospace;
	font-size: 13px;
}

.kb-test-form {
	max-width: 600px;
}

.kb-test-form textarea {
	width: 100%;
	margin-bottom: 15px;
}

.kb-test-options {
	display: flex;
	gap: 20px;
	margin-bottom: 15px;
}

.kb-test-results {
	margin-top: 30px;
	padding: 20px;
	background: #f9f9f9;
	border-radius: 4px;
}

.kb-loading {
	text-align: center;
	padding: 40px;
	color: #666;
}

.kb-loading .spinner {
	float: none;
	margin: 0 auto 10px;
}
</style>