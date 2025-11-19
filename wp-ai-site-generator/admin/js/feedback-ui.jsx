/**
 * Feedback UI Components
 *
 * @package WP_AI_Site_Generator
 */

import React, { useState, useEffect, useCallback } from 'react';
import { __ } from '@wordpress/i18n';
import { Button, Modal, TextControl, TextareaControl, SelectControl, CheckboxControl, Notice } from '@wordpress/components';
import { StarRating, StarOutline, ThumbUp, ThumbDown, Warning, CheckCircle } from '@wordpress/icons';
import apiFetch from '@wordpress/api-fetch';

/**
 * Rating Widget Component
 */
export const RatingWidget = ({ generationId, onComplete }) => {
    const [ratings, setRatings] = useState({
        overall: 0,
        quality: 0,
        accuracy: 0,
        relevance: 0,
        usefulness: 0,
    });
    const [hoveredRating, setHoveredRating] = useState({});
    const [submitted, setSubmitted] = useState(false);

    const dimensions = [
        { key: 'overall', label: __('Overall', 'wp-ai-site-generator') },
        { key: 'quality', label: __('Quality', 'wp-ai-site-generator') },
        { key: 'accuracy', label: __('Accuracy', 'wp-ai-site-generator') },
        { key: 'relevance', label: __('Relevance', 'wp-ai-site-generator') },
        { key: 'usefulness', label: __('Usefulness', 'wp-ai-site-generator') },
    ];

    const handleRating = (dimension, value) => {
        setRatings(prev => ({ ...prev, [dimension]: value }));
    };

    const renderStars = (dimension) => {
        const rating = hoveredRating[dimension] || ratings[dimension];
        const stars = [];

        for (let i = 1; i <= 5; i++) {
            stars.push(
                <button
                    key={i}
                    className={`star-button ${i <= rating ? 'filled' : ''}`}
                    onMouseEnter={() => setHoveredRating({ ...hoveredRating, [dimension]: i })}
                    onMouseLeave={() => setHoveredRating({ ...hoveredRating, [dimension]: 0 })}
                    onClick={() => handleRating(dimension, i)}
                    aria-label={`${i} stars`}
                >
                    {i <= rating ? '★' : '☆'}
                </button>
            );
        }

        return stars;
    };

    const handleSubmit = async () => {
        try {
            const response = await apiFetch({
                path: '/wp-ai-site-generator/v1/feedback/submit',
                method: 'POST',
                data: {
                    generation_id: generationId,
                    ...Object.keys(ratings).reduce((acc, key) => {
                        acc[`${key}_rating`] = ratings[key];
                        return acc;
                    }, {}),
                },
            });

            setSubmitted(true);
            if (onComplete) {
                onComplete(response);
            }
        } catch (error) {
            console.error('Failed to submit rating:', error);
        }
    };

    if (submitted) {
        return (
            <div className="rating-widget submitted">
                <CheckCircle />
                <p>{__('Thank you for your ratings!', 'wp-ai-site-generator')}</p>
            </div>
        );
    }

    return (
        <div className="rating-widget">
            <h3>{__('Rate this content', 'wp-ai-site-generator')}</h3>
            {dimensions.map(({ key, label }) => (
                <div key={key} className="rating-dimension">
                    <label>{label}</label>
                    <div className="stars">
                        {renderStars(key)}
                    </div>
                </div>
            ))}
            <Button
                variant="primary"
                onClick={handleSubmit}
                disabled={!ratings.overall}
            >
                {__('Submit Ratings', 'wp-ai-site-generator')}
            </Button>
        </div>
    );
};

/**
 * Feedback Modal Component
 */
