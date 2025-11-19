<?php
/**
 * Template Database Handler
 *
 * @package WP_AI_Site_Generator
 * @since 1.0.0
 */

namespace WP_AI_Site_Generator\Database;

/**
 * Class Template_DB_Handler
 *
 * Handles database operations for the template marketplace including
 * storage, tracking, ratings, reviews, and migrations.
 */
class Template_DB_Handler {

    /**
     * Database version
     *
     * @var string
     */
    private $db_version = '1.0.0';

    /**
     * Table names
     *
     * @var array
     */
    private $tables = array();

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;

        // Define table names
        $this->tables = array(
            'templates'         => $wpdb->prefix . 'ai_marketplace_templates',
            'template_meta'     => $wpdb->prefix . 'ai_marketplace_template_meta',
            'template_ratings'  => $wpdb->prefix . 'ai_marketplace_ratings',
            'template_reviews'  => $wpdb->prefix . 'ai_marketplace_reviews',
            'template_installs' => $wpdb->prefix . 'ai_marketplace_installs',
            'template_stats'    => $wpdb->prefix . 'ai_marketplace_stats',
            'template_bundles'  => $wpdb->prefix . 'ai_marketplace_bundles',
            'bundle_items'      => $wpdb->prefix . 'ai_marketplace_bundle_items',
            'user_favorites'    => $wpdb->prefix . 'ai_marketplace_favorites',
            'moderation_queue'  => $wpdb->prefix . 'ai_marketplace_moderation',
        );

