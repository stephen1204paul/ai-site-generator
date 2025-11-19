<?php
/**
 * Chat Database Handler Class.
 *
 * Manages database operations for chat conversations, messages, and contexts.
 *
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/database
 * @since      1.0.0
 */

namespace WPAISiteGenerator\Database;

use WP_Error;

/**
 * Chat Database Handler Class.
 *
 * @since      1.0.0
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/database
 */
class Chat_DB_Handler {

	/**
	 * WordPress database object.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      wpdb    $wpdb    WordPress database object.
	 */
	private $wpdb;

	/**
	 * Conversations table name.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $conversations_table    Conversations table name.
	 */
	private $conversations_table;

	/**
	 * Messages table name.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $messages_table    Messages table name.
	 */
	private $messages_table;

	/**
	 * Context table name.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $context_table    Context table name.
	 */
	private $context_table;

	/**
	 * Initialize the class.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;

		$this->conversations_table = $wpdb->prefix . 'wpaisg_conversations';
		$this->messages_table      = $wpdb->prefix . 'wpaisg_messages';
		$this->context_table       = $wpdb->prefix . 'wpaisg_context';
	}

	/**
	 * Create database tables.
	 *
	 * @since    1.0.0
	 * @return   bool Success status.
	 */
	public function create_tables() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $this->wpdb->get_charset_collate();

