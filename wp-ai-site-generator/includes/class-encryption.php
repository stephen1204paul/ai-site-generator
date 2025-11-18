<?php
/**
 * Encryption handler for sensitive data
 *
 * Provides secure encryption and decryption for API keys and other
 * sensitive data using WordPress salts and OpenSSL.
 *
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/includes
 * @since      1.0.0
 */

namespace WPAISiteGenerator\Includes;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Encryption class
 *
 * Handles encryption and decryption of sensitive data like API keys
 * using WordPress authentication salts and OpenSSL encryption.
 *
 * @since 1.0.0
 */
class Encryption {

	/**
	 * Encryption method.
	 *
	 * @var string
	 */
	private const CIPHER_METHOD = 'AES-256-CBC';

	/**
	 * Key prefix for encrypted data.
	 *
	 * @var string
	 */
	private const ENCRYPTED_PREFIX = 'enc_';

	/**
	 * Key rotation version.
	 *
	 * @var int
	 */
	private $key_version = 1;

	/**
	 * Instance of this class.
	 *
	 * @var Encryption|null
	 */
	private static $instance = null;

	/**
	 * Get the single instance of this class.
	 *
	 * @return Encryption
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor to prevent direct instantiation.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->key_version = get_option( 'waisg_encryption_key_version', 1 );
	}

	/**
	 * Generate encryption key from WordPress salts.
	 *
	 * @since  1.0.0
	 * @param  int $version Key version for rotation support.
	 * @return string
	 */
	private function get_encryption_key( $version = null ) {
		if ( null === $version ) {
			$version = $this->key_version;
		}

		// Combine WordPress salts to create a unique key
		$salt_base = '';

		if ( defined( 'AUTH_KEY' ) ) {
			$salt_base .= AUTH_KEY;
		}
		if ( defined( 'SECURE_AUTH_KEY' ) ) {
			$salt_base .= SECURE_AUTH_KEY;
		}
		if ( defined( 'LOGGED_IN_KEY' ) ) {
			$salt_base .= LOGGED_IN_KEY;
		}
		if ( defined( 'NONCE_KEY' ) ) {
			$salt_base .= NONCE_KEY;
		}

		// Fallback if salts are not defined
		if ( empty( $salt_base ) ) {
			$salt_base = get_site_url() . 'waisg-default-salt-2024';
		}

		// Add version to the salt for key rotation support
		$salt_base .= 'v' . $version;

		// Generate a consistent key from the salt
		return substr( hash( 'sha256', $salt_base ), 0, 32 );
	}

	/**
	 * Encrypt sensitive data.
	 *
	 * @since  1.0.0
	 * @param  string $data Data to encrypt.
	 * @return string|false Encrypted data or false on failure.
	 */
	public function encrypt( $data ) {
		// Don't encrypt empty data
		if ( empty( $data ) ) {
			return $data;
		}

		// Check if already encrypted
		if ( $this->is_encrypted( $data ) ) {
			return $data;
		}

		// Check if OpenSSL is available
		if ( ! extension_loaded( 'openssl' ) ) {
			$this->log_error( 'OpenSSL extension not available for encryption.' );
			return $data; // Return unencrypted if OpenSSL is not available
		}

		$key = $this->get_encryption_key();
		$iv_length = openssl_cipher_iv_length( self::CIPHER_METHOD );
		$iv = openssl_random_pseudo_bytes( $iv_length );

		$encrypted = openssl_encrypt(
			$data,
			self::CIPHER_METHOD,
			$key,
			OPENSSL_RAW_DATA,
			$iv
		);

		if ( false === $encrypted ) {
			$this->log_error( 'Failed to encrypt data.' );
			return false;
		}

		// Combine IV, version, and encrypted data
		$combined = base64_encode( $iv . ':' . $this->key_version . ':' . $encrypted );

		return self::ENCRYPTED_PREFIX . $combined;
	}

