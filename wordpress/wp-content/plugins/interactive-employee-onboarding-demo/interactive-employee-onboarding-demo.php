<?php
/**
 * Plugin Name: Interactive Employee Onboarding Demo
 * Description: Privacy-safe, independently rewritten onboarding flow demo with password gate, branching navigation, tutorial, dark mode and responsive step cards.
 * Version: 1.2.0
 * Author: Altan Yildirim
 * License: MIT
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('IEOD_VERSION', '1.2.0');
define('IEOD_FILE', __FILE__);
define('IEOD_DIR', plugin_dir_path(__FILE__));
define('IEOD_URL', plugin_dir_url(__FILE__));

require_once IEOD_DIR . 'includes/class-ieod-plugin.php';

register_activation_hook(__FILE__, ['IEOD_Plugin', 'activate']);
IEOD_Plugin::instance();
