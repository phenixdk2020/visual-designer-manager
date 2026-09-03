<?php

declare(strict_types=1);

namespace VisualDesignerManager\Core;

use VisualDesignerManager\Admin\AdminController;
use VisualDesignerManager\Http\RestController;

final class Plugin
{
    private static bool $booted = false;

    private function __construct()
    {
    }

    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }

        self::$booted = true;
        AdminController::register();
        RestController::register();
    }

    public static function activate(): void
    {
        update_option('vdm_schema_version', 2, true);
        update_option('vdm_plugin_version', VDM_VERSION, true);
    }

    public static function deactivate(): void
    {
        // No destructive work on deactivation.
    }
}
