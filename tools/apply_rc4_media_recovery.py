from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def replace_once(path: str, old: str, new: str) -> None:
    file = ROOT / path
    text = file.read_text(encoding='utf-8')
    if old not in text:
        raise SystemExit(f'Patch marker not found in {path}: {old[:120]!r}')
    file.write_text(text.replace(old, new, 1), encoding='utf-8')


# Version bootstrap.
replace_once('visual-designer-manager.php', ' * Version: 2.0.0-rc.3', ' * Version: 2.0.0-rc.4')
replace_once("visual-designer-manager.php", "define('VDM_VERSION', '2.0.0-rc.3');", "define('VDM_VERSION', '2.0.0-rc.4');")

# Schema 1.0 preflight: recover URL-only upload images before canonical conversion.
replace_once(
    'src/Transfer/SchemaOneMigrator.php',
    """            $mediaPayload = self::readJson($zip, 'media/media-index.json');

            $warnings = is_array($legacy['warnings'] ?? null) ? $legacy['warnings'] : [];
            $catalog = self::moduleCatalog($modulesPayload);
""",
    """            $mediaPayload = self::readJson($zip, 'media/media-index.json');

            $recovery = LegacyMediaRecovery::recover(
                $site,
                $pagesPayload,
                $templatesPayload,
                (array) ($mediaPayload['records'] ?? [])
            );
            $pagesPayload = is_array($recovery['pagesPayload'] ?? null) ? $recovery['pagesPayload'] : $pagesPayload;
            $templatesPayload = is_array($recovery['templatesPayload'] ?? null) ? $recovery['templatesPayload'] : $templatesPayload;
            foreach ((array) ($recovery['tempFiles'] ?? []) as $recoveryTemp) {
                if (is_string($recoveryTemp) && $recoveryTemp !== '') {
                    $tempFiles[] = $recoveryTemp;
                }
            }

            $warnings = is_array($legacy['warnings'] ?? null) ? $legacy['warnings'] : [];
            $warnings = array_merge($warnings, (array) ($recovery['warnings'] ?? []));
            $catalog = self::moduleCatalog($modulesPayload);
"""
)

replace_once(
    'src/Transfer/SchemaOneMigrator.php',
    """            $media = self::convertMedia((array) ($mediaPayload['records'] ?? []), (array) ($legacy['manifestFiles'] ?? []), $warnings);
            $nativeSite = self::convertSite($site);
""",
    """            $media = self::convertMedia((array) ($mediaPayload['records'] ?? []), (array) ($legacy['manifestFiles'] ?? []), $warnings);
            foreach ((array) ($recovery['items'] ?? []) as $recoveredRecord) {
                if (is_array($recoveredRecord)) {
                    $media[] = $recoveredRecord;
                }
            }
            $recoveredTempFiles = is_array($recovery['tempFiles'] ?? null) ? $recovery['tempFiles'] : [];
            $nativeSite = self::convertSite($site);
"""
)

replace_once(
    'src/Transfer/SchemaOneMigrator.php',
    """                    $sourceTemp = self::copyEntryToTemp($zip, $archive, (int) ($record['size'] ?? 0), (string) ($record['sha256'] ?? ''));
                    $tempFiles[] = $sourceTemp;
                    if (!$out->addFile($sourceTemp, $archive)) {
""",
    """                    if (isset($recoveredTempFiles[$archive])) {
                        $sourceTemp = (string) $recoveredTempFiles[$archive];
                        if ($sourceTemp === '' || !is_file($sourceTemp) || !is_readable($sourceTemp)) {
                            throw new \\RuntimeException('Recovered media temporary file is unavailable: ' . $archive);
                        }
                        $actualSize = filesize($sourceTemp);
                        $actualSha = hash_file('sha256', $sourceTemp);
                        if (!is_int($actualSize)
                            || $actualSize !== (int) ($record['size'] ?? -1)
                            || !is_string($actualSha)
                            || !hash_equals(strtolower((string) ($record['sha256'] ?? '')), strtolower($actualSha))
                        ) {
                            throw new \\RuntimeException('Recovered media changed before package assembly: ' . $archive);
                        }
                    } else {
                        $sourceTemp = self::copyEntryToTemp($zip, $archive, (int) ($record['size'] ?? 0), (string) ($record['sha256'] ?? ''));
                        $tempFiles[] = $sourceTemp;
                    }
                    if (!$out->addFile($sourceTemp, $archive)) {
"""
)

replace_once(
    'src/Transfer/SchemaOneMigrator.php',
    """                    'sourceManagerVersion' => (string) ($legacy['managerVersion'] ?? ''),
                    'warnings' => array_values(array_unique(array_map('strval', $warnings))),
                    'countsBefore' => is_array($legacy['counts'] ?? null) ? $legacy['counts'] : [],
""",
    """                    'sourceManagerVersion' => (string) ($legacy['managerVersion'] ?? ''),
                    'warnings' => array_values(array_unique(array_map('strval', $warnings))),
                    'recoveredMedia' => max(0, (int) ($recovery['recovered'] ?? 0)),
                    'unresolvedMedia' => max(0, (int) ($recovery['unresolved'] ?? 0)),
                    'countsBefore' => is_array($legacy['counts'] ?? null) ? $legacy['counts'] : [],
"""
)

