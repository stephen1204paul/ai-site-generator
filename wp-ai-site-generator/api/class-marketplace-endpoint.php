<?php
/**
 * Template Marketplace REST API Endpoints
 *
 * @package WP_AI_Site_Generator
 * @since 1.0.0
 */

namespace WP_AI_Site_Generator\API;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Class Marketplace_Endpoint
 *
 * Handles REST API endpoints for the template marketplace.
 */
class Marketplace_Endpoint extends WP_REST_Controller {

    /**
     * Namespace
     *
     * @var string
     */
    protected $namespace = 'wp-ai-site-generator/v1';

    /**
     * Rest base
     *
     * @var string
     */
    protected $rest_base = 'marketplace';

    /**
     * Template marketplace instance
     *
     * @var \WP_AI_Site_Generator\Includes\Template_Marketplace
     */
    private $marketplace;

    /**
     * Database handler instance
     *
     * @var \WP_AI_Site_Generator\Database\Template_DB_Handler
     */
    private $db_handler;

    /**
     * Constructor
     */
    public function __construct() {
        // Initialize marketplace
        if ( class_exists( '\WP_AI_Site_Generator\Includes\Template_Marketplace' ) ) {
            $this->marketplace = new \WP_AI_Site_Generator\Includes\Template_Marketplace();
        }

        // Initialize database handler
        if ( class_exists( '\WP_AI_Site_Generator\Database\Template_DB_Handler' ) ) {
            $this->db_handler = new \WP_AI_Site_Generator\Database\Template_DB_Handler();
        }
    }

