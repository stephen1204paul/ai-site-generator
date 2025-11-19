<?php
/**
 * Template Marketplace Manager
 *
 * @package WP_AI_Site_Generator
 * @since 1.0.0
 */

namespace WP_AI_Site_Generator\Includes;

/**
 * Class Template_Marketplace
 *
 * Manages the template marketplace functionality including browsing, searching,
 * filtering, submission, moderation, ratings, and licensing.
 */
class Template_Marketplace {

    /**
     * Database handler instance
     *
     * @var Template_DB_Handler
     */
    private $db_handler;

    /**
     * Template curator instance
     *
     * @var Template_Curator
     */
    private $curator;

    /**
     * Cache group for marketplace data
     *
     * @var string
     */
    private $cache_group = 'wp_ai_marketplace';

    /**
     * Cache expiration time (1 hour)
     *
     * @var int
     */
    private $cache_expiration = 3600;

    /**
     * Constructor
     */
    public function __construct() {
        $this->init();
    }

    /**
     * Initialize the marketplace
     */
    private function init() {
        // Initialize database handler
        if ( class_exists( '\WP_AI_Site_Generator\Database\Template_DB_Handler' ) ) {
            $this->db_handler = new \WP_AI_Site_Generator\Database\Template_DB_Handler();
        }

        // Initialize curator
        if ( class_exists( '\WP_AI_Site_Generator\Includes\Template_Curator' ) ) {
            $this->curator = new \WP_AI_Site_Generator\Includes\Template_Curator();
        }

        // Register hooks
        $this->register_hooks();
    }

    /**
     * Register WordPress hooks
     */
    private function register_hooks() {
        add_action( 'init', array( $this, 'register_template_post_type' ) );
        add_action( 'init', array( $this, 'register_taxonomies' ) );
        add_action( 'wp_ai_daily_cron', array( $this, 'update_trending_templates' ) );
        add_action( 'wp_ai_hourly_cron', array( $this, 'process_moderation_queue' ) );
        add_filter( 'wp_ai_template_submission', array( $this, 'validate_template_submission' ), 10, 2 );
    }

    /**
     * Register custom post type for marketplace templates
     */
    public function register_template_post_type() {
        $args = array(
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => false,
            'show_in_menu'       => false,
            'query_var'          => false,
            'rewrite'            => false,
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => null,
            'supports'           => array( 'title', 'editor', 'author', 'custom-fields' ),
            'labels'             => array(
                'name'          => __( 'Marketplace Templates', 'wp-ai-site-generator' ),
                'singular_name' => __( 'Marketplace Template', 'wp-ai-site-generator' ),
            ),
        );

        register_post_type( 'ai_marketplace_tpl', $args );
    }

    /**
     * Register taxonomies for templates
     */
    public function register_taxonomies() {
        // Categories
        register_taxonomy( 'ai_template_category', 'ai_marketplace_tpl', array(
            'hierarchical'      => true,
            'public'            => false,
            'show_ui'           => false,
            'show_admin_column' => false,
            'query_var'         => false,
            'rewrite'           => false,
            'labels'            => array(
                'name'          => __( 'Template Categories', 'wp-ai-site-generator' ),
                'singular_name' => __( 'Template Category', 'wp-ai-site-generator' ),
            ),
        ) );

        // Tags
        register_taxonomy( 'ai_template_tag', 'ai_marketplace_tpl', array(
            'hierarchical'      => false,
            'public'            => false,
            'show_ui'           => false,
            'show_admin_column' => false,
            'query_var'         => false,
            'rewrite'           => false,
            'labels'            => array(
                'name'          => __( 'Template Tags', 'wp-ai-site-generator' ),
                'singular_name' => __( 'Template Tag', 'wp-ai-site-generator' ),
            ),
        ) );

        // Industries
        register_taxonomy( 'ai_template_industry', 'ai_marketplace_tpl', array(
            'hierarchical'      => true,
            'public'            => false,
            'show_ui'           => false,
            'show_admin_column' => false,
            'query_var'         => false,
            'rewrite'           => false,
            'labels'            => array(
                'name'          => __( 'Industries', 'wp-ai-site-generator' ),
                'singular_name' => __( 'Industry', 'wp-ai-site-generator' ),
            ),
        ) );
    }

