<div class="wrap">
    <h1><?php esc_html_e( 'WordPress Bulk Cleanup Pro', 'wordpress-bulk-cleanup-pro' ); ?> <span style="font-size:14px; color:#666;">v<?php echo WBCP_VERSION; ?></span></h1>
    
    <?php
    // Show update notice if available
    $github_updater = new WBCP_GitHub_Updater( WBCP_PLUGIN_FILE, WBCP_GITHUB_REPO, WBCP_VERSION );
    $update_info = $github_updater->check_for_update();
    if ( $update_info && version_compare( WBCP_VERSION, $update_info['new_version'], '<' ) ) :
    ?>
    <div class="notice notice-info">
        <p><strong><?php esc_html_e( 'Update Available!', 'wordpress-bulk-cleanup-pro' ); ?></strong> 
        <?php printf( 
            esc_html__( 'Version %s is available. Current version: %s', 'wordpress-bulk-cleanup-pro' ), 
            $update_info['new_version'], 
            WBCP_VERSION 
        ); ?>
        <a href="<?php echo admin_url( 'plugins.php' ); ?>" class="button button-primary" style="margin-left:10px;">
            <?php esc_html_e( 'Update Now', 'wordpress-bulk-cleanup-pro' ); ?>
        </a>
        </p>
    </div>
    <?php endif; ?>
    
    <form method="post" action="options.php" id="wbcp-settings-form">
        <?php settings_fields( 'wbcp_settings_group' ); ?>
        <table class="form-table">
            <tr>
                <th><?php esc_html_e( 'Delete users without first & last name', 'wordpress-bulk-cleanup-pro' ); ?></th>
                <td>
                    <input type="checkbox" name="<?php echo WBCP_Utils::OPTION_KEY; ?>[delete_no_name]" value="1" <?php checked( $this->delete_no_name, 1 ); ?> />
                    <span><?php esc_html_e( 'Users missing both first and last name will be deleted.', 'wordpress-bulk-cleanup-pro' ); ?></span>
                </td>
            </tr>
            <!-- ...existing form fields from original file... -->
        </table>
        <?php submit_button(); ?>
    </form>
    
    <hr>
    
    <h2><?php esc_html_e( 'Start Bulk Cleanup', 'wordpress-bulk-cleanup-pro' ); ?></h2>
    <div class="notice notice-warning inline">
        <p><strong><?php esc_html_e( 'Warning:', 'wordpress-bulk-cleanup-pro' ); ?></strong> <?php esc_html_e( 'This action cannot be undone. Please backup your database before proceeding. The process will scan all users and orders first, then delete them in batches.', 'wordpress-bulk-cleanup-pro' ); ?></p>
    </div>
    
    <button id="wbcp-start-deletion" class="button button-primary"><?php esc_html_e( 'Start Deletion Process', 'wordpress-bulk-cleanup-pro' ); ?></button>
    
    <div id="wbcp-progress-wrapper" style="display:none; margin-top:10px;">
        <div id="wbcp-progress-bar" style="background:#e0e0e0; border-radius:5px; height:25px; width:400px; margin-bottom:10px;">
            <div id="wbcp-progress-inner" style="background:#0073aa; height:100%; width:0%; border-radius:5px;"></div>
        </div>
        <span id="wbcp-progress-label"></span>
    </div>
    
    <div id="wbcp-log" style="margin-top:15px; max-height:120px; overflow-y:auto; color:#23282d; font-size:14px;"></div>
</div>
