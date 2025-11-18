<?php
/**
 * Register all actions and filters for the plugin.
 *
 * Maintain a list of all hooks that are registered throughout
 * the plugin, and register them with the WordPress API.
 *
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/includes
 * @since      1.0.0
 */

namespace WPAISiteGenerator\Includes;

/**
 * Register all actions and filters for the plugin.
 *
 * @since      1.0.0
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/includes
 */
class Loader {

	/**
	 * The array of actions registered with WordPress.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      array    $actions    The actions registered with WordPress to fire when the plugin loads.
	 */
	protected $actions;

	/**
	 * The array of filters registered with WordPress.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      array    $filters    The filters registered with WordPress to fire when the plugin loads.
	 */
	protected $filters;

	/**
	 * The array of shortcodes registered with WordPress.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      array    $shortcodes    The shortcodes registered with WordPress.
	 */
	protected $shortcodes;

	/**
	 * Initialize the collections used to maintain the actions, filters, and shortcodes.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		$this->actions    = array();
		$this->filters    = array();
		$this->shortcodes = array();
	}

	/**
	 * Add a new action to the collection to be registered with WordPress.
	 *
	 * @since    1.0.0
	 * @param    string               $hook             The name of the WordPress action that is being registered.
	 * @param    object               $component        A reference to the instance of the object on which the action is defined.
	 * @param    string               $callback         The name of the function definition on the $component.
	 * @param    int                  $priority         Optional. The priority at which the function should be fired. Default is 10.
	 * @param    int                  $accepted_args    Optional. The number of arguments that should be passed to the $callback. Default is 1.
	 */
	public function add_action( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->actions = $this->add( $this->actions, $hook, $component, $callback, $priority, $accepted_args );
	}

	/**
	 * Add a new filter to the collection to be registered with WordPress.
	 *
	 * @since    1.0.0
	 * @param    string               $hook             The name of the WordPress filter that is being registered.
	 * @param    object               $component        A reference to the instance of the object on which the filter is defined.
	 * @param    string               $callback         The name of the function definition on the $component.
	 * @param    int                  $priority         Optional. The priority at which the function should be fired. Default is 10.
	 * @param    int                  $accepted_args    Optional. The number of arguments that should be passed to the $callback. Default is 1.
	 */
	public function add_filter( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->filters = $this->add( $this->filters, $hook, $component, $callback, $priority, $accepted_args );
	}

	/**
	 * Add a new shortcode to the collection to be registered with WordPress.
	 *
	 * @since    1.0.0
	 * @param    string               $tag              The name of the shortcode.
	 * @param    object               $component        A reference to the instance of the object on which the shortcode is defined.
	 * @param    string               $callback         The name of the function definition on the $component.
	 */
	public function add_shortcode( $tag, $component, $callback ) {
		$this->shortcodes = $this->add_shortcode_internal( $this->shortcodes, $tag, $component, $callback );
	}

	/**
	 * A utility function that is used to register the actions, filters, and shortcodes into a single collection.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array                $hooks            The collection of hooks that is being registered (that is, actions, filters, or shortcodes).
	 * @param    string               $hook             The name of the WordPress hook that is being registered.
	 * @param    object               $component        A reference to the instance of the object on which the hook is defined.
	 * @param    string               $callback         The name of the function definition on the $component.
	 * @param    int                  $priority         The priority at which the function should be fired.
	 * @param    int                  $accepted_args    The number of arguments that should be passed to the $callback.
	 * @return   array                                  The collection of hooks that was passed in, with the addition of the new hook.
	 */
	private function add( $hooks, $hook, $component, $callback, $priority, $accepted_args ) {
		$hooks[] = array(
			'hook'          => $hook,
			'component'     => $component,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);

		return $hooks;
	}

	/**
	 * A utility function that is used to register shortcodes into the collection.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array                $shortcodes       The collection of shortcodes that is being registered.
	 * @param    string               $tag              The name of the shortcode.
	 * @param    object               $component        A reference to the instance of the object on which the shortcode is defined.
	 * @param    string               $callback         The name of the function definition on the $component.
	 * @return   array                                  The collection of shortcodes that was passed in, with the addition of the new shortcode.
	 */
	private function add_shortcode_internal( $shortcodes, $tag, $component, $callback ) {
		$shortcodes[] = array(
			'tag'       => $tag,
			'component' => $component,
			'callback'  => $callback,
		);

		return $shortcodes;
	}

