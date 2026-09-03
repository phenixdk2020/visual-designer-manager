<?php
/**
 * Canonical VDM V2 page shell.
 *
 * @var array<string,mixed> $vdmPageDocument
 * @var array<string,mixed> $vdmHeaderDocument
 * @var array<string,mixed> $vdmFooterDocument
 * @var array<string,mixed> $vdmSiteDesign
 */

declare(strict_types=1);

use VisualDesignerManager\Frontend\Renderer;
use VisualDesignerManager\Storage\SiteDesignRepository;

if (!defined('ABSPATH')) {
    exit;
}

$style = SiteDesignRepository::cssVariables($vdmSiteDesign ?? null);
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class('vdm-site-shell-active'); ?>>
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
            <?php echo Renderer::render($vdmPageDocument); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
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
