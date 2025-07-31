<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * GitHub updater for WBCP
 */
class WBCP_GitHub_Updater {

    private $file;
    private $plugin_slug;
    private $version;
    private $username;
    private $repository;

    public function __construct( $file, $github_repo, $version ) {
        $this->file = $file;
        $this->plugin_slug = plugin_basename( $file );
        $this->version = $version;
        
        // Parse GitHub repo
        $repo_parts = explode( '/', $github_repo );
        $this->username = $repo_parts[0];
        $this->repository = $repo_parts[1];

        add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'modify_transient' ), 10, 1 );
        add_filter( 'plugins_api', array( $this, 'plugin_popup' ), 10, 3 );
        add_filter( 'upgrader_post_install', array( $this, 'after_install' ), 10, 3 );
    }

    public function check_for_update() {
        $remote_version = $this->get_remote_version();
        
        if ( $remote_version && version_compare( $this->version, $remote_version, '<' ) ) {
            return array(
                'new_version' => $remote_version,
                'package' => $this->get_download_url()
            );
        }
        
        return false;
    }

    private function get_remote_version() {
        $request = wp_remote_get( "https://api.github.com/repos/{$this->username}/{$this->repository}/releases/latest" );
        
        if ( ! is_wp_error( $request ) && wp_remote_retrieve_response_code( $request ) === 200 ) {
            $body = wp_remote_retrieve_body( $request );
            $data = json_decode( $body, true );
            
            if ( isset( $data['tag_name'] ) ) {
                return ltrim( $data['tag_name'], 'v' );
            }
        }
        
        return false;
    }

    private function get_download_url() {
        return "https://github.com/{$this->username}/{$this->repository}/archive/main.zip";
    }

    public function modify_transient( $transient ) {
        if ( isset( $transient->checked ) ) {
            $remote_version = $this->get_remote_version();
            
            if ( $remote_version && version_compare( $this->version, $remote_version, '<' ) ) {
                $transient->response[ $this->plugin_slug ] = (object) array(
                    'slug' => $this->plugin_slug,
                    'new_version' => $remote_version,
                    'package' => $this->get_download_url()
                );
            }
        }
        
        return $transient;
    }

    public function plugin_popup( $result, $action, $args ) {
        return $result;
    }

    public function after_install( $response, $hook_extra, $result ) {
        return $response;
    }
}
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
