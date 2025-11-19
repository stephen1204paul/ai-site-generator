/**
 * Knowledge Base Admin JavaScript
 *
 * @package AI_Site_Generator
 * @since 1.0.0
 */

(function($) {
    'use strict';

    // Knowledge Base Manager
    const KBManager = {
        // Initialize
        init: function() {
            this.bindEvents();
            this.loadDocuments();
            this.initDragDrop();
        },

        // Bind events
        bindEvents: function() {
            // Tab navigation
            $('.nav-tab').on('click', this.switchTab);

            // Upload button
            $('#kb-upload-btn').on('click', this.showUploadModal);
            $('#kb-bulk-upload-btn').on('click', this.showBulkUploadModal);

            // Modal controls
            $('.kb-modal-close, #kb-cancel-upload').on('click', this.closeModal);
            $('#kb-submit-upload').on('click', this.uploadDocuments);

            // File input
            $('#kb-file-input').on('change', this.handleFileSelection);

            // Search and filters
            $('#kb-search-btn').on('click', this.searchDocuments);
            $('#kb-search').on('keypress', function(e) {
                if (e.which === 13) {
                    KBManager.searchDocuments();
                }
            });
            $('#kb-category-filter').on('change', this.filterByCategory);

            // Test RAG
            $('#test-rag-btn').on('click', this.testRAG);

            // Document actions
            $(document).on('click', '.kb-doc-preview', this.previewDocument);
            $(document).on('click', '.kb-doc-edit', this.editDocument);
            $(document).on('click', '.kb-doc-delete', this.deleteDocument);
            $(document).on('click', '.kb-doc-reprocess', this.reprocessDocument);
        },

        // Initialize drag and drop
        initDragDrop: function() {
            const dropArea = $('#kb-drop-area');

            dropArea.on('dragover dragenter', function(e) {
                e.preventDefault();
                $(this).addClass('dragover');
            });

            dropArea.on('dragleave dragend', function(e) {
                e.preventDefault();
                $(this).removeClass('dragover');
            });

            dropArea.on('drop', function(e) {
                e.preventDefault();
                $(this).removeClass('dragover');

                const files = e.originalEvent.dataTransfer.files;
                KBManager.processFiles(files);
            });
        },

        // Switch tab
        switchTab: function(e) {
            e.preventDefault();
            const tab = $(this).data('tab');

            $('.nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');

            $('.tab-content').removeClass('active');
            $(`#${tab}-tab`).addClass('active');
        },

        // Show upload modal
        showUploadModal: function(e) {
            e.preventDefault();
            $('#kb-upload-modal').show();
        },

        // Show bulk upload modal
        showBulkUploadModal: function(e) {
            e.preventDefault();
            $('#kb-upload-modal').show();
            $('#kb-file-input').attr('multiple', 'multiple');
        },

        // Close modal
        closeModal: function() {
            $('.kb-modal').hide();
            KBManager.resetUploadForm();
        },

        // Reset upload form
        resetUploadForm: function() {
            $('#kb-upload-form')[0].reset();
            $('#kb-file-list').empty().hide();
            $('#kb-upload-progress').hide();
        },

        // Handle file selection
        handleFileSelection: function() {
            const files = this.files;
            KBManager.processFiles(files);
        },

        // Process selected files
        processFiles: function(files) {
            const fileList = $('#kb-file-list');
            fileList.empty();

            let validFiles = [];
            const maxSize = 10 * 1024 * 1024; // 10MB
            const allowedTypes = ['application/pdf', 'text/plain', 'text/markdown', 'text/html',
                                  'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];

            for (let file of files) {
                // Validate file
                if (file.size > maxSize) {
                    KBManager.showNotice(`File ${file.name} exceeds 10MB limit`, 'error');
                    continue;
                }

                // Check type by extension if MIME type is not recognized
                const ext = file.name.split('.').pop().toLowerCase();
                const validExtensions = ['pdf', 'txt', 'md', 'html', 'docx'];

                if (!allowedTypes.includes(file.type) && !validExtensions.includes(ext)) {
                    KBManager.showNotice(`File ${file.name} has unsupported type`, 'error');
                    continue;
                }

                validFiles.push(file);

                // Add to file list display
                const fileItem = $('<div class="kb-file-item"></div>');
                fileItem.html(`
                    <span>${file.name} (${Math.round(file.size / 1024)}KB)</span>
                    <button type="button" class="button-link" data-index="${validFiles.length - 1}">Remove</button>
                `);
                fileList.append(fileItem);
            }

            if (validFiles.length > 0) {
                fileList.show();
                // Store files for upload
                fileList.data('files', validFiles);
            }
        },

        // Upload documents
        uploadDocuments: function(e) {
            e.preventDefault();

            const fileList = $('#kb-file-list');
            const files = fileList.data('files');

            if (!files || files.length === 0) {
                KBManager.showNotice('Please select files to upload', 'error');
                return;
            }

            const formData = new FormData();
            for (let i = 0; i < files.length; i++) {
                formData.append('file', files[i]);
            }

            formData.append('category', $('#kb-upload-category').val());
            formData.append('_wpnonce', wpApiSettings.nonce);

            // Show progress
            $('#kb-upload-progress').show();
            const progressBar = $('.kb-progress-fill');
            progressBar.css('width', '0%');

            // Upload via REST API
            $.ajax({
                url: wpApiSettings.root + 'ai-site-generator/v1/kb/upload',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                headers: {
                    'X-WP-Nonce': wpApiSettings.nonce
                },
                xhr: function() {
                    const xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener('progress', function(e) {
                        if (e.lengthComputable) {
                            const percent = Math.round((e.loaded / e.total) * 100);
                            progressBar.css('width', percent + '%');
                        }
                    });
                    return xhr;
                },
                success: function(response) {
                    if (response.success) {
                        KBManager.showNotice('Documents uploaded successfully', 'success');
                        KBManager.closeModal();
                        KBManager.loadDocuments();
                    } else {
                        KBManager.showNotice(response.message || 'Upload failed', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    KBManager.showNotice('Upload failed: ' + error, 'error');
                },
                complete: function() {
                    $('#kb-upload-progress').hide();
                }
            });
        },

        // Load documents
        loadDocuments: function() {
            const container = $('#kb-documents-list');
            container.html('<div class="kb-loading"><span class="spinner is-active"></span><span>Loading documents...</span></div>');

            $.ajax({
                url: wpApiSettings.root + 'ai-site-generator/v1/kb/list',
                method: 'GET',
                headers: {
                    'X-WP-Nonce': wpApiSettings.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        KBManager.displayDocuments(response.data);
                    } else {
                        container.html('<p>No documents found.</p>');
                    }
                },
                error: function() {
                    container.html('<p>Error loading documents.</p>');
                }
            });
        },

        // Display documents
        displayDocuments: function(documents) {
            const container = $('#kb-documents-list');
            container.empty();

            if (documents.length === 0) {
                container.html('<p>No documents found. Upload your first document to get started.</p>');
                return;
            }

            const grid = $('<div class="kb-documents-grid"></div>');

            documents.forEach(function(doc) {
                const card = $(`
                    <div class="kb-document-card" data-id="${doc.id}">
                        <h3>${doc.title}</h3>
                        <div class="kb-doc-meta">
                            <span class="category">${doc.category}</span>
                            <span class="size">${KBManager.formatFileSize(doc.file_size)}</span>
                            <span class="date">${new Date(doc.created_at).toLocaleDateString()}</span>
                        </div>
                        <div class="kb-doc-status">
                            <span class="status status-${doc.status}">${doc.status}</span>
                        </div>
                        <div class="kb-doc-actions">
                            <button class="button button-small kb-doc-preview" data-id="${doc.id}">Preview</button>
                            <button class="button button-small kb-doc-edit" data-id="${doc.id}">Edit</button>
                            <button class="button button-small kb-doc-reprocess" data-id="${doc.id}">Reprocess</button>
                            <button class="button button-small button-link-delete kb-doc-delete" data-id="${doc.id}">Delete</button>
                        </div>
                    </div>
                `);
                grid.append(card);
            });

            container.html(grid);
        },

        // Search documents
        searchDocuments: function() {
            const query = $('#kb-search').val();

            if (!query) {
                KBManager.loadDocuments();
                return;
            }

            $.ajax({
                url: wpApiSettings.root + 'ai-site-generator/v1/kb/search',
                method: 'POST',
                headers: {
                    'X-WP-Nonce': wpApiSettings.nonce,
                    'Content-Type': 'application/json'
                },
                data: JSON.stringify({
                    query: query,
                    type: 'hybrid'
                }),
                success: function(response) {
                    if (response.success && response.data.results) {
                        KBManager.displayDocuments(response.data.results);
                    }
                },
                error: function() {
                    KBManager.showNotice('Search failed', 'error');
                }
            });
        },

        // Filter by category
        filterByCategory: function() {
            const category = $(this).val();
            const params = category ? `?category=${category}` : '';

            $.ajax({
                url: wpApiSettings.root + `ai-site-generator/v1/kb/list${params}`,
                method: 'GET',
                headers: {
                    'X-WP-Nonce': wpApiSettings.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        KBManager.displayDocuments(response.data);
                    }
                }
            });
        },

        // Preview document
        previewDocument: function() {
            const docId = $(this).data('id');

            $.ajax({
                url: wpApiSettings.root + `ai-site-generator/v1/kb/${docId}`,
                method: 'GET',
                headers: {
                    'X-WP-Nonce': wpApiSettings.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        KBManager.showDocumentPreview(response.data);
                    }
                }
            });
        },

        // Show document preview
        showDocumentPreview: function(doc) {
            $('#kb-preview-title').text(doc.title);
            $('#kb-preview-category').text(doc.category);
            $('#kb-preview-size').text(KBManager.formatFileSize(doc.file_size));
            $('#kb-preview-date').text(new Date(doc.created_at).toLocaleDateString());
            $('#kb-preview-usage').text(doc.usage_stats ? `${doc.usage_stats.total_uses} uses` : '0 uses');

            const content = doc.content || 'No content available';
            $('#kb-preview-content').text(content.substring(0, 5000));

            $('#kb-preview-modal').show();

            // Bind action buttons
            $('#kb-edit-document').off('click').on('click', function() {
                KBManager.editDocument(doc.id);
            });

            $('#kb-reprocess-document').off('click').on('click', function() {
                KBManager.reprocessDocument(doc.id);
            });

            $('#kb-delete-document').off('click').on('click', function() {
                KBManager.deleteDocument(doc.id);
            });
        },

        // Delete document
        deleteDocument: function(docId) {
            if (!docId && $(this).data('id')) {
                docId = $(this).data('id');
            }

            if (!confirm('Are you sure you want to delete this document?')) {
                return;
            }

            $.ajax({
                url: wpApiSettings.root + `ai-site-generator/v1/kb/${docId}`,
                method: 'DELETE',
                headers: {
                    'X-WP-Nonce': wpApiSettings.nonce
                },
                success: function(response) {
                    if (response.success) {
                        KBManager.showNotice('Document deleted successfully', 'success');
                        KBManager.loadDocuments();
                        $('.kb-modal').hide();
                    }
                },
                error: function() {
                    KBManager.showNotice('Failed to delete document', 'error');
                }
            });
        },

        // Reprocess document
        reprocessDocument: function(docId) {
            if (!docId && $(this).data('id')) {
                docId = $(this).data('id');
            }

            $.ajax({
                url: wpApiSettings.root + `ai-site-generator/v1/kb/${docId}/reprocess`,
                method: 'POST',
                headers: {
                    'X-WP-Nonce': wpApiSettings.nonce
                },
                success: function(response) {
                    if (response.success) {
                        KBManager.showNotice('Document reprocessing started', 'success');
                        KBManager.loadDocuments();
                    }
                },
                error: function() {
                    KBManager.showNotice('Failed to reprocess document', 'error');
                }
            });
        },

        // Test RAG system
        testRAG: function() {
            const query = $('#rag-test-query').val();
            const type = $('input[name="search-type"]:checked').val();

            if (!query) {
                KBManager.showNotice('Please enter a test query', 'error');
                return;
            }

            const resultsContainer = $('#rag-test-results');
            resultsContainer.html('<div class="kb-loading"><span class="spinner is-active"></span><span>Testing...</span></div>').show();

            $.ajax({
                url: wpApiSettings.root + 'ai-site-generator/v1/kb/test-rag',
                method: 'POST',
                headers: {
                    'X-WP-Nonce': wpApiSettings.nonce,
                    'Content-Type': 'application/json'
                },
                data: JSON.stringify({
                    query: query
                }),
                success: function(response) {
                    if (response.success && response.data) {
                        KBManager.displayTestResults(response.data);
                    }
                },
                error: function() {
                    resultsContainer.html('<p>Test failed</p>');
                }
            });
        },

        // Display test results
        displayTestResults: function(results) {
            const container = $('#rag-test-results');

            let html = '<h3>Test Results</h3>';
            html += '<div class="test-metrics">';
            html += `<p><strong>Query:</strong> ${results.query}</p>`;
            html += `<p><strong>Performance:</strong> ${results.performance.total_time}</p>`;
            html += '</div>';

            if (results.semantic_search) {
                html += '<h4>Semantic Search Results:</h4>';
                html += '<ul>';
                results.semantic_search.results.forEach(function(r) {
                    html += `<li>${r.title} (Score: ${r.score.toFixed(2)})</li>`;
                });
                html += '</ul>';
            }

            if (results.keyword_search) {
                html += '<h4>Keyword Search Results:</h4>';
                html += '<ul>';
                results.keyword_search.results.forEach(function(r) {
                    html += `<li>${r.title}</li>`;
                });
                html += '</ul>';
            }

            if (results.final_context) {
                html += '<h4>Final Context:</h4>';
                html += `<p>Content length: ${results.final_context.content_length} chars</p>`;
                html += `<p>Citations: ${results.final_context.citations}</p>`;
                html += `<p>Documents used: ${results.final_context.documents_used}</p>`;
            }

            container.html(html);
        },

        // Format file size
        formatFileSize: function(bytes) {
            if (!bytes) return 'N/A';
            const kb = bytes / 1024;
            if (kb < 1024) {
                return Math.round(kb) + ' KB';
            }
            return Math.round(kb / 1024) + ' MB';
        },

        // Show notice
        showNotice: function(message, type) {
            const notice = $(`<div class="notice notice-${type} is-dismissible"><p>${message}</p></div>`);
            $('.wrap h1').after(notice);

            setTimeout(function() {
                notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        if ($('.ai-site-generator-knowledge-base').length) {
            KBManager.init();
        }
    });

})(jQuery);