export const FeedbackModal = ({ isOpen, onClose, generationId, sectionId = null }) => {
    const [formData, setFormData] = useState({
        overall_rating: 0,
        quality_rating: 0,
        accuracy_rating: 0,
        relevance_rating: 0,
        usefulness_rating: 0,
        text_feedback: '',
        categories: [],
        is_anonymous: false,
        consent_given: false,
    });
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [submitStatus, setSubmitStatus] = useState(null);

    const categories = [
        'content_quality',
        'technical_accuracy',
        'relevance',
        'completeness',
        'creativity',
        'formatting',
        'tone_style',
        'length',
        'structure',
        'usefulness',
    ];

    const handleInputChange = (field, value) => {
        setFormData(prev => ({ ...prev, [field]: value }));
    };

    const handleCategoryToggle = (category) => {
        setFormData(prev => ({
            ...prev,
            categories: prev.categories.includes(category)
                ? prev.categories.filter(c => c !== category)
                : [...prev.categories, category],
        }));
    };

    const handleSubmit = async () => {
        if (!formData.consent_given && !formData.is_anonymous) {
            setSubmitStatus({
                type: 'error',
                message: __('Please provide consent or submit anonymously', 'wp-ai-site-generator'),
            });
            return;
        }

        setIsSubmitting(true);
        setSubmitStatus(null);

        try {
            const response = await apiFetch({
                path: '/wp-ai-site-generator/v1/feedback/submit',
                method: 'POST',
                data: {
                    generation_id: generationId,
                    section_id: sectionId,
                    ...formData,
                },
            });

            setSubmitStatus({
                type: 'success',
                message: response.message || __('Feedback submitted successfully!', 'wp-ai-site-generator'),
            });

            setTimeout(() => {
                onClose();
            }, 2000);
        } catch (error) {
            setSubmitStatus({
                type: 'error',
                message: error.message || __('Failed to submit feedback', 'wp-ai-site-generator'),
            });
        } finally {
            setIsSubmitting(false);
        }
    };

    if (!isOpen) return null;

    return (
        <Modal
            title={__('Provide Detailed Feedback', 'wp-ai-site-generator')}
            onRequestClose={onClose}
            className="feedback-modal"
        >
            <div className="feedback-form">
                {submitStatus && (
                    <Notice
                        status={submitStatus.type}
                        isDismissible={true}
                        onRemove={() => setSubmitStatus(null)}
                    >
                        {submitStatus.message}
                    </Notice>
                )}

                <div className="rating-section">
                    <h4>{__('Rate Different Aspects', 'wp-ai-site-generator')}</h4>
                    {['overall', 'quality', 'accuracy', 'relevance', 'usefulness'].map(dimension => (
                        <div key={dimension} className="rating-row">
                            <label>{dimension.charAt(0).toUpperCase() + dimension.slice(1)}</label>
                            <StarRatingInput
                                value={formData[`${dimension}_rating`]}
                                onChange={(value) => handleInputChange(`${dimension}_rating`, value)}
                            />
                        </div>
                    ))}
                </div>

                <TextareaControl
                    label={__('Your Feedback', 'wp-ai-site-generator')}
                    help={__('Please share your thoughts about this content', 'wp-ai-site-generator')}
                    value={formData.text_feedback}
                    onChange={(value) => handleInputChange('text_feedback', value)}
                    rows={4}
                />

                <div className="categories-section">
                    <h4>{__('Select Relevant Categories', 'wp-ai-site-generator')}</h4>
                    <div className="category-grid">
                        {categories.map(category => (
                            <CheckboxControl
                                key={category}
                                label={category.replace('_', ' ').charAt(0).toUpperCase() + category.replace('_', ' ').slice(1)}
                                checked={formData.categories.includes(category)}
                                onChange={() => handleCategoryToggle(category)}
                            />
                        ))}
                    </div>
                </div>

                <CheckboxControl
                    label={__('Submit anonymously', 'wp-ai-site-generator')}
                    checked={formData.is_anonymous}
                    onChange={(value) => handleInputChange('is_anonymous', value)}
                />

                {!formData.is_anonymous && (
                    <CheckboxControl
                        label={__('I consent to data collection for improvement purposes', 'wp-ai-site-generator')}
                        checked={formData.consent_given}
                        onChange={(value) => handleInputChange('consent_given', value)}
                    />
                )}

                <div className="modal-actions">
                    <Button
                        variant="secondary"
                        onClick={onClose}
                        disabled={isSubmitting}
                    >
                        {__('Cancel', 'wp-ai-site-generator')}
                    </Button>
                    <Button
                        variant="primary"
                        onClick={handleSubmit}
                        isBusy={isSubmitting}
                        disabled={isSubmitting || !formData.overall_rating}
                    >
                        {__('Submit Feedback', 'wp-ai-site-generator')}
                    </Button>
                </div>
            </div>
        </Modal>
    );
};