	/**
	 * Register the filters, actions, and shortcodes with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		// Register filters
		foreach ( $this->filters as $hook ) {
			add_filter(
				$hook['hook'],
				array( $hook['component'], $hook['callback'] ),
				$hook['priority'],
				$hook['accepted_args']
			);
		}

		// Register actions
		foreach ( $this->actions as $hook ) {
			add_action(
				$hook['hook'],
				array( $hook['component'], $hook['callback'] ),
				$hook['priority'],
				$hook['accepted_args']
			);
		}

		// Register shortcodes
		foreach ( $this->shortcodes as $shortcode ) {
			add_shortcode(
				$shortcode['tag'],
				array( $shortcode['component'], $shortcode['callback'] )
			);
		}
	}

	/**
	 * Remove a previously registered action.
	 *
	 * @since    1.0.0
	 * @param    string               $hook             The name of the WordPress action that was registered.
	 * @param    object               $component        A reference to the instance of the object on which the action was defined.
	 * @param    string               $callback         The name of the function definition on the $component.
	 * @param    int                  $priority         Optional. The priority at which the function should be removed. Default is 10.
	 */
	public function remove_action( $hook, $component, $callback, $priority = 10 ) {
		remove_action( $hook, array( $component, $callback ), $priority );

		// Also remove from internal collection
		foreach ( $this->actions as $key => $action ) {
			if ( $action['hook'] === $hook &&
			     $action['component'] === $component &&
			     $action['callback'] === $callback &&
			     $action['priority'] === $priority ) {
				unset( $this->actions[ $key ] );
			}
		}
	}

	/**
	 * Remove a previously registered filter.
	 *
	 * @since    1.0.0
	 * @param    string               $hook             The name of the WordPress filter that was registered.
	 * @param    object               $component        A reference to the instance of the object on which the filter was defined.
	 * @param    string               $callback         The name of the function definition on the $component.
	 * @param    int                  $priority         Optional. The priority at which the function should be removed. Default is 10.
	 */
	public function remove_filter( $hook, $component, $callback, $priority = 10 ) {
		remove_filter( $hook, array( $component, $callback ), $priority );

		// Also remove from internal collection
		foreach ( $this->filters as $key => $filter ) {
			if ( $filter['hook'] === $hook &&
			     $filter['component'] === $component &&
			     $filter['callback'] === $callback &&
			     $filter['priority'] === $priority ) {
				unset( $this->filters[ $key ] );
			}
		}
	}

	/**
	 * Remove a previously registered shortcode.
	 *
	 * @since    1.0.0
	 * @param    string               $tag              The name of the shortcode.
	 */
	public function remove_shortcode( $tag ) {
		remove_shortcode( $tag );

		// Also remove from internal collection
		foreach ( $this->shortcodes as $key => $shortcode ) {
			if ( $shortcode['tag'] === $tag ) {
				unset( $this->shortcodes[ $key ] );
			}
		}
	}

	/**
	 * Get all registered actions.
	 *
	 * @since    1.0.0
	 * @return   array    The registered actions.
	 */
	public function get_actions() {
		return $this->actions;
	}

	/**
	 * Get all registered filters.
	 *
	 * @since    1.0.0
	 * @return   array    The registered filters.
	 */
	public function get_filters() {
		return $this->filters;
	}

	/**
	 * Get all registered shortcodes.
	 *
	 * @since    1.0.0
	 * @return   array    The registered shortcodes.
	 */
	public function get_shortcodes() {
		return $this->shortcodes;
	}

	/**
	 * Check if a specific action is registered.
	 *
	 * @since    1.0.0
	 * @param    string               $hook             The name of the WordPress action.
	 * @param    object               $component        Optional. The component to check.
	 * @param    string               $callback         Optional. The callback to check.
	 * @return   bool                                   True if the action is registered, false otherwise.
	 */
	public function has_action( $hook, $component = null, $callback = null ) {
		foreach ( $this->actions as $action ) {
			if ( $action['hook'] === $hook ) {
				if ( $component === null ) {
					return true;
				}

				if ( $action['component'] === $component ) {
					if ( $callback === null ) {
						return true;
					}

					if ( $action['callback'] === $callback ) {
						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Check if a specific filter is registered.
	 *
	 * @since    1.0.0
	 * @param    string               $hook             The name of the WordPress filter.
	 * @param    object               $component        Optional. The component to check.
	 * @param    string               $callback         Optional. The callback to check.
	 * @return   bool                                   True if the filter is registered, false otherwise.
	 */
	public function has_filter( $hook, $component = null, $callback = null ) {
		foreach ( $this->filters as $filter ) {
			if ( $filter['hook'] === $hook ) {
				if ( $component === null ) {
					return true;
				}

				if ( $filter['component'] === $component ) {
					if ( $callback === null ) {
						return true;
					}

					if ( $filter['callback'] === $callback ) {
						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Check if a specific shortcode is registered.
	 *
	 * @since    1.0.0
	 * @param    string               $tag              The name of the shortcode.
	 * @return   bool                                   True if the shortcode is registered, false otherwise.
	 */
	public function has_shortcode( $tag ) {
		foreach ( $this->shortcodes as $shortcode ) {
			if ( $shortcode['tag'] === $tag ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get statistics about registered hooks.
	 *
	 * @since    1.0.0
	 * @return   array    Statistics about registered hooks.
	 */
	public function get_statistics() {
		return array(
			'total_actions'    => count( $this->actions ),
			'total_filters'    => count( $this->filters ),
			'total_shortcodes' => count( $this->shortcodes ),
			'total_hooks'      => count( $this->actions ) + count( $this->filters ) + count( $this->shortcodes ),
		);
	}
}