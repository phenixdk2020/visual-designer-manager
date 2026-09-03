from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
css_path = ROOT / 'assets' / 'frontend.css'
css = css_path.read_text(encoding='utf-8')

marker = '.vdm-form-wrap{width:100%}'
if marker in css:
    print('Beta.1 form CSS already present')
    raise SystemExit(0)

block = r'''
.vdm-node--contact-form,.vdm-node--membership-form{overflow:visible}
.vdm-form-wrap{width:100%}
.vdm-form{width:100%;box-sizing:border-box;padding:var(--vdm-form-padding,20px);background:var(--vdm-form-background,#fff);color:var(--vdm-form-text,#222);border-radius:var(--vdm-form-radius,6px)}
.vdm-form-grid{display:grid;grid-template-columns:repeat(var(--vdm-form-columns,2),minmax(0,1fr));gap:var(--vdm-form-gap,16px);align-items:start}
.vdm-form-field{display:flex;flex-direction:column;gap:6px;min-width:0;margin:0;color:var(--vdm-form-label,#222);font-weight:600}
.vdm-form-field--full{grid-column:1/-1}
.vdm-form-label{display:block;line-height:1.35}
.vdm-form input[type="text"],.vdm-form input[type="email"],.vdm-form input[type="tel"],.vdm-form textarea,.vdm-form select{display:block;width:100%;max-width:none;min-width:0;box-sizing:border-box;border:1px solid var(--vdm-form-border,#d0d0d0);border-radius:var(--vdm-form-radius,6px);background:var(--vdm-form-field-background,#fff);color:var(--vdm-form-text,#222);font:inherit;line-height:1.4;padding:10px 12px;box-shadow:none}
.vdm-form input[type="text"],.vdm-form input[type="email"],.vdm-form input[type="tel"],.vdm-form select{min-height:44px}
.vdm-form textarea{min-height:150px;resize:vertical}
.vdm-form input:focus,.vdm-form textarea:focus,.vdm-form select:focus{outline:2px solid color-mix(in srgb,var(--vdm-form-accent,#2f4858) 45%,transparent);outline-offset:1px;border-color:var(--vdm-form-accent,#2f4858)}
.vdm-form-consent{display:flex;align-items:flex-start;gap:10px;color:var(--vdm-form-text,#222);font-weight:400;line-height:1.45;cursor:pointer}
.vdm-form-consent input{flex:0 0 auto;margin-top:.2em}
.vdm-form-actions{display:flex;align-items:center;justify-content:flex-start}
.vdm-form-submit{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:10px 20px;border:0;border-radius:var(--vdm-form-radius,6px);background:var(--vdm-form-accent,#2f4858);color:var(--vdm-form-button-text,#fff);font:inherit;font-weight:600;line-height:1.2;cursor:pointer;text-decoration:none}
.vdm-form-submit:hover{filter:brightness(.96)}
.vdm-form-submit:focus-visible{outline:2px solid var(--vdm-form-accent,#2f4858);outline-offset:3px}
.vdm-form-status{box-sizing:border-box;width:100%;margin:0 0 var(--vdm-form-gap,16px);padding:12px 14px;border-radius:var(--vdm-form-radius,6px);line-height:1.45}
.vdm-form-status--success{border:1px solid #00a32a;background:color-mix(in srgb,#00a32a 8%,#fff);color:#145523}
.vdm-form-status--error{border:1px solid #d63638;background:color-mix(in srgb,#d63638 8%,#fff);color:#8a2424}
.vdm-form-honeypot{position:absolute!important;left:-10000px!important;top:auto!important;width:1px!important;height:1px!important;overflow:hidden!important}
@media (max-width:782px){
    .vdm-form-grid{grid-template-columns:1fr}
    .vdm-form-field--full{grid-column:1}
    .vdm-form{padding:min(var(--vdm-form-padding,20px),16px)}
    .vdm-form-submit{width:100%}
}
'''.lstrip()

if css and not css.endswith('\n'):
    css += '\n'
css += block
css_path.write_text(css, encoding='utf-8')
print('Beta.1 form CSS added')