# Preflight UI reports recovery rather than hiding it inside a warning list.
replace_once(
    'src/Admin/TransferController.php',
    """            self::row('Kilde-VDM', (string) ($migration['sourceManagerVersion'] ?? ''));
        }
""",
    """            self::row('Kilde-VDM', (string) ($migration['sourceManagerVersion'] ?? ''));
            self::row('Genfundne legacy-billeder', (string) max(0, (int) ($migration['recoveredMedia'] ?? 0)));
            self::row('Uafklarede legacy-billeder', (string) max(0, (int) ($migration['unresolvedMedia'] ?? 0)));
        }
"""
)
replace_once(
    'src/Admin/TransferController.php',
    """        echo '<div class=\"notice notice-info inline\"><p><strong>RC.1:</strong> Native V2 schema <code>' . esc_html(PortablePackage::SCHEMA_VERSION) . '</code> importeres direkte. Validerede schema 1.0-sitepakker konverteres isoleret til native V2 under preflight og importeres derefter gennem samme V2-importer.</p></div>';
""",
    """        echo '<div class=\"notice notice-info inline\"><p><strong>RC.4:</strong> Native V2 schema <code>' . esc_html(PortablePackage::SCHEMA_VERSION) . '</code> importeres direkte. Validerede schema 1.0-sitepakker konverteres isoleret til native V2. URL-baserede legacy-billeder uden media-ID forsøges genfundet sikkert fra uploadstien under preflight og pakkes lokalt før import.</p></div>';
"""
)

# RC.3 updater remains a permanent contract for later RC versions.
replace_once(
    'tests/rc3_github_updater_contract.py',
    """require('Version: 2.0.0-rc.3' in plugin, 'plugin header is not RC.3')
require(\"define('VDM_VERSION', '2.0.0-rc.3');\" in plugin, 'VDM_VERSION is not RC.3')
""",
    """version_match = re.search(r'Version:\\s*2\\.0\\.0-rc\\.(\\d+)', plugin)
runtime_match = re.search(r\"define\\('VDM_VERSION', '2\\.0\\.0-rc\\.(\\d+)'\\);\", plugin)
require(bool(version_match and int(version_match.group(1)) >= 3), 'plugin header is older than RC.3')
require(bool(runtime_match and int(runtime_match.group(1)) >= 3), 'VDM_VERSION is older than RC.3')
"""
)

# Add RC.4 contract to the permanent QA chain.
replace_once(
    '.github/workflows/qa.yml',
    """      - name: RC.3 GitHub updater contract
        run: python3 tests/rc3_github_updater_contract.py

      - name: Install package contract
""",
    """      - name: RC.3 GitHub updater contract
        run: python3 tests/rc3_github_updater_contract.py

      - name: RC.4 legacy media recovery contract
        run: python3 tests/rc4_legacy_media_recovery_contract.py

      - name: Install package contract
"""
)

# README release status.
replace_once('README.md', '`2.0.0-rc.3`', '`2.0.0-rc.4`')
replace_once(
    'README.md',
    """RC.2 preserves reusable field definitions and values, maps previous module-list styling into native V2 list nodes, and preserves previous 120-step horizontal placement through VDM2-native fine geometry while retaining the 12-column editor model.

## RC.3 acceptance status

RC.3 carries the RC.2 functional and visual parity work forward and adds the GitHub update channel. Production `2.0.0` remains blocked until environment acceptance confirms both V1 parity and the update workflow on the designated WordPress test target.
""",
    """RC.2 preserves reusable field definitions and values, maps previous module-list styling into native V2 list nodes, and preserves previous 120-step horizontal placement through VDM2-native fine geometry while retaining the 12-column editor model.

RC.4 hardens schema 1.0 preflight for image nodes that contain an uploads URL but no media ID. VDM first retries the same uploads path on the package source site and may then use the original URL through WordPress' safe HTTP client. A recovered file is size/type checked, SHA-256 hashed and embedded into the temporary native V2 package before the canonical importer runs. Preflight reports both recovered and unresolved legacy images and still does not create WordPress media objects.

## RC.4 acceptance status

RC.4 carries the RC.2 parity work and RC.3 GitHub update channel forward, and adds controlled legacy-media recovery for the real schema 1.0 acceptance package. Production `2.0.0` remains blocked until environment acceptance confirms V1 parity, recovered media and the update workflow on the designated WordPress test target.
"""
)
replace_once(
    'README.md',
    """14. `2.0.0-rc.3` — RC.2 parity plus native GitHub updater, verified package publishing and update-page integration. **Candidate for environment acceptance.**
15. `2.0.0` — production release after successful WordPress acceptance QA.
""",
    """14. `2.0.0-rc.3` — RC.2 parity plus native GitHub updater, verified package publishing and update-page integration. **Done; superseded by RC.4 acceptance.**
15. `2.0.0-rc.4` — controlled recovery of URL-only legacy upload images during schema 1.0 preflight, with explicit recovery reporting. **Candidate for environment acceptance.**
16. `2.0.0` — production release after successful WordPress acceptance QA.
"""
)

# Changelog release entry.
replace_once(
    'CHANGELOG.md',
    """# Changelog

## 2.0.0-rc.3
""",
    """# Changelog

## 2.0.0-rc.4

- Added controlled schema 1.0 recovery for Image nodes that have an uploads URL but no media ID.
- Recovery first retries the same `/wp-content/uploads/` path against the package source site, allowing stale source-host references to resolve without changing the source package.
- Original remote URLs are only fetched through WordPress' safe HTTP client and only for validated upload-image paths.
- Added strict 25 MiB recovery limit, non-SVG image validation, temporary-file validation and SHA-256 integrity checks.
- Recovered media is embedded into the temporary native V2 package and receives a normal source ID so the canonical importer remaps it like packaged media.
- Preflight remains non-mutating for WordPress content: recovered files exist only in temporary package storage until explicit import confirmation.
- Added explicit preflight counts for recovered and unresolved legacy images.
- Added the permanent RC.4 legacy-media recovery contract while retaining all earlier regression gates.
- RC.4 is the next environment-acceptance candidate before production `2.0.0`.

## 2.0.0-rc.3
"""
)

print('RC.4 integration patch applied')
