from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]

plugin = (ROOT / 'visual-designer-manager.php').read_text(encoding='utf-8')
core = (ROOT / 'src' / 'Core' / 'Plugin.php').read_text(encoding='utf-8')
repository = (ROOT / 'src' / 'Gallery' / 'GalleryRepository.php').read_text(encoding='utf-8')
admin = (ROOT / 'src' / 'Admin' / 'GalleryController.php').read_text(encoding='utf-8')
schema = (ROOT / 'src' / 'Model' / 'NodeSchema.php').read_text(encoding='utf-8')
renderer = (ROOT / 'src' / 'Frontend' / 'Renderer.php').read_text(encoding='utf-8')
gallery_renderer = (ROOT / 'src' / 'Frontend' / 'GalleryRenderer.php').read_text(encoding='utf-8')
gallery_frontend = (ROOT / 'src' / 'Frontend' / 'GalleryFrontendController.php').read_text(encoding='utf-8')
designer = (ROOT / 'assets' / 'designer.js').read_text(encoding='utf-8')
page_designer = (ROOT / 'src' / 'Admin' / 'DesignerController.php').read_text(encoding='utf-8')
template_designer = (ROOT / 'src' / 'Admin' / 'TemplateDesignerController.php').read_text(encoding='utf-8')
gallery_shell = (ROOT / 'templates' / 'single-gallery.php').read_text(encoding='utf-8')
gallery_admin_js = (ROOT / 'assets' / 'gallery-admin.js').read_text(encoding='utf-8')
gallery_admin_css = (ROOT / 'assets' / 'gallery-admin.css').read_text(encoding='utf-8')
css = (ROOT / 'assets' / 'frontend.css').read_text(encoding='utf-8')

version_match = re.search(r'Version:\s*2\.0\.0(?:-(alpha|beta|rc)\.(\d+))?', plugin)
version_ok = bool(version_match)
if version_match and version_match.group(1) == 'alpha':
    version_ok = int(version_match.group(2) or 0) >= 8
compact_schema = re.sub(r'\s+', '', schema)

checks = {
    'runtime version alpha.8 or newer': version_ok,
    'gallery post type and VDM meta': "public const POST_TYPE = 'vdm_gallery';" in repository and "'_vdm_gallery_images'" in repository and "'_vdm_gallery_summary'" in repository,
    'gallery album repository': 'normalizeIds' in repository and 'wp_attachment_is_image' in repository and 'menu_order' in repository and "'imageIds'" in repository,
    'gallery admin registration': 'register_post_type(GalleryRepository::POST_TYPE' in admin and 'vdm_save_gallery' in admin and 'Albumbilleder' in admin,
    'gallery media manager': all(token in admin for token in ('Vælg billeder', 'Fjern alle', 'vdm-gallery-image-ids')) and all(token in gallery_admin_js for token in ('wp.media', 'multiple:', 'dragstart', 'vdm-gallery-remove-image')),
    'gallery admin styling': '.vdm-gallery-image-list' in gallery_admin_css and '.vdm-gallery-admin-image' in gallery_admin_css,
    'gallery node schema': "publicconstGALLERIES='galleries';" in compact_schema and "self::EVENTS,self::VEHICLES,self::GALLERIES=>['x'=>0,'y'=>0,'w'=>12,'h'=>60]" in compact_schema and "'showCover'=>true" in compact_schema,
    'canonical gallery renderer': 'NodeSchema::GALLERIES' in renderer and 'GalleryRenderer::renderList' in renderer,
    'album list rendering': 'GalleryRepository::query' in gallery_renderer and 'vdm-gallery-albums' in gallery_renderer and 'Åbn album' in gallery_renderer,
    'album detail rendering': 'vdm-gallery-images' in gallery_renderer and 'figcaption' in gallery_renderer and 'renderDetail(int $postId)' in gallery_renderer,
    'designer gallery defaults': 'galleries: 60' in designer and "galleries: {x: 0, y: 0, w: 12, h: 60}" in designer and "node.type === 'galleries'" in designer,
    'designer gallery inspector': all(token in designer for token in ('Antal albummer', 'Kortbaggrund', 'Overskriftsfarve', 'Accentfarve', 'Vis cover')),
    'gallery palettes': "'galleries' => 'Billedgalleri'" in page_designer and "'galleries' => 'Billedgalleri'" in template_designer,
    'gallery shell routing': "is_singular(GalleryRepository::POST_TYPE)" in gallery_frontend and "templates/single-gallery.php" in gallery_frontend,
    'gallery shell canonical': 'GalleryRenderer::renderDetail($postId)' in gallery_shell and 'TemplateRepository::HEADER' in gallery_shell and 'TemplateRepository::FOOTER' in gallery_shell and 'wp_head();' in gallery_shell and 'wp_footer();' in gallery_shell,
    'gallery responsive CSS': all(token in css for token in ('.vdm-gallery-albums', '.vdm-gallery-album-card', '.vdm-gallery-images', '.vdm-gallery-image', '.vdm-node--galleries')) and 'grid-template-columns:repeat(4,minmax(0,1fr))' in css,
    'gallery booted': 'GalleryController::register();' in core and 'GalleryFrontendController::register();' in core and 'GalleryController::postType();' in core,
}

failed = [name for name, ok in checks.items() if not ok]
if failed:
    print('Alpha 8 Gallery contract: FAIL')
    for name in failed:
        print(' - ' + name)
    sys.exit(1)

print('Alpha 8 Gallery contract: PASS')
