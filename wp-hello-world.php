<?php
	/**
	 * An example plugin for a membership course walking readers through how to work with GitHub.
	 *
	 * @link              https://github.com/tommcfarlin/wp-hello-world/
	 * @since             1.0.0
	 * @package           WPHW
	 *
	 * @wordpress-plugin
	 * Plugin Name:       WordPress Hello World
	 * Plugin URI:        https://github.com/tommcfarlin/wp-hello-world/
	 * Description:       An example plugin for a membership course walking readers through how to
	 * work with GitHub. Version:           1.0.0 Author:            Tom McFarlin Author URI:
	 * https://tommcfarlin.com/ License:           GPL-2.0+ License URI:
	 * http://www.gnu.org/licenses/gpl-2.0.txt
	 */

// If this file is called directly, abort.
	if (!defined('WPINC')) {
		die;
	}
	
	add_filter('login_message', 'wphw_login_message');
	/**
	 * Adds 'Hello World' above the login form in the WordPress login form.
	 *
	 * @param string $message The default message to display above the form.
	 *
	 * @return   string $message    The message to display above the form.
	 */
	function wphw_login_message ($message) {
		
		$message = '<h2>';
		$message .= 'Hello World!';
		$message .= '</h2>';
		
		return $message;
	}
	
	
	defined('ABSPATH') || exit;
	
	
	if (!class_exists('mishaUpdateChecker')) {
		
		class mishaUpdateChecker {
			
			public $plugin_slug;
			public $version;
			public $cache_key;
			public $cache_allowed;
			
			public function __construct () {
				
				$this->plugin_slug   = plugin_basename(__DIR__);
				$this->version       = '1.0';
				$this->cache_key     = 'misha_custom_upd';
				$this->cache_allowed = false;
				
				add_filter('plugins_api', array($this, 'info'), 20, 3);
				add_filter('site_transient_update_plugins', array($this, 'update'));
				add_action('upgrader_process_complete', array($this, 'purge'), 10, 2);
				
			}
			
			public function request () {
				
				$remote = get_transient($this->cache_key);
				
				if (false === $remote || !$this->cache_allowed) {
					
					$remote = wp_remote_get(
						'https://api.github.com/repos/rarest-adam/wp-hello-world/releases/latest',
						array(
							'timeout' => 10,
							'headers' => array(
								'Accept' => 'application/json'
							)
						)
					);
					
					if (
						is_wp_error($remote)
						|| 200 !== wp_remote_retrieve_response_code($remote)
						|| empty(wp_remote_retrieve_body($remote))
					) {
						return false;
					}
					
					set_transient($this->cache_key, $remote, DAY_IN_SECONDS);
					
				}
				
				$remote = json_decode(wp_remote_retrieve_body($remote));
				
				return $remote;
				
			}
			
			
			function info ($res, $action, $args) {
				
				// print_r( $action );
				// print_r( $args );
				
				// do nothing if you're not getting plugin information right now
				if ('plugin_information' !== $action) {
					return $res;
				}
				
				// do nothing if it is not our plugin
				if ($this->plugin_slug !== $args->slug) {
					return $res;
				}
				
				// get updates
				$remote = $this->request();
				
				if (!$remote) {
					return $res;
				}
				
				$res = new stdClass();
				
				$res->name           = $remote->tag_name;
//				$res->slug           = $remote->slug;
				$res->version        = $remote->tag_name;
//				$res->tested         = $remote->tested;
//				$res->requires       = $remote->requires;
//				$res->author         = $remote->author;
//				$res->author_profile = $remote->author_profile;
				$res->download_link  = $remote->assets[0]->url;
				$res->trunk          = $remote->assets[0]->url;
//				$res->requires_php   = $remote->requires_php;
				$res->last_updated   = $remote->published_at;
				
				$res->sections = array(
					'description'  => $remote->sections->description,
					'installation' => $remote->sections->installation,
					'changelog'    => $remote->sections->changelog
				);
				
				if (!empty($remote->banners)) {
					$res->banners = array(
						'low'  => $remote->banners->low,
						'high' => $remote->banners->high
					);
				}
				
				return $res;
				
			}
			
			public function update ($transient) {
				
				if (empty($transient->checked)) {
					return $transient;
				}
				
				$remote = $this->request();
				
				if (
					$remote
					&& version_compare($this->version, $remote->version, '<')
					&& version_compare($remote->requires, get_bloginfo('version'), '<=')
					&& version_compare($remote->requires_php, PHP_VERSION, '<')
				) {
					$res              = new stdClass();
					$res->slug        = $this->plugin_slug;
					$res->plugin      = plugin_basename(__FILE__); // misha-update-plugin/misha-update-plugin.php
					$res->new_version = $remote->version;
					$res->tested      = $remote->tested;
					$res->package     = $remote->download_url;
					
					$transient->response[$res->plugin] = $res;
					
				}
				
				return $transient;
				
			}
			
			public function purge ($upgrader, $options) {
				
				if (
					$this->cache_allowed
					&& 'update' === $options['action']
					&& 'plugin' === $options['type']
				) {
					// just clean the cache when new plugin version is installed
					delete_transient($this->cache_key);
				}
				
			}
			
			
		}
		
		new mishaUpdateChecker();
		
	}