    /**
     * Register routes
     */
    public function register_routes() {
        // Browse templates
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/templates',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_templates' ),
                    'permission_callback' => array( $this, 'get_templates_permissions_check' ),
                    'args'                => $this->get_collection_params(),
                ),
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'create_template' ),
                    'permission_callback' => array( $this, 'create_template_permissions_check' ),
                    'args'                => $this->get_create_template_params(),
                ),
            )
        );

        // Single template operations
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/templates/(?P<id>[\d]+)',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_template' ),
                    'permission_callback' => array( $this, 'get_template_permissions_check' ),
                    'args'                => array(
                        'id' => array(
                            'description'       => __( 'Template ID', 'wp-ai-site-generator' ),
                            'type'              => 'integer',
                            'required'          => true,
                            'validate_callback' => function( $param ) {
                                return is_numeric( $param );
                            },
                        ),
                    ),
                ),
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array( $this, 'update_template' ),
                    'permission_callback' => array( $this, 'update_template_permissions_check' ),
                    'args'                => $this->get_update_template_params(),
                ),
                array(
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => array( $this, 'delete_template' ),
                    'permission_callback' => array( $this, 'delete_template_permissions_check' ),
                ),
            )
        );

        // Install template
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/templates/(?P<id>[\d]+)/install',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'install_template' ),
                'permission_callback' => array( $this, 'install_template_permissions_check' ),
                'args'                => array(
                    'id' => array(
                        'description'       => __( 'Template ID', 'wp-ai-site-generator' ),
                        'type'              => 'integer',
                        'required'          => true,
                        'validate_callback' => function( $param ) {
                            return is_numeric( $param );
                        },
                    ),
                ),
            )
        );

        // Rate template
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/templates/(?P<id>[\d]+)/rate',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'rate_template' ),
                'permission_callback' => array( $this, 'rate_template_permissions_check' ),
                'args'                => array(
                    'id' => array(
                        'description'       => __( 'Template ID', 'wp-ai-site-generator' ),
                        'type'              => 'integer',
                        'required'          => true,
                        'validate_callback' => function( $param ) {
                            return is_numeric( $param );
                        },
                    ),
                    'rating' => array(
                        'description'       => __( 'Rating value (1-5)', 'wp-ai-site-generator' ),
                        'type'              => 'integer',
                        'required'          => true,
                        'minimum'           => 1,
                        'maximum'           => 5,
                        'validate_callback' => function( $param ) {
                            return is_numeric( $param ) && $param >= 1 && $param <= 5;
                        },
                    ),
                ),
            )
        );

        // Add review
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/templates/(?P<id>[\d]+)/review',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'add_review' ),
                'permission_callback' => array( $this, 'add_review_permissions_check' ),
                'args'                => array(
                    'id' => array(
                        'description'       => __( 'Template ID', 'wp-ai-site-generator' ),
                        'type'              => 'integer',
                        'required'          => true,
                        'validate_callback' => function( $param ) {
                            return is_numeric( $param );
                        },
                    ),
                    'content' => array(
                        'description'       => __( 'Review content', 'wp-ai-site-generator' ),
                        'type'              => 'string',
                        'required'          => true,
                        'validate_callback' => function( $param ) {
                            return ! empty( $param );
                        },
                    ),
                    'rating' => array(
                        'description'       => __( 'Rating value (1-5)', 'wp-ai-site-generator' ),
                        'type'              => 'integer',
                        'minimum'           => 1,
                        'maximum'           => 5,
                        'validate_callback' => function( $param ) {
                            return is_numeric( $param ) && $param >= 1 && $param <= 5;
                        },
                    ),
                    'title' => array(
                        'description' => __( 'Review title', 'wp-ai-site-generator' ),
                        'type'        => 'string',
                    ),
                ),
            )
        );

        // Get reviews
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/templates/(?P<id>[\d]+)/reviews',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_reviews' ),
                'permission_callback' => array( $this, 'get_reviews_permissions_check' ),
                'args'                => array(
                    'id' => array(
                        'description'       => __( 'Template ID', 'wp-ai-site-generator' ),
                        'type'              => 'integer',
                        'required'          => true,
                        'validate_callback' => function( $param ) {
                            return is_numeric( $param );
                        },
                    ),
                    'page' => array(
                        'description' => __( 'Page number', 'wp-ai-site-generator' ),
                        'type'        => 'integer',
                        'default'     => 1,
                    ),
                    'per_page' => array(
                        'description' => __( 'Items per page', 'wp-ai-site-generator' ),
                        'type'        => 'integer',
                        'default'     => 10,
                        'minimum'     => 1,
                        'maximum'     => 100,
                    ),
                ),
            )
        );

        // Favorite/unfavorite template
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/templates/(?P<id>[\d]+)/favorite',
            array(
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'add_favorite' ),
                    'permission_callback' => array( $this, 'favorite_permissions_check' ),
                    'args'                => array(
                        'id' => array(
                            'description'       => __( 'Template ID', 'wp-ai-site-generator' ),
                            'type'              => 'integer',
                            'required'          => true,
                            'validate_callback' => function( $param ) {
                                return is_numeric( $param );
                            },
                        ),
                    ),
                ),
                array(
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => array( $this, 'remove_favorite' ),
                    'permission_callback' => array( $this, 'favorite_permissions_check' ),
                    'args'                => array(
                        'id' => array(
                            'description'       => __( 'Template ID', 'wp-ai-site-generator' ),
                            'type'              => 'integer',
                            'required'          => true,
                            'validate_callback' => function( $param ) {
                                return is_numeric( $param );
                            },
                        ),
                    ),
                ),
            )
        );

        // Get categories
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/categories',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_categories' ),
                'permission_callback' => array( $this, 'get_categories_permissions_check' ),
            )
        );

        // Get industries
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/industries',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_industries' ),
                'permission_callback' => array( $this, 'get_industries_permissions_check' ),
            )
        );

        // Get trending templates
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/trending',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_trending' ),
                'permission_callback' => array( $this, 'get_trending_permissions_check' ),
                'args'                => array(
                    'limit' => array(
                        'description' => __( 'Number of templates to return', 'wp-ai-site-generator' ),
                        'type'        => 'integer',
                        'default'     => 10,
                        'minimum'     => 1,
                        'maximum'     => 50,
                    ),
                ),
            )
        );

        // Get featured templates
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/featured',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_featured' ),
                'permission_callback' => array( $this, 'get_featured_permissions_check' ),
                'args'                => array(
                    'limit' => array(
                        'description' => __( 'Number of templates to return', 'wp-ai-site-generator' ),
                        'type'        => 'integer',
                        'default'     => 6,
                        'minimum'     => 1,
                        'maximum'     => 20,
                    ),
                ),
            )
        );

        // Get user templates
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/my-templates',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_user_templates' ),
                'permission_callback' => array( $this, 'get_user_templates_permissions_check' ),
            )
        );

        // Get user favorites
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/favorites',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_favorites' ),
                'permission_callback' => array( $this, 'get_favorites_permissions_check' ),
            )
        );

        // Get bundles
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/bundles',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_bundles' ),
                'permission_callback' => array( $this, 'get_bundles_permissions_check' ),
                'args'                => array(
                    'featured' => array(
                        'description' => __( 'Filter by featured status', 'wp-ai-site-generator' ),
                        'type'        => 'boolean',
                    ),
                    'page' => array(
                        'description' => __( 'Page number', 'wp-ai-site-generator' ),
                        'type'        => 'integer',
                        'default'     => 1,
                    ),
                    'per_page' => array(
                        'description' => __( 'Items per page', 'wp-ai-site-generator' ),
                        'type'        => 'integer',
                        'default'     => 10,
                    ),
                ),
            )
        );

        // Get template statistics
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/templates/(?P<id>[\d]+)/stats',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_template_stats' ),
                'permission_callback' => array( $this, 'get_template_stats_permissions_check' ),
                'args'                => array(
                    'id' => array(
                        'description'       => __( 'Template ID', 'wp-ai-site-generator' ),
                        'type'              => 'integer',
                        'required'          => true,
                        'validate_callback' => function( $param ) {
                            return is_numeric( $param );
                        },
                    ),
                    'period' => array(
                        'description' => __( 'Time period', 'wp-ai-site-generator' ),
                        'type'        => 'string',
                        'default'     => 'month',
                        'enum'        => array( 'day', 'week', 'month', 'year' ),
                    ),
                ),
            )
        );

        // Report template
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/templates/(?P<id>[\d]+)/report',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'report_template' ),
                'permission_callback' => array( $this, 'report_template_permissions_check' ),
                'args'                => array(
                    'id' => array(
                        'description'       => __( 'Template ID', 'wp-ai-site-generator' ),
                        'type'              => 'integer',
                        'required'          => true,
                        'validate_callback' => function( $param ) {
                            return is_numeric( $param );
                        },
                    ),
                    'reason' => array(
                        'description' => __( 'Report reason', 'wp-ai-site-generator' ),
                        'type'        => 'string',
                        'required'    => true,
                        'enum'        => array( 'spam', 'inappropriate', 'copyright', 'broken', 'other' ),
                    ),
                    'details' => array(
                        'description' => __( 'Report details', 'wp-ai-site-generator' ),
                        'type'        => 'string',
                    ),
                ),
            )
        );

        // Search templates
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/search',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'search_templates' ),
                'permission_callback' => array( $this, 'search_templates_permissions_check' ),
                'args'                => array(
                    'query' => array(
                        'description' => __( 'Search query', 'wp-ai-site-generator' ),
                        'type'        => 'string',
                        'required'    => true,
                    ),
                    'page' => array(
                        'description' => __( 'Page number', 'wp-ai-site-generator' ),
                        'type'        => 'integer',
                        'default'     => 1,
                    ),
                    'per_page' => array(
                        'description' => __( 'Items per page', 'wp-ai-site-generator' ),
                        'type'        => 'integer',
                        'default'     => 12,
                    ),
                ),
            )
        );
    }

    /**
     * Get templates
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response object.
     */
    public function get_templates( $request ) {
        if ( ! $this->marketplace ) {
            return new WP_Error(
                'marketplace_not_available',
                __( 'Marketplace is not available', 'wp-ai-site-generator' ),
                array( 'status' => 500 )
            );
        }

        $args = array(
            'page'       => $request->get_param( 'page' ),
            'per_page'   => $request->get_param( 'per_page' ),
            'category'   => $request->get_param( 'category' ),
            'industry'   => $request->get_param( 'industry' ),
            'tags'       => $request->get_param( 'tags' ),
            'search'     => $request->get_param( 'search' ),
            'sort_by'    => $request->get_param( 'sort_by' ),
            'license'    => $request->get_param( 'license' ),
            'rating_min' => $request->get_param( 'rating_min' ),
            'author'     => $request->get_param( 'author' ),
        );

        $templates = $this->marketplace->browse_templates( $args );

        return rest_ensure_response( $templates );
    }

    /**
     * Get single template
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response object.
     */
    public function get_template( $request ) {
        if ( ! $this->db_handler ) {
            return new WP_Error(
                'database_not_available',
                __( 'Database handler is not available', 'wp-ai-site-generator' ),
                array( 'status' => 500 )
            );
        }

        $template_id = $request->get_param( 'id' );
        $template    = $this->db_handler->get_template( $template_id );

        if ( ! $template ) {
            return new WP_Error(
                'template_not_found',
                __( 'Template not found', 'wp-ai-site-generator' ),
                array( 'status' => 404 )
            );
        }

        // Track view
        $this->db_handler->update_daily_stats( $template_id, 'views', 1 );

        return rest_ensure_response( $template );
    }

    /**
     * Create new template
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response object.
     */
    public function create_template( $request ) {
        if ( ! $this->marketplace ) {
            return new WP_Error(
                'marketplace_not_available',
                __( 'Marketplace is not available', 'wp-ai-site-generator' ),
                array( 'status' => 500 )
            );
        }

        $template_data = array(
            'title'         => $request->get_param( 'title' ),
            'description'   => $request->get_param( 'description' ),
            'content'       => $request->get_param( 'content' ),
            'category'      => $request->get_param( 'category' ),
            'industry'      => $request->get_param( 'industry' ),
            'tags'          => $request->get_param( 'tags' ),
            'license'       => $request->get_param( 'license' ),
            'visibility'    => $request->get_param( 'visibility' ),
            'configuration' => $request->get_param( 'configuration' ),
            'prompts'       => $request->get_param( 'prompts' ),
            'demo_url'      => $request->get_param( 'demo_url' ),
            'support_url'   => $request->get_param( 'support_url' ),
            'documentation_url' => $request->get_param( 'documentation_url' ),
            'screenshots'   => $request->get_param( 'screenshots' ),
        );

        $template_id = $this->marketplace->submit_template( $template_data );

        if ( is_wp_error( $template_id ) ) {
            return $template_id;
        }

        $response = array(
            'id'      => $template_id,
            'message' => __( 'Template submitted successfully. It will be reviewed before publishing.', 'wp-ai-site-generator' ),
        );

        return rest_ensure_response( $response );
    }

    /**
     * Update template
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response object.
     */
    public function update_template( $request ) {
        if ( ! $this->marketplace ) {
            return new WP_Error(
                'marketplace_not_available',
                __( 'Marketplace is not available', 'wp-ai-site-generator' ),
                array( 'status' => 500 )
            );
        }

        $template_id = $request->get_param( 'id' );
        $template_data = $request->get_params();

        // Remove ID from data
        unset( $template_data['id'] );

        $result = $this->marketplace->update_template( $template_id, $template_data );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $response = array(
            'success' => true,
            'message' => __( 'Template updated successfully.', 'wp-ai-site-generator' ),
        );

        return rest_ensure_response( $response );
    }

    /**
     * Delete template
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response object.
     */
    public function delete_template( $request ) {
        $template_id = $request->get_param( 'id' );

        // Check if template exists
        $template = get_post( $template_id );
        if ( ! $template || 'ai_marketplace_tpl' !== $template->post_type ) {
            return new WP_Error(
                'template_not_found',
                __( 'Template not found', 'wp-ai-site-generator' ),
                array( 'status' => 404 )
            );
        }

        // Check permissions
        if ( get_current_user_id() !== intval( $template->post_author ) && ! current_user_can( 'manage_options' ) ) {
            return new WP_Error(
                'permission_denied',
                __( 'You do not have permission to delete this template.', 'wp-ai-site-generator' ),
                array( 'status' => 403 )
            );
        }

        $result = wp_delete_post( $template_id, true );

        if ( ! $result ) {
            return new WP_Error(
                'delete_failed',
                __( 'Failed to delete template.', 'wp-ai-site-generator' ),
                array( 'status' => 500 )
            );
        }

        $response = array(
            'success' => true,
            'message' => __( 'Template deleted successfully.', 'wp-ai-site-generator' ),
        );

        return rest_ensure_response( $response );
    }

    /**
     * Install template
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response object.
     */
    public function install_template( $request ) {
        if ( ! $this->marketplace ) {
            return new WP_Error(
                'marketplace_not_available',
                __( 'Marketplace is not available', 'wp-ai-site-generator' ),
                array( 'status' => 500 )
            );
        }

        $template_id = $request->get_param( 'id' );
        $result      = $this->marketplace->install_template( $template_id );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        // Track installation
        if ( $this->db_handler ) {
            $this->db_handler->track_install(
                $template_id,
                get_current_user_id(),
                site_url(),
                '1.0.0'
            );
        }

        return rest_ensure_response( $result );
    }

    /**
     * Rate template
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response object.
     */
    public function rate_template( $request ) {
        if ( ! $this->marketplace ) {
            return new WP_Error(
                'marketplace_not_available',
                __( 'Marketplace is not available', 'wp-ai-site-generator' ),
                array( 'status' => 500 )
            );
        }

        $template_id = $request->get_param( 'id' );
        $rating      = $request->get_param( 'rating' );

        $result = $this->marketplace->rate_template( $template_id, $rating );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        // Store in database
        if ( $this->db_handler ) {
            $this->db_handler->store_rating( $template_id, get_current_user_id(), $rating );
        }

        $response = array(
            'success' => true,
            'message' => __( 'Rating submitted successfully.', 'wp-ai-site-generator' ),
        );

        return rest_ensure_response( $response );
    }

    /**
     * Add review
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response object.
     */
    public function add_review( $request ) {
        if ( ! $this->db_handler ) {
            return new WP_Error(
                'database_not_available',
                __( 'Database handler is not available', 'wp-ai-site-generator' ),
                array( 'status' => 500 )
            );
        }

        $template_id = $request->get_param( 'id' );
        $review_data = array(
            'title'   => $request->get_param( 'title' ),
            'content' => $request->get_param( 'content' ),
            'rating'  => $request->get_param( 'rating' ),
        );

        $review_id = $this->db_handler->store_review(
            $template_id,
            get_current_user_id(),
            $review_data
        );

        if ( ! $review_id ) {
            return new WP_Error(
                'review_failed',
                __( 'Failed to add review.', 'wp-ai-site-generator' ),
                array( 'status' => 500 )
            );
        }

        $response = array(
            'id'      => $review_id,
            'message' => __( 'Review submitted successfully. It will be published after moderation.', 'wp-ai-site-generator' ),
        );

        return rest_ensure_response( $response );
    }

    /**
     * Get reviews
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response object.
     */
    public function get_reviews( $request ) {
        if ( ! $this->db_handler ) {
            return new WP_Error(
                'database_not_available',
                __( 'Database handler is not available', 'wp-ai-site-generator' ),
                array( 'status' => 500 )
            );
        }

        $template_id = $request->get_param( 'id' );
        $page        = $request->get_param( 'page' );
        $per_page    = $request->get_param( 'per_page' );

        $args = array(
            'status' => 'approved',
            'limit'  => $per_page,
            'offset' => ( $page - 1 ) * $per_page,
        );

        $reviews = $this->db_handler->get_reviews( $template_id, $args );

        return rest_ensure_response( $reviews );
    }

    /**
     * Add favorite
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response object.
     */
    public function add_favorite( $request ) {
        if ( ! $this->db_handler ) {
            return new WP_Error(
                'database_not_available',
                __( 'Database handler is not available', 'wp-ai-site-generator' ),
                array( 'status' => 500 )
            );
        }

        $template_id = $request->get_param( 'id' );
        $user_id     = get_current_user_id();

        $result = $this->db_handler->add_favorite( $user_id, $template_id );

        if ( ! $result ) {
            return new WP_Error(
                'favorite_failed',
                __( 'Failed to add favorite.', 'wp-ai-site-generator' ),
                array( 'status' => 500 )
            );
        }

        $response = array(
            'success' => true,
            'message' => __( 'Template added to favorites.', 'wp-ai-site-generator' ),
        );

        return rest_ensure_response( $response );
    }

    /**
     * Remove favorite
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response object.
     */
    public function remove_favorite( $request ) {
        if ( ! $this->db_handler ) {
            return new WP_Error(
                'database_not_available',
                __( 'Database handler is not available', 'wp-ai-site-generator' ),
                array( 'status' => 500 )
            );
        }

        $template_id = $request->get_param( 'id' );
        $user_id     = get_current_user_id();

        $result = $this->db_handler->remove_favorite( $user_id, $template_id );

        if ( ! $result ) {
            return new WP_Error(
                'unfavorite_failed',
                __( 'Failed to remove favorite.', 'wp-ai-site-generator' ),
                array( 'status' => 500 )
            );
        }

        $response = array(
            'success' => true,
            'message' => __( 'Template removed from favorites.', 'wp-ai-site-generator' ),
        );

        return rest_ensure_response( $response );
    }

    /**
     * Get categories
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response object.
     */
    public function get_categories( $request ) {
        if ( ! $this->marketplace ) {
            return new WP_Error(
                'marketplace_not_available',
                __( 'Marketplace is not available', 'wp-ai-site-generator' ),
                array( 'status' => 500 )
            );
        }

        $categories = $this->marketplace->get_categories();

        return rest_ensure_response( $categories );
    }

    /**
     * Get industries
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response object.
     */
    public function get_industries( $request ) {
        if ( ! $this->marketplace ) {
            return new WP_Error(
                'marketplace_not_available',
                __( 'Marketplace is not available', 'wp-ai-site-generator' ),
                array( 'status' => 500 )
            );
        }

        $industries = $this->marketplace->get_industries();

        return rest_ensure_response( $industries );
    }

    /**
     * Get trending templates
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response object.
     */
    public function get_trending( $request ) {
        if ( ! $this->marketplace ) {
            return new WP_Error(
                'marketplace_not_available',
                __( 'Marketplace is not available', 'wp-ai-site-generator' ),
                array( 'status' => 500 )
            );
        }

        $limit     = $request->get_param( 'limit' );
        $templates = $this->marketplace->get_trending_templates( $limit );

        return rest_ensure_response( $templates );
    }

    /**
     * Get featured templates
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response object.
     */
    public function get_featured( $request ) {
        if ( ! $this->marketplace ) {
            return new WP_Error(
                'marketplace_not_available',
                __( 'Marketplace is not available', 'wp-ai-site-generator' ),
                array( 'status' => 500 )
            );
        }

        $limit     = $request->get_param( 'limit' );
        $templates = $this->marketplace->get_featured_templates( $limit );

        return rest_ensure_response( $templates );
    }

    /**
     * Get user templates
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response object.
     */
    public function get_user_templates( $request ) {
        if ( ! $this->marketplace ) {
            return new WP_Error(
                'marketplace_not_available',
                __( 'Marketplace is not available', 'wp-ai-site-generator' ),
                array( 'status' => 500 )
            );
        }

        $templates = $this->marketplace->get_user_templates();

        return rest_ensure_response( $templates );
    }

    /**
     * Get user favorites
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response object.
     */
    public function get_favorites( $request ) {
        if ( ! $this->db_handler ) {
            return new WP_Error(
                'database_not_available',
                __( 'Database handler is not available', 'wp-ai-site-generator' ),
                array( 'status' => 500 )
            );
        }

        $user_id   = get_current_user_id();
        $favorites = $this->db_handler->get_user_favorites( $user_id );

        return rest_ensure_response( $favorites );
    }

    /**
     * Get bundles
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response object.
     */
    public function get_bundles( $request ) {
        if ( ! $this->db_handler ) {
            return new WP_Error(
                'database_not_available',
                __( 'Database handler is not available', 'wp-ai-site-generator' ),
                array( 'status' => 500 )
            );
        }

        $page     = $request->get_param( 'page' );
        $per_page = $request->get_param( 'per_page' );
        $featured = $request->get_param( 'featured' );

        $args = array(
            'status'  => 'active',
            'featured'=> $featured,
            'limit'   => $per_page,
            'offset'  => ( $page - 1 ) * $per_page,
        );

        $bundles = $this->db_handler->get_bundles( $args );

        return rest_ensure_response( $bundles );
    }

    /**
     * Get template statistics
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response object.
     */
    public function get_template_stats( $request ) {
        if ( ! $this->db_handler ) {
            return new WP_Error(
                'database_not_available',
                __( 'Database handler is not available', 'wp-ai-site-generator' ),
                array( 'status' => 500 )
            );
        }

        $template_id = $request->get_param( 'id' );
        $period      = $request->get_param( 'period' );

        $stats = $this->db_handler->get_template_stats( $template_id, $period );

        return rest_ensure_response( $stats );
    }

    /**
     * Report template
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response object.
     */
    public function report_template( $request ) {
        if ( ! $this->db_handler ) {
            return new WP_Error(
                'database_not_available',
                __( 'Database handler is not available', 'wp-ai-site-generator' ),
                array( 'status' => 500 )
            );
        }

        $template_id = $request->get_param( 'id' );
        $reason      = $request->get_param( 'reason' );
        $details     = $request->get_param( 'details' );

        $full_reason = $reason;
        if ( $details ) {
            $full_reason .= ': ' . $details;
        }

        $result = $this->db_handler->add_to_moderation(
            $template_id,
            'review_report',
            $full_reason,
            'spam' === $reason ? 10 : 5
        );

        if ( ! $result ) {
            return new WP_Error(
                'report_failed',
                __( 'Failed to report template.', 'wp-ai-site-generator' ),
                array( 'status' => 500 )
            );
        }

        $response = array(
            'success' => true,
            'message' => __( 'Template reported successfully. Our team will review it.', 'wp-ai-site-generator' ),
        );

        return rest_ensure_response( $response );
    }

    /**
     * Search templates
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response object.
     */
    public function search_templates( $request ) {
        if ( ! $this->marketplace ) {
            return new WP_Error(
                'marketplace_not_available',
                __( 'Marketplace is not available', 'wp-ai-site-generator' ),
                array( 'status' => 500 )
            );
        }

        $args = array(
            'search'   => $request->get_param( 'query' ),
            'page'     => $request->get_param( 'page' ),
            'per_page' => $request->get_param( 'per_page' ),
        );

        $templates = $this->marketplace->browse_templates( $args );

        return rest_ensure_response( $templates );
    }

    /**
     * Get collection params
     *
     * @return array Collection parameters.
     */
    public function get_collection_params() {
        return array(
            'page' => array(
                'description'       => __( 'Current page of the collection.', 'wp-ai-site-generator' ),
                'type'              => 'integer',
                'default'           => 1,
                'sanitize_callback' => 'absint',
                'validate_callback' => 'rest_validate_request_arg',
                'minimum'           => 1,
            ),
            'per_page' => array(
                'description'       => __( 'Maximum number of items to be returned in result set.', 'wp-ai-site-generator' ),
                'type'              => 'integer',
                'default'           => 12,
                'minimum'           => 1,
                'maximum'           => 100,
                'sanitize_callback' => 'absint',
                'validate_callback' => 'rest_validate_request_arg',
            ),
            'category' => array(
                'description'       => __( 'Filter by category slug.', 'wp-ai-site-generator' ),
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'industry' => array(
                'description'       => __( 'Filter by industry slug.', 'wp-ai-site-generator' ),
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'tags' => array(
                'description'       => __( 'Filter by tags.', 'wp-ai-site-generator' ),
                'type'              => 'array',
                'items'             => array(
                    'type' => 'string',
                ),
                'sanitize_callback' => function( $tags ) {
                    return array_map( 'sanitize_text_field', $tags );
                },
            ),
            'search' => array(
                'description'       => __( 'Limit results to those matching a string.', 'wp-ai-site-generator' ),
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'sort_by' => array(
                'description'       => __( 'Sort templates by.', 'wp-ai-site-generator' ),
                'type'              => 'string',
                'default'           => 'popular',
                'enum'              => array( 'newest', 'popular', 'trending', 'rating', 'name' ),
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'license' => array(
                'description'       => __( 'Filter by license type.', 'wp-ai-site-generator' ),
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'rating_min' => array(
                'description'       => __( 'Minimum rating filter.', 'wp-ai-site-generator' ),
                'type'              => 'number',
                'default'           => 0,
                'minimum'           => 0,
                'maximum'           => 5,
            ),
            'author' => array(
                'description'       => __( 'Filter by author ID.', 'wp-ai-site-generator' ),
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
            ),
        );
    }

    /**
     * Get create template params
     *
     * @return array Create template parameters.
     */
    public function get_create_template_params() {
        return array(
            'title' => array(
                'description'       => __( 'Template title.', 'wp-ai-site-generator' ),
                'type'              => 'string',
                'required'          => true,
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => function( $param ) {
                    return ! empty( $param );
                },
            ),
            'description' => array(
                'description'       => __( 'Template description.', 'wp-ai-site-generator' ),
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_textarea_field',
            ),
            'content' => array(
                'description'       => __( 'Template content.', 'wp-ai-site-generator' ),
                'type'              => 'string',
                'required'          => true,
                'sanitize_callback' => 'wp_kses_post',
                'validate_callback' => function( $param ) {
                    return ! empty( $param );
                },
            ),
            'category' => array(
                'description'       => __( 'Template category.', 'wp-ai-site-generator' ),
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'industry' => array(
                'description'       => __( 'Template industry.', 'wp-ai-site-generator' ),
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'tags' => array(
                'description'       => __( 'Template tags.', 'wp-ai-site-generator' ),
                'type'              => 'array',
                'items'             => array(
                    'type' => 'string',
                ),
                'sanitize_callback' => function( $tags ) {
                    return array_map( 'sanitize_text_field', $tags );
                },
            ),
            'license' => array(
                'description'       => __( 'Template license.', 'wp-ai-site-generator' ),
                'type'              => 'string',
                'default'           => 'free',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'visibility' => array(
                'description'       => __( 'Template visibility.', 'wp-ai-site-generator' ),
                'type'              => 'string',
                'default'           => 'public',
                'enum'              => array( 'public', 'private' ),
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'configuration' => array(
                'description' => __( 'Template configuration.', 'wp-ai-site-generator' ),
                'type'        => 'object',
            ),
            'prompts' => array(
                'description' => __( 'Template prompts.', 'wp-ai-site-generator' ),
                'type'        => 'object',
            ),
            'demo_url' => array(
                'description'       => __( 'Template demo URL.', 'wp-ai-site-generator' ),
                'type'              => 'string',
                'format'            => 'uri',
                'sanitize_callback' => 'esc_url_raw',
            ),
            'support_url' => array(
                'description'       => __( 'Template support URL.', 'wp-ai-site-generator' ),
                'type'              => 'string',
                'format'            => 'uri',
                'sanitize_callback' => 'esc_url_raw',
            ),
            'documentation_url' => array(
                'description'       => __( 'Template documentation URL.', 'wp-ai-site-generator' ),
                'type'              => 'string',
                'format'            => 'uri',
                'sanitize_callback' => 'esc_url_raw',
            ),
            'screenshots' => array(
                'description' => __( 'Template screenshots.', 'wp-ai-site-generator' ),
                'type'        => 'array',
                'items'       => array(
                    'type' => 'string',
                ),
            ),
        );
    }

    /**
     * Get update template params
     *
     * @return array Update template parameters.
     */
    public function get_update_template_params() {
        $params = $this->get_create_template_params();

        // Make fields optional for update
        foreach ( $params as $key => &$param ) {
            unset( $param['required'] );
        }

        return $params;
    }

    /**
     * Check permissions for getting templates
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error
     */
    public function get_templates_permissions_check( $request ) {
        return true; // Public endpoint
    }

    /**
     * Check permissions for getting single template
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error
     */
    public function get_template_permissions_check( $request ) {
        return true; // Public endpoint
    }

    /**
     * Check permissions for creating template
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error
     */
    public function create_template_permissions_check( $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error(
                'rest_forbidden',
                __( 'You must be logged in to create templates.', 'wp-ai-site-generator' ),
                array( 'status' => 401 )
            );
        }

        return current_user_can( 'edit_posts' );
    }

    /**
     * Check permissions for updating template
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error
     */
    public function update_template_permissions_check( $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error(
                'rest_forbidden',
                __( 'You must be logged in to update templates.', 'wp-ai-site-generator' ),
                array( 'status' => 401 )
            );
        }

        $template_id = $request->get_param( 'id' );
        $template    = get_post( $template_id );

        if ( ! $template ) {
            return true; // Let the callback handle the not found error
        }

        // Check if user is author or admin
        if ( get_current_user_id() === intval( $template->post_author ) || current_user_can( 'manage_options' ) ) {
            return true;
        }

        return new WP_Error(
            'rest_forbidden',
            __( 'You do not have permission to update this template.', 'wp-ai-site-generator' ),
            array( 'status' => 403 )
        );
    }

    /**
     * Check permissions for deleting template
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error
     */
    public function delete_template_permissions_check( $request ) {
        return $this->update_template_permissions_check( $request );
    }

    /**
     * Check permissions for installing template
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error
     */
    public function install_template_permissions_check( $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error(
                'rest_forbidden',
                __( 'You must be logged in to install templates.', 'wp-ai-site-generator' ),
                array( 'status' => 401 )
            );
        }

        return current_user_can( 'edit_posts' );
    }

    /**
     * Check permissions for rating template
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error
     */
    public function rate_template_permissions_check( $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error(
                'rest_forbidden',
                __( 'You must be logged in to rate templates.', 'wp-ai-site-generator' ),
                array( 'status' => 401 )
            );
        }

        return true;
    }

    /**
     * Check permissions for adding review
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error
     */
    public function add_review_permissions_check( $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error(
                'rest_forbidden',
                __( 'You must be logged in to add reviews.', 'wp-ai-site-generator' ),
                array( 'status' => 401 )
            );
        }

        return true;
    }

    /**
     * Check permissions for getting reviews
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error
     */
    public function get_reviews_permissions_check( $request ) {
        return true; // Public endpoint
    }

    /**
     * Check permissions for favorites
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error
     */
    public function favorite_permissions_check( $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error(
                'rest_forbidden',
                __( 'You must be logged in to manage favorites.', 'wp-ai-site-generator' ),
                array( 'status' => 401 )
            );
        }

        return true;
    }

    /**
     * Check permissions for getting categories
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error
     */
    public function get_categories_permissions_check( $request ) {
        return true; // Public endpoint
    }

    /**
     * Check permissions for getting industries
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error
     */
    public function get_industries_permissions_check( $request ) {
        return true; // Public endpoint
    }

    /**
     * Check permissions for getting trending
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error
     */
    public function get_trending_permissions_check( $request ) {
        return true; // Public endpoint
    }

    /**
     * Check permissions for getting featured
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error
     */
    public function get_featured_permissions_check( $request ) {
        return true; // Public endpoint
    }

    /**
     * Check permissions for getting user templates
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error
     */
    public function get_user_templates_permissions_check( $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error(
                'rest_forbidden',
                __( 'You must be logged in to view your templates.', 'wp-ai-site-generator' ),
                array( 'status' => 401 )
            );
        }

        return true;
    }

    /**
     * Check permissions for getting favorites
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error
     */
    public function get_favorites_permissions_check( $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error(
                'rest_forbidden',
                __( 'You must be logged in to view your favorites.', 'wp-ai-site-generator' ),
                array( 'status' => 401 )
            );
        }

        return true;
    }

    /**
     * Check permissions for getting bundles
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error
     */
    public function get_bundles_permissions_check( $request ) {
        return true; // Public endpoint
    }

    /**
     * Check permissions for getting template stats
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error
     */
    public function get_template_stats_permissions_check( $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error(
                'rest_forbidden',
                __( 'You must be logged in to view template statistics.', 'wp-ai-site-generator' ),
                array( 'status' => 401 )
            );
        }

        // Check if user is template author or admin
        $template_id = $request->get_param( 'id' );
        $template    = get_post( $template_id );

        if ( ! $template ) {
            return true; // Let the callback handle the not found error
        }

        if ( get_current_user_id() === intval( $template->post_author ) || current_user_can( 'manage_options' ) ) {
            return true;
        }

        return new WP_Error(
            'rest_forbidden',
            __( 'You do not have permission to view statistics for this template.', 'wp-ai-site-generator' ),
            array( 'status' => 403 )
        );
    }

    /**
     * Check permissions for reporting template
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error
     */
    public function report_template_permissions_check( $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error(
                'rest_forbidden',
                __( 'You must be logged in to report templates.', 'wp-ai-site-generator' ),
                array( 'status' => 401 )
            );
        }

        return true;
    }

    /**
     * Check permissions for searching templates
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error
     */
    public function search_templates_permissions_check( $request ) {
        return true; // Public endpoint
    }
}