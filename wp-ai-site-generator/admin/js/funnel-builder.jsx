/**
 * Funnel Builder UI Component
 *
 * Visual funnel builder interface for WordPress admin
 *
 * @package WP_AI_Site_Generator
 */

import React, { useState, useEffect, useCallback } from 'react';
import {
    DndContext,
    closestCenter,
    KeyboardSensor,
    PointerSensor,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import {
    arrayMove,
    SortableContext,
    sortableKeyboardCoordinates,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import {
    useSortable,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';

/**
 * Main Funnel Builder Component
 */
const FunnelBuilder = () => {
    const [funnel, setFunnel] = useState(null);
    const [stages, setStages] = useState([]);
    const [selectedStage, setSelectedStage] = useState(null);
    const [templates, setTemplates] = useState([]);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [analytics, setAnalytics] = useState(null);
    const [showTemplates, setShowTemplates] = useState(false);
    const [showAnalytics, setShowAnalytics] = useState(false);

    const sensors = useSensors(
        useSensor(PointerSensor),
        useSensor(KeyboardSensor, {
            coordinateGetter: sortableKeyboardCoordinates,
        })
    );

    // Load funnel data
    useEffect(() => {
        loadFunnel();
        loadTemplates();
    }, []);

    const loadFunnel = async () => {
        const urlParams = new URLSearchParams(window.location.search);
        const funnelId = urlParams.get('funnel_id');

        if (funnelId) {
            try {
                const response = await fetch(`/wp-json/wp-ai-site-generator/v1/funnels/${funnelId}`, {
                    headers: {
                        'X-WP-Nonce': wpApiSettings.nonce,
                    },
                });

                if (response.ok) {
                    const data = await response.json();
                    setFunnel(data);
                    setStages(data.stages || []);
                    loadAnalytics(funnelId);
                }
            } catch (error) {
                console.error('Error loading funnel:', error);
            }
        }

        setLoading(false);
    };

    const loadTemplates = async () => {
        try {
            const response = await fetch('/wp-json/wp-ai-site-generator/v1/funnels/templates', {
                headers: {
                    'X-WP-Nonce': wpApiSettings.nonce,
                },
            });

            if (response.ok) {
                const data = await response.json();
                setTemplates(data);
            }
        } catch (error) {
            console.error('Error loading templates:', error);
        }
    };

    const loadAnalytics = async (funnelId) => {
        try {
            const response = await fetch(`/wp-json/wp-ai-site-generator/v1/funnels/${funnelId}/analytics?date_range=30days`, {
                headers: {
                    'X-WP-Nonce': wpApiSettings.nonce,
                },
            });

            if (response.ok) {
                const data = await response.json();
                setAnalytics(data);
            }
        } catch (error) {
            console.error('Error loading analytics:', error);
        }
    };

    const saveFunnel = async () => {
        if (!funnel) return;

        setSaving(true);

        try {
            const response = await fetch(`/wp-json/wp-ai-site-generator/v1/funnels/${funnel.id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': wpApiSettings.nonce,
                },
                body: JSON.stringify({
                    stages: stages,
                }),
            });

            if (response.ok) {
                showNotification('Funnel saved successfully!', 'success');
            }
        } catch (error) {
            console.error('Error saving funnel:', error);
            showNotification('Error saving funnel', 'error');
        }

        setSaving(false);
    };

    const handleDragEnd = (event) => {
        const { active, over } = event;

        if (active.id !== over.id) {
            setStages((items) => {
                const oldIndex = items.findIndex(i => i.id === active.id);
                const newIndex = items.findIndex(i => i.id === over.id);
                return arrayMove(items, oldIndex, newIndex);
            });
        }
    };

    const addStage = (type = 'custom') => {
        const newStage = {
            id: generateId(),
            name: 'New Stage',
            type: type,
            goal: '',
            content: {},
            metrics: {
                views: 0,
                conversions: 0,
                conversion_rate: 0,
            },
            status: 'active',
        };

        setStages([...stages, newStage]);
        setSelectedStage(newStage);
    };

    const removeStage = (stageId) => {
        setStages(stages.filter(s => s.id !== stageId));
        if (selectedStage?.id === stageId) {
            setSelectedStage(null);
        }
    };

    const updateStage = (stageId, updates) => {
        setStages(stages.map(s =>
            s.id === stageId ? { ...s, ...updates } : s
        ));
    };

    const applyTemplate = (templateId) => {
        const template = templates[templateId];
        if (template) {
            setStages(template.stages);
            setShowTemplates(false);
            showNotification(`Applied ${template.name} template`, 'success');
        }
    };

    const generateId = () => {
        return 'stage-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
    };

    const showNotification = (message, type = 'info') => {
        // This would integrate with WordPress admin notices
        console.log(`${type}: ${message}`);
    };

    if (loading) {
        return <div className="funnel-builder-loading">Loading...</div>;
    }

    return (
        <div className="funnel-builder">
            <FunnelHeader
                funnel={funnel}
                onSave={saveFunnel}
                saving={saving}
                onToggleAnalytics={() => setShowAnalytics(!showAnalytics)}
                onToggleTemplates={() => setShowTemplates(!showTemplates)}
            />

            {showAnalytics && analytics && (
                <FunnelAnalytics analytics={analytics} onClose={() => setShowAnalytics(false)} />
            )}

            {showTemplates && (
                <TemplateSelector
                    templates={templates}
                    onSelect={applyTemplate}
                    onClose={() => setShowTemplates(false)}
                />
            )}

            <div className="funnel-builder-content">
                <div className="funnel-canvas">
                    <DndContext
                        sensors={sensors}
                        collisionDetection={closestCenter}
                        onDragEnd={handleDragEnd}
                    >
                        <SortableContext
                            items={stages.map(s => s.id)}
                            strategy={verticalListSortingStrategy}
                        >
                            {stages.map((stage) => (
                                <SortableStage
                                    key={stage.id}
                                    stage={stage}
                                    isSelected={selectedStage?.id === stage.id}
                                    onClick={() => setSelectedStage(stage)}
                                    onRemove={() => removeStage(stage.id)}
                                />
                            ))}
                        </SortableContext>
                    </DndContext>

                    <button
                        className="add-stage-button"
                        onClick={() => addStage()}
                    >
                        + Add Stage
                    </button>
                </div>

                <div className="funnel-sidebar">
                    {selectedStage ? (
                        <StageEditor
                            stage={selectedStage}
                            onChange={(updates) => updateStage(selectedStage.id, updates)}
                        />
                    ) : (
                        <div className="no-stage-selected">
                            <p>Select a stage to edit or add a new stage to get started</p>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
};

/**
 * Funnel Header Component
 */
const FunnelHeader = ({ funnel, onSave, saving, onToggleAnalytics, onToggleTemplates }) => {
    return (
        <div className="funnel-header">
            <h2>{funnel?.name || 'New Funnel'}</h2>
            <div className="funnel-actions">
                <button
                    className="button"
                    onClick={onToggleTemplates}
                >
                    Templates
                </button>
                <button
                    className="button"
                    onClick={onToggleAnalytics}
                >
                    Analytics
                </button>
                <button
                    className="button button-primary"
                    onClick={onSave}
                    disabled={saving}
                >
                    {saving ? 'Saving...' : 'Save Funnel'}
                </button>
            </div>
        </div>
    );
};

/**
 * Sortable Stage Component
 */
const SortableStage = ({ stage, isSelected, onClick, onRemove }) => {
    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
    } = useSortable({ id: stage.id });

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
    };

    return (
        <div
            ref={setNodeRef}
            style={style}
            {...attributes}
            className={`funnel-stage ${isSelected ? 'selected' : ''}`}
            onClick={onClick}
        >
            <div className="stage-header" {...listeners}>
                <span className="stage-handle">⋮⋮</span>
                <h3>{stage.name}</h3>
                <button
                    className="stage-remove"
                    onClick={(e) => {
                        e.stopPropagation();
                        onRemove();
                    }}
                >
                    ×
                </button>
            </div>
            <div className="stage-content">
                <div className="stage-type">{stage.type}</div>
                {stage.metrics && (
                    <div className="stage-metrics">
                        <div className="metric">
                            <span className="metric-label">Views:</span>
                            <span className="metric-value">{stage.metrics.views}</span>
                        </div>
                        <div className="metric">
                            <span className="metric-label">Conversions:</span>
                            <span className="metric-value">{stage.metrics.conversions}</span>
                        </div>
                        <div className="metric">
                            <span className="metric-label">Rate:</span>
                            <span className="metric-value">{stage.metrics.conversion_rate}%</span>
                        </div>
                    </div>
                )}
            </div>
            <div className="stage-connector"></div>
        </div>
    );
};

/**
 * Stage Editor Component
 */
const StageEditor = ({ stage, onChange }) => {
    const [localStage, setLocalStage] = useState(stage);

    useEffect(() => {
        setLocalStage(stage);
    }, [stage]);

    const handleChange = (field, value) => {
        const updated = { ...localStage, [field]: value };
        setLocalStage(updated);
        onChange({ [field]: value });
    };

    const handleContentChange = (section, value) => {
        const content = { ...localStage.content, [section]: value };
        handleChange('content', content);
    };

    return (
        <div className="stage-editor">
            <h3>Edit Stage</h3>

            <div className="form-group">
                <label>Stage Name</label>
                <input
                    type="text"
                    value={localStage.name}
                    onChange={(e) => handleChange('name', e.target.value)}
                />
            </div>

            <div className="form-group">
                <label>Stage Type</label>
                <select
                    value={localStage.type}
                    onChange={(e) => handleChange('type', e.target.value)}
                >
                    <option value="landing">Landing Page</option>
                    <option value="opt_in">Opt-In Page</option>
                    <option value="sales">Sales Page</option>
                    <option value="upsell">Upsell Page</option>
                    <option value="thank_you">Thank You Page</option>
                    <option value="webinar">Webinar Page</option>
                    <option value="email_sequence">Email Sequence</option>
                    <option value="custom">Custom</option>
                </select>
            </div>

            <div className="form-group">
                <label>Stage Goal</label>
                <input
                    type="text"
                    value={localStage.goal}
                    onChange={(e) => handleChange('goal', e.target.value)}
                    placeholder="e.g., Capture leads, Close sale"
                />
            </div>

            <div className="form-group">
                <label>Status</label>
                <select
                    value={localStage.status}
                    onChange={(e) => handleChange('status', e.target.value)}
                >
                    <option value="active">Active</option>
                    <option value="paused">Paused</option>
                    <option value="draft">Draft</option>
                </select>
            </div>

            <h4>Content Sections</h4>

            <div className="form-group">
                <label>Headline</label>
                <input
                    type="text"
                    value={localStage.content?.headline || ''}
                    onChange={(e) => handleContentChange('headline', e.target.value)}
                />
            </div>

            <div className="form-group">
                <label>Subheadline</label>
                <input
                    type="text"
                    value={localStage.content?.subheadline || ''}
                    onChange={(e) => handleContentChange('subheadline', e.target.value)}
                />
            </div>

            <div className="form-group">
                <label>Body Content</label>
                <textarea
                    value={localStage.content?.body || ''}
                    onChange={(e) => handleContentChange('body', e.target.value)}
                    rows="6"
                />
            </div>

            <div className="form-group">
                <label>Call to Action</label>
                <input
                    type="text"
                    value={localStage.content?.cta || ''}
                    onChange={(e) => handleContentChange('cta', e.target.value)}
                    placeholder="e.g., Get Started Now"
                />
            </div>

            <button
                className="button button-secondary"
                onClick={() => generateAIContent(stage, onChange)}
            >
                Generate AI Content
            </button>
        </div>
    );
};

/**
 * Template Selector Component
 */
const TemplateSelector = ({ templates, onSelect, onClose }) => {
    return (
        <div className="template-selector-overlay">
            <div className="template-selector">
                <div className="template-selector-header">
                    <h3>Choose a Funnel Template</h3>
                    <button className="close-button" onClick={onClose}>×</button>
                </div>
                <div className="template-grid">
                    {Object.entries(templates).map(([id, template]) => (
                        <div
                            key={id}
                            className="template-card"
                            onClick={() => onSelect(id)}
                        >
                            <h4>{template.name}</h4>
                            <p>{template.description}</p>
                            <div className="template-stages">
                                {template.stages.length} stages
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
};

/**
 * Funnel Analytics Component
 */
const FunnelAnalytics = ({ analytics, onClose }) => {
    const calculateDropOff = (current, previous) => {
        if (!previous) return 0;
        return ((previous - current) / previous * 100).toFixed(1);
    };

    return (
        <div className="funnel-analytics-overlay">
            <div className="funnel-analytics">
                <div className="analytics-header">
                    <h3>Funnel Analytics (Last 30 Days)</h3>
                    <button className="close-button" onClick={onClose}>×</button>
                </div>

                <div className="analytics-overview">
                    <div className="metric-card">
                        <span className="metric-label">Total Visitors</span>
                        <span className="metric-value">{analytics.overall?.total_visitors || 0}</span>
                    </div>
                    <div className="metric-card">
                        <span className="metric-label">Conversions</span>
                        <span className="metric-value">{analytics.overall?.total_conversions || 0}</span>
                    </div>
                    <div className="metric-card">
                        <span className="metric-label">Conversion Rate</span>
                        <span className="metric-value">{analytics.overall?.conversion_rate?.toFixed(2) || 0}%</span>
                    </div>
                    <div className="metric-card">
                        <span className="metric-label">Revenue</span>
                        <span className="metric-value">${analytics.overall?.revenue || 0}</span>
                    </div>
                </div>

                <div className="funnel-visualization">
                    <h4>Funnel Flow</h4>
                    {analytics.stages?.map((stage, index) => (
                        <div key={stage.stage_id} className="funnel-stage-analytics">
                            <div className="stage-bar">
                                <div
                                    className="stage-bar-fill"
                                    style={{
                                        width: `${(stage.views / analytics.stages[0].views) * 100}%`
                                    }}
                                />
                                <span className="stage-label">{stage.stage_id}</span>
                                <span className="stage-stats">
                                    {stage.views} visitors | {stage.conversions} conversions
                                </span>
                            </div>
                            {index > 0 && (
                                <div className="drop-off">
                                    ↓ {calculateDropOff(stage.views, analytics.stages[index - 1].views)}% drop-off
                                </div>
                            )}
                        </div>
                    ))}
                </div>

                {analytics.drop_off_points?.length > 0 && (
                    <div className="drop-off-analysis">
                        <h4>Major Drop-off Points</h4>
                        {analytics.drop_off_points.map((point, index) => (
                            <div key={index} className="drop-off-point">
                                <span>{point.between[0]} → {point.between[1]}</span>
                                <span className="drop-off-rate">{point.rate.toFixed(1)}% loss</span>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
};

/**
 * Helper function to generate AI content
 */
const generateAIContent = async (stage, onChange) => {
    try {
        const response = await fetch('/wp-json/wp-ai-site-generator/v1/generate/funnel-content', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': wpApiSettings.nonce,
            },
            body: JSON.stringify({
                stage_type: stage.type,
                stage_name: stage.name,
                stage_goal: stage.goal,
            }),
        });

        if (response.ok) {
            const content = await response.json();
            onChange({ content });
        }
    } catch (error) {
        console.error('Error generating AI content:', error);
    }
};

// Export for WordPress integration
window.WPAIFunnelBuilder = FunnelBuilder;