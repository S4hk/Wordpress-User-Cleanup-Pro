<?php

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'WBCP_GitHub_Updater' ) ) :

class WBCP_GitHub_Updater {

    private $plugin_file;
    private $plugin_basename;
    private $github_repo;
    private $current_version;
    private $plugin_slug;

    public function __construct( $plugin_file, $github_repo, $current_version ) {
        $this->plugin_file = $plugin_file;
        $this->plugin_basename = plugin_basename( $plugin_file );
        $this->github_repo = $github_repo;
        $this->current_version = $current_version;
        $this->plugin_slug = dirname( $this->plugin_basename );

        // Hook into WordPress update system
        add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_plugin_update' ) );
        add_filter( 'plugins_api', array( $this, 'plugin_api_call' ), 10, 3 );
        add_filter( 'upgrader_pre_download', array( $this, 'download_package' ), 10, 3 );
        add_action( 'upgrader_process_complete', array( $this, 'after_update' ), 10, 2 );
    }

    /**
     * Check for plugin updates
     */
    public function check_for_plugin_update( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        $update_info = $this->check_for_update();
        
        if ( $update_info && version_compare( $this->current_version, $update_info['new_version'], '<' ) ) {
            $transient->response[ $this->plugin_basename ] = (object) array(
                'slug' => $this->plugin_slug,
                'plugin' => $this->plugin_basename,
                'new_version' => $update_info['new_version'],
                'url' => $update_info['details_url'],
                'package' => $update_info['download_url'],
                'tested' => $update_info['tested'],
                'requires_php' => $update_info['requires_php'],
                'compatibility' => new stdClass()
            );
        }

        return $transient;
    }

    /**
     * Get plugin information for the update details popup
     */
    public function plugin_api_call( $result, $action, $args ) {
        if ( $action !== 'plugin_information' || $args->slug !== $this->plugin_slug ) {
            return $result;
        }

        $update_info = $this->check_for_update();
        
        if ( ! $update_info ) {
            return $result;
        }

        return (object) array(
            'slug' => $this->plugin_slug,
            'plugin' => $this->plugin_basename,
            'name' => 'WordPress Bulk Cleanup Pro',
            'version' => $update_info['new_version'],
            'author' => 'S4hk',
            'author_profile' => 'https://github.com/' . dirname( $this->github_repo ),
            'requires' => '5.6',
            'tested' => $update_info['tested'],
            'requires_php' => $update_info['requires_php'],
            'download_link' => $update_info['download_url'],
            'trunk' => $update_info['download_url'],
            'last_updated' => $update_info['last_updated'],
            'sections' => array(
                'description' => 'Advanced bulk cleanup tool for administrators. Delete users by missing names, email domains, or roles, and WooCommerce orders/coupons by status, in safe batches with progress bar.',
                'installation' => 'Upload the plugin files to the `/wp-content/plugins/wordpress-bulk-cleanup-pro` directory, or install the plugin through the WordPress plugins screen directly. Activate the plugin through the \'Plugins\' screen in WordPress.',
                'changelog' => $this->get_changelog( $update_info['body'] )
            ),
            'banners' => array(),
            'icons' => array()
        );
    }

    /**
     * Download the update package from GitHub
     */
    public function download_package( $reply, $package, $upgrader ) {
        if ( strpos( $package, 'github.com' ) === false || strpos( $package, $this->github_repo ) === false ) {
            return $reply;
        }

        $download_file = download_url( $package );
        
        if ( is_wp_error( $download_file ) ) {
            return new WP_Error( 'download_failed', __( 'Download failed.', 'wordpress-bulk-cleanup-pro' ) . ' ' . $download_file->get_error_message() );
        }

        return $download_file;
    }

    /**
     * Clean up after update
     */
    public function after_update( $upgrader_object, $options ) {
        if ( $options['action'] === 'update' && $options['type'] === 'plugin' ) {
            // Clear any cached update information
            delete_transient( 'wbcp_github_update_check' );
            
            // Clear plugin update transients
            delete_site_transient( 'update_plugins' );
        }
    }

    /**
     * Check GitHub for new version
     */
    public function check_for_update() {
        // Check cache first
        $cached_update = get_transient( 'wbcp_github_update_check' );
        if ( $cached_update !== false ) {
            return $cached_update;
        }

        $api_url = "https://api.github.com/repos/{$this->github_repo}/releases/latest";
        
        $response = wp_remote_get( $api_url, array(
            'timeout' => 15,
            'headers' => array(
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url()
            )
        ) );

        if ( is_wp_error( $response ) ) {
            // Cache negative result for 1 hour
            set_transient( 'wbcp_github_update_check', false, HOUR_IN_SECONDS );
            return false;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( ! isset( $data['tag_name'] ) ) {
            // Cache negative result for 1 hour
            set_transient( 'wbcp_github_update_check', false, HOUR_IN_SECONDS );
            return false;
        }

        // Clean version number (remove 'v' prefix if present)
        $new_version = ltrim( $data['tag_name'], 'v' );
        
        $update_info = array(
            'new_version' => $new_version,
            'download_url' => $data['zipball_url'],
            'details_url' => $data['html_url'],
            'body' => isset( $data['body'] ) ? $data['body'] : '',
            'last_updated' => $data['published_at'],
            'tested' => get_bloginfo( 'version' ), // Assume compatibility with current WP version
            'requires_php' => '7.4' // Minimum PHP version
        );

        // Cache for 12 hours
        set_transient( 'wbcp_github_update_check', $update_info, 12 * HOUR_IN_SECONDS );

        return $update_info;
    }

    /**
     * Parse changelog from release body
     */
    private function get_changelog( $body ) {
        if ( empty( $body ) ) {
            return 'No changelog available.';
        }

        // Convert markdown to HTML for better display
        $changelog = wpautop( $body );
        
        // Convert markdown headers
        $changelog = preg_replace( '/^### (.+)$/m', '<h4>$1</h4>', $changelog );
        $changelog = preg_replace( '/^## (.+)$/m', '<h3>$1</h3>', $changelog );
        $changelog = preg_replace( '/^# (.+)$/m', '<h2>$1</h2>', $changelog );
        
        // Convert markdown lists
        $changelog = preg_replace( '/^\* (.+)$/m', '<li>$1</li>', $changelog );
        $changelog = preg_replace( '/(<li>.+<\/li>)/s', '<ul>$1</ul>', $changelog );
        
        return $changelog;
    }

    /**
     * Force check for updates (for manual checking)
     */
    public function force_check() {
        delete_transient( 'wbcp_github_update_check' );
        return $this->check_for_update();
    }

    /**
     * Get current version info
     */
    public function get_version_info() {
        return array(
            'current_version' => $this->current_version,
            'plugin_basename' => $this->plugin_basename,
            'github_repo' => $this->github_repo
        );
    }
}

endif;
