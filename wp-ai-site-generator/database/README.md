# Database Migration System

This directory contains the database migration system for the WordPress AI Site Generator plugin.

## Overview

The migration system provides a robust, versioned approach to database schema management with the following features:

- **Version Tracking**: Each migration is versioned and tracked in the database
- **Rollback Capability**: Migrations can be rolled back to previous versions
- **Idempotent Operations**: Safe to run multiple times without side effects
- **Foreign Key Support**: Proper relationships between tables when supported
- **Performance Optimization**: Includes appropriate indexes for optimal queries
- **Fallback System**: Legacy table creation if migrations fail

## Directory Structure

```
database/
├── class-migration-manager.php     # Core migration management system
├── class-db-handler.php           # Database operations handler
├── migration-cli.php               # WP-CLI commands for migrations
├── migrations/                     # Individual migration files
│   ├── 001-create-sessions-table.php
│   ├── 002-create-messages-table.php
│   ├── 003-create-designs-table.php
│   └── 004-create-generation-history-table.php
└── README.md                       # This file
```

## Database Schema

### Sessions Table (`wp_waisg_sessions`)
Tracks user chat sessions with the AI system.

- **id**: Primary key
- **user_id**: WordPress user ID (foreign key to wp_users)
- **session_name**: Optional session name
- **provider**: AI provider (openai, anthropic, etc.)
- **model**: Specific AI model used
- **status**: Session status (active, completed, archived)
- **metadata**: JSON metadata
- **created_at**: Creation timestamp
- **updated_at**: Last update timestamp

### Messages Table (`wp_waisg_messages`)
Stores conversation history between users and AI.

- **id**: Primary key
- **session_id**: Foreign key to sessions table
- **role**: Message role (user, assistant, system)
- **content**: Message content
- **tokens_used**: Number of tokens consumed
- **metadata**: JSON metadata
- **created_at**: Creation timestamp

Features:
- Cascade delete when session is removed
- Fulltext index on content for searching (when supported)

### Designs Table (`wp_waisg_designs`)
Stores generated website designs and layouts.

- **id**: Primary key
- **session_id**: Foreign key to sessions table
- **page_id**: Optional WordPress page ID
- **design_type**: Type of design
- **design_data**: JSON design configuration
- **blocks_data**: Serialized block data
- **version**: Design version number
- **is_active**: Active flag
- **created_at**: Creation timestamp

Features:
- Composite indexes for performance
- Version tracking for design iterations
- Soft delete via is_active flag

### Generation History Table (`wp_waisg_generation_history`)
Tracks regeneration history for sections and blocks.

- **id**: Primary key
- **design_id**: Foreign key to designs table
- **section_id**: Section identifier
- **prompt**: Generation prompt
- **response**: AI response
- **blocks_generated**: Generated block data
- **tokens_used**: Token consumption
- **created_at**: Creation timestamp

Features:
- Cascade delete with parent design
- Fulltext search on prompts
- Section-level tracking

## Usage

### During Plugin Activation

The migration system is automatically invoked during plugin activation:

```php
// In class-activator.php
$migration_manager = new \WPAISiteGenerator\Database\Migration_Manager();
$result = $migration_manager->run();
```

### Manual Migration Management

#### Via WP-CLI

```bash
# Check migration status
wp waisg status

# Run pending migrations
wp waisg migrate

# Rollback last migration
wp waisg rollback

# Rollback to specific version
wp waisg rollback 002

# View migration history
wp waisg history

# Reset all tables (DANGEROUS!)
wp waisg reset --force
```

#### Programmatically

```php
use WPAISiteGenerator\Database\Migration_Manager;

$manager = new Migration_Manager();

// Check if migrations are needed
if ( $manager->needs_migration() ) {
    // Run migrations
    $result = $manager->run();

    if ( $result['success'] ) {
        // Migrations successful
        echo $result['message'];
    } else {
        // Handle errors
        foreach ( $result['errors'] as $error ) {
            error_log( $error['migration'] . ': ' . $error['error'] );
        }
    }
}

// Get migration status
$status = $manager->get_status();
echo "Current version: " . $status['current_version'];
echo "Pending migrations: " . $status['pending_migrations'];
```

## Creating New Migrations

To add a new migration:

1. Create a new file in `migrations/` with the naming pattern: `XXX-description.php`
   - XXX is a three-digit version number (e.g., 005, 006)
   - description should be kebab-case (e.g., add-analytics-table)

2. Create a migration class following this template:

```php
<?php
namespace WPAISiteGenerator\Database\Migrations;

class Migration_XXX {
    private $wpdb;
    private $table_name;

    public function __construct( $wpdb ) {
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'waisg_your_table';
    }

    public function up() {
        // Create/modify tables
        $charset_collate = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            // ... columns ...
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        return true; // Return false on failure
    }

    public function down() {
        // Reverse the migration
        $sql = "DROP TABLE IF EXISTS {$this->table_name}";
        $this->wpdb->query( $sql );

        return true; // Return false on failure
    }
}
```

## Best Practices

1. **Always Test Migrations**: Test both `up()` and `down()` methods
2. **Use Transactions**: Wrap operations in transactions when possible
3. **Handle Edge Cases**: Check for table/column existence before operations
4. **Suppress Errors Gracefully**: Use `$wpdb->suppress_errors()` for optional features
5. **Document Changes**: Add clear comments explaining the migration purpose
6. **Version Carefully**: Never reuse or modify existing migration version numbers
7. **Foreign Keys**: Only add if InnoDB engine is available
8. **Indexes**: Add appropriate indexes but check for existence first

## Troubleshooting

### Migration Failures

If migrations fail during activation:
1. The system will fall back to legacy table creation
2. Check WordPress debug log for specific errors
3. Manually run migrations via WP-CLI to see detailed output

### Foreign Key Issues

Foreign keys are only added when:
- Table engine is InnoDB
- Referenced table exists
- No conflicting constraints exist

The system gracefully handles environments without foreign key support.

### Performance Considerations

- Indexes are added conditionally based on database capabilities
- Fulltext indexes require MyISAM or InnoDB 5.6+
- Composite indexes improve query performance for common operations

## Security

- All table names use WordPress prefix
- Prepared statements prevent SQL injection
- Proper capability checks in CLI commands
- Safe rollback mechanisms with confirmations