	/**
	 * Decrypt sensitive data.
	 *
	 * @since  1.0.0
	 * @param  string $encrypted_data Encrypted data to decrypt.
	 * @return string|false Decrypted data or false on failure.
	 */
	public function decrypt( $encrypted_data ) {
		// Return as-is if not encrypted
		if ( ! $this->is_encrypted( $encrypted_data ) ) {
			return $encrypted_data;
		}

		// Check if OpenSSL is available
		if ( ! extension_loaded( 'openssl' ) ) {
			$this->log_error( 'OpenSSL extension not available for decryption.' );
			return $encrypted_data;
		}

		// Remove prefix
		$encrypted_data = substr( $encrypted_data, strlen( self::ENCRYPTED_PREFIX ) );

		// Decode base64
		$decoded = base64_decode( $encrypted_data );
		if ( false === $decoded ) {
			$this->log_error( 'Failed to decode encrypted data.' );
			return false;
		}

		// Extract components (IV:version:data)
		$parts = explode( ':', $decoded, 3 );
		if ( count( $parts ) !== 3 ) {
			$this->log_error( 'Invalid encrypted data format.' );
			return false;
		}

		list( $iv, $version, $encrypted ) = $parts;

		// Get the appropriate key for this version
		$key = $this->get_encryption_key( (int) $version );

		$decrypted = openssl_decrypt(
			$encrypted,
			self::CIPHER_METHOD,
			$key,
			OPENSSL_RAW_DATA,
			$iv
		);

		if ( false === $decrypted ) {
			$this->log_error( 'Failed to decrypt data.' );
			return false;
		}

		return $decrypted;
	}

	/**
	 * Check if data is encrypted.
	 *
	 * @since  1.0.0
	 * @param  string $data Data to check.
	 * @return bool
	 */
	public function is_encrypted( $data ) {
		if ( ! is_string( $data ) ) {
			return false;
		}
		return 0 === strpos( $data, self::ENCRYPTED_PREFIX );
	}

	/**
	 * Rotate encryption keys.
	 *
	 * Re-encrypts all data with a new key version.
	 * Should be called periodically or when salts change.
	 *
	 * @since  1.0.0
	 * @return bool True on success, false on failure.
	 */
	public function rotate_keys() {
		// Increment key version
		$new_version = $this->key_version + 1;

		// Get all settings that might contain encrypted data
		$settings_to_rotate = array(
			'waisg_provider_settings',
			'waisg_custom_api_keys',
			'waisg_secure_settings',
		);

		$success = true;

		foreach ( $settings_to_rotate as $option_name ) {
			$settings = get_option( $option_name, array() );

			if ( ! empty( $settings ) ) {
				$updated_settings = $this->rotate_array_keys( $settings, $new_version );

				if ( false === $updated_settings ) {
					$success = false;
					continue;
				}

				update_option( $option_name, $updated_settings );
			}
		}

		if ( $success ) {
			// Update key version
			$this->key_version = $new_version;
			update_option( 'waisg_encryption_key_version', $new_version );
			update_option( 'waisg_last_key_rotation', current_time( 'mysql' ) );
		}

		return $success;
	}

