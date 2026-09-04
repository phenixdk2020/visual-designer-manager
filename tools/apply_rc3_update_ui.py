from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
path = ROOT / 'src/Admin/ParityController.php'
text = path.read_text(encoding='utf-8')

old_use = 'use VisualDesignerManager\\Transfer\\PortableExporter;\n'
new_use = old_use + 'use VisualDesignerManager\\Update\\GitHubUpdater;\n'
if old_use not in text:
    raise SystemExit('PortableExporter use marker not found')
if 'use VisualDesignerManager\\Update\\GitHubUpdater;' not in text:
    text = text.replace(old_use, new_use, 1)

old = '''    public static function updates(): void
    {
        self::guard('update_plugins');
        self::open('Opdateringer', 'Versionsstatus for Visual Designer Manager.');
        echo '<div class="card" style="max-width:760px"><h2>Installeret version</h2><p><strong>' . esc_html(VDM_VERSION) . '</strong></p>';
        echo '<p>RC.2 bruger GitHub Actions som kontrolleret build- og QA-kanal. En pakke må først installeres, når både QA og package-workflow er grønne.</p>';
        echo '<p><a class="button" href="' . esc_url('https://github.com/phenixdk2020/visual-designer-manager/actions') . '" target="_blank" rel="noopener noreferrer">Åbn build-status på GitHub</a> ';
        echo '<a class="button" href="' . esc_url(admin_url('plugins.php')) . '">WordPress plugins</a></p></div>';
        self::close();
    }
'''
new = '''    public static function updates(): void
    {
        self::guard('update_plugins');
        $force = isset($_GET['vdm_update_check']);
        $status = GitHubUpdater::status($force);
        self::open('Opdateringer', 'Kontroller og installer publicerede Visual Designer Manager-versioner direkte fra GitHub.');

        $check = sanitize_key((string) ($_GET['vdm_update_check'] ?? ''));
        $message = sanitize_text_field((string) ($_GET['vdm_update_message'] ?? ''));
        if ($check === 'installed') {
            echo '<div class="notice notice-success inline"><p>Visual Designer Manager blev opdateret til <strong>' . esc_html((string) ($_GET['vdm_update_version'] ?? VDM_VERSION)) . '</strong>.</p></div>';
        } elseif ($check === 'install-error') {
            echo '<div class="notice notice-error inline"><p>Opdateringen fejlede' . ($message !== '' ? ': ' . esc_html($message) : '.') . '</p></div>';
        } elseif ($check === 'error') {
            echo '<div class="notice notice-error inline"><p>GitHub-manifestet kunne ikke hentes eller valideres.</p></div>';
        } elseif ($check === 'current') {
            echo '<div class="notice notice-success inline"><p>Du har allerede den seneste publicerede version.</p></div>';
        } elseif ($check === 'available') {
            echo '<div class="notice notice-info inline"><p>En nyere version er tilgængelig.</p></div>';
        }

        echo '<div class="card" style="max-width:820px"><h2>Versionsstatus</h2>';
        echo '<table class="widefat striped"><tbody>';
        echo '<tr><th style="width:220px">Installeret</th><td><strong>' . esc_html(VDM_VERSION) . '</strong></td></tr>';
        echo '<tr><th>Seneste på GitHub</th><td>' . ($status['ok'] ? '<strong>' . esc_html($status['latest']) . '</strong>' : '<span style="color:#b32d2e">Kunne ikke læses</span>') . '</td></tr>';
        echo '<tr><th>Status</th><td>';
        if (!$status['ok']) {
            echo '<strong style="color:#b32d2e">GitHub ikke tilgængelig</strong>';
        } elseif ($status['available']) {
            echo '<strong>Opdatering tilgængelig</strong>';
        } else {
            echo '<strong>Opdateret</strong>';
        }
        echo '</td></tr></tbody></table>';
        echo '<p style="margin-top:16px">' . GitHubUpdater::checkButtonHtml() . ' ' . GitHubUpdater::installButtonHtml() . ' ';
        echo '<a class="button" href="' . esc_url(admin_url('plugins.php')) . '">WordPress plugins</a> ';
        echo '<a class="button" href="' . esc_url('https://github.com/phenixdk2020/visual-designer-manager/actions') . '" target="_blank" rel="noopener noreferrer">GitHub build-status</a></p>';
        echo '<p><strong>Sikkerhed:</strong> VDM installerer kun pakken fra det publicerede <code>update.json</code>, validerer SHA-256 før installation og opretter automatisk en programbackup før opdatering.</p>';
        echo '</div>';
        self::close();
    }
'''
if old not in text:
    raise SystemExit('Updates method marker not found')
text = text.replace(old, new, 1)
text = text.replace('<h2>RC.2 test</h2>', '<h2>RC.3 test</h2>', 1)
text = text.replace('Sammenlign den migrerede installation med referenceinstallationen på desktop, laptop, tablet og mobil. Ingen manuel efterstyling skal være nødvendig for et godkendt resultat.', 'Sammenlign den migrerede installation med referenceinstallationen på desktop, laptop, tablet og mobil. Kontrollér desuden at Opdateringer kan læse GitHub-manifestet. Ingen manuel efterstyling skal være nødvendig for et godkendt resultat.', 1)
path.write_text(text, encoding='utf-8')
print('RC.3 update UI integration applied')