/**
 * Quick Feedback Buttons Component
 */
export const QuickFeedbackButtons = ({ generationId, sectionId = null }) => {
    const [feedback, setFeedback] = useState(null);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleQuickFeedback = async (isPositive) => {
        if (isSubmitting || feedback !== null) return;

        setIsSubmitting(true);

        try {
            await apiFetch({
                path: '/wp-ai-site-generator/v1/feedback/quick',
                method: 'POST',
                data: {
                    generation_id: generationId,
                    is_positive: isPositive,
                    section_id: sectionId,
                },
            });

            setFeedback(isPositive ? 'positive' : 'negative');
        } catch (error) {
            console.error('Failed to submit quick feedback:', error);
        } finally {
            setIsSubmitting(false);
        }
    };

    return (
        <div className="quick-feedback-buttons">
            <button
                className={`feedback-btn thumbs-up ${feedback === 'positive' ? 'active' : ''}`}
                onClick={() => handleQuickFeedback(true)}
                disabled={isSubmitting || feedback !== null}
                title={__('Helpful', 'wp-ai-site-generator')}
            >
                <ThumbUp />
            </button>
            <button
                className={`feedback-btn thumbs-down ${feedback === 'negative' ? 'active' : ''}`}
                onClick={() => handleQuickFeedback(false)}
                disabled={isSubmitting || feedback !== null}
                title={__('Not helpful', 'wp-ai-site-generator')}
            >
                <ThumbDown />
            </button>
        </div>
    );
};

/**
 * Issue Reporting Interface Component
 */
export const IssueReporter = ({ generationId, sectionId = null, onClose }) => {
    const [issueData, setIssueData] = useState({
        issue_type: '',
        severity: 'medium',
        description: '',
        affected_section: sectionId || '',
        steps_to_reproduce: '',
        expected_behavior: '',
        actual_behavior: '',
    });
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [submitStatus, setSubmitStatus] = useState(null);

    const issueTypes = [
        { value: 'bug', label: __('Bug', 'wp-ai-site-generator') },
        { value: 'error', label: __('Error', 'wp-ai-site-generator') },
        { value: 'quality', label: __('Quality Issue', 'wp-ai-site-generator') },
        { value: 'formatting', label: __('Formatting Issue', 'wp-ai-site-generator') },
        { value: 'content', label: __('Content Issue', 'wp-ai-site-generator') },
        { value: 'performance', label: __('Performance Issue', 'wp-ai-site-generator') },
        { value: 'other', label: __('Other', 'wp-ai-site-generator') },
    ];

    const severityLevels = [
        { value: 'low', label: __('Low', 'wp-ai-site-generator') },
        { value: 'medium', label: __('Medium', 'wp-ai-site-generator') },
        { value: 'high', label: __('High', 'wp-ai-site-generator') },
        { value: 'critical', label: __('Critical', 'wp-ai-site-generator') },
    ];

    const handleSubmit = async () => {
        if (!issueData.issue_type || !issueData.description) {
            setSubmitStatus({
                type: 'error',
                message: __('Please provide issue type and description', 'wp-ai-site-generator'),
            });
            return;
        }

        setIsSubmitting(true);
        setSubmitStatus(null);

        try {
            const response = await apiFetch({
                path: '/wp-ai-site-generator/v1/feedback/report-issue',
                method: 'POST',
                data: {
                    generation_id: generationId,
                    ...issueData,
                },
            });

            setSubmitStatus({
                type: 'success',
                message: response.message || __('Issue reported successfully!', 'wp-ai-site-generator'),
            });

            setTimeout(() => {
                if (onClose) onClose();
            }, 2000);
        } catch (error) {
            setSubmitStatus({
                type: 'error',
                message: error.message || __('Failed to report issue', 'wp-ai-site-generator'),
            });
        } finally {
            setIsSubmitting(false);
        }
    };

    return (
        <div className="issue-reporter">
            <h3>{__('Report an Issue', 'wp-ai-site-generator')}</h3>

            {submitStatus && (
                <Notice
                    status={submitStatus.type}
                    isDismissible={true}
                    onRemove={() => setSubmitStatus(null)}
                >
                    {submitStatus.message}
                </Notice>
            )}

            <SelectControl
                label={__('Issue Type', 'wp-ai-site-generator')}
                value={issueData.issue_type}
                options={[
                    { value: '', label: __('Select type...', 'wp-ai-site-generator') },
                    ...issueTypes,
                ]}
                onChange={(value) => setIssueData({ ...issueData, issue_type: value })}
            />

            <SelectControl
                label={__('Severity', 'wp-ai-site-generator')}
                value={issueData.severity}
                options={severityLevels}
                onChange={(value) => setIssueData({ ...issueData, severity: value })}
            />

            <TextareaControl
                label={__('Description', 'wp-ai-site-generator')}
                help={__('Describe the issue in detail', 'wp-ai-site-generator')}
                value={issueData.description}
                onChange={(value) => setIssueData({ ...issueData, description: value })}
                rows={4}
            />

            <TextControl
                label={__('Affected Section', 'wp-ai-site-generator')}
                value={issueData.affected_section}
                onChange={(value) => setIssueData({ ...issueData, affected_section: value })}
            />

            <TextareaControl
                label={__('Steps to Reproduce', 'wp-ai-site-generator')}
                value={issueData.steps_to_reproduce}
                onChange={(value) => setIssueData({ ...issueData, steps_to_reproduce: value })}
                rows={3}
            />

            <TextareaControl
                label={__('Expected Behavior', 'wp-ai-site-generator')}
                value={issueData.expected_behavior}
                onChange={(value) => setIssueData({ ...issueData, expected_behavior: value })}
                rows={2}
            />

            <TextareaControl
                label={__('Actual Behavior', 'wp-ai-site-generator')}
                value={issueData.actual_behavior}
                onChange={(value) => setIssueData({ ...issueData, actual_behavior: value })}
                rows={2}
            />

            <div className="actions">
                <Button
                    variant="primary"
                    onClick={handleSubmit}
                    isBusy={isSubmitting}
                    disabled={isSubmitting}
                >
                    {__('Report Issue', 'wp-ai-site-generator')}
                </Button>
            </div>
        </div>
    );
};

