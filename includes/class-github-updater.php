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
    private $github_repo;

    public function __construct( $file, $github_repo, $version ) {
        $this->file = $file;
        $this->plugin_slug = plugin_basename( $file );
        $this->version = $version;
        $this->github_repo = $github_repo;
        
        // Parse GitHub repo
        $repo_parts = explode( '/', $github_repo );
        $this->username = $repo_parts[0];
        $this->repository = $repo_parts[1];

        // Hook into WordPress update system
        add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'modify_transient' ), 10, 1 );
        add_filter( 'plugins_api', array( $this, 'plugin_popup' ), 10, 3 );
        add_filter( 'upgrader_post_install', array( $this, 'after_install' ), 10, 3 );
        add_filter( 'upgrader_pre_download', array( $this, 'download_package' ), 10, 3 );
    }

    public function modify_transient( $transient ) {
        if ( ! isset( $transient->checked ) || ! isset( $transient->checked[ $this->plugin_slug ] ) ) {
            return $transient;
        }

        $update_info = $this->check_for_update();
        
        if ( $update_info && version_compare( $this->version, $update_info['new_version'], '<' ) ) {
            $transient->response[ $this->plugin_slug ] = (object) array(
                'slug' => dirname( $this->plugin_slug ),
                'plugin' => $this->plugin_slug,
                'new_version' => $update_info['new_version'],
                'url' => $update_info['details_url'],
                'package' => $update_info['download_url'],
                'tested' => get_bloginfo( 'version' ),
                'requires_php' => '7.4',
                'compatibility' => new stdClass()
            );
        }
        
        return $transient;
    }

    public function plugin_popup( $result, $action, $args ) {
        if ( $action !== 'plugin_information' ) {
            return $result;
        }

        if ( ! isset( $args->slug ) || $args->slug !== dirname( $this->plugin_slug ) ) {
            return $result;
        }

        $update_info = $this->check_for_update();
        
        if ( ! $update_info ) {
            return $result;
        }

        return (object) array(
            'name' => 'WordPress Bulk Cleanup Pro',
            'slug' => dirname( $this->plugin_slug ),
            'version' => $update_info['new_version'],
            'author' => 'S4hk',
            'author_profile' => 'https://github.com/S4hk',
            'last_updated' => $update_info['last_updated'],
            'homepage' => "https://github.com/{$this->github_repo}",
            'download_link' => $update_info['download_url'],
            'trunk' => $update_info['download_url'],
            'requires' => '5.6',
            'tested' => get_bloginfo( 'version' ),
            'requires_php' => '7.4',
            'sections' => array(
                'description' => 'Advanced bulk cleanup tool for administrators. Delete users by missing names, email domains, or roles, and WooCommerce orders/coupons by status.',
                'changelog' => $this->get_changelog( $update_info['body'] ?? '' )
            ),
            'banners' => array(),
            'icons' => array()
        );
    }

    public function after_install( $response, $hook_extra, $result ) {
        global $wp_filesystem;

        $install_directory = plugin_dir_path( $this->file );
        $wp_filesystem->move( $result['destination'], $install_directory );
        $result['destination'] = $install_directory;

        if ( $this->plugin_slug ) {
            activate_plugin( $this->plugin_slug );
        }

        return $result;
    }

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
            set_transient( 'wbcp_github_update_check', false, HOUR_IN_SECONDS );
            return false;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( ! isset( $data['tag_name'] ) ) {
            set_transient( 'wbcp_github_update_check', false, HOUR_IN_SECONDS );
            return false;
        }

        $new_version = ltrim( $data['tag_name'], 'v' );
        
        $update_info = array(
            'new_version' => $new_version,
            'download_url' => $data['zipball_url'],
            'details_url' => $data['html_url'],
            'body' => isset( $data['body'] ) ? $data['body'] : '',
            'last_updated' => $data['published_at'],
            'tested' => get_bloginfo( 'version' ),
            'requires_php' => '7.4'
        );

        // Cache for 12 hours
        set_transient( 'wbcp_github_update_check', $update_info, 12 * HOUR_IN_SECONDS );

        return $update_info;
    }

    private function get_changelog( $body ) {
        if ( empty( $body ) ) {
            return 'No changelog available.';
        }

        $changelog = wpautop( $body );
        $changelog = preg_replace( '/^### (.+)$/m', '<h4>$1</h4>', $changelog );
        $changelog = preg_replace( '/^## (.+)$/m', '<h3>$1</h3>', $changelog );
        $changelog = preg_replace( '/^# (.+)$/m', '<h2>$1</h2>', $changelog );
        $changelog = preg_replace( '/^\* (.+)$/m', '<li>$1</li>', $changelog );
        $changelog = preg_replace( '/(<li>.+<\/li>)/s', '<ul>$1</ul>', $changelog );
        
        return $changelog;
    }

    public function force_check() {
        delete_transient( 'wbcp_github_update_check' );
        return $this->check_for_update();
    }
}
        
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