    /**
     * Browse public templates with filters
     *
     * @param array $args Query arguments
     * @return array Templates data
     */
    public function browse_templates( $args = array() ) {
        $defaults = array(
            'page'       => 1,
            'per_page'   => 12,
            'category'   => '',
            'industry'   => '',
            'tags'       => array(),
            'search'     => '',
            'sort_by'    => 'popular',
            'license'    => '',
            'rating_min' => 0,
            'author'     => '',
            'status'     => 'approved',
        );

        $args = wp_parse_args( $args, $defaults );

        // Build cache key
        $cache_key = 'browse_' . md5( serialize( $args ) );
        $cached    = wp_cache_get( $cache_key, $this->cache_group );

        if ( false !== $cached ) {
            return $cached;
        }

        // Query templates
        $query_args = array(
            'post_type'      => 'ai_marketplace_tpl',
            'posts_per_page' => $args['per_page'],
            'paged'          => $args['page'],
            'post_status'    => 'publish',
            'meta_query'     => array(),
            'tax_query'      => array(),
        );

        // Add search
        if ( ! empty( $args['search'] ) ) {
            $query_args['s'] = sanitize_text_field( $args['search'] );
        }

        // Add category filter
        if ( ! empty( $args['category'] ) ) {
            $query_args['tax_query'][] = array(
                'taxonomy' => 'ai_template_category',
                'field'    => 'slug',
                'terms'    => sanitize_text_field( $args['category'] ),
            );
        }

        // Add industry filter
        if ( ! empty( $args['industry'] ) ) {
            $query_args['tax_query'][] = array(
                'taxonomy' => 'ai_template_industry',
                'field'    => 'slug',
                'terms'    => sanitize_text_field( $args['industry'] ),
            );
        }

        // Add tags filter
        if ( ! empty( $args['tags'] ) && is_array( $args['tags'] ) ) {
            $query_args['tax_query'][] = array(
                'taxonomy' => 'ai_template_tag',
                'field'    => 'slug',
                'terms'    => array_map( 'sanitize_text_field', $args['tags'] ),
            );
        }

        // Add license filter
        if ( ! empty( $args['license'] ) ) {
            $query_args['meta_query'][] = array(
                'key'     => '_template_license',
                'value'   => sanitize_text_field( $args['license'] ),
                'compare' => '=',
            );
        }

        // Add rating filter
        if ( $args['rating_min'] > 0 ) {
            $query_args['meta_query'][] = array(
                'key'     => '_template_rating',
                'value'   => floatval( $args['rating_min'] ),
                'compare' => '>=',
                'type'    => 'DECIMAL',
            );
        }

        // Add author filter
        if ( ! empty( $args['author'] ) ) {
            $query_args['author'] = intval( $args['author'] );
        }

        // Add status filter
        $query_args['meta_query'][] = array(
            'key'     => '_template_status',
            'value'   => sanitize_text_field( $args['status'] ),
            'compare' => '=',
        );

        // Add sorting
        switch ( $args['sort_by'] ) {
            case 'newest':
                $query_args['orderby'] = 'date';
                $query_args['order']   = 'DESC';
                break;
            case 'popular':
                $query_args['meta_key'] = '_template_downloads';
                $query_args['orderby']  = 'meta_value_num';
                $query_args['order']    = 'DESC';
                break;
            case 'trending':
                $query_args['meta_key'] = '_template_trending_score';
                $query_args['orderby']  = 'meta_value_num';
                $query_args['order']    = 'DESC';
                break;
            case 'rating':
                $query_args['meta_key'] = '_template_rating';
                $query_args['orderby']  = 'meta_value_num';
                $query_args['order']    = 'DESC';
                break;
            case 'name':
                $query_args['orderby'] = 'title';
                $query_args['order']   = 'ASC';
                break;
        }

        $query = new \WP_Query( $query_args );

        $templates = array();
        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $templates[] = $this->format_template_data( get_the_ID() );
            }
            wp_reset_postdata();
        }

        $result = array(
            'templates'    => $templates,
            'total'        => $query->found_posts,
            'total_pages'  => $query->max_num_pages,
            'current_page' => $args['page'],
        );

        // Cache result
        wp_cache_set( $cache_key, $result, $this->cache_group, $this->cache_expiration );

        return $result;
    }

    /**
     * Get featured templates
     *
     * @param int $limit Number of templates to return
     * @return array Featured templates
     */
    public function get_featured_templates( $limit = 6 ) {
        $cache_key = 'featured_templates_' . $limit;
        $cached    = wp_cache_get( $cache_key, $this->cache_group );

        if ( false !== $cached ) {
            return $cached;
        }

        $query_args = array(
            'post_type'      => 'ai_marketplace_tpl',
            'posts_per_page' => $limit,
            'post_status'    => 'publish',
            'meta_query'     => array(
                array(
                    'key'     => '_template_featured',
                    'value'   => '1',
                    'compare' => '=',
                ),
                array(
                    'key'     => '_template_status',
                    'value'   => 'approved',
                    'compare' => '=',
                ),
            ),
            'orderby'        => 'rand',
        );

        $query = new \WP_Query( $query_args );

        $templates = array();
        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $templates[] = $this->format_template_data( get_the_ID() );
            }
            wp_reset_postdata();
        }

        wp_cache_set( $cache_key, $templates, $this->cache_group, $this->cache_expiration );

        return $templates;
    }

    /**
     * Get trending templates
     *
     * @param int $limit Number of templates to return
     * @return array Trending templates
     */
    public function get_trending_templates( $limit = 10 ) {
        $cache_key = 'trending_templates_' . $limit;
        $cached    = wp_cache_get( $cache_key, $this->cache_group );

        if ( false !== $cached ) {
            return $cached;
        }

        $query_args = array(
            'post_type'      => 'ai_marketplace_tpl',
            'posts_per_page' => $limit,
            'post_status'    => 'publish',
            'meta_query'     => array(
                array(
                    'key'     => '_template_status',
                    'value'   => 'approved',
                    'compare' => '=',
                ),
            ),
            'meta_key'       => '_template_trending_score',
            'orderby'        => 'meta_value_num',
            'order'          => 'DESC',
        );

        $query = new \WP_Query( $query_args );

        $templates = array();
        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $templates[] = $this->format_template_data( get_the_ID() );
            }
            wp_reset_postdata();
        }

        wp_cache_set( $cache_key, $templates, $this->cache_group, $this->cache_expiration );

        return $templates;
    }

    /**
     * Submit new template to marketplace
     *
     * @param array $template_data Template data
     * @return int|WP_Error Template ID or error
     */
    public function submit_template( $template_data ) {
        // Validate submission
        $validation = apply_filters( 'wp_ai_template_submission', true, $template_data );
        if ( is_wp_error( $validation ) ) {
            return $validation;
        }

        // Sanitize data
        $title       = sanitize_text_field( $template_data['title'] );
        $description = sanitize_textarea_field( $template_data['description'] );
        $content     = wp_kses_post( $template_data['content'] );
        $category    = isset( $template_data['category'] ) ? sanitize_text_field( $template_data['category'] ) : '';
        $industry    = isset( $template_data['industry'] ) ? sanitize_text_field( $template_data['industry'] ) : '';
        $tags        = isset( $template_data['tags'] ) ? array_map( 'sanitize_text_field', $template_data['tags'] ) : array();
        $license     = isset( $template_data['license'] ) ? sanitize_text_field( $template_data['license'] ) : 'free';
        $visibility  = isset( $template_data['visibility'] ) ? sanitize_text_field( $template_data['visibility'] ) : 'public';

        // Create template post
        $post_args = array(
            'post_title'   => $title,
            'post_content' => $content,
            'post_excerpt' => $description,
            'post_type'    => 'ai_marketplace_tpl',
            'post_status'  => 'pending', // Requires moderation
            'post_author'  => get_current_user_id(),
        );

        $template_id = wp_insert_post( $post_args );

        if ( is_wp_error( $template_id ) ) {
            return $template_id;
        }

        // Set taxonomies
        if ( ! empty( $category ) ) {
            wp_set_object_terms( $template_id, $category, 'ai_template_category' );
        }

        if ( ! empty( $industry ) ) {
            wp_set_object_terms( $template_id, $industry, 'ai_template_industry' );
        }

        if ( ! empty( $tags ) ) {
            wp_set_object_terms( $template_id, $tags, 'ai_template_tag' );
        }

        // Set metadata
        update_post_meta( $template_id, '_template_license', $license );
        update_post_meta( $template_id, '_template_visibility', $visibility );
        update_post_meta( $template_id, '_template_status', 'pending' );
        update_post_meta( $template_id, '_template_version', '1.0.0' );
        update_post_meta( $template_id, '_template_downloads', 0 );
        update_post_meta( $template_id, '_template_rating', 0 );
        update_post_meta( $template_id, '_template_rating_count', 0 );
        update_post_meta( $template_id, '_template_trending_score', 0 );
        update_post_meta( $template_id, '_template_featured', 0 );
        update_post_meta( $template_id, '_template_submission_date', current_time( 'mysql' ) );

        // Store template configuration
        if ( isset( $template_data['configuration'] ) ) {
            update_post_meta( $template_id, '_template_configuration', $template_data['configuration'] );
        }

        // Store template prompts
        if ( isset( $template_data['prompts'] ) ) {
            update_post_meta( $template_id, '_template_prompts', $template_data['prompts'] );
        }

        // Trigger moderation workflow
        do_action( 'wp_ai_template_submitted', $template_id, $template_data );

        return $template_id;
    }

    /**
     * Update existing template
     *
     * @param int   $template_id Template ID
     * @param array $template_data Updated template data
     * @return bool|WP_Error Success or error
     */
    public function update_template( $template_id, $template_data ) {
        // Check permissions
        if ( ! $this->can_edit_template( $template_id ) ) {
            return new \WP_Error( 'permission_denied', __( 'You do not have permission to edit this template.', 'wp-ai-site-generator' ) );
        }

        // Update post data if provided
        $post_args = array( 'ID' => $template_id );

        if ( isset( $template_data['title'] ) ) {
            $post_args['post_title'] = sanitize_text_field( $template_data['title'] );
        }

        if ( isset( $template_data['content'] ) ) {
            $post_args['post_content'] = wp_kses_post( $template_data['content'] );
        }

        if ( isset( $template_data['description'] ) ) {
            $post_args['post_excerpt'] = sanitize_textarea_field( $template_data['description'] );
        }

        $result = wp_update_post( $post_args );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        // Update metadata
        if ( isset( $template_data['license'] ) ) {
            update_post_meta( $template_id, '_template_license', sanitize_text_field( $template_data['license'] ) );
        }

        if ( isset( $template_data['visibility'] ) ) {
            update_post_meta( $template_id, '_template_visibility', sanitize_text_field( $template_data['visibility'] ) );
        }

        if ( isset( $template_data['configuration'] ) ) {
            update_post_meta( $template_id, '_template_configuration', $template_data['configuration'] );
        }

        if ( isset( $template_data['prompts'] ) ) {
            update_post_meta( $template_id, '_template_prompts', $template_data['prompts'] );
        }

        // Update version
        $current_version = get_post_meta( $template_id, '_template_version', true );
        $new_version     = $this->increment_version( $current_version );
        update_post_meta( $template_id, '_template_version', $new_version );

        // Clear cache
        $this->clear_template_cache( $template_id );

        return true;
    }

    /**
     * Install template for current user
     *
     * @param int $template_id Template ID
     * @return array|WP_Error Installation result
     */
    public function install_template( $template_id ) {
        $template = get_post( $template_id );

        if ( ! $template || 'ai_marketplace_tpl' !== $template->post_type ) {
            return new \WP_Error( 'invalid_template', __( 'Invalid template ID.', 'wp-ai-site-generator' ) );
        }

        // Check if already installed
        $user_id = get_current_user_id();
        $installed_templates = get_user_meta( $user_id, 'wp_ai_installed_templates', true );

        if ( ! is_array( $installed_templates ) ) {
            $installed_templates = array();
        }

        if ( in_array( $template_id, $installed_templates, true ) ) {
            return new \WP_Error( 'already_installed', __( 'Template already installed.', 'wp-ai-site-generator' ) );
        }

        // Get template data
        $template_data = $this->format_template_data( $template_id );

        // Copy template to user's library
        $user_template_data = array(
            'title'         => $template_data['title'] . ' (Copy)',
            'content'       => $template_data['content'],
            'description'   => $template_data['description'],
            'configuration' => $template_data['configuration'],
            'prompts'       => $template_data['prompts'],
            'original_id'   => $template_id,
            'installed_at'  => current_time( 'mysql' ),
        );

        // Store in user's templates
        $installed_templates[] = $template_id;
        update_user_meta( $user_id, 'wp_ai_installed_templates', $installed_templates );

        // Store template data
        $user_templates = get_user_meta( $user_id, 'wp_ai_user_templates', true );
        if ( ! is_array( $user_templates ) ) {
            $user_templates = array();
        }
        $user_templates[] = $user_template_data;
        update_user_meta( $user_id, 'wp_ai_user_templates', $user_templates );

        // Increment download count
        $downloads = intval( get_post_meta( $template_id, '_template_downloads', true ) );
        update_post_meta( $template_id, '_template_downloads', $downloads + 1 );

        // Update trending score
        if ( $this->curator ) {
            $this->curator->update_trending_score( $template_id );
        }

        return array(
            'success' => true,
            'message' => __( 'Template installed successfully.', 'wp-ai-site-generator' ),
            'template' => $user_template_data,
        );
    }

    /**
     * Rate template
     *
     * @param int $template_id Template ID
     * @param int $rating Rating value (1-5)
     * @return bool|WP_Error Success or error
     */
    public function rate_template( $template_id, $rating ) {
        $template = get_post( $template_id );

        if ( ! $template || 'ai_marketplace_tpl' !== $template->post_type ) {
            return new \WP_Error( 'invalid_template', __( 'Invalid template ID.', 'wp-ai-site-generator' ) );
        }

        $rating = intval( $rating );
        if ( $rating < 1 || $rating > 5 ) {
            return new \WP_Error( 'invalid_rating', __( 'Rating must be between 1 and 5.', 'wp-ai-site-generator' ) );
        }

        $user_id = get_current_user_id();

        // Check if user already rated
        $user_ratings = get_user_meta( $user_id, 'wp_ai_template_ratings', true );
        if ( ! is_array( $user_ratings ) ) {
            $user_ratings = array();
        }

        $previous_rating = isset( $user_ratings[ $template_id ] ) ? $user_ratings[ $template_id ] : 0;

        // Update user's rating
        $user_ratings[ $template_id ] = $rating;
        update_user_meta( $user_id, 'wp_ai_template_ratings', $user_ratings );

        // Update template rating
        $current_rating = floatval( get_post_meta( $template_id, '_template_rating', true ) );
        $rating_count   = intval( get_post_meta( $template_id, '_template_rating_count', true ) );

        if ( $previous_rating > 0 ) {
            // Update existing rating
            $total_rating = ( $current_rating * $rating_count ) - $previous_rating + $rating;
            $new_rating   = $total_rating / $rating_count;
        } else {
            // New rating
            $total_rating = ( $current_rating * $rating_count ) + $rating;
            $rating_count++;
            $new_rating = $total_rating / $rating_count;
        }

        update_post_meta( $template_id, '_template_rating', $new_rating );
        update_post_meta( $template_id, '_template_rating_count', $rating_count );

        // Update trending score
        if ( $this->curator ) {
            $this->curator->update_trending_score( $template_id );
        }

        return true;
    }

    /**
     * Add review to template
     *
     * @param int    $template_id Template ID
     * @param string $review Review content
     * @param int    $rating Optional rating
     * @return int|WP_Error Review ID or error
     */
    public function add_review( $template_id, $review, $rating = 0 ) {
        $template = get_post( $template_id );

        if ( ! $template || 'ai_marketplace_tpl' !== $template->post_type ) {
            return new \WP_Error( 'invalid_template', __( 'Invalid template ID.', 'wp-ai-site-generator' ) );
        }

        $review = sanitize_textarea_field( $review );
        if ( empty( $review ) ) {
            return new \WP_Error( 'empty_review', __( 'Review content cannot be empty.', 'wp-ai-site-generator' ) );
        }

        // Add rating if provided
        if ( $rating > 0 ) {
            $this->rate_template( $template_id, $rating );
        }

        // Create review as comment
        $comment_data = array(
            'comment_post_ID'      => $template_id,
            'comment_author'       => wp_get_current_user()->display_name,
            'comment_author_email' => wp_get_current_user()->user_email,
            'comment_author_url'   => '',
            'comment_content'      => $review,
            'comment_type'         => 'template_review',
            'comment_parent'       => 0,
            'user_id'              => get_current_user_id(),
            'comment_approved'     => 0, // Requires moderation
        );

        $review_id = wp_insert_comment( $comment_data );

        if ( ! $review_id ) {
            return new \WP_Error( 'review_failed', __( 'Failed to add review.', 'wp-ai-site-generator' ) );
        }

        // Store rating with review
        if ( $rating > 0 ) {
            add_comment_meta( $review_id, '_review_rating', $rating );
        }

        return $review_id;
    }

    /**
     * Get template categories
     *
     * @return array Categories
     */
    public function get_categories() {
        $cache_key = 'template_categories';
        $cached    = wp_cache_get( $cache_key, $this->cache_group );

        if ( false !== $cached ) {
            return $cached;
        }

        $categories = get_terms( array(
            'taxonomy'   => 'ai_template_category',
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ) );

        $formatted = array();
        foreach ( $categories as $category ) {
            $formatted[] = array(
                'id'    => $category->term_id,
                'slug'  => $category->slug,
                'name'  => $category->name,
                'count' => $category->count,
            );
        }

        wp_cache_set( $cache_key, $formatted, $this->cache_group, $this->cache_expiration );

        return $formatted;
    }

    /**
     * Get template industries
     *
     * @return array Industries
     */
    public function get_industries() {
        $cache_key = 'template_industries';
        $cached    = wp_cache_get( $cache_key, $this->cache_group );

        if ( false !== $cached ) {
            return $cached;
        }

        $industries = get_terms( array(
            'taxonomy'   => 'ai_template_industry',
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ) );

        $formatted = array();
        foreach ( $industries as $industry ) {
            $formatted[] = array(
                'id'    => $industry->term_id,
                'slug'  => $industry->slug,
                'name'  => $industry->name,
                'count' => $industry->count,
            );
        }

        wp_cache_set( $cache_key, $formatted, $this->cache_group, $this->cache_expiration );

        return $formatted;
    }

    /**
     * Get user's templates
     *
     * @param int $user_id User ID
     * @return array User's templates
     */
    public function get_user_templates( $user_id = 0 ) {
        if ( 0 === $user_id ) {
            $user_id = get_current_user_id();
        }

        // Get created templates
        $created_args = array(
            'post_type'      => 'ai_marketplace_tpl',
            'posts_per_page' => -1,
            'author'         => $user_id,
            'post_status'    => array( 'publish', 'pending', 'draft' ),
        );

        $created_query = new \WP_Query( $created_args );

        $created_templates = array();
        if ( $created_query->have_posts() ) {
            while ( $created_query->have_posts() ) {
                $created_query->the_post();
                $created_templates[] = $this->format_template_data( get_the_ID() );
            }
            wp_reset_postdata();
        }

        // Get installed templates
        $installed_templates = get_user_meta( $user_id, 'wp_ai_user_templates', true );
        if ( ! is_array( $installed_templates ) ) {
            $installed_templates = array();
        }

        return array(
            'created'   => $created_templates,
            'installed' => $installed_templates,
        );
    }

    /**
     * Format template data for output
     *
     * @param int $template_id Template ID
     * @return array Formatted template data
     */
    private function format_template_data( $template_id ) {
        $template = get_post( $template_id );

        if ( ! $template ) {
            return null;
        }

        // Get categories
        $categories = wp_get_post_terms( $template_id, 'ai_template_category', array( 'fields' => 'names' ) );

        // Get tags
        $tags = wp_get_post_terms( $template_id, 'ai_template_tag', array( 'fields' => 'names' ) );

        // Get industries
        $industries = wp_get_post_terms( $template_id, 'ai_template_industry', array( 'fields' => 'names' ) );

        // Get reviews
        $reviews = get_comments( array(
            'post_id' => $template_id,
            'type'    => 'template_review',
            'status'  => 'approve',
            'number'  => 5,
        ) );

        $formatted_reviews = array();
        foreach ( $reviews as $review ) {
            $formatted_reviews[] = array(
                'id'      => $review->comment_ID,
                'author'  => $review->comment_author,
                'content' => $review->comment_content,
                'date'    => $review->comment_date,
                'rating'  => get_comment_meta( $review->comment_ID, '_review_rating', true ),
            );
        }

        return array(
            'id'            => $template_id,
            'title'         => $template->post_title,
            'description'   => $template->post_excerpt,
            'content'       => $template->post_content,
            'author'        => array(
                'id'   => $template->post_author,
                'name' => get_the_author_meta( 'display_name', $template->post_author ),
            ),
            'categories'    => $categories,
            'tags'          => $tags,
            'industries'    => $industries,
            'license'       => get_post_meta( $template_id, '_template_license', true ),
            'version'       => get_post_meta( $template_id, '_template_version', true ),
            'downloads'     => intval( get_post_meta( $template_id, '_template_downloads', true ) ),
            'rating'        => floatval( get_post_meta( $template_id, '_template_rating', true ) ),
            'rating_count'  => intval( get_post_meta( $template_id, '_template_rating_count', true ) ),
            'featured'      => (bool) get_post_meta( $template_id, '_template_featured', true ),
            'trending_score'=> floatval( get_post_meta( $template_id, '_template_trending_score', true ) ),
            'status'        => get_post_meta( $template_id, '_template_status', true ),
            'visibility'    => get_post_meta( $template_id, '_template_visibility', true ),
            'configuration' => get_post_meta( $template_id, '_template_configuration', true ),
            'prompts'       => get_post_meta( $template_id, '_template_prompts', true ),
            'created_at'    => $template->post_date,
            'updated_at'    => $template->post_modified,
            'reviews'       => $formatted_reviews,
        );
    }

    /**
     * Check if user can edit template
     *
     * @param int $template_id Template ID
     * @return bool
     */
    private function can_edit_template( $template_id ) {
        $template = get_post( $template_id );

        if ( ! $template ) {
            return false;
        }

        // Check if user is author
        if ( get_current_user_id() === intval( $template->post_author ) ) {
            return true;
        }

        // Check for admin capabilities
        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }

        return false;
    }

    /**
     * Increment version number
     *
     * @param string $version Current version
     * @return string New version
     */
    private function increment_version( $version ) {
        $parts = explode( '.', $version );

        if ( count( $parts ) !== 3 ) {
            return '1.0.1';
        }

        $parts[2] = intval( $parts[2] ) + 1;

        return implode( '.', $parts );
    }

    /**
     * Clear template cache
     *
     * @param int $template_id Template ID
     */
    private function clear_template_cache( $template_id ) {
        wp_cache_delete( 'template_' . $template_id, $this->cache_group );
        wp_cache_delete( 'featured_templates_6', $this->cache_group );
        wp_cache_delete( 'trending_templates_10', $this->cache_group );
    }

    /**
     * Validate template submission
     *
     * @param bool  $valid Current validation status
     * @param array $template_data Template data
     * @return bool|WP_Error Validation result
     */
    public function validate_template_submission( $valid, $template_data ) {
        if ( ! $valid ) {
            return $valid;
        }

        // Check required fields
        if ( empty( $template_data['title'] ) ) {
            return new \WP_Error( 'missing_title', __( 'Template title is required.', 'wp-ai-site-generator' ) );
        }

        if ( empty( $template_data['content'] ) ) {
            return new \WP_Error( 'missing_content', __( 'Template content is required.', 'wp-ai-site-generator' ) );
        }

        // Check for spam
        if ( $this->is_spam( $template_data ) ) {
            return new \WP_Error( 'spam_detected', __( 'Your submission has been flagged as spam.', 'wp-ai-site-generator' ) );
        }

        // Check for duplicate
        if ( $this->is_duplicate( $template_data ) ) {
            return new \WP_Error( 'duplicate_detected', __( 'A similar template already exists.', 'wp-ai-site-generator' ) );
        }

        return true;
    }

    /**
     * Check if submission is spam
     *
     * @param array $template_data Template data
     * @return bool
     */
    private function is_spam( $template_data ) {
        // Basic spam check - can be enhanced with Akismet or similar
        $spam_keywords = array( 'viagra', 'cialis', 'casino', 'poker' );
        $content       = strtolower( $template_data['title'] . ' ' . $template_data['content'] );

        foreach ( $spam_keywords as $keyword ) {
            if ( strpos( $content, $keyword ) !== false ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if template is duplicate
     *
     * @param array $template_data Template data
     * @return bool
     */
    private function is_duplicate( $template_data ) {
        $similar = get_posts( array(
            'post_type'      => 'ai_marketplace_tpl',
            'posts_per_page' => 1,
            'title'          => $template_data['title'],
            'post_status'    => 'any',
        ) );

        return ! empty( $similar );
    }

    /**
     * Update trending templates
     */
    public function update_trending_templates() {
        if ( $this->curator ) {
            $this->curator->calculate_trending_scores();
        }
    }

    /**
     * Process moderation queue
     */
    public function process_moderation_queue() {
        if ( $this->curator ) {
            $this->curator->moderate_pending_templates();
        }
    }
}