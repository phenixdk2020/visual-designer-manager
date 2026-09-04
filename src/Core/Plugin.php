<?php

declare(strict_types=1);

namespace VisualDesignerManager\Core;

use VisualDesignerManager\Admin\AdminController;
use VisualDesignerManager\Admin\DesignerController;
use VisualDesignerManager\Admin\EventController;
use VisualDesignerManager\Admin\GalleryController;
use VisualDesignerManager\Admin\ManualController;
use VisualDesignerManager\Admin\NavigationController;
use VisualDesignerManager\Admin\PageWorkflowController;
use VisualDesignerManager\Admin\ParityController;
use VisualDesignerManager\Admin\SiteDesignController;
use VisualDesignerManager\Admin\SiteSettingsController;
use VisualDesignerManager\Admin\TemplateAssignmentController;
use VisualDesignerManager\Admin\TemplateDesignerController;
use VisualDesignerManager\Admin\TransferController;
use VisualDesignerManager\Admin\UpdateCheckpointController;
use VisualDesignerManager\Admin\VehicleController;
use VisualDesignerManager\Frontend\EventFrontendController;
use VisualDesignerManager\Frontend\FrontendController;
use VisualDesignerManager\Frontend\GalleryFrontendController;
use VisualDesignerManager\Frontend\VehicleFrontendController;
use VisualDesignerManager\Forms\FormSubmissionController;
use VisualDesignerManager\Http\RestController;
use VisualDesignerManager\Http\PageWorkflowRestController;
use VisualDesignerManager\Update\GitHubUpdater;
use VisualDesignerManager\Update\UpdateCheckpointManager;

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
        ParityController::register();
        UpdateCheckpointController::register();
        PageWorkflowController::register();
        DesignerController::register();
        TemplateDesignerController::register();
        TemplateAssignmentController::register();
        SiteDesignController::register();
        SiteSettingsController::register();
        NavigationController::register();
        TransferController::register();
        EventController::register();
        VehicleController::register();
        GalleryController::register();
        ManualController::register();
        FrontendController::register();
        EventFrontendController::register();
        VehicleFrontendController::register();
        GalleryFrontendController::register();
        FormSubmissionController::register();
        RestController::register();
        PageWorkflowRestController::register();
        GitHubUpdater::register();
        UpdateCheckpointManager::register();
    }

    public static function activate(): void
    {
        update_option('vdm_schema_version', 2, true);
        update_option('vdm_plugin_version', VDM_VERSION, true);
        EventController::postType();
        VehicleController::postType();
        GalleryController::postType();
        flush_rewrite_rules(false);
    }

    public static function deactivate(): void
    {
        flush_rewrite_rules(false);
    }
}
