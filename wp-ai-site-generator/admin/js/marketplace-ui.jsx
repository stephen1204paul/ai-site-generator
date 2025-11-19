/**
 * Template Marketplace UI Component
 *
 * @package WP_AI_Site_Generator
 */

import React, { useState, useEffect, useCallback, useMemo } from 'react';
import {
    Card,
    CardHeader,
    CardBody,
    CardFooter,
    Button,
    SearchControl,
    SelectControl,
    Spinner,
    Modal,
    Notice,
    TabPanel,
    TextControl,
    TextareaControl,
    CheckboxControl,
    __experimentalGrid as Grid,
    __experimentalHStack as HStack,
    __experimentalVStack as VStack,
    __experimentalText as Text,
    __experimentalHeading as Heading,
    __experimentalDivider as Divider,
    Icon,
    Flex,
    FlexItem,
    FlexBlock,
    Tooltip,
    ToggleControl,
    RangeControl,
    Panel,
    PanelBody,
    PanelRow,
} from '@wordpress/components';
import {
    star,
    starFilled,
    download,
    seen,
    edit,
    trash,
    plus,
    grid,
    list,
    filter,
    close,
    check,
    info,
    warning,
    upload,
    external,
    category,
    tag,
    people,
    chart,
    trendingUp,
} from '@wordpress/icons';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/**
 * Template Marketplace Component
 */