	/**
	 * Recursively rotate encryption keys in an array.
	 *
	 * @since  1.0.0
	 * @param  array $data       Data array to process.
	 * @param  int   $new_version New key version.
	 * @return array|false Processed array or false on failure.
	 */
	private function rotate_array_keys( $data, $new_version ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		$rotated = array();

		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) {
				$rotated[ $key ] = $this->rotate_array_keys( $value, $new_version );
			} elseif ( is_string( $value ) && $this->is_encrypted( $value ) ) {
				// Decrypt with old key
				$decrypted = $this->decrypt( $value );

				if ( false === $decrypted ) {
					return false;
				}

				// Store new version temporarily
				$old_version = $this->key_version;
				$this->key_version = $new_version;

				// Re-encrypt with new key
				$rotated[ $key ] = $this->encrypt( $decrypted );

				// Restore old version
				$this->key_version = $old_version;
			} else {
				$rotated[ $key ] = $value;
			}
		}

		return $rotated;
	}

	/**
	 * Get key rotation status.
	 *
	 * @since  1.0.0
	 * @return array
	 */
	public function get_rotation_status() {
		return array(
			'current_version'   => $this->key_version,
			'last_rotation'     => get_option( 'waisg_last_key_rotation', 'Never' ),
			'openssl_available' => extension_loaded( 'openssl' ),
			'encryption_active' => $this->is_encryption_available(),
		);
	}

	/**
	 * Check if encryption is available and working.
	 *
	 * @since  1.0.0
	 * @return bool
	 */
	public function is_encryption_available() {
		if ( ! extension_loaded( 'openssl' ) ) {
			return false;
		}

		// Test encryption/decryption
		$test_string = 'test_encryption_' . wp_rand();
		$encrypted = $this->encrypt( $test_string );

		if ( false === $encrypted || ! $this->is_encrypted( $encrypted ) ) {
			return false;
		}

		$decrypted = $this->decrypt( $encrypted );

		return $decrypted === $test_string;
	}

	/**
	 * Sanitize and encrypt an API key.
	 *
	 * @since  1.0.0
	 * @param  string $api_key API key to sanitize and encrypt.
	 * @return string|false Encrypted API key or false on failure.
	 */
	public function sanitize_and_encrypt_api_key( $api_key ) {
		// Remove whitespace
		$api_key = trim( $api_key );

		// Basic validation
		if ( empty( $api_key ) ) {
			return '';
		}

		// Check for common API key patterns and warn if suspicious
		$suspicious_patterns = array(
			'/^test/i',
			'/^demo/i',
			'/^sample/i',
			'/^your[-_]?api[-_]?key/i',
		);

		foreach ( $suspicious_patterns as $pattern ) {
			if ( preg_match( $pattern, $api_key ) ) {
				$this->log_error( 'Suspicious API key pattern detected: ' . $api_key );
			}
		}

		return $this->encrypt( $api_key );
	}

	/**
	 * Batch encrypt multiple values.
	 *
	 * @since  1.0.0
	 * @param  array $data Array of data to encrypt.
	 * @return array Encrypted data array.
	 */
	public function batch_encrypt( array $data ) {
		$encrypted = array();

		foreach ( $data as $key => $value ) {
			if ( is_string( $value ) && ! empty( $value ) ) {
				$encrypted[ $key ] = $this->encrypt( $value );
			} else {
				$encrypted[ $key ] = $value;
			}
		}

		return $encrypted;
	}

	/**
	 * Batch decrypt multiple values.
	 *
	 * @since  1.0.0
	 * @param  array $data Array of data to decrypt.
	 * @return array Decrypted data array.
	 */
	public function batch_decrypt( array $data ) {
		$decrypted = array();

		foreach ( $data as $key => $value ) {
			if ( is_string( $value ) && $this->is_encrypted( $value ) ) {
				$decrypted[ $key ] = $this->decrypt( $value );
			} else {
				$decrypted[ $key ] = $value;
			}
		}

		return $decrypted;
	}

	/**
	 * Clear all encrypted data.
	 *
	 * Emergency function to remove all encrypted data from the database.
	 *
	 * @since  1.0.0
	 * @return bool
	 */
	public function clear_all_encrypted_data() {
		$settings_to_clear = array(
			'waisg_provider_settings',
			'waisg_custom_api_keys',
			'waisg_secure_settings',
		);

		foreach ( $settings_to_clear as $option_name ) {
			$settings = get_option( $option_name, array() );

			if ( ! empty( $settings ) ) {
				$cleared_settings = $this->clear_encrypted_from_array( $settings );
				update_option( $option_name, $cleared_settings );
			}
		}

		return true;
	}

	/**
	 * Recursively clear encrypted data from an array.
	 *
	 * @since  1.0.0
	 * @param  array $data Data array to process.
	 * @return array Processed array with encrypted data cleared.
	 */
	private function clear_encrypted_from_array( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		$cleared = array();

		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) {
				$cleared[ $key ] = $this->clear_encrypted_from_array( $value );
			} elseif ( is_string( $value ) && $this->is_encrypted( $value ) ) {
				$cleared[ $key ] = ''; // Clear encrypted values
			} else {
				$cleared[ $key ] = $value;
			}
		}

		return $cleared;
	}

	/**
	 * Log encryption errors.
	 *
	 * @since  1.0.0
	 * @param  string $message Error message.
	 * @return void
	 */
	private function log_error( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'WAISG Encryption Error: ' . $message );
		}
	}
}