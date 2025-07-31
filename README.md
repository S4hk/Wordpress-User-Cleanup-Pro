# WordPress Bulk Cleanup Pro

A powerful WordPress plugin for administrators to safely delete users in bulk based on various criteria. Designed to handle millions of users with memory-efficient scanning and batch processing.

![WordPress Plugin Version](https://img.shields.io/badge/version-1.2.0-blue.svg)
![WordPress Compatibility](https://img.shields.io/badge/wordpress-5.6%2B-blue.svg)
![PHP Compatibility](https://img.shields.io/badge/php-7.4%2B-blue.svg)
![License](https://img.shields.io/badge/license-GPL2%2B-green.svg)

## 🚀 Features

### Core Functionality
- **Bulk User Deletion**: Delete users based on multiple criteria
- **Role-Based Deletion**: Remove all users with specific roles (excludes administrators)
- **Domain-Based Filtering**: Delete users whose email domains don't match your allowed list
- **Name-Based Filtering**: Remove users missing both first and last names
- **WooCommerce Integration**: Delete orders and coupons by status
- **Administrator Protection**: Multiple safety checks prevent accidental admin deletion

### Performance & Scalability
- **Millions of Users Support**: Handles large user bases with memory-efficient processing
- **Two-Phase Process**: Separate scanning and deletion phases for optimal performance
- **Batch Processing**: Configurable batch sizes (50-1000 users per batch)
- **Progress Tracking**: Real-time progress bars and detailed logging
- **Memory Management**: Automatic cache cleanup and transient storage

### Safety Features
- **Database Backups Recommended**: Clear warnings before irreversible operations
- **Error Handling**: Comprehensive try-catch blocks and graceful error recovery
- **Network Resilience**: Handles AJAX failures and connection issues
- **Execution Limits**: Configurable timeouts to prevent server overload
- **Detailed Logging**: Complete audit trail of all operations

## 📋 Requirements

- WordPress 5.6 or higher
- PHP 7.4 or higher
- MySQL/MariaDB database
- Administrator privileges

## 🛠️ Installation

### Method 1: Manual Installation
1. Download the plugin files
2. Upload the `wordpress-bulk-cleanup-pro` folder to `/wp-content/plugins/`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Navigate to **Users > Bulk Cleanup Pro** to configure settings

### Method 2: WordPress Admin
1. Go to **Plugins > Add New**
2. Upload the plugin ZIP file
3. Click **Install Now** and then **Activate**

## 📖 Usage

### Basic Setup
1. Navigate to **Users > Bulk Cleanup Pro** in your WordPress admin
2. Configure your deletion criteria:
   - **Delete users without names**: Remove users missing both first and last names
   - **Domain filtering**: Specify allowed email domains (comma-separated)
   - **Role deletion**: Select a specific role to delete (administrators excluded)
   - **WooCommerce options**: Delete orders and coupons by status
   - **Batch size**: Choose processing speed vs. server load balance

### Running the Cleanup Process

⚠️ **Important**: Always backup your database before running bulk cleanup operations!

1. Click **"Start Deletion Process"**
2. **Scanning Phase**: The plugin will scan all users and WooCommerce data to identify deletion candidates
3. **Deletion Phase**: Users, orders, and coupons will be deleted in batches with real-time progress updates
4. **Completion**: Review the detailed log of all operations

### Configuration Options

#### Batch Size Selection
- **50 (Slower, safest)**: Best for shared hosting or limited resources
- **100 (Recommended)**: Balanced performance for most setups
- **250 (Faster)**: Good for VPS with adequate resources
- **500 (Fast)**: Suitable for dedicated servers
- **1000 (Very fast, advanced)**: High-performance servers only

#### Deletion Criteria
- **No Name Filter**: Targets users with empty first_name AND last_name meta fields
- **Domain Filter**: Allows only specified email domains (e.g., `company.com,partner.org`)
- **Role Filter**: Removes all users with the selected role (never affects administrators)
- **WooCommerce Orders**: Delete orders by status (pending, completed, cancelled, etc.)
- **WooCommerce Coupons**: Delete coupons by status (published, draft, trash, etc.)

## 🔧 Technical Details

### Architecture
- **Memory Efficient**: Uses WordPress transients instead of loading all user IDs into memory
- **Paginated Scanning**: Processes users in batches of 1,000 during scanning phase
- **Safe Database Operations**: Proper prepared statements and error handling
- **Cache Management**: Automatic cleanup of WordPress user caches

### Database Operations
```php
// Safe user metadata deletion
$wpdb->delete( $wpdb->usermeta, array( 'user_id' => $user_id ), array( '%d' ) );

// WordPress core user deletion
wp_delete_user( $user_id );

// WooCommerce order deletion
$order->delete( true ); // Force delete

// WooCommerce coupon deletion
wp_delete_post( $coupon_id, true ); // Force delete
```

### Security Features
- **Nonce Verification**: All AJAX requests include WordPress nonces
- **Capability Checks**: Requires `manage_options` capability
- **Input Sanitization**: All user inputs are properly sanitized
- **Admin Protection**: Multiple checks prevent administrator deletion

## 📊 Performance Benchmarks

| User Count | Scanning Time | Deletion Time (100/batch) |
|------------|---------------|---------------------------|
| 10,000     | ~30 seconds   | ~5 minutes               |
| 100,000    | ~5 minutes    | ~45 minutes              |
| 1,000,000  | ~45 minutes   | ~7 hours                 |

*Times vary based on server performance and criteria complexity*

## 🚨 Safety Guidelines

### Before Running
1. **Create a full database backup**
2. **Test on a staging environment first**
3. **Verify your criteria settings carefully**
4. **Ensure you have administrator access via other means**

### During Operation
- **Don't close the browser tab** during processing
- **Monitor server resources** for large operations
- **Check error logs** if issues occur

### After Completion
- **Review the operation log** for any errors
- **Verify expected users were deleted**
- **Check site functionality**

## 🐛 Troubleshooting

### Common Issues

#### "Scan state lost" Error
- **Cause**: Transient expired or server restart
- **Solution**: Restart the cleanup process

#### Memory Limit Errors
- **Cause**: Insufficient PHP memory
- **Solution**: Reduce batch size or increase PHP memory limit

#### Timeout Errors
- **Cause**: Long-running operations
- **Solution**: Use smaller batch sizes or increase execution time

#### Network Errors
- **Cause**: AJAX timeout or connection issues
- **Solution**: Check network connection and server logs

### Debug Mode
Enable WordPress debug mode to get detailed error information:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## 📝 Changelog

### Version 1.2.0 (Current)
- ✅ Added WooCommerce coupon deletion functionality
- ✅ Added GitHub-based automatic updates
- ✅ Enhanced safety checks and error handling
- ✅ Improved batch processing with configurable sizes
- ✅ Added comprehensive progress tracking
- ✅ Better UI with warnings and descriptions
- ✅ Renamed to WordPress Bulk Cleanup Pro

### Version 1.1.0
- ✅ Added WooCommerce order deletion
- ✅ Implemented memory-efficient scanning
- ✅ Enhanced progress tracking

### Version 1.0.0
- Initial release with basic bulk deletion features
- Simple progress tracking
- Basic safety measures

## 🤝 Contributing

We welcome contributions! Please feel free to:
- Report bugs via GitHub Issues
- Submit feature requests
- Create pull requests for improvements
- Improve documentation

### Development Setup
1. Clone the repository: `git clone https://github.com/S4hk/wordpress-bulk-cleanup-pro.git`
2. Set up a local WordPress development environment
3. Install the plugin in development mode
4. Make your changes and test thoroughly

## 📄 License

This plugin is licensed under the [GPL v2 or later](https://www.gnu.org/licenses/gpl-2.0.html).

```
WordPress Bulk Cleanup Pro WordPress Plugin
Copyright (C) 2025

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
```

## ⚠️ Disclaimer

This plugin performs irreversible user deletion operations. The authors are not responsible for any data loss or damage resulting from the use of this plugin. Always backup your database before use and test thoroughly in a development environment.

## 📞 Support

- **GitHub Issues**: [Report bugs or request features](https://github.com/S4hk/wordpress-bulk-cleanup-pro/issues)
- **Documentation**: This README file contains comprehensive usage information
- **WordPress Forums**: Community support available

---

**Made with ❤️ for the WordPress community**