		// Conversations table.
		$sql_conversations = "CREATE TABLE {$this->conversations_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			parent_id bigint(20) unsigned DEFAULT NULL,
			title varchar(255) NOT NULL,
			type varchar(50) NOT NULL DEFAULT 'general',
			template_id varchar(100) DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			metadata longtext,
			created_at datetime NOT NULL,
			updated_at datetime DEFAULT NULL,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY parent_id (parent_id),
			KEY type (type),
			KEY status (status),
			KEY created_at (created_at)
		) $charset_collate;";

		// Messages table.
		$sql_messages = "CREATE TABLE {$this->messages_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			conversation_id bigint(20) unsigned NOT NULL,
			role varchar(20) NOT NULL,
			content longtext NOT NULL,
			tokens int(11) DEFAULT 0,
			reference_id bigint(20) unsigned DEFAULT NULL,
			metadata longtext,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY conversation_id (conversation_id),
			KEY role (role),
			KEY reference_id (reference_id),
			KEY created_at (created_at)
		) $charset_collate;";

		// Context table.
		$sql_context = "CREATE TABLE {$this->context_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			conversation_id bigint(20) unsigned NOT NULL,
			summary longtext,
			key_points longtext,
			entities longtext,
			preferences longtext,
			current_topic varchar(255),
			topic_history longtext,
			references longtext,
			compression_level int(11) DEFAULT 0,
			token_count int(11) DEFAULT 0,
			metadata longtext,
			created_at datetime NOT NULL,
			updated_at datetime DEFAULT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY conversation_id (conversation_id),
			KEY current_topic (current_topic),
			KEY compression_level (compression_level)
		) $charset_collate;";

		dbDelta( $sql_conversations );
		dbDelta( $sql_messages );
		dbDelta( $sql_context );

		return true;
	}

	/**
	 * Drop database tables.
	 *
	 * @since    1.0.0
	 * @return   bool Success status.
	 */
	public function drop_tables() {
		$this->wpdb->query( "DROP TABLE IF EXISTS {$this->context_table}" );
		$this->wpdb->query( "DROP TABLE IF EXISTS {$this->messages_table}" );
		$this->wpdb->query( "DROP TABLE IF EXISTS {$this->conversations_table}" );

		return true;
	}

	/**
	 * Insert conversation.
	 *
	 * @since    1.0.0
	 * @param    array $data Conversation data.
	 * @return   int|WP_Error Conversation ID or error.
	 */
	public function insert_conversation( $data ) {
		$defaults = array(
			'user_id'     => get_current_user_id(),
			'parent_id'   => null,
			'title'       => '',
			'type'        => 'general',
			'template_id' => null,
			'status'      => 'active',
			'metadata'    => array(),
			'created_at'  => current_time( 'mysql' ),
			'updated_at'  => null,
		);

		$data = wp_parse_args( $data, $defaults );

		// Serialize metadata.
		if ( is_array( $data['metadata'] ) ) {
			$data['metadata'] = wp_json_encode( $data['metadata'] );
		}

		$result = $this->wpdb->insert(
			$this->conversations_table,
			$data,
			array(
				'%d', // user_id
				'%d', // parent_id
				'%s', // title
				'%s', // type
				'%s', // template_id
				'%s', // status
				'%s', // metadata
				'%s', // created_at
				'%s', // updated_at
			)
		);

		if ( false === $result ) {
			return new WP_Error(
				'db_insert_error',
				__( 'Failed to insert conversation', 'wp-ai-site-generator' )
			);
		}

		return $this->wpdb->insert_id;
	}

	/**
	 * Get conversation by ID.
	 *
	 * @since    1.0.0
	 * @param    int $conversation_id Conversation ID.
	 * @return   array|null Conversation data or null.
	 */
	public function get_conversation( $conversation_id ) {
		$conversation = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->conversations_table} WHERE id = %d",
				$conversation_id
			),
			ARRAY_A
		);

		if ( $conversation && isset( $conversation['metadata'] ) ) {
			$conversation['metadata'] = json_decode( $conversation['metadata'], true );
		}

		return $conversation;
	}

	/**
	 * Get conversations by parent ID.
	 *
	 * @since    1.0.0
	 * @param    int $parent_id Parent conversation ID.
	 * @return   array Conversations.
	 */
	public function get_conversations_by_parent( $parent_id ) {
		$conversations = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->conversations_table} WHERE parent_id = %d ORDER BY created_at DESC",
				$parent_id
			),
			ARRAY_A
		);

		foreach ( $conversations as &$conversation ) {
			if ( isset( $conversation['metadata'] ) ) {
				$conversation['metadata'] = json_decode( $conversation['metadata'], true );
			}
		}

		return $conversations;
	}

	/**
	 * Update conversation.
	 *
	 * @since    1.0.0
	 * @param    int   $conversation_id Conversation ID.
	 * @param    array $data Update data.
	 * @return   bool|WP_Error Success or error.
	 */
	public function update_conversation( $conversation_id, $data ) {
		$data['updated_at'] = current_time( 'mysql' );

		// Serialize metadata if present.
		if ( isset( $data['metadata'] ) && is_array( $data['metadata'] ) ) {
			$data['metadata'] = wp_json_encode( $data['metadata'] );
		}

		$result = $this->wpdb->update(
			$this->conversations_table,
			$data,
			array( 'id' => $conversation_id )
		);

		if ( false === $result ) {
			return new WP_Error(
				'db_update_error',
				__( 'Failed to update conversation', 'wp-ai-site-generator' )
			);
		}

		return true;
	}

	/**
	 * Delete conversation.
	 *
	 * @since    1.0.0
	 * @param    int $conversation_id Conversation ID.
	 * @return   bool|WP_Error Success or error.
	 */
	public function delete_conversation( $conversation_id ) {
		// Delete associated messages.
		$this->wpdb->delete(
			$this->messages_table,
			array( 'conversation_id' => $conversation_id ),
			array( '%d' )
		);

		// Delete associated context.
		$this->wpdb->delete(
			$this->context_table,
			array( 'conversation_id' => $conversation_id ),
			array( '%d' )
		);

		// Delete conversation.
		$result = $this->wpdb->delete(
			$this->conversations_table,
			array( 'id' => $conversation_id ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'db_delete_error',
				__( 'Failed to delete conversation', 'wp-ai-site-generator' )
			);
		}

		return true;
	}

	/**
	 * Insert message.
	 *
	 * @since    1.0.0
	 * @param    array $data Message data.
	 * @return   int|WP_Error Message ID or error.
	 */
	public function insert_message( $data ) {
		$defaults = array(
			'conversation_id' => 0,
			'role'            => 'user',
			'content'         => '',
			'tokens'          => 0,
			'reference_id'    => null,
			'metadata'        => array(),
			'created_at'      => current_time( 'mysql' ),
		);

		$data = wp_parse_args( $data, $defaults );

		// Serialize metadata.
		if ( is_array( $data['metadata'] ) ) {
			$data['metadata'] = wp_json_encode( $data['metadata'] );
		}

		$result = $this->wpdb->insert(
			$this->messages_table,
			$data,
			array(
				'%d', // conversation_id
				'%s', // role
				'%s', // content
				'%d', // tokens
				'%d', // reference_id
				'%s', // metadata
				'%s', // created_at
			)
		);

		if ( false === $result ) {
			return new WP_Error(
				'db_insert_error',
				__( 'Failed to insert message', 'wp-ai-site-generator' )
			);
		}

		return $this->wpdb->insert_id;
	}

	/**
	 * Get messages for conversation.
	 *
	 * @since    1.0.0
	 * @param    int   $conversation_id Conversation ID.
	 * @param    array $args Query arguments.
	 * @return   array Messages.
	 */
	public function get_messages( $conversation_id, $args = array() ) {
		$defaults = array(
			'limit'  => 100,
			'offset' => 0,
			'order'  => 'ASC',
		);

		$args = wp_parse_args( $args, $defaults );

		$query = $this->wpdb->prepare(
			"SELECT * FROM {$this->messages_table}
			WHERE conversation_id = %d
			ORDER BY created_at {$args['order']}
			LIMIT %d OFFSET %d",
			$conversation_id,
			$args['limit'],
			$args['offset']
		);

		$messages = $this->wpdb->get_results( $query, ARRAY_A );

		foreach ( $messages as &$message ) {
			if ( isset( $message['metadata'] ) ) {
				$message['metadata'] = json_decode( $message['metadata'], true );
			}
		}

		return $messages;
	}

	/**
	 * Get message by ID.
	 *
	 * @since    1.0.0
	 * @param    int $message_id Message ID.
	 * @return   array|null Message data or null.
	 */
	public function get_message( $message_id ) {
		$message = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->messages_table} WHERE id = %d",
				$message_id
			),
			ARRAY_A
		);

		if ( $message && isset( $message['metadata'] ) ) {
			$message['metadata'] = json_decode( $message['metadata'], true );
		}

		return $message;
	}

	/**
	 * Update message.
	 *
	 * @since    1.0.0
	 * @param    int   $message_id Message ID.
	 * @param    array $data Update data.
	 * @return   bool|WP_Error Success or error.
	 */
	public function update_message( $message_id, $data ) {
		// Serialize metadata if present.
		if ( isset( $data['metadata'] ) && is_array( $data['metadata'] ) ) {
			$data['metadata'] = wp_json_encode( $data['metadata'] );
		}

		$result = $this->wpdb->update(
			$this->messages_table,
			$data,
			array( 'id' => $message_id )
		);

		if ( false === $result ) {
			return new WP_Error(
				'db_update_error',
				__( 'Failed to update message', 'wp-ai-site-generator' )
			);
		}

		return true;
	}

	/**
	 * Delete message.
	 *
	 * @since    1.0.0
	 * @param    int $message_id Message ID.
	 * @return   bool|WP_Error Success or error.
	 */
	public function delete_message( $message_id ) {
		$result = $this->wpdb->delete(
			$this->messages_table,
			array( 'id' => $message_id ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'db_delete_error',
				__( 'Failed to delete message', 'wp-ai-site-generator' )
			);
		}

		return true;
	}

	/**
	 * Save context.
	 *
	 * @since    1.0.0
	 * @param    int   $conversation_id Conversation ID.
	 * @param    array $context Context data.
	 * @return   bool|WP_Error Success or error.
	 */
	public function save_context( $conversation_id, $context ) {
		// Prepare data.
		$data = array(
			'conversation_id'   => $conversation_id,
			'summary'           => $context['summary'] ?? '',
			'current_topic'     => $context['current_topic'] ?? null,
			'compression_level' => $context['compression_level'] ?? 0,
			'token_count'       => $context['token_count'] ?? 0,
			'updated_at'        => current_time( 'mysql' ),
		);

		// Serialize complex fields.
		$complex_fields = array( 'key_points', 'entities', 'preferences', 'topic_history', 'references', 'metadata' );
		foreach ( $complex_fields as $field ) {
			if ( isset( $context[ $field ] ) ) {
				$data[ $field ] = wp_json_encode( $context[ $field ] );
			}
		}

		// Check if context exists.
		$existing = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT id FROM {$this->context_table} WHERE conversation_id = %d",
				$conversation_id
			)
		);

		if ( $existing ) {
			// Update existing context.
			$result = $this->wpdb->update(
				$this->context_table,
				$data,
				array( 'conversation_id' => $conversation_id )
			);
		} else {
			// Insert new context.
			$data['created_at'] = current_time( 'mysql' );
			$result = $this->wpdb->insert( $this->context_table, $data );
		}

		if ( false === $result ) {
			return new WP_Error(
				'db_context_error',
				__( 'Failed to save context', 'wp-ai-site-generator' )
			);
		}

		return true;
	}

	/**
	 * Get context.
	 *
	 * @since    1.0.0
	 * @param    int $conversation_id Conversation ID.
	 * @return   array|null Context data or null.
	 */
	public function get_context( $conversation_id ) {
		$context = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->context_table} WHERE conversation_id = %d",
				$conversation_id
			),
			ARRAY_A
		);

		if ( ! $context ) {
			return null;
		}

		// Deserialize complex fields.
		$complex_fields = array( 'key_points', 'entities', 'preferences', 'topic_history', 'references', 'metadata' );
		foreach ( $complex_fields as $field ) {
			if ( isset( $context[ $field ] ) ) {
				$context[ $field ] = json_decode( $context[ $field ], true );
			}
		}

		return $context;
	}

	/**
	 * Get conversation statistics.
	 *
	 * @since    1.0.0
	 * @param    int $user_id User ID.
	 * @return   array Statistics.
	 */
	public function get_user_statistics( $user_id ) {
		$stats = array();

		// Total conversations.
		$stats['total_conversations'] = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->conversations_table} WHERE user_id = %d",
				$user_id
			)
		);

		// Active conversations.
		$stats['active_conversations'] = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->conversations_table} WHERE user_id = %d AND status = 'active'",
				$user_id
			)
		);

		// Total messages.
		$stats['total_messages'] = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->messages_table} m
				INNER JOIN {$this->conversations_table} c ON m.conversation_id = c.id
				WHERE c.user_id = %d",
				$user_id
			)
		);

		// Messages by role.
		$stats['messages_by_role'] = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT m.role, COUNT(*) as count FROM {$this->messages_table} m
				INNER JOIN {$this->conversations_table} c ON m.conversation_id = c.id
				WHERE c.user_id = %d
				GROUP BY m.role",
				$user_id
			),
			ARRAY_A
		);

		// Conversation types.
		$stats['conversation_types'] = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT type, COUNT(*) as count FROM {$this->conversations_table}
				WHERE user_id = %d
				GROUP BY type",
				$user_id
			),
			ARRAY_A
		);

		// Recent activity.
		$stats['recent_conversations'] = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT id, title, created_at FROM {$this->conversations_table}
				WHERE user_id = %d
				ORDER BY created_at DESC
				LIMIT 5",
				$user_id
			),
			ARRAY_A
		);

		return $stats;
	}

	/**
	 * Search conversations.
	 *
	 * @since    1.0.0
	 * @param    string $search_term Search term.
	 * @param    int    $user_id User ID.
	 * @return   array Search results.
	 */
	public function search_conversations( $search_term, $user_id ) {
		// Search in conversation titles.
		$conversations = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT c.*, COUNT(m.id) as message_count
				FROM {$this->conversations_table} c
				LEFT JOIN {$this->messages_table} m ON c.id = m.conversation_id
				WHERE c.user_id = %d
				AND (c.title LIKE %s OR m.content LIKE %s)
				GROUP BY c.id
				ORDER BY c.created_at DESC",
				$user_id,
				'%' . $this->wpdb->esc_like( $search_term ) . '%',
				'%' . $this->wpdb->esc_like( $search_term ) . '%'
			),
			ARRAY_A
		);

		foreach ( $conversations as &$conversation ) {
			if ( isset( $conversation['metadata'] ) ) {
				$conversation['metadata'] = json_decode( $conversation['metadata'], true );
			}
		}

		return $conversations;
	}

	/**
	 * Clean up old conversations.
	 *
	 * @since    1.0.0
	 * @param    int $days_old Days threshold.
	 * @return   int Number of deleted conversations.
	 */
	public function cleanup_old_conversations( $days_old = 90 ) {
		$threshold_date = date( 'Y-m-d H:i:s', strtotime( "-{$days_old} days" ) );

		// Get old conversation IDs.
		$old_conversation_ids = $this->wpdb->get_col(
			$this->wpdb->prepare(
				"SELECT id FROM {$this->conversations_table}
				WHERE status = 'inactive'
				AND updated_at < %s",
				$threshold_date
			)
		);

		$deleted_count = 0;
		foreach ( $old_conversation_ids as $conversation_id ) {
			if ( $this->delete_conversation( $conversation_id ) ) {
				$deleted_count++;
			}
		}

		return $deleted_count;
	}
}