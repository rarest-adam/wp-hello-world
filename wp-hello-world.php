<?php
	/*
 * Plugin name: Misha Update Checker
 * Description: This simple plugin does nothing, only gets updates from a custom server
 * Version: 1.0
 * Author: Misha Rudrastyh
 * Author URI: https://rudrastyh.com
 * License: GPL
 */
	
	/**/


	
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
				error_log('THIS IS THE START OF MY CUSTOM DEBUG info');
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
				
				$res->name    = $remote->tag_name;
				$res->slug    = $remote->tag_name;
				$res->version = $remote->tag_name;
//				$res->tested         = $remote->tested;
//				$res->requires       = $remote->requires;
//				$res->author         = $remote->author;
//				$res->author_profile = $remote->author_profile;
				$res->download_link = $remote->assets[0]->browser_download_url;
				$res->trunk         = $remote->assets[0]->browser_download_url;
//				$res->requires_php   = $remote->requires_php;
				$res->last_updated = $remote->published_at;
				
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
	
	add_filter('site_transient_update_plugins', 'misha_push_update');
	
	function misha_push_update ($transient) {

		if (empty($transient->checked)) {
			return $transient;
		}
		
		$remote = wp_remote_get(
			'https://api.github.com/repos/rarest-adam/wp-hello-world/releases/latest',
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept' => 'application/json',
					'Authorization' => 'Bearer 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'
				)
			)
		);
		
		error_log('gettting remote');
		
		error_log('remote: ' . print_r($remote, true));
		
		if (
			is_wp_error($remote)
			|| 200 !== wp_remote_retrieve_response_code($remote)
			|| empty(wp_remote_retrieve_body($remote))
			) {
			error_log('Error getting remote...');
			return $transient;
		}
		
		$remote = json_decode(wp_remote_retrieve_body($remote));
		
		error_log('tag_name: ' . $remote->tag_name);
		error_log('url: ' . $remote->assets[0]->browser_download_url);
		
		// your installed plugin version should be on the line below! You can obtain it dynamically of course
//		if (
//			$remote
//			&& version_compare($this->version, $remote->version, '<')
//			&& version_compare($remote->requires, get_bloginfo('version'), '<')
//			&& version_compare($remote->requires_php, PHP_VERSION, '<')
//		)
 		{
			
			$res                               = new stdClass();
			$res->slug                         = $remote->tag_name;
			$res->plugin                       = plugin_basename(__FILE__); // it could be just YOUR_PLUGIN_SLUG.php if your plugin doesn't have its own directory
			$res->new_version                  = $remote->tag_name;
//			$res->tested                       = $remote->tested;
			$res->package                      = $remote->assets[0]->browser_download_url;;
			$transient->response[$res->plugin] = $res;
			
			//$transient->checked[$res->plugin] = $remote->version;
		}
		
		return $transient;
		
	}