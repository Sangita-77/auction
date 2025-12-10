# Auction Plugin Documentation

## Table of Contents

1. [Overview](#overview)
2. [Installation](#installation)
3. [Features](#features)
4. [Configuration](#configuration)
5. [Usage Guide](#usage-guide)
6. [Developer Guide](#developer-guide)
7. [File Structure](#file-structure)
8. [Database Schema](#database-schema)
9. [Hooks and Filters](#hooks-and-filters)
10. [API Reference](#api-reference)
11. [Troubleshooting](#troubleshooting)

---

## Overview

The **Auction Plugin** is a comprehensive WooCommerce extension that transforms regular products into auction items. It provides a complete bidding system with automatic bidding, sealed auctions, watchlists, and real-time countdown timers.

### Version
- **Current Version:** 1.0.0
- **Plugin Slug:** auction
- **Text Domain:** auction
- **Author:** Sangita

### Requirements
- WordPress 5.0 or higher
- WooCommerce 3.0 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher

---

## Installation

### Manual Installation

1. Upload the `auction` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Ensure WooCommerce is installed and activated
4. Go to **Auction > Settings** to configure the plugin

### Activation Process

Upon activation, the plugin will:
- Create the `wp_auction_bids` database table
- Register hourly cron events for processing ending auctions
- Flush rewrite rules for custom endpoints

---

## Features

### Core Features

#### 1. **Product Auction Configuration**
- Enable/disable auction functionality per product
- Set start and end times
- Configure start price, minimum increment, and reserve price
- Support for sealed auctions (hidden bids)
- Buy Now option with custom pricing

#### 2. **Bidding System**
- **Manual Bidding:** Users place bids manually
- **Automatic Bidding (Proxy Bidding):** Users set a maximum bid, and the system automatically bids on their behalf
- **Bid Increment Modes:**
  - Simple: Fixed increment amount
  - Advanced: Rule-based increments based on price ranges

#### 3. **Auction Types**
- **Standard Auctions:** Open bidding with visible bid history
- **Sealed Auctions:** Hidden bids until auction ends
- **Scheduled Auctions:** Future start times
- **Active Auctions:** Currently running auctions

#### 4. **User Features**
- **Watchlist:** Save favorite auctions
- **My Account Integration:** 
  - "My Auctions" endpoint showing participated auctions
  - "Watchlist" endpoint for saved auctions
- **Bid History:** View all bids placed on an auction
- **Registration Modal:** Quick registration without leaving the page

#### 5. **Frontend Display**
- Real-time countdown timers
- Current bid display
- Next bid amount calculation
- Auction badges on product listings
- Related auctions section
- Responsive design

#### 6. **Admin Features**
- Product meta panel for auction configuration
- Settings page for global options
- Bid management
- Auction status tracking

#### 7. **Event Management**
- Automatic processing of ended auctions
- Winner notification emails
- Reserve price validation
- Auction status tracking

---

## Configuration

### Global Settings

Navigate to **Auction > Settings** to configure:

#### Display Options
- **Show Countdown:** Enable countdown timers on product pages
- **Show Countdown in Loop:** Display countdown on shop/category pages
- **Custom Badge:** Upload custom auction badge image
- **Bid Username Display:** Choose between 'full', 'masked', or 'hidden'

#### Catalog Options
- **Show on Shop:** Display auction products on main shop page
- **Hide Ended Auctions:** Automatically hide ended auctions from catalog
- **Hide Future Auctions:** Hide scheduled auctions until they start
- **Hide Out of Stock:** Hide out-of-stock auction products

#### Watchlist
- **Enable Watchlist:** Allow users to save auctions to watchlist

### Product-Level Configuration

When editing a WooCommerce product, you'll find an **"Auction"** tab with the following options:

#### Basic Settings
- **Enable Auction:** Toggle auction functionality
- **Auction Condition:** Product condition (New, Used, etc.)
- **Auction Type:** Standard or Sealed
- **Sealed Auction:** Hide bidder identities

#### Schedule
- **Start Time:** When the auction begins
- **End Time:** When the auction closes

#### Pricing
- **Start Price:** Initial bidding price
- **Minimum Increment:** Minimum bid increase amount
- **Reserve Price:** Minimum winning bid (optional)
- **Buy Now Enabled:** Allow direct purchase
- **Buy Now Price:** Fixed purchase price

#### Bid Increment Options
- **Override Bid Options:** Use custom increment settings
- **Bid Increment Mode:** Simple or Advanced
- **Automatic Increment Value:** Default increment for automatic bidding
- **Automatic Increment Rules:** Advanced rules for price-based increments

#### Advanced Options
- **Override Fee Options:** Custom fee settings
- **Override Commission Options:** Custom commission settings
- **Override Reschedule Options:** Custom rescheduling rules
- **Override Overtime Options:** Custom overtime bidding rules

---

## Usage Guide

### For Store Administrators

#### Creating an Auction Product

1. Go to **Products > Add New**
2. Create a standard WooCommerce product
3. Navigate to the **"Auction"** tab
4. Enable auction functionality
5. Configure auction settings:
   - Set start and end times
   - Define pricing (start price, increments, reserve)
   - Choose auction type (standard or sealed)
   - Configure bid increments
6. Publish the product

#### Managing Auctions

- View all auction products in the Products list
- Monitor bid activity through the product's auction panel
- Process ended auctions automatically (via cron)
- Manually check auction status in product meta

### For Customers

#### Placing a Bid

1. Navigate to an auction product page
2. Review current bid and next bid amount
3. Click "Place Bid" button
4. Enter your bid amount (must meet minimum requirement)
5. Optionally enable automatic bidding with a maximum amount
6. Confirm your bid
7. Receive instant feedback on bid status

#### Automatic Bidding

1. When placing a bid, check "Enable automatic bidding"
2. Enter your maximum bid amount
3. The system will automatically bid on your behalf up to your maximum
4. You'll be notified if you're outbid

#### Using Watchlist

1. Click the watchlist button on any auction product
2. View saved auctions in **My Account > Watchlist**
3. Use the `[auction_watchlist]` shortcode to display watchlist anywhere

#### Viewing Auction History

- **My Account > My Auctions:** See all auctions you've participated in
- Product page: View bid history for the current auction
- Bid history shows bidder names (masked or full based on settings), amounts, and timestamps

---

## Developer Guide

### File Structure

```
auction/
├── auction.php                          # Main plugin file
├── assets/
│   ├── css/
│   │   ├── admin-pages.css             # Admin settings page styles
│   │   ├── admin-product.css           # Product edit page styles
│   │   └── frontend.css                 # Frontend styles
│   └── js/
│       ├── admin-pages.js              # Admin settings JavaScript
│       ├── admin-product.js            # Product edit JavaScript
│       └── frontend.js                 # Frontend JavaScript
├── includes/
│   ├── admin/
│   │   ├── class-auction-admin.php    # Admin bootstrap
│   │   ├── product/
│   │   │   ├── class-auction-product-tabs.php
│   │   │   └── views/
│   │   │       └── html-auction-product-panel.php
│   │   └── settings/
│   │       └── class-auction-admin-menu.php
│   ├── class-auction-account.php       # My Account integration
│   ├── class-auction-bid-manager.php   # Bid processing logic
│   ├── class-auction-event-manager.php # Auction event processing
│   ├── class-auction-install.php      # Installation routines
│   ├── class-auction-loader.php       # Plugin loader
│   ├── class-auction-product-helper.php # Product utilities
│   ├── class-auction-settings.php     # Settings management
│   └── frontend/
│       └── class-auction-frontend.php  # Frontend integration
└── templates/
    └── frontend/
        └── single-auction-panel.php    # Single product auction template
```

### Core Classes

#### `Auction_Plugin`
Main plugin class implementing singleton pattern.

**Methods:**
- `init()`: Bootstrap the plugin
- `activate()`: Activation hook
- `deactivate()`: Deactivation hook

#### `Auction_Product_Helper`
Utility class for auction product operations.

**Key Methods:**
- `is_auction_product( WC_Product $product ): bool` - Check if product has auction enabled
- `get_config( WC_Product $product ): array` - Get auction configuration
- `get_runtime_state( WC_Product $product ): array` - Get current auction state
- `set_runtime_state( WC_Product $product, array $state ): void` - Update auction state
- `get_auction_status( array $config ): string` - Get status (active/scheduled/ended)
- `get_manual_increment( array $config ): float` - Get manual increment amount
- `get_automatic_increment( array $config, float $current_bid ): float` - Get auto increment

#### `Auction_Bid_Manager`
Handles all bid-related operations.

**Key Methods:**
- `place_bid( array $args ): array|WP_Error` - Process a new bid
- `get_leading_bid( int $product_id ): ?array` - Get current winning bid
- `get_bid_history( int $product_id, int $limit, bool $include_outbid ): array` - Get bid history
- `generate_session_id(): string` - Generate session ID for anonymous bidders

#### `Auction_Event_Manager`
Processes auction events and notifications.

**Key Methods:**
- `process_ending_auctions(): void` - Process auctions that have ended
- `maybe_process_due_auctions(): void` - Opportunistic processing on page load

#### `Auction_Frontend`
Frontend integration and display.

**Key Methods:**
- `render_single_product_auction_panel(): void` - Display auction panel
- `ajax_place_bid(): void` - AJAX handler for bid submission
- `ajax_toggle_watchlist(): void` - AJAX handler for watchlist

#### `Auction_Settings`
Settings management wrapper.

**Key Methods:**
- `all(): array` - Get all settings
- `get( string $key, $default = null )` - Get single setting
- `is_enabled( string $key, bool $default = false ): bool` - Check if setting is enabled

---

## Database Schema

### Table: `wp_auction_bids`

Stores all bid records.

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT UNSIGNED | Primary key, auto-increment |
| `product_id` | BIGINT UNSIGNED | WooCommerce product ID |
| `user_id` | BIGINT UNSIGNED | WordPress user ID (NULL for guests) |
| `session_id` | VARCHAR(64) | Session identifier for anonymous bidders |
| `bid_amount` | DECIMAL(19,4) | Bid amount |
| `max_auto_amount` | DECIMAL(19,4) | Maximum automatic bid (NULL if manual) |
| `is_auto` | TINYINT(1) | Whether this is an automatic bid |
| `status` | VARCHAR(20) | Bid status: 'active', 'outbid' |
| `ip_address` | VARCHAR(100) | Bidder IP address |
| `user_agent` | TEXT | Bidder user agent |
| `created_at` | DATETIME | Bid creation timestamp |
| `updated_at` | DATETIME | Last update timestamp |

**Indexes:**
- PRIMARY KEY (`id`)
- KEY `product_status` (`product_id`, `status`)
- KEY `user_lookup` (`user_id`)
- KEY `session_lookup` (`session_id`)

### Product Meta Keys

All auction-related product meta uses the `_auction_` prefix:

- `_auction_enabled` - 'yes' if auction is enabled
- `_auction_condition` - Product condition
- `_auction_type` - Auction type
- `_auction_sealed` - 'yes' for sealed auctions
- `_auction_start_time` - Auction start datetime
- `_auction_end_time` - Auction end datetime
- `_auction_start_price` - Starting bid price
- `_auction_min_increment` - Minimum bid increment
- `_auction_reserve_price` - Reserve price
- `_auction_buy_now_enabled` - 'yes' if buy now is enabled
- `_auction_buy_now_price` - Buy now price
- `_auction_automatic_bidding` - 'yes' if automatic bidding is enabled
- `_auction_bid_increment_mode` - 'simple' or 'advanced'
- `_auction_automatic_increment_value` - Default auto increment
- `_auction_automatic_increment_rules` - JSON array of increment rules
- `_auction_current_bid` - Current winning bid amount
- `_auction_winning_bid_id` - ID of winning bid record
- `_auction_winning_user_id` - User ID of winner
- `_auction_winning_session_id` - Session ID of winner (if guest)
- `_auction_proxy_max` - Maximum proxy bid amount
- `_auction_proxy_user_id` - User ID with active proxy bid
- `_auction_proxy_bid_id` - Bid ID of active proxy bid
- `_auction_processed` - 'yes' if auction has been processed
- `_auction_status_flag` - Processing status flag
- `_auction_winner_user_id` - Final winner user ID
- `_auction_winner_session_id` - Final winner session ID
- `_auction_winner_name` - Formatted winner name
- `_auction_winner_amount` - Final winning amount
- `_auction_winner_time` - Winning bid timestamp
- `_auction_winning_bid_id` - Final winning bid ID

---

## Hooks and Filters

### Actions

#### `auction_check_ending_events`
Fired hourly to process ending auctions.

**Usage:**
```php
add_action( 'auction_check_ending_events', 'my_custom_processing' );
```

#### `woocommerce_single_product_summary`
Renders auction panel on product page (priority 25).

#### `woocommerce_after_shop_loop_item`
Renders auction badge/countdown in product loop (priority 20).

### Filters

#### `auction_realtime_process_cooldown`
Adjust the cooldown period for opportunistic auction processing.

**Parameters:**
- `$cooldown` (int) - Cooldown in seconds (default: 60)

**Usage:**
```php
add_filter( 'auction_realtime_process_cooldown', function( $cooldown ) {
    return 30; // Process every 30 seconds instead of 60
} );
```

#### `auction_register_page_url`
Filter the registration page URL.

**Parameters:**
- `$url` (string) - Default registration URL

**Usage:**
```php
add_filter( 'auction_register_page_url', function( $url ) {
    return 'https://example.com/custom-register';
} );
```

#### `auction_enable_registration_modal`
Control whether registration modal is enabled.

**Parameters:**
- `$enabled` (bool) - Default: true

**Usage:**
```php
add_filter( 'auction_enable_registration_modal', '__return_false' );
```

#### `woocommerce_is_purchasable`
Controls if auction products can be purchased directly (when buy now is disabled).

#### `woocommerce_product_single_add_to_cart_text`
Filters "Add to Cart" button text for auction products.

#### `woocommerce_product_add_to_cart_text`
Filters "Add to Cart" button text in product loops.

#### `woocommerce_loop_add_to_cart_link`
Filters the add to cart link in product loops.

#### `woocommerce_is_sold_individually`
Forces auction products to be sold individually.

#### `woocommerce_related_products`
Filters related product IDs to show only auction products.

#### `woocommerce_product_is_visible`
Controls visibility of ended auctions in catalog.

---

## API Reference

### AJAX Endpoints

#### `auction_place_bid`
Place a bid on an auction product.

**Request:**
```javascript
{
    action: 'auction_place_bid',
    nonce: '...',
    product_id: 123,
    bid_amount: 100.00,
    is_auto: 0, // or 1
    max_auto_amount: 150.00 // if is_auto = 1
}
```

**Response (Success):**
```json
{
    "success": true,
    "data": {
        "result": "accepted",
        "current_bid": 100.00,
        "was_outbid": false
    }
}
```

**Response (Error):**
```json
{
    "success": false,
    "data": {
        "message": "Your bid must be at least $105.00."
    }
}
```

#### `auction_toggle_watchlist`
Add or remove an auction from watchlist.

**Request:**
```javascript
{
    action: 'auction_toggle_watchlist',
    nonce: '...',
    product_id: 123
}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "action": "added", // or "removed"
        "watchlisted": true
    }
}
```

### Shortcodes

#### `[auction_watchlist]`
Displays the current user's watchlist.

**Usage:**
```
[auction_watchlist]
```

**Output:**
- Unordered list of watchlisted auction products
- Links to product pages
- Empty message if watchlist is empty
- Login prompt if user is not logged in

#### `[auction_register_form]`
Displays a registration form.

**Usage:**
```
[auction_register_form]
```

**Output:**
- Registration form with fields:
  - First Name
  - Last Name
  - Email
  - Password
  - Confirm Password

### JavaScript API

#### `AuctionFrontendConfig`
Global JavaScript object containing configuration.

**Properties:**
- `ajax_url` - WordPress AJAX URL
- `nonce` - Security nonce
- `session_id` - Current session ID
- `register_form` - Registration form HTML
- `currency` - Currency formatting settings
- `i18n` - Internationalization strings

**Usage:**
```javascript
jQuery.post( AuctionFrontendConfig.ajax_url, {
    action: 'auction_place_bid',
    nonce: AuctionFrontendConfig.nonce,
    product_id: 123,
    bid_amount: 100.00
} );
```

---

## Troubleshooting

### Common Issues

#### 1. Bids Not Processing
**Problem:** Bids are submitted but not updating the current bid.

**Solutions:**
- Check that the auction is active (not scheduled or ended)
- Verify minimum increment requirements
- Check database table exists: `wp_auction_bids`
- Review PHP error logs

#### 2. Countdown Not Displaying
**Problem:** Countdown timer not showing on product pages.

**Solutions:**
- Ensure "Show Countdown" is enabled in settings
- Check that end time is set on the product
- Verify JavaScript is loading (check browser console)
- Clear browser cache

#### 3. Cron Events Not Running
**Problem:** Ended auctions not being processed automatically.

**Solutions:**
- Verify cron is enabled: `wp_next_scheduled( 'auction_check_ending_events' )`
- Check WordPress cron is working
- Consider using a real cron job instead of WP-Cron
- Manually trigger: `Auction_Event_Manager::instance()->process_ending_auctions()`

#### 4. Watchlist Not Working
**Problem:** Watchlist button not responding.

**Solutions:**
- Ensure user is logged in
- Check "Enable Watchlist" in settings
- Verify AJAX nonce is correct
- Check browser console for JavaScript errors

#### 5. Registration Modal Not Showing
**Problem:** Registration modal doesn't appear when clicking bid button.

**Solutions:**
- Check `auction_enable_registration_modal` filter
- Verify user is not logged in
- Check JavaScript errors in console
- Ensure registration form shortcode is working

### Debug Mode

Enable WordPress debug mode to see detailed error messages:

```php
// wp-config.php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
```

Check logs in `/wp-content/debug.log`.

### Database Queries

Check bid data directly:

```sql
-- View all bids for a product
SELECT * FROM wp_auction_bids 
WHERE product_id = 123 
ORDER BY created_at DESC;

-- View active bids
SELECT * FROM wp_auction_bids 
WHERE product_id = 123 
AND status = 'active'
ORDER BY bid_amount DESC;

-- View user's bids
SELECT * FROM wp_auction_bids 
WHERE user_id = 456 
ORDER BY created_at DESC;
```

---

## Support

For issues, questions, or contributions, please contact the plugin author or refer to the plugin repository.

---

## Changelog

### Version 1.0.0
- Initial release
- Core auction functionality
- Automatic bidding support
- Watchlist feature
- My Account integration
- Admin settings page
- Frontend auction display
- Event processing system

---

## License

This plugin is proprietary software. All rights reserved.

---

**Last Updated:** 2024

