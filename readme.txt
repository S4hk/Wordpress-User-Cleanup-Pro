=== WordPress Bulk Cleanup Pro ===
Contributors: S4hk
Tags: users, cleanup, bulk delete, woocommerce, admin tools, bulk cleanup
Requires at least: 5.6
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.2.3
License: GPL2+
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Advanced bulk cleanup tool for administrators. Delete users by missing names, email domains, or roles, and WooCommerce orders/coupons by status.

== Description ==

WordPress Bulk Cleanup Pro is a powerful WordPress plugin designed for administrators who need to efficiently manage and clean up their user database. The plugin provides safe, batch-based deletion of users, WooCommerce orders, and coupons based on various criteria.

**Key Features:**

* **User Management:**
  * Delete users without first and last names
  * Delete users by email domain filtering
  * Delete users by specific roles (administrators are always protected)

* **WooCommerce Integration:**
  * Delete orders by status (pending, completed, cancelled, etc.)
  * Delete coupons by status (published, draft, trash, etc.)
  * Complete removal of all related metadata

* **Safety & Performance:**
  * Batch processing with configurable batch sizes
  * Memory-efficient scanning for millions of records
  * Real-time progress tracking with visual progress bar
  * Administrator accounts are never deleted (safety protection)
  * Comprehensive logging of all operations

* **Advanced Features:**
  * Automatic updates from GitHub repository
  * AJAX-powered interface for smooth operation
  * Transient-based state management for large operations
  * Network error handling and recovery

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/wordpress-bulk-cleanup-pro` directory
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Navigate to Users > Bulk Cleanup Pro to configure and use the plugin

== Frequently Asked Questions ==

= Is it safe to use this plugin? =

Yes, the plugin includes multiple safety measures:
- Administrator accounts are never deleted
- Batch processing prevents timeouts
- Comprehensive logging of all operations
- Database backup is recommended before use

= Can I recover deleted users? =

No, deletions are permanent. Always backup your database before running cleanup operations.

= Does it work with large datasets? =

Yes, the plugin is designed to handle millions of users and orders through memory-efficient scanning and batch processing.

== Screenshots ==

1. Main settings page with all cleanup options
2. Progress tracking during cleanup operations
3. Real-time logging of deletion operations

== Changelog ==

= 1.2.3 =
* Stable tag updated to 1.2.3

= 1.2.0 =
* Added WooCommerce coupon deletion functionality
* Added GitHub-based automatic updates
* Improved scanning performance for large datasets
* Enhanced error handling and logging
* Added version display in admin interface
* Renamed to WordPress Bulk Cleanup Pro

= 1.1.0 =
* Added WooCommerce order deletion
* Improved batch processing
* Enhanced progress tracking

= 1.0.0 =
* Initial release
* User deletion by name and email domain criteria
* Role-based user deletion
* Basic batch processing

== Upgrade Notice ==

= 1.2.0 =
This version adds coupon deletion, automatic updates, and rebrands to WordPress Bulk Cleanup Pro. Backup your database before upgrading.
