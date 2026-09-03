from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]


def replace_once(path: Path, old: str, new: str) -> None:
    text = path.read_text(encoding='utf-8')
    count = text.count(old)
    if count != 1:
        raise SystemExit(f'{path}: expected exactly one match, found {count}: {old[:100]!r}')
    path.write_text(text.replace(old, new, 1), encoding='utf-8')


controller = ROOT / 'src' / 'Admin' / 'TransferController.php'
replace_once(
    controller,
    "use VisualDesignerManager\\Transfer\\PortablePackage;\n",
    "use VisualDesignerManager\\Transfer\\PortablePackage;\nuse VisualDesignerManager\\Transfer\\SchemaOneMigrator;\n",
)

replace_once(
    controller,
    """        try {
            $inspection = PortablePackage::inspect($stagedPath);
            $sha = hash_file('sha256', $stagedPath);
""",
    """        $migration = null;
        try {
            try {
                $inspection = PortablePackage::inspect($stagedPath);
            } catch (\\Throwable $nativeError) {
                try {
                    $converted = SchemaOneMigrator::convert($stagedPath);
                } catch (\\Throwable $migrationError) {
                    throw new \\RuntimeException(
                        'Pakken er hverken en gyldig native V2-pakke eller en migrerbar schema 1.0-pakke. ' .
                        'V2-validering: ' . $nativeError->getMessage() . ' Schema 1.0-validering: ' . $migrationError->getMessage()
                    );
                }

                $convertedPath = (string) ($converted['path'] ?? '');
                $convertedInspection = $converted['inspection'] ?? null;
                if ($convertedPath === '' || !is_file($convertedPath) || !is_array($convertedInspection)) {
                    throw new \\RuntimeException('Schema 1.0-konverteringen returnerede ikke en gyldig native V2-pakke.');
                }
                @unlink($stagedPath);
                $stagedPath = $convertedPath;
                $inspection = $convertedInspection;
                $migration = is_array($converted['migration'] ?? null) ? $converted['migration'] : [];
            }

            $sha = hash_file('sha256', $stagedPath);
""",
)

replace_once(
    controller,
    """                'inspection' => $inspection,
                'createdAt' => time(),
""",
    """                'inspection' => $inspection,
                'migration' => $migration,
                'createdAt' => time(),
""",
)

replace_once(
    controller,
    """        echo '<div class=\"notice notice-warning inline\"><p><strong>Bemærk:</strong> Beta.3 accepterer kun native V2 portable schema <code>' . esc_html(PortablePackage::SCHEMA_VERSION) . '</code>. En schema 1.0-pakke kræver den kontrollerede migrationsimport i RC-fasen.</p></div>';
""",
    """        echo '<div class=\"notice notice-info inline\"><p><strong>RC.1:</strong> Native V2 schema <code>' . esc_html(PortablePackage::SCHEMA_VERSION) . '</code> importeres direkte. Validerede schema 1.0-sitepakker konverteres isoleret til native V2 under preflight og importeres derefter gennem samme V2-importer.</p></div>';
""",
)

replace_once(
    controller,
    """        $source = is_array($manifest['source'] ?? null) ? $manifest['source'] : [];

        echo '<hr><h2>Preflight godkendt</h2>';
""",
    """        $source = is_array($manifest['source'] ?? null) ? $manifest['source'] : [];
        $migration = is_array($state['migration'] ?? null) ? $state['migration'] : [];
        $migrationWarnings = is_array($migration['warnings'] ?? null) ? array_values(array_filter(array_map('strval', $migration['warnings']))) : [];

        echo '<hr><h2>Preflight godkendt</h2>';
""",
)

replace_once(
    controller,
    """        self::row('Kildesite', (string) ($source['name'] ?? ''));
        self::row('Content SHA-256', (string) ($manifest['contentSha256'] ?? ''));
""",
    """        self::row('Kildesite', (string) ($source['name'] ?? ''));
        if ($migration !== []) {
            self::row('Kildeschema', (string) ($migration['sourceSchemaVersion'] ?? '1.0'));
            self::row('Konverteret til', (string) ($manifest['schemaVersion'] ?? PortablePackage::SCHEMA_VERSION));
            self::row('Kilde-VDM', (string) ($migration['sourceManagerVersion'] ?? ''));
        }
        self::row('Content SHA-256', (string) ($manifest['contentSha256'] ?? ''));
""",
)

replace_once(
    controller,
    """        echo '</tbody></table>';
        echo '<p><strong>Preflight har ikke ændret WordPress.</strong> Ved import remappes kilde-ID’er til mål-ID’er. Allerede importerede objekter fra samme kilde kan genbruges/opdateres.</p>';
""",
    """        echo '</tbody></table>';
        if ($migrationWarnings !== []) {
            echo '<h3>Migrationsbemærkninger</h3><ul>';
            foreach ($migrationWarnings as $warning) {
                echo '<li>' . esc_html($warning) . '</li>';
            }
            echo '</ul>';
        }
        echo '<p><strong>Preflight har ikke ændret WordPress.</strong> Ved import remappes kilde-ID’er til mål-ID’er. Allerede importerede objekter fra samme kilde kan genbruges/opdateres.</p>';
""",
)

plugin = ROOT / 'visual-designer-manager.php'
replace_once(plugin, ' * Version: 2.0.0-beta.3\n', ' * Version: 2.0.0-rc.1\n')
replace_once(plugin, "define('VDM_VERSION', '2.0.0-beta.3');", "define('VDM_VERSION', '2.0.0-rc.1');")

print('RC.1 schema 1.0 preflight integration applied')