        // Initialize
        $this->init();
    }

    /**
     * Initialize database handler
     */
    private function init() {
        add_action( 'wp_ai_activate', array( $this, 'create_tables' ) );
        add_action( 'wp_ai_upgrade', array( $this, 'upgrade_tables' ) );
        add_action( 'wp_ai_uninstall', array( $this, 'drop_tables' ) );
    }

    /**
     * Create database tables
     */
    public function create_tables() {
        global $wpdb;

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        $charset_collate = $wpdb->get_charset_collate();

        // Templates table
        $sql_templates = "CREATE TABLE IF NOT EXISTS {$this->tables['templates']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id bigint(20) UNSIGNED DEFAULT NULL,
            title varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            description text,
            content longtext,
            author_id bigint(20) UNSIGNED NOT NULL,
            category varchar(100),
            industry varchar(100),
            tags text,
            license varchar(50) DEFAULT 'free',
            visibility varchar(20) DEFAULT 'public',
            status varchar(20) DEFAULT 'pending',
            version varchar(20) DEFAULT '1.0.0',
            downloads int(11) DEFAULT 0,
            installs int(11) DEFAULT 0,
            rating decimal(3,2) DEFAULT 0.00,
            rating_count int(11) DEFAULT 0,
            review_count int(11) DEFAULT 0,
            trending_score decimal(10,4) DEFAULT 0.0000,
            featured tinyint(1) DEFAULT 0,
            price decimal(10,2) DEFAULT 0.00,
            currency varchar(3) DEFAULT 'USD',
            configuration longtext,
            prompts longtext,
            dependencies text,
            screenshots text,
            demo_url varchar(255),
            support_url varchar(255),
            documentation_url varchar(255),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            published_at datetime,
            last_reviewed datetime,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY post_id (post_id),
            KEY author_id (author_id),
            KEY category (category),
            KEY industry (industry),
            KEY status (status),
            KEY featured (featured),
            KEY trending_score (trending_score),
            KEY rating (rating),
            KEY downloads (downloads),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Template metadata table
        $sql_template_meta = "CREATE TABLE IF NOT EXISTS {$this->tables['template_meta']} (
            meta_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            template_id bigint(20) UNSIGNED NOT NULL,
            meta_key varchar(255) NOT NULL,
            meta_value longtext,
            PRIMARY KEY (meta_id),
            KEY template_id (template_id),
            KEY meta_key (meta_key)
        ) $charset_collate;";

        // Ratings table
        $sql_ratings = "CREATE TABLE IF NOT EXISTS {$this->tables['template_ratings']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            template_id bigint(20) UNSIGNED NOT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            rating tinyint(1) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY template_user (template_id, user_id),
            KEY template_id (template_id),
            KEY user_id (user_id),
            KEY rating (rating)
        ) $charset_collate;";

        // Reviews table
        $sql_reviews = "CREATE TABLE IF NOT EXISTS {$this->tables['template_reviews']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            template_id bigint(20) UNSIGNED NOT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            rating tinyint(1),
            title varchar(255),
            content text NOT NULL,
            helpful_count int(11) DEFAULT 0,
            status varchar(20) DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            approved_at datetime,
            PRIMARY KEY (id),
            KEY template_id (template_id),
            KEY user_id (user_id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Template installs table
        $sql_installs = "CREATE TABLE IF NOT EXISTS {$this->tables['template_installs']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            template_id bigint(20) UNSIGNED NOT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            site_url varchar(255),
            version varchar(20),
            status varchar(20) DEFAULT 'active',
            activated_at datetime DEFAULT CURRENT_TIMESTAMP,
            deactivated_at datetime,
            last_used datetime,
            use_count int(11) DEFAULT 0,
            PRIMARY KEY (id),
            KEY template_id (template_id),
            KEY user_id (user_id),
            KEY status (status),
            KEY activated_at (activated_at)
        ) $charset_collate;";

        // Template statistics table
        $sql_stats = "CREATE TABLE IF NOT EXISTS {$this->tables['template_stats']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            template_id bigint(20) UNSIGNED NOT NULL,
            date date NOT NULL,
            views int(11) DEFAULT 0,
            downloads int(11) DEFAULT 0,
            installs int(11) DEFAULT 0,
            uninstalls int(11) DEFAULT 0,
            ratings_added int(11) DEFAULT 0,
            reviews_added int(11) DEFAULT 0,
            revenue decimal(10,2) DEFAULT 0.00,
            PRIMARY KEY (id),
            UNIQUE KEY template_date (template_id, date),
            KEY template_id (template_id),
            KEY date (date)
        ) $charset_collate;";

        // Template bundles table
        $sql_bundles = "CREATE TABLE IF NOT EXISTS {$this->tables['template_bundles']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            description text,
            author_id bigint(20) UNSIGNED NOT NULL,
            price decimal(10,2) DEFAULT 0.00,
            discount_price decimal(10,2),
            status varchar(20) DEFAULT 'active',
            featured tinyint(1) DEFAULT 0,
            downloads int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY author_id (author_id),
            KEY status (status),
            KEY featured (featured)
        ) $charset_collate;";

        // Bundle items table
        $sql_bundle_items = "CREATE TABLE IF NOT EXISTS {$this->tables['bundle_items']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            bundle_id bigint(20) UNSIGNED NOT NULL,
            template_id bigint(20) UNSIGNED NOT NULL,
            position int(11) DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY bundle_template (bundle_id, template_id),
            KEY bundle_id (bundle_id),
            KEY template_id (template_id)
        ) $charset_collate;";

        // User favorites table
        $sql_favorites = "CREATE TABLE IF NOT EXISTS {$this->tables['user_favorites']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            template_id bigint(20) UNSIGNED NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_template (user_id, template_id),
            KEY user_id (user_id),
            KEY template_id (template_id)
        ) $charset_collate;";

        // Moderation queue table
        $sql_moderation = "CREATE TABLE IF NOT EXISTS {$this->tables['moderation_queue']} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            template_id bigint(20) UNSIGNED NOT NULL,
            action varchar(50) NOT NULL,
            reason text,
            moderator_id bigint(20) UNSIGNED,
            status varchar(20) DEFAULT 'pending',
            priority int(11) DEFAULT 0,
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            resolved_at datetime,
            PRIMARY KEY (id),
            KEY template_id (template_id),
            KEY status (status),
            KEY priority (priority),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Execute queries
        dbDelta( $sql_templates );
        dbDelta( $sql_template_meta );
        dbDelta( $sql_ratings );
        dbDelta( $sql_reviews );
        dbDelta( $sql_installs );
        dbDelta( $sql_stats );
        dbDelta( $sql_bundles );
        dbDelta( $sql_bundle_items );
        dbDelta( $sql_favorites );
        dbDelta( $sql_moderation );

        // Store database version
        update_option( 'wp_ai_marketplace_db_version', $this->db_version );

        // Insert default categories if they don't exist
        $this->insert_default_data();
    }

    /**
     * Insert default data
     */
    private function insert_default_data() {
        // Default categories
        $categories = array(
            'business'    => __( 'Business', 'wp-ai-site-generator' ),
            'blog'        => __( 'Blog', 'wp-ai-site-generator' ),
            'portfolio'   => __( 'Portfolio', 'wp-ai-site-generator' ),
            'ecommerce'   => __( 'E-Commerce', 'wp-ai-site-generator' ),
            'landing'     => __( 'Landing Page', 'wp-ai-site-generator' ),
            'personal'    => __( 'Personal', 'wp-ai-site-generator' ),
            'nonprofit'   => __( 'Non-Profit', 'wp-ai-site-generator' ),
            'educational' => __( 'Educational', 'wp-ai-site-generator' ),
        );

        foreach ( $categories as $slug => $name ) {
            if ( ! term_exists( $slug, 'ai_template_category' ) ) {
                wp_insert_term( $name, 'ai_template_category', array( 'slug' => $slug ) );
            }
        }

        // Default industries
        $industries = array(
            'technology'   => __( 'Technology', 'wp-ai-site-generator' ),
            'healthcare'   => __( 'Healthcare', 'wp-ai-site-generator' ),
            'finance'      => __( 'Finance', 'wp-ai-site-generator' ),
            'retail'       => __( 'Retail', 'wp-ai-site-generator' ),
            'education'    => __( 'Education', 'wp-ai-site-generator' ),
            'realestate'   => __( 'Real Estate', 'wp-ai-site-generator' ),
            'hospitality'  => __( 'Hospitality', 'wp-ai-site-generator' ),
            'professional' => __( 'Professional Services', 'wp-ai-site-generator' ),
            'creative'     => __( 'Creative & Design', 'wp-ai-site-generator' ),
            'manufacturing'=> __( 'Manufacturing', 'wp-ai-site-generator' ),
        );

        foreach ( $industries as $slug => $name ) {
            if ( ! term_exists( $slug, 'ai_template_industry' ) ) {
                wp_insert_term( $name, 'ai_template_industry', array( 'slug' => $slug ) );
            }
        }
    }

    /**
     * Upgrade database tables
     */
    public function upgrade_tables() {
        $current_version = get_option( 'wp_ai_marketplace_db_version', '0.0.0' );

        if ( version_compare( $current_version, $this->db_version, '<' ) ) {
            $this->create_tables();
        }
    }

    /**
     * Drop database tables
     */
    public function drop_tables() {
        global $wpdb;

        foreach ( $this->tables as $table ) {
            $wpdb->query( "DROP TABLE IF EXISTS $table" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        }

        delete_option( 'wp_ai_marketplace_db_version' );
    }

    /**
     * Store marketplace template
     *
     * @param array $template_data Template data
     * @return int|false Template ID or false on failure
     */
    public function store_template( $template_data ) {
        global $wpdb;

        $data = array(
            'title'          => $template_data['title'],
            'slug'           => $this->generate_unique_slug( $template_data['title'] ),
            'description'    => isset( $template_data['description'] ) ? $template_data['description'] : '',
            'content'        => isset( $template_data['content'] ) ? $template_data['content'] : '',
            'author_id'      => isset( $template_data['author_id'] ) ? $template_data['author_id'] : get_current_user_id(),
            'category'       => isset( $template_data['category'] ) ? $template_data['category'] : '',
            'industry'       => isset( $template_data['industry'] ) ? $template_data['industry'] : '',
            'tags'           => isset( $template_data['tags'] ) ? json_encode( $template_data['tags'] ) : '',
            'license'        => isset( $template_data['license'] ) ? $template_data['license'] : 'free',
            'visibility'     => isset( $template_data['visibility'] ) ? $template_data['visibility'] : 'public',
            'status'         => isset( $template_data['status'] ) ? $template_data['status'] : 'pending',
            'version'        => isset( $template_data['version'] ) ? $template_data['version'] : '1.0.0',
            'price'          => isset( $template_data['price'] ) ? $template_data['price'] : 0,
            'configuration'  => isset( $template_data['configuration'] ) ? json_encode( $template_data['configuration'] ) : '',
            'prompts'        => isset( $template_data['prompts'] ) ? json_encode( $template_data['prompts'] ) : '',
            'dependencies'   => isset( $template_data['dependencies'] ) ? json_encode( $template_data['dependencies'] ) : '',
            'screenshots'    => isset( $template_data['screenshots'] ) ? json_encode( $template_data['screenshots'] ) : '',
            'demo_url'       => isset( $template_data['demo_url'] ) ? $template_data['demo_url'] : '',
            'support_url'    => isset( $template_data['support_url'] ) ? $template_data['support_url'] : '',
            'documentation_url' => isset( $template_data['documentation_url'] ) ? $template_data['documentation_url'] : '',
        );

        $result = $wpdb->insert( $this->tables['templates'], $data );

        if ( false === $result ) {
            return false;
        }

        $template_id = $wpdb->insert_id;

        // Store additional metadata
        if ( isset( $template_data['meta'] ) && is_array( $template_data['meta'] ) ) {
            foreach ( $template_data['meta'] as $key => $value ) {
                $this->add_template_meta( $template_id, $key, $value );
            }
        }

        return $template_id;
    }

    /**
     * Update marketplace template
     *
     * @param int   $template_id Template ID
     * @param array $template_data Updated data
     * @return bool Success or failure
     */
    public function update_template( $template_id, $template_data ) {
        global $wpdb;

        $data = array();

        // Prepare update data
        $allowed_fields = array(
            'title', 'description', 'content', 'category', 'industry',
            'tags', 'license', 'visibility', 'status', 'version',
            'price', 'configuration', 'prompts', 'dependencies',
            'screenshots', 'demo_url', 'support_url', 'documentation_url',
        );

        foreach ( $allowed_fields as $field ) {
            if ( isset( $template_data[ $field ] ) ) {
                $value = $template_data[ $field ];

                // JSON encode arrays
                if ( in_array( $field, array( 'tags', 'configuration', 'prompts', 'dependencies', 'screenshots' ), true ) && is_array( $value ) ) {
                    $value = json_encode( $value );
                }

                $data[ $field ] = $value;
            }
        }

        if ( empty( $data ) ) {
            return false;
        }

        // Update slug if title changed
        if ( isset( $data['title'] ) ) {
            $data['slug'] = $this->generate_unique_slug( $data['title'], $template_id );
        }

        $result = $wpdb->update(
            $this->tables['templates'],
            $data,
            array( 'id' => $template_id )
        );

        return false !== $result;
    }

    /**
     * Get template by ID
     *
     * @param int $template_id Template ID
     * @return object|null Template object or null
     */
    public function get_template( $template_id ) {
        global $wpdb;

        $template = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->tables['templates']} WHERE id = %d",
                $template_id
            )
        );

        if ( $template ) {
            // Decode JSON fields
            $template->tags          = json_decode( $template->tags, true );
            $template->configuration = json_decode( $template->configuration, true );
            $template->prompts       = json_decode( $template->prompts, true );
            $template->dependencies  = json_decode( $template->dependencies, true );
            $template->screenshots   = json_decode( $template->screenshots, true );

            // Get metadata
            $template->meta = $this->get_template_meta( $template_id );
        }

        return $template;
    }

    /**
     * Track template download
     *
     * @param int $template_id Template ID
     * @param int $user_id User ID
     * @return bool Success or failure
     */
    public function track_download( $template_id, $user_id = 0 ) {
        global $wpdb;

        // Update download count
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$this->tables['templates']}
                SET downloads = downloads + 1
                WHERE id = %d",
                $template_id
            )
        );

        // Update daily stats
        $this->update_daily_stats( $template_id, 'downloads', 1 );

        return true;
    }

    /**
     * Track template install
     *
     * @param int    $template_id Template ID
     * @param int    $user_id User ID
     * @param string $site_url Site URL
     * @param string $version Template version
     * @return int|false Install ID or false
     */
    public function track_install( $template_id, $user_id, $site_url = '', $version = '' ) {
        global $wpdb;

        // Check if already installed
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$this->tables['template_installs']}
                WHERE template_id = %d AND user_id = %d AND status = 'active'",
                $template_id,
                $user_id
            )
        );

        if ( $existing ) {
            // Update use count
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$this->tables['template_installs']}
                    SET use_count = use_count + 1, last_used = NOW()
                    WHERE id = %d",
                    $existing
                )
            );
            return $existing;
        }

        // Insert new install record
        $data = array(
            'template_id' => $template_id,
            'user_id'     => $user_id,
            'site_url'    => $site_url,
            'version'     => $version,
            'status'      => 'active',
        );

        $result = $wpdb->insert( $this->tables['template_installs'], $data );

        if ( false === $result ) {
            return false;
        }

        // Update install count
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$this->tables['templates']}
                SET installs = installs + 1
                WHERE id = %d",
                $template_id
            )
        );

        // Update daily stats
        $this->update_daily_stats( $template_id, 'installs', 1 );

        return $wpdb->insert_id;
    }

    /**
     * Store template rating
     *
     * @param int $template_id Template ID
     * @param int $user_id User ID
     * @param int $rating Rating value
     * @return bool Success or failure
     */
    public function store_rating( $template_id, $user_id, $rating ) {
        global $wpdb;

        // Insert or update rating
        $result = $wpdb->replace(
            $this->tables['template_ratings'],
            array(
                'template_id' => $template_id,
                'user_id'     => $user_id,
                'rating'      => $rating,
            )
        );

        if ( false === $result ) {
            return false;
        }

        // Recalculate average rating
        $this->recalculate_rating( $template_id );

        // Update daily stats
        $this->update_daily_stats( $template_id, 'ratings_added', 1 );

        return true;
    }

    /**
     * Store template review
     *
     * @param int    $template_id Template ID
     * @param int    $user_id User ID
     * @param array  $review_data Review data
     * @return int|false Review ID or false
     */
    public function store_review( $template_id, $user_id, $review_data ) {
        global $wpdb;

        $data = array(
            'template_id' => $template_id,
            'user_id'     => $user_id,
            'rating'      => isset( $review_data['rating'] ) ? $review_data['rating'] : null,
            'title'       => isset( $review_data['title'] ) ? $review_data['title'] : '',
            'content'     => $review_data['content'],
            'status'      => isset( $review_data['status'] ) ? $review_data['status'] : 'pending',
        );

        $result = $wpdb->insert( $this->tables['template_reviews'], $data );

        if ( false === $result ) {
            return false;
        }

        $review_id = $wpdb->insert_id;

        // Update review count if approved
        if ( 'approved' === $data['status'] ) {
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$this->tables['templates']}
                    SET review_count = review_count + 1
                    WHERE id = %d",
                    $template_id
                )
            );

            // Update daily stats
            $this->update_daily_stats( $template_id, 'reviews_added', 1 );
        }

        return $review_id;
    }

    /**
     * Get template reviews
     *
     * @param int   $template_id Template ID
     * @param array $args Query arguments
     * @return array Reviews
     */
    public function get_reviews( $template_id, $args = array() ) {
        global $wpdb;

        $defaults = array(
            'status'   => 'approved',
            'limit'    => 10,
            'offset'   => 0,
            'orderby'  => 'created_at',
            'order'    => 'DESC',
        );

        $args = wp_parse_args( $args, $defaults );

        $query = "SELECT r.*, u.display_name as author_name
                 FROM {$this->tables['template_reviews']} r
                 LEFT JOIN {$wpdb->users} u ON r.user_id = u.ID
                 WHERE r.template_id = %d";

        $params = array( $template_id );

        if ( ! empty( $args['status'] ) ) {
            $query .= " AND r.status = %s";
            $params[] = $args['status'];
        }

        $query .= " ORDER BY r.{$args['orderby']} {$args['order']}";
        $query .= " LIMIT %d OFFSET %d";
        $params[] = $args['limit'];
        $params[] = $args['offset'];

        $reviews = $wpdb->get_results(
            $wpdb->prepare( $query, $params ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        );

        return $reviews;
    }

    /**
     * Add user favorite
     *
     * @param int $user_id User ID
     * @param int $template_id Template ID
     * @return bool Success or failure
     */
    public function add_favorite( $user_id, $template_id ) {
        global $wpdb;

        $result = $wpdb->replace(
            $this->tables['user_favorites'],
            array(
                'user_id'     => $user_id,
                'template_id' => $template_id,
            )
        );

        return false !== $result;
    }

    /**
     * Remove user favorite
     *
     * @param int $user_id User ID
     * @param int $template_id Template ID
     * @return bool Success or failure
     */
    public function remove_favorite( $user_id, $template_id ) {
        global $wpdb;

        $result = $wpdb->delete(
            $this->tables['user_favorites'],
            array(
                'user_id'     => $user_id,
                'template_id' => $template_id,
            )
        );

        return false !== $result;
    }

    /**
     * Get user favorites
     *
     * @param int $user_id User ID
     * @return array Favorite template IDs
     */
    public function get_user_favorites( $user_id ) {
        global $wpdb;

        $favorites = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT template_id FROM {$this->tables['user_favorites']}
                WHERE user_id = %d
                ORDER BY created_at DESC",
                $user_id
            )
        );

        return $favorites;
    }

    /**
     * Add template to moderation queue
     *
     * @param int    $template_id Template ID
     * @param string $action Moderation action
     * @param string $reason Reason for moderation
     * @param int    $priority Priority level
     * @return int|false Queue ID or false
     */
    public function add_to_moderation( $template_id, $action, $reason = '', $priority = 0 ) {
        global $wpdb;

        $data = array(
            'template_id' => $template_id,
            'action'      => $action,
            'reason'      => $reason,
            'priority'    => $priority,
            'status'      => 'pending',
        );

        $result = $wpdb->insert( $this->tables['moderation_queue'], $data );

        if ( false === $result ) {
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Get moderation queue items
     *
     * @param array $args Query arguments
     * @return array Queue items
     */
    public function get_moderation_queue( $args = array() ) {
        global $wpdb;

        $defaults = array(
            'status'  => 'pending',
            'limit'   => 20,
            'offset'  => 0,
            'orderby' => 'priority DESC, created_at ASC',
        );

        $args = wp_parse_args( $args, $defaults );

        $query = "SELECT m.*, t.title as template_title
                 FROM {$this->tables['moderation_queue']} m
                 LEFT JOIN {$this->tables['templates']} t ON m.template_id = t.id
                 WHERE 1=1";

        $params = array();

        if ( ! empty( $args['status'] ) ) {
            $query .= " AND m.status = %s";
            $params[] = $args['status'];
        }

        $query .= " ORDER BY {$args['orderby']}";
        $query .= " LIMIT %d OFFSET %d";
        $params[] = $args['limit'];
        $params[] = $args['offset'];

        if ( ! empty( $params ) ) {
            $query = $wpdb->prepare( $query, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        }

        $items = $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        return $items;
    }

    /**
     * Resolve moderation queue item
     *
     * @param int    $queue_id Queue ID
     * @param string $status Resolution status
     * @param string $notes Moderator notes
     * @return bool Success or failure
     */
    public function resolve_moderation( $queue_id, $status, $notes = '' ) {
        global $wpdb;

        $data = array(
            'status'      => $status,
            'notes'       => $notes,
            'moderator_id'=> get_current_user_id(),
            'resolved_at' => current_time( 'mysql' ),
        );

        $result = $wpdb->update(
            $this->tables['moderation_queue'],
            $data,
            array( 'id' => $queue_id )
        );

        return false !== $result;
    }

    /**
     * Get template statistics
     *
     * @param int    $template_id Template ID
     * @param string $period Time period (day, week, month, year)
     * @return array Statistics
     */
    public function get_template_stats( $template_id, $period = 'month' ) {
        global $wpdb;

        $date_format = '%Y-%m-%d';
        $interval    = '30 DAY';

        switch ( $period ) {
            case 'day':
                $interval = '1 DAY';
                break;
            case 'week':
                $interval = '7 DAY';
                break;
            case 'month':
                $interval = '30 DAY';
                break;
            case 'year':
                $interval = '365 DAY';
                $date_format = '%Y-%m';
                break;
        }

        $stats = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    DATE_FORMAT(date, %s) as period,
                    SUM(views) as views,
                    SUM(downloads) as downloads,
                    SUM(installs) as installs,
                    SUM(uninstalls) as uninstalls,
                    SUM(ratings_added) as ratings_added,
                    SUM(reviews_added) as reviews_added,
                    SUM(revenue) as revenue
                FROM {$this->tables['template_stats']}
                WHERE template_id = %d
                    AND date >= DATE_SUB(CURDATE(), INTERVAL {$interval})
                GROUP BY period
                ORDER BY date ASC",
                $date_format,
                $template_id
            )
        );

        return $stats;
    }

    /**
     * Create template bundle
     *
     * @param array $bundle_data Bundle data
     * @param array $template_ids Template IDs to include
     * @return int|false Bundle ID or false
     */
    public function create_bundle( $bundle_data, $template_ids ) {
        global $wpdb;

        // Create bundle
        $data = array(
            'title'          => $bundle_data['title'],
            'slug'           => $this->generate_unique_slug( $bundle_data['title'] ),
            'description'    => isset( $bundle_data['description'] ) ? $bundle_data['description'] : '',
            'author_id'      => isset( $bundle_data['author_id'] ) ? $bundle_data['author_id'] : get_current_user_id(),
            'price'          => isset( $bundle_data['price'] ) ? $bundle_data['price'] : 0,
            'discount_price' => isset( $bundle_data['discount_price'] ) ? $bundle_data['discount_price'] : null,
            'status'         => isset( $bundle_data['status'] ) ? $bundle_data['status'] : 'active',
            'featured'       => isset( $bundle_data['featured'] ) ? $bundle_data['featured'] : 0,
        );

        $result = $wpdb->insert( $this->tables['template_bundles'], $data );

        if ( false === $result ) {
            return false;
        }

        $bundle_id = $wpdb->insert_id;

        // Add templates to bundle
        foreach ( $template_ids as $position => $template_id ) {
            $wpdb->insert(
                $this->tables['bundle_items'],
                array(
                    'bundle_id'   => $bundle_id,
                    'template_id' => $template_id,
                    'position'    => $position,
                )
            );
        }

        return $bundle_id;
    }

    /**
     * Get template bundles
     *
     * @param array $args Query arguments
     * @return array Bundles
     */
    public function get_bundles( $args = array() ) {
        global $wpdb;

        $defaults = array(
            'status'  => 'active',
            'featured'=> null,
            'limit'   => 10,
            'offset'  => 0,
            'orderby' => 'created_at',
            'order'   => 'DESC',
        );

        $args = wp_parse_args( $args, $defaults );

        $query = "SELECT * FROM {$this->tables['template_bundles']} WHERE 1=1";
        $params = array();

        if ( ! empty( $args['status'] ) ) {
            $query .= " AND status = %s";
            $params[] = $args['status'];
        }

        if ( null !== $args['featured'] ) {
            $query .= " AND featured = %d";
            $params[] = $args['featured'];
        }

        $query .= " ORDER BY {$args['orderby']} {$args['order']}";
        $query .= " LIMIT %d OFFSET %d";
        $params[] = $args['limit'];
        $params[] = $args['offset'];

        $bundles = $wpdb->get_results(
            $wpdb->prepare( $query, $params ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        );

        // Get bundle items
        foreach ( $bundles as $bundle ) {
            $bundle->templates = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT template_id FROM {$this->tables['bundle_items']}
                    WHERE bundle_id = %d
                    ORDER BY position ASC",
                    $bundle->id
                )
            );
        }

        return $bundles;
    }

    /**
     * Add template metadata
     *
     * @param int    $template_id Template ID
     * @param string $meta_key Meta key
     * @param mixed  $meta_value Meta value
     * @return int|false Meta ID or false
     */
    private function add_template_meta( $template_id, $meta_key, $meta_value ) {
        global $wpdb;

        $data = array(
            'template_id' => $template_id,
            'meta_key'    => $meta_key,
            'meta_value'  => maybe_serialize( $meta_value ),
        );

        $result = $wpdb->insert( $this->tables['template_meta'], $data );

        if ( false === $result ) {
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Get template metadata
     *
     * @param int    $template_id Template ID
     * @param string $meta_key Optional meta key
     * @return mixed Meta value or array of all meta
     */
    private function get_template_meta( $template_id, $meta_key = '' ) {
        global $wpdb;

        if ( ! empty( $meta_key ) ) {
            $value = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT meta_value FROM {$this->tables['template_meta']}
                    WHERE template_id = %d AND meta_key = %s",
                    $template_id,
                    $meta_key
                )
            );

            return maybe_unserialize( $value );
        }

        $meta = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT meta_key, meta_value FROM {$this->tables['template_meta']}
                WHERE template_id = %d",
                $template_id
            ),
            OBJECT_K
        );

        $result = array();
        foreach ( $meta as $key => $data ) {
            $result[ $key ] = maybe_unserialize( $data->meta_value );
        }

        return $result;
    }

    /**
     * Recalculate template rating
     *
     * @param int $template_id Template ID
     */
    private function recalculate_rating( $template_id ) {
        global $wpdb;

        $stats = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT AVG(rating) as avg_rating, COUNT(*) as count
                FROM {$this->tables['template_ratings']}
                WHERE template_id = %d",
                $template_id
            )
        );

        $wpdb->update(
            $this->tables['templates'],
            array(
                'rating'       => round( $stats->avg_rating, 2 ),
                'rating_count' => $stats->count,
            ),
            array( 'id' => $template_id )
        );
    }

    /**
     * Update daily statistics
     *
     * @param int    $template_id Template ID
     * @param string $stat_type Statistic type
     * @param int    $value Value to add
     */
    private function update_daily_stats( $template_id, $stat_type, $value = 1 ) {
        global $wpdb;

        $today = current_time( 'Y-m-d' );

        // Check if row exists for today
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$this->tables['template_stats']}
                WHERE template_id = %d AND date = %s",
                $template_id,
                $today
            )
        );

        if ( $exists ) {
            // Update existing row
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$this->tables['template_stats']}
                    SET {$stat_type} = {$stat_type} + %d
                    WHERE template_id = %d AND date = %s",
                    $value,
                    $template_id,
                    $today
                )
            );
        } else {
            // Insert new row
            $data = array(
                'template_id' => $template_id,
                'date'        => $today,
                $stat_type    => $value,
            );

            $wpdb->insert( $this->tables['template_stats'], $data );
        }
    }

    /**
     * Generate unique slug
     *
     * @param string $title Title to generate slug from
     * @param int    $exclude_id Template ID to exclude
     * @return string Unique slug
     */
    private function generate_unique_slug( $title, $exclude_id = 0 ) {
        global $wpdb;

        $slug = sanitize_title( $title );
        $original_slug = $slug;
        $counter = 1;

        while ( true ) {
            $query = "SELECT id FROM {$this->tables['templates']} WHERE slug = %s";
            $params = array( $slug );

            if ( $exclude_id > 0 ) {
                $query .= " AND id != %d";
                $params[] = $exclude_id;
            }

            $exists = $wpdb->get_var(
                $wpdb->prepare( $query, $params ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            );

            if ( ! $exists ) {
                break;
            }

            $slug = $original_slug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}