/**
 * Feedback History Viewer Component
 */
export const FeedbackHistoryViewer = ({ generationId }) => {
    const [feedbackData, setFeedbackData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        fetchFeedbackHistory();
    }, [generationId]);

    const fetchFeedbackHistory = async () => {
        try {
            const response = await apiFetch({
                path: `/wp-ai-site-generator/v1/feedback/generation/${generationId}`,
            });
            setFeedbackData(response);
        } catch (err) {
            setError(err.message);
        } finally {
            setLoading(false);
        }
    };

    if (loading) {
        return <div className="feedback-history loading">{__('Loading feedback...', 'wp-ai-site-generator')}</div>;
    }

    if (error) {
        return <div className="feedback-history error">{error}</div>;
    }

    if (!feedbackData || !feedbackData.feedback_records.length) {
        return <div className="feedback-history empty">{__('No feedback yet', 'wp-ai-site-generator')}</div>;
    }

    const { statistics, feedback_records } = feedbackData;

    return (
        <div className="feedback-history-viewer">
            <div className="statistics-summary">
                <h3>{__('Feedback Summary', 'wp-ai-site-generator')}</h3>
                <div className="stats-grid">
                    <div className="stat">
                        <span className="label">{__('Total Feedback', 'wp-ai-site-generator')}</span>
                        <span className="value">{statistics.total_feedback}</span>
                    </div>
                    <div className="stat">
                        <span className="label">{__('Average Rating', 'wp-ai-site-generator')}</span>
                        <span className="value">{statistics.average_ratings.overall.toFixed(1)}/5</span>
                    </div>
                    <div className="stat">
                        <span className="label">{__('Quick Feedback', 'wp-ai-site-generator')}</span>
                        <span className="value positive">{statistics.quick_feedback.positive}</span>
                        <span className="value negative">{statistics.quick_feedback.negative}</span>
                    </div>
                    <div className="stat">
                        <span className="label">{__('Issues Reported', 'wp-ai-site-generator')}</span>
                        <span className="value">{statistics.issues_reported}</span>
                    </div>
                </div>
            </div>

            <div className="feedback-records">
                <h3>{__('Feedback History', 'wp-ai-site-generator')}</h3>
                {feedback_records.map((record, index) => (
                    <div key={record.id} className="feedback-record">
                        <div className="record-header">
                            <span className="date">{new Date(record.created_at).toLocaleDateString()}</span>
                            {record.is_anonymous && <span className="anonymous-badge">{__('Anonymous', 'wp-ai-site-generator')}</span>}
                        </div>
                        <div className="ratings">
                            {Object.entries(record.ratings || {}).map(([dimension, rating]) => (
                                <span key={dimension} className="rating-item">
                                    {dimension}: <StarDisplay rating={rating} />
                                </span>
                            ))}
                        </div>
                        {record.text_feedback && (
                            <div className="text-feedback">
                                <p>{record.text_feedback}</p>
                            </div>
                        )}
                        {record.categories && record.categories.length > 0 && (
                            <div className="categories">
                                {record.categories.map(cat => (
                                    <span key={cat} className="category-tag">{cat}</span>
                                ))}
                            </div>
                        )}
                    </div>
                ))}
            </div>
        </div>
    );
};

