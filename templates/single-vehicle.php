<?php
/**
 * Canonical VDM V2 Vehicle shell.
 */

declare(strict_types=1);

use VisualDesignerManager\Frontend\Renderer;
use VisualDesignerManager\Frontend\VehicleRenderer;
use VisualDesignerManager\Storage\SiteDesignRepository;
use VisualDesignerManager\Storage\TemplateRepository;

if (!defined('ABSPATH')) {
    exit;
}

$postId = get_queried_object_id();
$vdmHeaderDocument = TemplateRepository::get(TemplateRepository::HEADER);
$vdmFooterDocument = TemplateRepository::get(TemplateRepository::FOOTER);
$vdmSiteDesign = SiteDesignRepository::get();
$style = SiteDesignRepository::cssVariables($vdmSiteDesign);
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class('vdm-site-shell-active vdm-vehicle-shell-active'); ?>>
<?php if (function_exists('wp_body_open')) { wp_body_open(); } ?>
<div class="vdm-site-shell" style="<?php echo esc_attr($style); ?>">
    <?php if (($vdmHeaderDocument['nodes'] ?? []) !== []) : ?>
        <header class="vdm-site-header" role="banner">
            <div class="vdm-site-region">
                <?php echo Renderer::render($vdmHeaderDocument); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
        </header>
    <?php endif; ?>

    <main class="vdm-site-main" id="main" role="main">
        <div class="vdm-site-region">
            <?php echo VehicleRenderer::renderDetail($postId); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>
    </main>

    <?php if (($vdmFooterDocument['nodes'] ?? []) !== []) : ?>
        <footer class="vdm-site-footer" role="contentinfo">
            <div class="vdm-site-region">
                <?php echo Renderer::render($vdmFooterDocument); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
        </footer>
    <?php endif; ?>
</div>
<?php wp_footer(); ?>
</body>
</html>
