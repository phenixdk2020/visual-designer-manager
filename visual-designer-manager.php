<?php
/**
 * Plugin Name: Visual Designer Manager
 * Plugin URI: https://github.com/phenixdk2020/visual-designer-manager
 * Update URI: https://github.com/phenixdk2020/visual-designer-manager
 * Description: Model-driven visual WordPress designer with responsive layouts, reusable modules, site settings, export/import and release QA.
 * Version: 2.0.0-rc.3
 * Author: Visual Designer Manager
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Text Domain: visual-designer-manager
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('VDM_VERSION', '2.0.0-rc.3');
define('VDM_FILE', __FILE__);
define('VDM_DIR', plugin_dir_path(__FILE__));
define('VDM_URL', plugin_dir_url(__FILE__));

require_once VDM_DIR . 'src/Support/Autoloader.php';

\VisualDesignerManager\Support\Autoloader::register(
    'VisualDesignerManager\\',
    VDM_DIR . 'src/'
);

register_activation_hook(VDM_FILE, [\VisualDesignerManager\Core\Plugin::class, 'activate']);
register_deactivation_hook(VDM_FILE, [\VisualDesignerManager\Core\Plugin::class, 'deactivate']);

\VisualDesignerManager\Core\Plugin::boot();