/**
 * Insights Dashboard Component
 */
export const InsightsDashboard = ({ period = '30days' }) => {
    const [insights, setInsights] = useState(null);
    const [trends, setTrends] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        fetchInsights();
    }, [period]);

    const fetchInsights = async () => {
        setLoading(true);
        try {
            const [insightsResponse, trendsResponse] = await Promise.all([
                apiFetch({
                    path: `/wp-ai-site-generator/v1/feedback/insights?period=${period}`,
                }),
                apiFetch({
                    path: `/wp-ai-site-generator/v1/feedback/trends?period=${period}`,
                }),
            ]);

            setInsights(insightsResponse);
            setTrends(trendsResponse);
        } catch (error) {
            console.error('Failed to fetch insights:', error);
        } finally {
            setLoading(false);
        }
    };

    if (loading) {
        return <div className="insights-dashboard loading">{__('Loading insights...', 'wp-ai-site-generator')}</div>;
    }

    if (!insights) {
        return <div className="insights-dashboard error">{__('Failed to load insights', 'wp-ai-site-generator')}</div>;
    }

    return (
        <div className="insights-dashboard">
            <h2>{__('Feedback Insights', 'wp-ai-site-generator')}</h2>

            <div className="key-metrics">
                <div className="metric-card">
                    <h3>{__('User Satisfaction', 'wp-ai-site-generator')}</h3>
                    <div className="metric-value">{insights.user_satisfaction_index?.toFixed(0)}%</div>
                    <TrendIndicator trend={insights.performance_trends?.satisfaction_trend} />
                </div>

                <div className="metric-card">
                    <h3>{__('Rating Trend', 'wp-ai-site-generator')}</h3>
                    <TrendIndicator trend={insights.performance_trends?.rating_trend} />
                </div>

                <div className="metric-card">
                    <h3>{__('Feedback Volume', 'wp-ai-site-generator')}</h3>
                    <TrendIndicator trend={insights.performance_trends?.feedback_volume_trend} />
                </div>
            </div>

            {insights.problem_areas?.length > 0 && (
                <div className="problem-areas">
                    <h3>{__('Areas Needing Improvement', 'wp-ai-site-generator')}</h3>
                    {insights.problem_areas.map((area, index) => (
                        <div key={index} className="problem-item">
                            <Warning />
                            <div>
                                <strong>{area.area}</strong>
                                <p>{area.recommendation}</p>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {insights.improvement_suggestions?.length > 0 && (
                <div className="suggestions">
                    <h3>{__('Improvement Suggestions', 'wp-ai-site-generator')}</h3>
                    {insights.improvement_suggestions.map((suggestion, index) => (
                        <div key={index} className="suggestion-item">
                            <p>{suggestion.message || suggestion}</p>
                        </div>
                    ))}
                </div>
            )}

            {trends && trends.data && (
                <div className="trend-chart">
                    <h3>{__('Trend Analysis', 'wp-ai-site-generator')}</h3>
                    <SimpleTrendChart data={trends.data} />
                    <div className="trend-summary">
                        <p>{__('Direction:', 'wp-ai-site-generator')} {trends.analysis.direction}</p>
                        <p>{__('Change:', 'wp-ai-site-generator')} {trends.analysis.change}%</p>
                    </div>
                </div>
            )}
        </div>
    );
};

/**
 * Visual Feedback Indicators Component
 */
export const FeedbackIndicator = ({ generationId, rating, quickFeedback }) => {
    const getIndicatorClass = () => {
        if (rating >= 4) return 'excellent';
        if (rating >= 3) return 'good';
        if (rating >= 2) return 'fair';
        return 'poor';
    };

    const getQuickFeedbackRatio = () => {
        const total = quickFeedback.positive + quickFeedback.negative;
        if (total === 0) return null;
        return (quickFeedback.positive / total * 100).toFixed(0);
    };

    const ratio = getQuickFeedbackRatio();

    return (
        <div className={`feedback-indicator ${getIndicatorClass()}`}>
            {rating && (
                <div className="rating-indicator">
                    <StarDisplay rating={rating} />
                    <span className="rating-value">{rating.toFixed(1)}</span>
                </div>
            )}
            {ratio !== null && (
                <div className="quick-feedback-indicator">
                    <span className="ratio">{ratio}% positive</span>
                </div>
            )}
        </div>
    );
};

/**
 * Helper Components
 */
const StarRatingInput = ({ value, onChange }) => {
    const [hovered, setHovered] = useState(0);

    return (
        <div className="star-rating-input">
            {[1, 2, 3, 4, 5].map(star => (
                <button
                    key={star}
                    className={`star ${star <= (hovered || value) ? 'filled' : ''}`}
                    onMouseEnter={() => setHovered(star)}
                    onMouseLeave={() => setHovered(0)}
                    onClick={() => onChange(star)}
                >
                    {star <= (hovered || value) ? '★' : '☆'}
                </button>
            ))}
        </div>
    );
};

const StarDisplay = ({ rating }) => {
    const fullStars = Math.floor(rating);
    const hasHalfStar = rating % 1 >= 0.5;

    return (
        <span className="star-display">
            {[...Array(fullStars)].map((_, i) => <span key={i}>★</span>)}
            {hasHalfStar && <span>☆</span>}
        </span>
    );
};

const TrendIndicator = ({ trend }) => {
    const getTrendIcon = () => {
        switch (trend) {
            case 'improving':
                return '↑';
            case 'declining':
                return '↓';
            case 'stable':
                return '→';
            default:
                return '•';
        }
    };

    return (
        <span className={`trend-indicator ${trend}`}>
            {getTrendIcon()} {trend}
        </span>
    );
};

const SimpleTrendChart = ({ data }) => {
    if (!data || data.length === 0) return null;

    const maxValue = Math.max(...data.map(d => d.value));
    const minValue = Math.min(...data.map(d => d.value));
    const range = maxValue - minValue;

    return (
        <div className="simple-trend-chart">
            <svg viewBox={`0 0 ${data.length * 20} 100`} className="chart-svg">
                <polyline
                    points={data.map((d, i) => {
                        const x = i * 20 + 10;
                        const y = 90 - ((d.value - minValue) / range) * 80;
                        return `${x},${y}`;
                    }).join(' ')}
                    fill="none"
                    stroke="#0073aa"
                    strokeWidth="2"
                />
                {data.map((d, i) => {
                    const x = i * 20 + 10;
                    const y = 90 - ((d.value - minValue) / range) * 80;
                    return (
                        <circle
                            key={i}
                            cx={x}
                            cy={y}
                            r="3"
                            fill="#0073aa"
                            title={`${d.date}: ${d.value}`}
                        />
                    );
                })}
            </svg>
        </div>
    );
};

// Export all components
export default {
    RatingWidget,
    FeedbackModal,
    QuickFeedbackButtons,
    IssueReporter,
    FeedbackHistoryViewer,
    InsightsDashboard,
    FeedbackIndicator,
};