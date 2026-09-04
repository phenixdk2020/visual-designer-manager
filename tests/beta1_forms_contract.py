from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]

plugin = (ROOT / 'visual-designer-manager.php').read_text(encoding='utf-8')
core = (ROOT / 'src' / 'Core' / 'Plugin.php').read_text(encoding='utf-8')
schema = (ROOT / 'src' / 'Model' / 'NodeSchema.php').read_text(encoding='utf-8')
renderer = (ROOT / 'src' / 'Frontend' / 'Renderer.php').read_text(encoding='utf-8')
form_renderer = (ROOT / 'src' / 'Frontend' / 'FormRenderer.php').read_text(encoding='utf-8')
submission = (ROOT / 'src' / 'Forms' / 'FormSubmissionController.php').read_text(encoding='utf-8')
designer = (ROOT / 'assets' / 'designer.js').read_text(encoding='utf-8')
page_designer = (ROOT / 'src' / 'Admin' / 'DesignerController.php').read_text(encoding='utf-8')
template_designer = (ROOT / 'src' / 'Admin' / 'TemplateDesignerController.php').read_text(encoding='utf-8')
css = (ROOT / 'assets' / 'frontend.css').read_text(encoding='utf-8')

version_match = re.search(r'Version:\s*2\.0\.0(?:-(alpha|beta|rc)\.(\d+))?', plugin)
version_ok = False
if version_match:
    phase = version_match.group(1)
    number = int(version_match.group(2) or 0)
    if phase is None or phase == 'rc':
        version_ok = True
    elif phase == 'beta':
        version_ok = number >= 1

compact_schema = re.sub(r'\s+', '', schema)

checks = {
    'runtime version beta.1 or newer': version_ok,
    'form node types': (
        "publicconstCONTACT_FORM='contact-form';" in compact_schema
        and "publicconstMEMBERSHIP_FORM='membership-form';" in compact_schema
        and "self::CONTACT_FORM=>['x'=>0,'y'=>0,'w'=>12,'h'=>100]" in compact_schema
        and "self::MEMBERSHIP_FORM=>['x'=>0,'y'=>0,'w'=>12,'h'=>128]" in compact_schema
        and "'submitLabel'=>'Sendbesked'" not in compact_schema
        and "'submitLabel'=>'Send besked'" in schema
        and "'submitLabel'=>'Send indmeldelse'" in schema
        and "'requireConsent'=>true" in compact_schema
    ),
    'form props normalized': (
        "in_array($type,[self::CONTACT_FORM,self::MEMBERSHIP_FORM],true)" in compact_schema
        and "'fieldBackground'=>self::color" in compact_schema
        and "'borderColor'=>self::color" in compact_schema
        and "'messageRows'=>self::int($props,'messageRows'" in compact_schema
        and "'consentText'=>sanitize_text_field" in compact_schema
        and "'recipient'=>sanitize_email" in compact_schema
        and "'sendReceipt'=>" in compact_schema
    ),
    'canonical form renderer wired': 'FormRenderer::render($type' in renderer and 'NodeSchema::CONTACT_FORM' in renderer and 'NodeSchema::MEMBERSHIP_FORM' in renderer,
    'canonical form style variables': all(token in renderer for token in (
        '--vdm-form-columns:', '--vdm-form-background:', '--vdm-form-field-background:',
        '--vdm-form-border:', '--vdm-form-accent:', '--vdm-form-button-text:',
    )),
    'form renderer structure': all(token in form_renderer for token in (
        "admin_url('admin-post.php')", "wp_nonce_field('vdm_submit_form_' . $nodeId",
        'name="vdm_page_id"', 'name="vdm_form_id"', 'name="vdm_form_type"',
        'name="vdm_website"', 'vdm-form-grid', 'vdm-form-submit', 'Send indmeldelse',
    )),
    'server validated submission': all(token in submission for token in (
        'admin_post_vdm_submit_form', 'admin_post_nopriv_vdm_submit_form',
        "wp_verify_nonce($nonce, 'vdm_submit_form_' . $formId)", 'LayoutRepository::get($pageId)',
        "(string) ($node['id'] ?? '') === $formId", "(string) ($node['type'] ?? '') === $type",
        'sanitize_email', 'sanitize_textarea_field', 'wp_mail(',
    )),
    'recipient cannot be posted': "get_option('vdm_contact_email'" in submission and "get_option('admin_email'" in submission and 'vdm_recipient' not in submission and "$_POST['recipient']" not in submission,
    'safe redirect': 'wp_validate_redirect' in submission and 'wp_safe_redirect($target, 303)' in submission,
    'no form data persistence': 'update_post_meta(' not in submission and 'wp_insert_post(' not in submission,
    'forms booted': 'FormSubmissionController::register();' in core,
    'page palette forms': "'contact-form' => 'Kontaktformular'" in page_designer and "'membership-form' => 'Bliv medlem'" in page_designer,
    'forms excluded from header footer palette': "'contact-form' =>" not in template_designer and "'membership-form' =>" not in template_designer,
    'designer form defaults': all(token in designer for token in (
        "'contact-form': 100", "'membership-form': 128", "submitLabel: 'Send besked'",
        "submitLabel: 'Send indmeldelse'", "['contact-form', 'membership-form'].includes(node.type)",
        "field('Samtykketekst'", "field('Knaptekst'",
    )),
    'designer blocks preview submission': "canvas.addEventListener('submit', event => event.preventDefault());" in designer,
    'canonical form CSS': all(token in css for token in (
        '.vdm-node--contact-form,.vdm-node--membership-form', '.vdm-form-grid', '.vdm-form textarea',
        '.vdm-form-consent', '.vdm-form-submit', '.vdm-form-status--success', '.vdm-form-honeypot',
        '@media (max-width:782px)', '.vdm-form-grid{grid-template-columns:1fr}',
    )),
}

failed = [name for name, ok in checks.items() if not ok]
if failed:
    print('Beta 1 Forms contract: FAIL')
    for name in failed:
        print(' - ' + name)
    sys.exit(1)

print('Beta 1 Forms contract: PASS')
