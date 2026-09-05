<?php
/**
 * Canonical VDM V2 page shell.
 */

declare(strict_types=1);

use VisualDesignerManager\Frontend\Renderer;
use VisualDesignerManager\Storage\LayoutRepository;
use VisualDesignerManager\Storage\PreviewRepository;
use VisualDesignerManager\Storage\SiteDesignRepository;
use VisualDesignerManager\Storage\TemplateAssignmentRepository;
use VisualDesignerManager\Storage\TemplateRepository;

if (!defined('ABSPATH')) {
    exit;
}

$postId = get_queried_object_id();
$vdmPreviewDocument = $postId > 0 ? PreviewRepository::resolve($postId) : null;
$vdmPageDocument = is_array($vdmPreviewDocument) ? $vdmPreviewDocument : ($postId > 0 ? LayoutRepository::get($postId) : []);
$vdmPreviewActive = is_array($vdmPreviewDocument);
$vdmHeaderDocument = $postId > 0 ? TemplateAssignmentRepository::resolveDocument($postId, TemplateRepository::HEADER) : TemplateRepository::get(TemplateRepository::HEADER);
$vdmFooterDocument = $postId > 0 ? TemplateAssignmentRepository::resolveDocument($postId, TemplateRepository::FOOTER) : TemplateRepository::get(TemplateRepository::FOOTER);
$vdmHeaderSettings = $postId > 0 ? TemplateAssignmentRepository::resolveSettings($postId, TemplateRepository::HEADER) : [];
$vdmFooterSettings = $postId > 0 ? TemplateAssignmentRepository::resolveSettings($postId, TemplateRepository::FOOTER) : [];
$vdmSiteDesign = SiteDesignRepository::get();
$style = SiteDesignRepository::cssVariables($vdmSiteDesign);
$headerClasses = ['vdm-site-header'];
if (!empty($vdmHeaderSettings['sticky'])) {
    $headerClasses[] = 'is-sticky';
}
if (!empty($vdmHeaderSettings['overlay'])) {
    $headerClasses[] = 'is-overlay';
}
$headerWidth = max(320, min(2400, (int) ($vdmHeaderSettings['contentWidth'] ?? 1440)));
$footerWidth = max(320, min(2400, (int) ($vdmFooterSettings['contentWidth'] ?? 1440)));
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class('vdm-site-shell-active'); ?>>
<?php if (function_exists('wp_body_open')) { wp_body_open(); } ?>
<div class="vdm-site-shell<?php echo !empty($vdmHeaderSettings['overlay']) ? ' has-overlay-header' : ''; ?>" style="<?php echo esc_attr($style); ?>">
    <?php if ($vdmPreviewActive) : ?>
        <div class="vdm-preview-banner" role="status" style="position:relative;z-index:99999;padding:10px 16px;background:#fff3cd;border:1px solid #dba617;color:#1d2327;font:600 14px/1.4 sans-serif;text-align:center"><?php echo esc_html__('Ikke-gemt forhåndsvisning · kun synlig for dig', 'visual-designer-manager'); ?></div>
    <?php endif; ?>
    <?php if (($vdmHeaderDocument['nodes'] ?? []) !== []) : ?>
        <header class="<?php echo esc_attr(implode(' ', $headerClasses)); ?>" role="banner">
            <div class="vdm-site-region" style="--vdm-template-content-width:<?php echo esc_attr((string) $headerWidth); ?>px">
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
            <div class="vdm-site-region" style="--vdm-template-content-width:<?php echo esc_attr((string) $footerWidth); ?>px">
                <?php echo Renderer::render($vdmFooterDocument); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
        </footer>
    <?php endif; ?>
</div>
<?php wp_footer(); ?>
</body>
</html>