const TemplateMarketplace = () => {
    // State management
    const [templates, setTemplates] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [selectedTemplate, setSelectedTemplate] = useState(null);
    const [viewMode, setViewMode] = useState('grid');
    const [searchQuery, setSearchQuery] = useState('');
    const [selectedCategory, setSelectedCategory] = useState('');
    const [selectedIndustry, setSelectedIndustry] = useState('');
    const [selectedTags, setSelectedTags] = useState([]);
    const [sortBy, setSortBy] = useState('popular');
    const [filterLicense, setFilterLicense] = useState('');
    const [filterRating, setFilterRating] = useState(0);
    const [categories, setCategories] = useState([]);
    const [industries, setIndustries] = useState([]);
    const [currentPage, setCurrentPage] = useState(1);
    const [totalPages, setTotalPages] = useState(1);
    const [showFilters, setShowFilters] = useState(false);
    const [showSubmitModal, setShowSubmitModal] = useState(false);
    const [showPreviewModal, setShowPreviewModal] = useState(false);
    const [showCompareModal, setShowCompareModal] = useState(false);
    const [compareTemplates, setCompareTemplates] = useState([]);
    const [featuredTemplates, setFeaturedTemplates] = useState([]);
    const [trendingTemplates, setTrendingTemplates] = useState([]);
    const [userTemplates, setUserTemplates] = useState({ created: [], installed: [] });
    const [favorites, setFavorites] = useState([]);
    const [activeTab, setActiveTab] = useState('browse');
    const [installingTemplate, setInstallingTemplate] = useState(null);
    const [submitFormData, setSubmitFormData] = useState({
        title: '',
        description: '',
        content: '',
        category: '',
        industry: '',
        tags: [],
        license: 'free',
        visibility: 'public',
        configuration: {},
        prompts: {},
        demo_url: '',
        support_url: '',
        documentation_url: '',
        screenshots: [],
    });

    // API base path
    const API_BASE = '/wp-ai-site-generator/v1/marketplace';

    // Fetch templates
    const fetchTemplates = useCallback(async () => {
        setLoading(true);
        setError(null);

        try {
            const params = new URLSearchParams({
                page: currentPage,
                per_page: 12,
                sort_by: sortBy,
            });

            if (searchQuery) params.append('search', searchQuery);
            if (selectedCategory) params.append('category', selectedCategory);
            if (selectedIndustry) params.append('industry', selectedIndustry);
            if (selectedTags.length > 0) {
                selectedTags.forEach(tag => params.append('tags[]', tag));
            }
            if (filterLicense) params.append('license', filterLicense);
            if (filterRating > 0) params.append('rating_min', filterRating);

            const response = await apiFetch({
                path: `${API_BASE}/templates?${params.toString()}`,
            });

            setTemplates(response.templates || []);
            setTotalPages(response.total_pages || 1);
        } catch (err) {
            setError(err.message || __('Failed to fetch templates', 'wp-ai-site-generator'));
        } finally {
            setLoading(false);
        }
    }, [currentPage, sortBy, searchQuery, selectedCategory, selectedIndustry, selectedTags, filterLicense, filterRating]);

    // Fetch categories and industries
    const fetchMetadata = useCallback(async () => {
        try {
            const [categoriesData, industriesData] = await Promise.all([
                apiFetch({ path: `${API_BASE}/categories` }),
                apiFetch({ path: `${API_BASE}/industries` }),
            ]);

            setCategories(categoriesData || []);
            setIndustries(industriesData || []);
        } catch (err) {
            console.error('Failed to fetch metadata:', err);
        }
    }, []);

    // Fetch featured and trending templates
    const fetchHighlightedTemplates = useCallback(async () => {
        try {
            const [featured, trending] = await Promise.all([
                apiFetch({ path: `${API_BASE}/featured?limit=6` }),
                apiFetch({ path: `${API_BASE}/trending?limit=10` }),
            ]);

            setFeaturedTemplates(featured || []);
            setTrendingTemplates(trending || []);
        } catch (err) {
            console.error('Failed to fetch highlighted templates:', err);
        }
    }, []);

    // Fetch user templates
    const fetchUserTemplates = useCallback(async () => {
        try {
            const [myTemplates, myFavorites] = await Promise.all([
                apiFetch({ path: `${API_BASE}/my-templates` }),
                apiFetch({ path: `${API_BASE}/favorites` }),
            ]);

            setUserTemplates(myTemplates || { created: [], installed: [] });
            setFavorites(myFavorites || []);
        } catch (err) {
            console.error('Failed to fetch user templates:', err);
        }
    }, []);

    // Initialize
    useEffect(() => {
        fetchMetadata();
        fetchHighlightedTemplates();
        fetchUserTemplates();
    }, [fetchMetadata, fetchHighlightedTemplates, fetchUserTemplates]);

    // Fetch templates when filters change
    useEffect(() => {
        if (activeTab === 'browse') {
            fetchTemplates();
        }
    }, [fetchTemplates, activeTab]);

    // Install template
    const installTemplate = async (templateId) => {
        setInstallingTemplate(templateId);

        try {
            const response = await apiFetch({
                path: `${API_BASE}/templates/${templateId}/install`,
                method: 'POST',
            });

            if (response.success) {
                // Refresh user templates
                await fetchUserTemplates();

                // Show success notice
                wp.data.dispatch('core/notices').createSuccessNotice(
                    response.message || __('Template installed successfully', 'wp-ai-site-generator'),
                    { type: 'snackbar' }
                );
            }
        } catch (err) {
            wp.data.dispatch('core/notices').createErrorNotice(
                err.message || __('Failed to install template', 'wp-ai-site-generator'),
                { type: 'snackbar' }
            );
        } finally {
            setInstallingTemplate(null);
        }
    };

    // Rate template
    const rateTemplate = async (templateId, rating) => {
        try {
            const response = await apiFetch({
                path: `${API_BASE}/templates/${templateId}/rate`,
                method: 'POST',
                data: { rating },
            });

            if (response.success) {
                // Update template in list
                setTemplates(prev => prev.map(t =>
                    t.id === templateId
                        ? { ...t, rating: rating, rating_count: t.rating_count + 1 }
                        : t
                ));

                wp.data.dispatch('core/notices').createSuccessNotice(
                    __('Rating submitted successfully', 'wp-ai-site-generator'),
                    { type: 'snackbar' }
                );
            }
        } catch (err) {
            wp.data.dispatch('core/notices').createErrorNotice(
                err.message || __('Failed to submit rating', 'wp-ai-site-generator'),
                { type: 'snackbar' }
            );
        }
    };

    // Toggle favorite
    const toggleFavorite = async (templateId) => {
        const isFavorite = favorites.includes(templateId);
        const method = isFavorite ? 'DELETE' : 'POST';

        try {
            const response = await apiFetch({
                path: `${API_BASE}/templates/${templateId}/favorite`,
                method,
            });

            if (response.success) {
                if (isFavorite) {
                    setFavorites(prev => prev.filter(id => id !== templateId));
                } else {
                    setFavorites(prev => [...prev, templateId]);
                }

                wp.data.dispatch('core/notices').createSuccessNotice(
                    isFavorite
                        ? __('Removed from favorites', 'wp-ai-site-generator')
                        : __('Added to favorites', 'wp-ai-site-generator'),
                    { type: 'snackbar' }
                );
            }
        } catch (err) {
            wp.data.dispatch('core/notices').createErrorNotice(
                err.message || __('Failed to update favorites', 'wp-ai-site-generator'),
                { type: 'snackbar' }
            );
        }
    };

    // Submit new template
    const submitTemplate = async () => {
        try {
            const response = await apiFetch({
                path: `${API_BASE}/templates`,
                method: 'POST',
                data: submitFormData,
            });

            if (response.id) {
                setShowSubmitModal(false);
                setSubmitFormData({
                    title: '',
                    description: '',
                    content: '',
                    category: '',
                    industry: '',
                    tags: [],
                    license: 'free',
                    visibility: 'public',
                    configuration: {},
                    prompts: {},
                    demo_url: '',
                    support_url: '',
                    documentation_url: '',
                    screenshots: [],
                });

                wp.data.dispatch('core/notices').createSuccessNotice(
                    response.message || __('Template submitted successfully', 'wp-ai-site-generator'),
                    { type: 'snackbar' }
                );

                // Refresh user templates
                await fetchUserTemplates();
            }
        } catch (err) {
            wp.data.dispatch('core/notices').createErrorNotice(
                err.message || __('Failed to submit template', 'wp-ai-site-generator'),
                { type: 'snackbar' }
            );
        }
    };

    // Add to comparison
    const addToComparison = (template) => {
        if (compareTemplates.length >= 3) {
            wp.data.dispatch('core/notices').createWarningNotice(
                __('You can compare up to 3 templates at a time', 'wp-ai-site-generator'),
                { type: 'snackbar' }
            );
            return;
        }

        if (!compareTemplates.find(t => t.id === template.id)) {
            setCompareTemplates(prev => [...prev, template]);
        }
    };

    // Remove from comparison
    const removeFromComparison = (templateId) => {
        setCompareTemplates(prev => prev.filter(t => t.id !== templateId));
    };

    // Render template card
    const TemplateCard = ({ template }) => {
        const isFavorite = favorites.includes(template.id);
        const isInstalled = userTemplates.installed.some(t => t.original_id === template.id);
        const isComparing = compareTemplates.find(t => t.id === template.id);

        return (
            <Card className="template-card">
                <CardHeader>
                    <Flex>
                        <FlexBlock>
                            <Heading level={4}>{template.title}</Heading>
                        </FlexBlock>
                        <FlexItem>
                            <Button
                                icon={isFavorite ? starFilled : star}
                                label={isFavorite ? __('Remove from favorites', 'wp-ai-site-generator') : __('Add to favorites', 'wp-ai-site-generator')}
                                onClick={() => toggleFavorite(template.id)}
                                isSmall
                            />
                        </FlexItem>
                    </Flex>
                </CardHeader>
                <CardBody>
                    {template.screenshots && template.screenshots[0] && (
                        <div className="template-screenshot">
                            <img src={template.screenshots[0]} alt={template.title} />
                        </div>
                    )}
                    <Text>{template.description}</Text>
                    <HStack spacing={2} className="template-meta">
                        <Text>
                            <Icon icon={star} />
                            {template.rating.toFixed(1)} ({template.rating_count})
                        </Text>
                        <Text>
                            <Icon icon={download} />
                            {template.downloads}
                        </Text>
                        <Text>
                            <Icon icon={category} />
                            {template.categories[0]}
                        </Text>
                    </HStack>
                </CardBody>
                <CardFooter>
                    <HStack spacing={2}>
                        <Button
                            variant="primary"
                            onClick={() => setSelectedTemplate(template)}
                            isSmall
                        >
                            {__('Preview', 'wp-ai-site-generator')}
                        </Button>
                        {!isInstalled ? (
                            <Button
                                variant="secondary"
                                icon={download}
                                onClick={() => installTemplate(template.id)}
                                isBusy={installingTemplate === template.id}
                                disabled={installingTemplate !== null}
                                isSmall
                            >
                                {__('Install', 'wp-ai-site-generator')}
                            </Button>
                        ) : (
                            <Button
                                variant="secondary"
                                icon={check}
                                disabled
                                isSmall
                            >
                                {__('Installed', 'wp-ai-site-generator')}
                            </Button>
                        )}
                        <Button
                            icon={chart}
                            label={__('Compare', 'wp-ai-site-generator')}
                            onClick={() => addToComparison(template)}
                            isPressed={isComparing}
                            isSmall
                        />
                    </HStack>
                </CardFooter>
            </Card>
        );
    };

    // Render filters panel
    const FiltersPanel = () => (
        <Panel className="marketplace-filters">
            <PanelBody title={__('Filters', 'wp-ai-site-generator')} initialOpen={true}>
                <SelectControl
                    label={__('Category', 'wp-ai-site-generator')}
                    value={selectedCategory}
                    options={[
                        { value: '', label: __('All Categories', 'wp-ai-site-generator') },
                        ...categories.map(cat => ({ value: cat.slug, label: cat.name }))
                    ]}
                    onChange={setSelectedCategory}
                />
                <SelectControl
                    label={__('Industry', 'wp-ai-site-generator')}
                    value={selectedIndustry}
                    options={[
                        { value: '', label: __('All Industries', 'wp-ai-site-generator') },
                        ...industries.map(ind => ({ value: ind.slug, label: ind.name }))
                    ]}
                    onChange={setSelectedIndustry}
                />
                <SelectControl
                    label={__('License', 'wp-ai-site-generator')}
                    value={filterLicense}
                    options={[
                        { value: '', label: __('All Licenses', 'wp-ai-site-generator') },
                        { value: 'free', label: __('Free', 'wp-ai-site-generator') },
                        { value: 'premium', label: __('Premium', 'wp-ai-site-generator') },
                        { value: 'gpl', label: __('GPL', 'wp-ai-site-generator') },
                        { value: 'mit', label: __('MIT', 'wp-ai-site-generator') },
                    ]}
                    onChange={setFilterLicense}
                />
                <RangeControl
                    label={__('Minimum Rating', 'wp-ai-site-generator')}
                    value={filterRating}
                    onChange={setFilterRating}
                    min={0}
                    max={5}
                    step={0.5}
                />
                <Button
                    variant="secondary"
                    onClick={() => {
                        setSelectedCategory('');
                        setSelectedIndustry('');
                        setFilterLicense('');
                        setFilterRating(0);
                        setSelectedTags([]);
                    }}
                    isSmall
                >
                    {__('Clear Filters', 'wp-ai-site-generator')}
                </Button>
            </PanelBody>
        </Panel>
    );

    // Render template preview modal
    const TemplatePreviewModal = () => {
        if (!selectedTemplate) return null;

        return (
            <Modal
                title={selectedTemplate.title}
                onRequestClose={() => setSelectedTemplate(null)}
                className="template-preview-modal"
            >
                <VStack spacing={4}>
                    {selectedTemplate.screenshots && selectedTemplate.screenshots.length > 0 && (
                        <div className="template-screenshots">
                            {selectedTemplate.screenshots.map((screenshot, index) => (
                                <img key={index} src={screenshot} alt={`${selectedTemplate.title} screenshot ${index + 1}`} />
                            ))}
                        </div>
                    )}

                    <Text>{selectedTemplate.description}</Text>

                    <Divider />

                    <Grid columns={2} gap={4}>
                        <div>
                            <Heading level={5}>{__('Details', 'wp-ai-site-generator')}</Heading>
                            <dl>
                                <dt>{__('Author', 'wp-ai-site-generator')}</dt>
                                <dd>{selectedTemplate.author.name}</dd>
                                <dt>{__('Version', 'wp-ai-site-generator')}</dt>
                                <dd>{selectedTemplate.version}</dd>
                                <dt>{__('License', 'wp-ai-site-generator')}</dt>
                                <dd>{selectedTemplate.license}</dd>
                                <dt>{__('Downloads', 'wp-ai-site-generator')}</dt>
                                <dd>{selectedTemplate.downloads}</dd>
                                <dt>{__('Rating', 'wp-ai-site-generator')}</dt>
                                <dd>{selectedTemplate.rating.toFixed(1)} / 5.0 ({selectedTemplate.rating_count} reviews)</dd>
                            </dl>
                        </div>
                        <div>
                            <Heading level={5}>{__('Categories', 'wp-ai-site-generator')}</Heading>
                            <HStack spacing={2} wrap>
                                {selectedTemplate.categories.map(cat => (
                                    <span key={cat} className="template-badge">{cat}</span>
                                ))}
                            </HStack>

                            <Heading level={5}>{__('Tags', 'wp-ai-site-generator')}</Heading>
                            <HStack spacing={2} wrap>
                                {selectedTemplate.tags.map(tag => (
                                    <span key={tag} className="template-tag">{tag}</span>
                                ))}
                            </HStack>
                        </div>
                    </Grid>

                    <Divider />

                    {selectedTemplate.demo_url && (
                        <Button
                            variant="link"
                            icon={external}
                            href={selectedTemplate.demo_url}
                            target="_blank"
                        >
                            {__('View Demo', 'wp-ai-site-generator')}
                        </Button>
                    )}

                    {selectedTemplate.documentation_url && (
                        <Button
                            variant="link"
                            icon={info}
                            href={selectedTemplate.documentation_url}
                            target="_blank"
                        >
                            {__('Documentation', 'wp-ai-site-generator')}
                        </Button>
                    )}

                    <HStack spacing={2} justify="flex-end">
                        <Button
                            variant="secondary"
                            onClick={() => setSelectedTemplate(null)}
                        >
                            {__('Close', 'wp-ai-site-generator')}
                        </Button>
                        <Button
                            variant="primary"
                            icon={download}
                            onClick={() => {
                                installTemplate(selectedTemplate.id);
                                setSelectedTemplate(null);
                            }}
                            isBusy={installingTemplate === selectedTemplate.id}
                        >
                            {__('Install Template', 'wp-ai-site-generator')}
                        </Button>
                    </HStack>
                </VStack>
            </Modal>
        );
    };

    // Render template submission modal
    const TemplateSubmissionModal = () => (
        <Modal
            title={__('Submit New Template', 'wp-ai-site-generator')}
            onRequestClose={() => setShowSubmitModal(false)}
            className="template-submit-modal"
        >
            <VStack spacing={4}>
                <TextControl
                    label={__('Template Title', 'wp-ai-site-generator')}
                    value={submitFormData.title}
                    onChange={(title) => setSubmitFormData({ ...submitFormData, title })}
                    required
                />

                <TextareaControl
                    label={__('Description', 'wp-ai-site-generator')}
                    value={submitFormData.description}
                    onChange={(description) => setSubmitFormData({ ...submitFormData, description })}
                    rows={4}
                />

                <TextareaControl
                    label={__('Template Content', 'wp-ai-site-generator')}
                    value={submitFormData.content}
                    onChange={(content) => setSubmitFormData({ ...submitFormData, content })}
                    rows={10}
                    required
                />

                <SelectControl
                    label={__('Category', 'wp-ai-site-generator')}
                    value={submitFormData.category}
                    options={[
                        { value: '', label: __('Select Category', 'wp-ai-site-generator') },
                        ...categories.map(cat => ({ value: cat.slug, label: cat.name }))
                    ]}
                    onChange={(category) => setSubmitFormData({ ...submitFormData, category })}
                />

                <SelectControl
                    label={__('Industry', 'wp-ai-site-generator')}
                    value={submitFormData.industry}
                    options={[
                        { value: '', label: __('Select Industry', 'wp-ai-site-generator') },
                        ...industries.map(ind => ({ value: ind.slug, label: ind.name }))
                    ]}
                    onChange={(industry) => setSubmitFormData({ ...submitFormData, industry })}
                />

                <SelectControl
                    label={__('License', 'wp-ai-site-generator')}
                    value={submitFormData.license}
                    options={[
                        { value: 'free', label: __('Free', 'wp-ai-site-generator') },
                        { value: 'premium', label: __('Premium', 'wp-ai-site-generator') },
                        { value: 'gpl', label: __('GPL', 'wp-ai-site-generator') },
                        { value: 'mit', label: __('MIT', 'wp-ai-site-generator') },
                    ]}
                    onChange={(license) => setSubmitFormData({ ...submitFormData, license })}
                />

                <SelectControl
                    label={__('Visibility', 'wp-ai-site-generator')}
                    value={submitFormData.visibility}
                    options={[
                        { value: 'public', label: __('Public', 'wp-ai-site-generator') },
                        { value: 'private', label: __('Private', 'wp-ai-site-generator') },
                    ]}
                    onChange={(visibility) => setSubmitFormData({ ...submitFormData, visibility })}
                />

                <TextControl
                    label={__('Demo URL', 'wp-ai-site-generator')}
                    value={submitFormData.demo_url}
                    onChange={(demo_url) => setSubmitFormData({ ...submitFormData, demo_url })}
                    type="url"
                />

                <HStack spacing={2} justify="flex-end">
                    <Button
                        variant="secondary"
                        onClick={() => setShowSubmitModal(false)}
                    >
                        {__('Cancel', 'wp-ai-site-generator')}
                    </Button>
                    <Button
                        variant="primary"
                        onClick={submitTemplate}
                        disabled={!submitFormData.title || !submitFormData.content}
                    >
                        {__('Submit Template', 'wp-ai-site-generator')}
                    </Button>
                </HStack>
            </VStack>
        </Modal>
    );

    // Render comparison modal
    const TemplateComparisonModal = () => (
        <Modal
            title={__('Compare Templates', 'wp-ai-site-generator')}
            onRequestClose={() => setShowCompareModal(false)}
            className="template-compare-modal"
            isFullScreen
        >
            <div className="template-comparison-grid">
                {compareTemplates.map(template => (
                    <div key={template.id} className="comparison-column">
                        <Card>
                            <CardHeader>
                                <Flex>
                                    <FlexBlock>
                                        <Heading level={4}>{template.title}</Heading>
                                    </FlexBlock>
                                    <FlexItem>
                                        <Button
                                            icon={close}
                                            label={__('Remove', 'wp-ai-site-generator')}
                                            onClick={() => removeFromComparison(template.id)}
                                            isSmall
                                        />
                                    </FlexItem>
                                </Flex>
                            </CardHeader>
                            <CardBody>
                                <dl>
                                    <dt>{__('Author', 'wp-ai-site-generator')}</dt>
                                    <dd>{template.author.name}</dd>
                                    <dt>{__('Rating', 'wp-ai-site-generator')}</dt>
                                    <dd>{template.rating.toFixed(1)} / 5.0</dd>
                                    <dt>{__('Downloads', 'wp-ai-site-generator')}</dt>
                                    <dd>{template.downloads}</dd>
                                    <dt>{__('License', 'wp-ai-site-generator')}</dt>
                                    <dd>{template.license}</dd>
                                    <dt>{__('Version', 'wp-ai-site-generator')}</dt>
                                    <dd>{template.version}</dd>
                                    <dt>{__('Categories', 'wp-ai-site-generator')}</dt>
                                    <dd>{template.categories.join(', ')}</dd>
                                    <dt>{__('Tags', 'wp-ai-site-generator')}</dt>
                                    <dd>{template.tags.join(', ')}</dd>
                                </dl>
                            </CardBody>
                            <CardFooter>
                                <Button
                                    variant="primary"
                                    icon={download}
                                    onClick={() => installTemplate(template.id)}
                                    isSmall
                                >
                                    {__('Install', 'wp-ai-site-generator')}
                                </Button>
                            </CardFooter>
                        </Card>
                    </div>
                ))}
            </div>
            <HStack spacing={2} justify="flex-end" className="comparison-footer">
                <Button
                    variant="secondary"
                    onClick={() => setCompareTemplates([])}
                >
                    {__('Clear All', 'wp-ai-site-generator')}
                </Button>
                <Button
                    variant="primary"
                    onClick={() => setShowCompareModal(false)}
                >
                    {__('Close', 'wp-ai-site-generator')}
                </Button>
            </HStack>
        </Modal>
    );

    // Render featured carousel
    const FeaturedCarousel = () => (
        <div className="featured-templates-carousel">
            <Heading level={3}>{__('Featured Templates', 'wp-ai-site-generator')}</Heading>
            <Grid columns={3} gap={4}>
                {featuredTemplates.map(template => (
                    <TemplateCard key={template.id} template={template} />
                ))}
            </Grid>
        </div>
    );

    // Render trending section
    const TrendingSection = () => (
        <div className="trending-templates">
            <Heading level={3}>
                <Icon icon={trendingUp} />
                {__('Trending Templates', 'wp-ai-site-generator')}
            </Heading>
            <VStack spacing={2}>
                {trendingTemplates.map((template, index) => (
                    <Card key={template.id} className="trending-template-item">
                        <CardBody>
                            <Flex>
                                <FlexItem>
                                    <span className="trending-rank">#{index + 1}</span>
                                </FlexItem>
                                <FlexBlock>
                                    <Text>{template.title}</Text>
                                    <Text variant="muted" size="small">
                                        {template.downloads} downloads
                                    </Text>
                                </FlexBlock>
                                <FlexItem>
                                    <Button
                                        variant="secondary"
                                        onClick={() => setSelectedTemplate(template)}
                                        isSmall
                                    >
                                        {__('View', 'wp-ai-site-generator')}
                                    </Button>
                                </FlexItem>
                            </Flex>
                        </CardBody>
                    </Card>
                ))}
            </VStack>
        </div>
    );

    // Render my templates section
    const MyTemplatesSection = () => (
        <div className="my-templates">
            <VStack spacing={4}>
                <div className="created-templates">
                    <Heading level={3}>{__('My Created Templates', 'wp-ai-site-generator')}</Heading>
                    {userTemplates.created.length > 0 ? (
                        <Grid columns={3} gap={4}>
                            {userTemplates.created.map(template => (
                                <TemplateCard key={template.id} template={template} />
                            ))}
                        </Grid>
                    ) : (
                        <Notice status="info" isDismissible={false}>
                            {__('You have not created any templates yet.', 'wp-ai-site-generator')}
                        </Notice>
                    )}
                </div>

                <Divider />

                <div className="installed-templates">
                    <Heading level={3}>{__('My Installed Templates', 'wp-ai-site-generator')}</Heading>
                    {userTemplates.installed.length > 0 ? (
                        <Grid columns={3} gap={4}>
                            {userTemplates.installed.map(template => (
                                <Card key={template.original_id} className="installed-template">
                                    <CardHeader>
                                        <Heading level={4}>{template.title}</Heading>
                                    </CardHeader>
                                    <CardBody>
                                        <Text>{template.description}</Text>
                                        <Text variant="muted" size="small">
                                            {__('Installed:', 'wp-ai-site-generator')} {template.installed_at}
                                        </Text>
                                    </CardBody>
                                    <CardFooter>
                                        <Button variant="primary" isSmall>
                                            {__('Use Template', 'wp-ai-site-generator')}
                                        </Button>
                                    </CardFooter>
                                </Card>
                            ))}
                        </Grid>
                    ) : (
                        <Notice status="info" isDismissible={false}>
                            {__('You have not installed any templates yet.', 'wp-ai-site-generator')}
                        </Notice>
                    )}
                </div>
            </VStack>
        </div>
    );

    // Main render
    return (
        <div className="wp-ai-template-marketplace">
            <div className="marketplace-header">
                <Flex>
                    <FlexBlock>
                        <Heading level={2}>{__('Template Marketplace', 'wp-ai-site-generator')}</Heading>
                    </FlexBlock>
                    <FlexItem>
                        <HStack spacing={2}>
                            <Button
                                variant="primary"
                                icon={plus}
                                onClick={() => setShowSubmitModal(true)}
                            >
                                {__('Submit Template', 'wp-ai-site-generator')}
                            </Button>
                            {compareTemplates.length > 0 && (
                                <Button
                                    variant="secondary"
                                    icon={chart}
                                    onClick={() => setShowCompareModal(true)}
                                >
                                    {__('Compare', 'wp-ai-site-generator')} ({compareTemplates.length})
                                </Button>
                            )}
                        </HStack>
                    </FlexItem>
                </Flex>
            </div>

            <TabPanel
                className="marketplace-tabs"
                activeClass="is-active"
                tabs={[
                    { name: 'browse', title: __('Browse', 'wp-ai-site-generator') },
                    { name: 'featured', title: __('Featured', 'wp-ai-site-generator') },
                    { name: 'trending', title: __('Trending', 'wp-ai-site-generator') },
                    { name: 'my-templates', title: __('My Templates', 'wp-ai-site-generator') },
                ]}
                onSelect={(tab) => setActiveTab(tab)}
            >
                {(tab) => {
                    switch (tab.name) {
                        case 'browse':
                            return (
                                <div className="browse-templates">
                                    <div className="marketplace-controls">
                                        <Flex>
                                            <FlexBlock>
                                                <SearchControl
                                                    value={searchQuery}
                                                    onChange={setSearchQuery}
                                                    placeholder={__('Search templates...', 'wp-ai-site-generator')}
                                                />
                                            </FlexBlock>
                                            <FlexItem>
                                                <SelectControl
                                                    value={sortBy}
                                                    options={[
                                                        { value: 'popular', label: __('Most Popular', 'wp-ai-site-generator') },
                                                        { value: 'newest', label: __('Newest', 'wp-ai-site-generator') },
                                                        { value: 'trending', label: __('Trending', 'wp-ai-site-generator') },
                                                        { value: 'rating', label: __('Highest Rated', 'wp-ai-site-generator') },
                                                        { value: 'name', label: __('Name', 'wp-ai-site-generator') },
                                                    ]}
                                                    onChange={setSortBy}
                                                />
                                            </FlexItem>
                                            <FlexItem>
                                                <Button
                                                    icon={filter}
                                                    onClick={() => setShowFilters(!showFilters)}
                                                    isPressed={showFilters}
                                                >
                                                    {__('Filters', 'wp-ai-site-generator')}
                                                </Button>
                                            </FlexItem>
                                            <FlexItem>
                                                <Button
                                                    icon={viewMode === 'grid' ? list : grid}
                                                    onClick={() => setViewMode(viewMode === 'grid' ? 'list' : 'grid')}
                                                    label={viewMode === 'grid'
                                                        ? __('Switch to list view', 'wp-ai-site-generator')
                                                        : __('Switch to grid view', 'wp-ai-site-generator')}
                                                />
                                            </FlexItem>
                                        </Flex>
                                    </div>

                                    <div className="marketplace-content">
                                        {showFilters && (
                                            <div className="marketplace-sidebar">
                                                <FiltersPanel />
                                            </div>
                                        )}

                                        <div className="marketplace-main">
                                            {loading && (
                                                <div className="loading-spinner">
                                                    <Spinner />
                                                </div>
                                            )}

                                            {error && (
                                                <Notice status="error" isDismissible={false}>
                                                    {error}
                                                </Notice>
                                            )}

                                            {!loading && !error && templates.length === 0 && (
                                                <Notice status="info" isDismissible={false}>
                                                    {__('No templates found matching your criteria.', 'wp-ai-site-generator')}
                                                </Notice>
                                            )}

                                            {!loading && !error && templates.length > 0 && (
                                                <>
                                                    {viewMode === 'grid' ? (
                                                        <Grid columns={3} gap={4} className="templates-grid">
                                                            {templates.map(template => (
                                                                <TemplateCard key={template.id} template={template} />
                                                            ))}
                                                        </Grid>
                                                    ) : (
                                                        <VStack spacing={3} className="templates-list">
                                                            {templates.map(template => (
                                                                <Card key={template.id} className="template-list-item">
                                                                    <CardBody>
                                                                        <Flex>
                                                                            <FlexBlock>
                                                                                <Heading level={4}>{template.title}</Heading>
                                                                                <Text>{template.description}</Text>
                                                                                <HStack spacing={4}>
                                                                                    <Text variant="muted">
                                                                                        {__('By', 'wp-ai-site-generator')} {template.author.name}
                                                                                    </Text>
                                                                                    <Text variant="muted">
                                                                                        <Icon icon={star} /> {template.rating.toFixed(1)}
                                                                                    </Text>
                                                                                    <Text variant="muted">
                                                                                        <Icon icon={download} /> {template.downloads}
                                                                                    </Text>
                                                                                </HStack>
                                                                            </FlexBlock>
                                                                            <FlexItem>
                                                                                <HStack spacing={2}>
                                                                                    <Button
                                                                                        variant="primary"
                                                                                        onClick={() => setSelectedTemplate(template)}
                                                                                        isSmall
                                                                                    >
                                                                                        {__('Preview', 'wp-ai-site-generator')}
                                                                                    </Button>
                                                                                    <Button
                                                                                        variant="secondary"
                                                                                        icon={download}
                                                                                        onClick={() => installTemplate(template.id)}
                                                                                        isSmall
                                                                                    >
                                                                                        {__('Install', 'wp-ai-site-generator')}
                                                                                    </Button>
                                                                                </HStack>
                                                                            </FlexItem>
                                                                        </Flex>
                                                                    </CardBody>
                                                                </Card>
                                                            ))}
                                                        </VStack>
                                                    )}

                                                    {totalPages > 1 && (
                                                        <div className="marketplace-pagination">
                                                            <HStack spacing={2} justify="center">
                                                                <Button
                                                                    variant="secondary"
                                                                    onClick={() => setCurrentPage(prev => Math.max(1, prev - 1))}
                                                                    disabled={currentPage === 1}
                                                                >
                                                                    {__('Previous', 'wp-ai-site-generator')}
                                                                </Button>
                                                                <Text>
                                                                    {__('Page', 'wp-ai-site-generator')} {currentPage} {__('of', 'wp-ai-site-generator')} {totalPages}
                                                                </Text>
                                                                <Button
                                                                    variant="secondary"
                                                                    onClick={() => setCurrentPage(prev => Math.min(totalPages, prev + 1))}
                                                                    disabled={currentPage === totalPages}
                                                                >
                                                                    {__('Next', 'wp-ai-site-generator')}
                                                                </Button>
                                                            </HStack>
                                                        </div>
                                                    )}
                                                </>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            );

                        case 'featured':
                            return <FeaturedCarousel />;

                        case 'trending':
                            return <TrendingSection />;

                        case 'my-templates':
                            return <MyTemplatesSection />;

                        default:
                            return null;
                    }
                }}
            </TabPanel>

            {selectedTemplate && <TemplatePreviewModal />}
            {showSubmitModal && <TemplateSubmissionModal />}
            {showCompareModal && <TemplateComparisonModal />}
        </div>
    );
};

export default TemplateMarketplace;