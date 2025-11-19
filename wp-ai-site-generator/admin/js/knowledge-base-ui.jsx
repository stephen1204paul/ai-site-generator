/**
 * Knowledge Base UI Component
 *
 * @package AI_Site_Generator
 * @since 1.0.0
 */

import React, { useState, useEffect, useCallback } from 'react';
import {
    Card,
    CardBody,
    CardHeader,
    Button,
    ButtonGroup,
    Spinner,
    Notice,
    SelectControl,
    TextControl,
    Modal,
    TabPanel,
    SearchControl,
    FormFileUpload,
    __experimentalText as Text,
    __experimentalVStack as VStack,
    __experimentalHStack as HStack,
    __experimentalGrid as Grid,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useState as useLocalState } from '@wordpress/element';
import { upload, download, search, info, edit, trash, refresh } from '@wordpress/icons';
import apiFetch from '@wordpress/api-fetch';

/**
 * Knowledge Base UI Component
 */
const KnowledgeBaseUI = () => {
    const [documents, setDocuments] = useState([]);
    const [categories, setCategories] = useState({});
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [success, setSuccess] = useState(null);
    const [selectedCategory, setSelectedCategory] = useState('');
    const [searchQuery, setSearchQuery] = useState('');
    const [selectedDocument, setSelectedDocument] = useState(null);
    const [showUploadModal, setShowUploadModal] = useState(false);
    const [showEditModal, setShowEditModal] = useState(false);
    const [showPreviewModal, setShowPreviewModal] = useState(false);
    const [uploadProgress, setUploadProgress] = useState(0);
    const [stats, setStats] = useState(null);
    const [activeTab, setActiveTab] = useState('library');

    // Load documents on mount
    useEffect(() => {
        loadDocuments();
        loadCategories();
        loadStats();
    }, []);

    /**
     * Load documents from API
     */
    const loadDocuments = async (category = '') => {
        setLoading(true);
        setError(null);

        try {
            const params = new URLSearchParams({
                limit: 100,
                status: 'active',
            });

            if (category) {
                params.append('category', category);
            }

            const response = await apiFetch({
                path: `/ai-site-generator/v1/kb/list?${params}`,
                method: 'GET',
            });

            if (response.success) {
                setDocuments(response.data);
            }
        } catch (err) {
            setError(__('Failed to load documents', 'ai-site-generator'));
            console.error('Load documents error:', err);
        } finally {
            setLoading(false);
        }
    };

    /**
     * Load categories
     */
    const loadCategories = async () => {
        try {
            const response = await apiFetch({
                path: '/ai-site-generator/v1/kb/categories',
                method: 'GET',
            });

            if (response.success) {
                setCategories(response.data.categories);
            }
        } catch (err) {
            console.error('Load categories error:', err);
        }
    };

    /**
     * Load statistics
     */
    const loadStats = async () => {
        try {
            const response = await apiFetch({
                path: '/ai-site-generator/v1/kb/stats',
                method: 'GET',
            });

            if (response.success) {
                setStats(response.data);
            }
        } catch (err) {
            console.error('Load stats error:', err);
        }
    };

    /**
     * Handle file upload
     */
    const handleFileUpload = async (files) => {
        setUploadProgress(0);
        const formData = new FormData();

        // Add files to form data
        for (let i = 0; i < files.length; i++) {
            formData.append(`file${i}`, files[i]);
        }

        // Add metadata
        formData.append('category', selectedCategory || 'company');

        try {
            const response = await fetch(
                `${wpApiSettings.root}ai-site-generator/v1/kb/upload`,
                {
                    method: 'POST',
                    headers: {
                        'X-WP-Nonce': wpApiSettings.nonce,
                    },
                    body: formData,
                }
            );

            const result = await response.json();

            if (result.success) {
                setSuccess(__('Document uploaded successfully', 'ai-site-generator'));
                setShowUploadModal(false);
                loadDocuments();
                loadStats();
            } else {
                throw new Error(result.message || 'Upload failed');
            }
        } catch (err) {
            setError(__('Failed to upload document', 'ai-site-generator'));
            console.error('Upload error:', err);
        }
    };

    /**
     * Handle document deletion
     */
    const handleDelete = async (documentId) => {
        if (!confirm(__('Are you sure you want to delete this document?', 'ai-site-generator'))) {
            return;
        }

        try {
            const response = await apiFetch({
                path: `/ai-site-generator/v1/kb/${documentId}`,
                method: 'DELETE',
            });

            if (response.success) {
                setSuccess(__('Document deleted successfully', 'ai-site-generator'));
                loadDocuments();
                loadStats();
            }
        } catch (err) {
            setError(__('Failed to delete document', 'ai-site-generator'));
            console.error('Delete error:', err);
        }
    };

    /**
     * Handle document reprocessing
     */
    const handleReprocess = async (documentId) => {
        try {
            const response = await apiFetch({
                path: `/ai-site-generator/v1/kb/${documentId}/reprocess`,
                method: 'POST',
            });

            if (response.success) {
                setSuccess(__('Document reprocessing started', 'ai-site-generator'));
                loadDocuments();
            }
        } catch (err) {
            setError(__('Failed to reprocess document', 'ai-site-generator'));
            console.error('Reprocess error:', err);
        }
    };

    /**
     * Handle search
     */
    const handleSearch = async () => {
        if (!searchQuery) {
            loadDocuments();
            return;
        }

        setLoading(true);
        try {
            const response = await apiFetch({
                path: '/ai-site-generator/v1/kb/search',
                method: 'POST',
                data: {
                    query: searchQuery,
                    type: 'hybrid',
                    limit: 20,
                },
            });

            if (response.success) {
                setDocuments(response.data.results);
            }
        } catch (err) {
            setError(__('Search failed', 'ai-site-generator'));
            console.error('Search error:', err);
        } finally {
            setLoading(false);
        }
    };

    /**
     * Upload Modal Component
     */
    const UploadModal = () => {
        const [uploadFiles, setUploadFiles] = useState([]);
        const [uploadTitle, setUploadTitle] = useState('');
        const [uploadCategory, setUploadCategory] = useState('company');

        return (
            <Modal
                title={__('Upload Documents', 'ai-site-generator')}
                onRequestClose={() => setShowUploadModal(false)}
                size="medium"
            >
                <VStack spacing={4}>
                    <FormFileUpload
                        accept=".pdf,.docx,.txt,.md,.html"
                        multiple
                        onChange={(event) => setUploadFiles(Array.from(event.target.files))}
                        render={({ openFileDialog }) => (
                            <Card>
                                <CardBody>
                                    <VStack spacing={3}>
                                        <div className="kb-upload-area" onClick={openFileDialog}>
                                            <Button icon={upload} variant="primary" size="large">
                                                {__('Select Files', 'ai-site-generator')}
                                            </Button>
                                            <Text variant="muted">
                                                {__('or drag and drop files here', 'ai-site-generator')}
                                            </Text>
                                            <Text variant="caption">
                                                {__('Supported: PDF, DOCX, TXT, MD, HTML', 'ai-site-generator')}
                                            </Text>
                                        </div>
                                        {uploadFiles.length > 0 && (
                                            <div className="selected-files">
                                                <Text weight="600">
                                                    {__('Selected files:', 'ai-site-generator')}
                                                </Text>
                                                <ul>
                                                    {uploadFiles.map((file, index) => (
                                                        <li key={index}>
                                                            {file.name} ({Math.round(file.size / 1024)}KB)
                                                        </li>
                                                    ))}
                                                </ul>
                                            </div>
                                        )}
                                    </VStack>
                                </CardBody>
                            </Card>
                        )}
                    />

                    <TextControl
                        label={__('Document Title', 'ai-site-generator')}
                        value={uploadTitle}
                        onChange={setUploadTitle}
                        help={__('Optional: Override default title', 'ai-site-generator')}
                    />

                    <SelectControl
                        label={__('Category', 'ai-site-generator')}
                        value={uploadCategory}
                        options={Object.entries(categories).map(([value, label]) => ({
                            value,
                            label,
                        }))}
                        onChange={setUploadCategory}
                    />

                    {uploadProgress > 0 && (
                        <div className="upload-progress">
                            <progress value={uploadProgress} max="100">
                                {uploadProgress}%
                            </progress>
                        </div>
                    )}

                    <HStack justify="flex-end">
                        <Button
                            variant="secondary"
                            onClick={() => setShowUploadModal(false)}
                        >
                            {__('Cancel', 'ai-site-generator')}
                        </Button>
                        <Button
                            variant="primary"
                            onClick={() => handleFileUpload(uploadFiles)}
                            disabled={uploadFiles.length === 0}
                        >
                            {__('Upload', 'ai-site-generator')}
                        </Button>
                    </HStack>
                </VStack>
            </Modal>
        );
    };

    /**
     * Document Card Component
     */
    const DocumentCard = ({ document }) => {
        const formatDate = (dateString) => {
            const date = new Date(dateString);
            return date.toLocaleDateString();
        };

        const formatSize = (bytes) => {
            if (!bytes) return 'N/A';
            const kb = bytes / 1024;
            if (kb < 1024) return `${Math.round(kb)} KB`;
            return `${Math.round(kb / 1024)} MB`;
        };

        return (
            <Card className="kb-document-card">
                <CardHeader>
                    <HStack justify="space-between" align="start">
                        <VStack spacing={1}>
                            <Text weight="600" size="16">
                                {document.title}
                            </Text>
                            <Text variant="muted" size="12">
                                {categories[document.category] || document.category}
                            </Text>
                        </VStack>
                        <ButtonGroup>
                            <Button
                                icon={info}
                                size="small"
                                onClick={() => {
                                    setSelectedDocument(document);
                                    setShowPreviewModal(true);
                                }}
                                label={__('Preview', 'ai-site-generator')}
                            />
                            <Button
                                icon={edit}
                                size="small"
                                onClick={() => {
                                    setSelectedDocument(document);
                                    setShowEditModal(true);
                                }}
                                label={__('Edit', 'ai-site-generator')}
                            />
                            <Button
                                icon={refresh}
                                size="small"
                                onClick={() => handleReprocess(document.id)}
                                label={__('Reprocess', 'ai-site-generator')}
                            />
                            <Button
                                icon={trash}
                                size="small"
                                isDestructive
                                onClick={() => handleDelete(document.id)}
                                label={__('Delete', 'ai-site-generator')}
                            />
                        </ButtonGroup>
                    </HStack>
                </CardHeader>
                <CardBody>
                    <Grid columns={3} gap={2}>
                        <div>
                            <Text variant="muted" size="11">
                                {__('Size', 'ai-site-generator')}
                            </Text>
                            <Text size="13">{formatSize(document.file_size)}</Text>
                        </div>
                        <div>
                            <Text variant="muted" size="11">
                                {__('Status', 'ai-site-generator')}
                            </Text>
                            <Text size="13">
                                <span className={`status-badge status-${document.status}`}>
                                    {document.status}
                                </span>
                            </Text>
                        </div>
                        <div>
                            <Text variant="muted" size="11">
                                {__('Uploaded', 'ai-site-generator')}
                            </Text>
                            <Text size="13">{formatDate(document.created_at)}</Text>
                        </div>
                    </Grid>
                    {document.metadata?.summary && (
                        <div className="document-summary">
                            <Text variant="muted" size="12" numberOfLines={2}>
                                {document.metadata.summary}
                            </Text>
                        </div>
                    )}
                </CardBody>
            </Card>
        );
    };

    /**
     * Statistics Dashboard
     */
    const StatsDashboard = () => {
        if (!stats) return null;

        return (
            <Grid columns={4} gap={4}>
                <Card>
                    <CardBody>
                        <Text variant="muted" size="12">
                            {__('Total Documents', 'ai-site-generator')}
                        </Text>
                        <Text size="24" weight="600">
                            {stats.total_documents}
                        </Text>
                    </CardBody>
                </Card>
                <Card>
                    <CardBody>
                        <Text variant="muted" size="12">
                            {__('Categories Used', 'ai-site-generator')}
                        </Text>
                        <Text size="24" weight="600">
                            {Object.keys(stats.categories || {}).length}
                        </Text>
                    </CardBody>
                </Card>
                <Card>
                    <CardBody>
                        <Text variant="muted" size="12">
                            {__('Cache Size', 'ai-site-generator')}
                        </Text>
                        <Text size="24" weight="600">
                            {stats.rag_stats?.cache_size || 0}
                        </Text>
                    </CardBody>
                </Card>
                <Card>
                    <CardBody>
                        <Text variant="muted" size="12">
                            {__('Embeddings', 'ai-site-generator')}
                        </Text>
                        <Text size="24" weight="600">
                            {stats.rag_stats?.active_tracking || 0}
                        </Text>
                    </CardBody>
                </Card>
            </Grid>
        );
    };

    return (
        <div className="kb-ui-container">
            {error && (
                <Notice status="error" isDismissible onRemove={() => setError(null)}>
                    {error}
                </Notice>
            )}
            {success && (
                <Notice status="success" isDismissible onRemove={() => setSuccess(null)}>
                    {success}
                </Notice>
            )}

            <div className="kb-header">
                <HStack justify="space-between" align="center">
                    <VStack spacing={1}>
                        <Text size="24" weight="600">
                            {__('Knowledge Base', 'ai-site-generator')}
                        </Text>
                        <Text variant="muted">
                            {__('Upload and manage documents for AI context', 'ai-site-generator')}
                        </Text>
                    </VStack>
                    <ButtonGroup>
                        <Button
                            variant="primary"
                            icon={upload}
                            onClick={() => setShowUploadModal(true)}
                        >
                            {__('Upload Documents', 'ai-site-generator')}
                        </Button>
                    </ButtonGroup>
                </HStack>
            </div>

            <TabPanel
                className="kb-tabs"
                activeClass="active-tab"
                onSelect={setActiveTab}
                tabs={[
                    {
                        name: 'library',
                        title: __('Document Library', 'ai-site-generator'),
                    },
                    {
                        name: 'stats',
                        title: __('Statistics', 'ai-site-generator'),
                    },
                    {
                        name: 'settings',
                        title: __('Settings', 'ai-site-generator'),
                    },
                ]}
            >
                {(tab) => (
                    <>
                        {tab.name === 'library' && (
                            <VStack spacing={4}>
                                <Card>
                                    <CardBody>
                                        <HStack spacing={3} align="end">
                                            <SearchControl
                                                label={__('Search documents', 'ai-site-generator')}
                                                value={searchQuery}
                                                onChange={setSearchQuery}
                                                onKeyDown={(e) => {
                                                    if (e.key === 'Enter') {
                                                        handleSearch();
                                                    }
                                                }}
                                            />
                                            <SelectControl
                                                label={__('Filter by category', 'ai-site-generator')}
                                                value={selectedCategory}
                                                options={[
                                                    { value: '', label: __('All Categories', 'ai-site-generator') },
                                                    ...Object.entries(categories).map(([value, label]) => ({
                                                        value,
                                                        label,
                                                    })),
                                                ]}
                                                onChange={(value) => {
                                                    setSelectedCategory(value);
                                                    loadDocuments(value);
                                                }}
                                            />
                                            <Button
                                                variant="secondary"
                                                icon={search}
                                                onClick={handleSearch}
                                            >
                                                {__('Search', 'ai-site-generator')}
                                            </Button>
                                        </HStack>
                                    </CardBody>
                                </Card>

                                {loading ? (
                                    <Card>
                                        <CardBody>
                                            <HStack justify="center">
                                                <Spinner />
                                                <Text>{__('Loading documents...', 'ai-site-generator')}</Text>
                                            </HStack>
                                        </CardBody>
                                    </Card>
                                ) : documents.length > 0 ? (
                                    <Grid columns={2} gap={4}>
                                        {documents.map((document) => (
                                            <DocumentCard key={document.id} document={document} />
                                        ))}
                                    </Grid>
                                ) : (
                                    <Card>
                                        <CardBody>
                                            <VStack spacing={3} align="center">
                                                <Text size="16">
                                                    {__('No documents found', 'ai-site-generator')}
                                                </Text>
                                                <Text variant="muted">
                                                    {__('Upload documents to get started', 'ai-site-generator')}
                                                </Text>
                                                <Button
                                                    variant="primary"
                                                    icon={upload}
                                                    onClick={() => setShowUploadModal(true)}
                                                >
                                                    {__('Upload First Document', 'ai-site-generator')}
                                                </Button>
                                            </VStack>
                                        </CardBody>
                                    </Card>
                                )}
                            </VStack>
                        )}

                        {tab.name === 'stats' && (
                            <VStack spacing={4}>
                                <StatsDashboard />

                                {stats?.most_used && stats.most_used.length > 0 && (
                                    <Card>
                                        <CardHeader>
                                            <Text weight="600" size="16">
                                                {__('Most Used Documents', 'ai-site-generator')}
                                            </Text>
                                        </CardHeader>
                                        <CardBody>
                                            <VStack spacing={2}>
                                                {stats.most_used.map((doc, index) => (
                                                    <HStack key={doc.id} justify="space-between">
                                                        <Text>
                                                            {index + 1}. {doc.title}
                                                        </Text>
                                                        <Text variant="muted">
                                                            {doc.usage_count} uses
                                                        </Text>
                                                    </HStack>
                                                ))}
                                            </VStack>
                                        </CardBody>
                                    </Card>
                                )}
                            </VStack>
                        )}

                        {tab.name === 'settings' && (
                            <Card>
                                <CardHeader>
                                    <Text weight="600" size="16">
                                        {__('RAG Settings', 'ai-site-generator')}
                                    </Text>
                                </CardHeader>
                                <CardBody>
                                    <VStack spacing={4}>
                                        <Text variant="muted">
                                            {__('Configure Retrieval Augmented Generation settings', 'ai-site-generator')}
                                        </Text>
                                        {/* Settings controls will be added here */}
                                    </VStack>
                                </CardBody>
                            </Card>
                        )}
                    </>
                )}
            </TabPanel>

            {showUploadModal && <UploadModal />}
        </div>
    );
};

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('knowledge-base-ui-root');
    if (container) {
        const { render } = wp.element;
        render(<KnowledgeBaseUI />, container);
    }
});

export default KnowledgeBaseUI;