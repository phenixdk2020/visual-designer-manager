<?php

declare(strict_types=1);

namespace VisualDesignerManager\Admin;

/**
 * VDM navigation editor.
 *
 * WordPress nav menus remain the canonical data source, but the full V1-style
 * Manager workflow is restored here: create menu, add pages/custom links/menu
 * headings, rename/reorder/reparent items, theme locations and 30 snapshots.
 */
final class NavigationController
{
    public const MENU_SLUG = 'vdm-navigation';
    private const HISTORY_OPTION = 'vdm_navigation_history_v2';
    private const MAX_HISTORY = 30;
    private const ACTION_CREATE = 'vdm_nav_create';
    private const ACTION_ADD = 'vdm_nav_add';
    private const ACTION_SAVE = 'vdm_nav_save';
    private const ACTION_DELETE = 'vdm_nav_delete';
    private const ACTION_LOCATIONS = 'vdm_nav_locations';
    private const ACTION_RESTORE = 'vdm_nav_restore';

    private function __construct()
    {
    }

    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'menu'], 24);
        add_action('admin_post_' . self::ACTION_CREATE, [self::class, 'createMenu']);
        add_action('admin_post_' . self::ACTION_ADD, [self::class, 'addItem']);
        add_action('admin_post_' . self::ACTION_SAVE, [self::class, 'saveMenu']);
        add_action('admin_post_' . self::ACTION_DELETE, [self::class, 'deleteItem']);
        add_action('admin_post_' . self::ACTION_LOCATIONS, [self::class, 'saveLocations']);
        add_action('admin_post_' . self::ACTION_RESTORE, [self::class, 'restoreSnapshot']);
    }

    public static function menu(): void
    {
        add_submenu_page(
            AdminController::MENU_SLUG,
            'Menu / Navigation',
            'Menu',
            'edit_theme_options',
            self::MENU_SLUG,
            [self::class, 'render']
        );
    }

    public static function render(): void
    {
        self::guard();
        $menus = wp_get_nav_menus();
        $selectedId = absint($_GET['menu_id'] ?? 0);
        if ($selectedId <= 0 && $menus !== []) {
            $selectedId = (int) $menus[0]->term_id;
        }
        $selected = $selectedId > 0 ? wp_get_nav_menu_object($selectedId) : false;
        if (!$selected instanceof \WP_Term && $menus !== []) {
            $selectedId = (int) $menus[0]->term_id;
            $selected = wp_get_nav_menu_object($selectedId);
        }

        echo '<div class="wrap"><h1>Visual Designer Manager · Menu</h1>';
        echo '<p>WordPress-menuen er canonical datakilde. Her kan du administrere hele menuen uden at forlade Visual Designer Manager. Design og responsiv visning styres fortsat af Navigation-elementet i Designeren.</p>';
        self::notice();

        if ($menus === []) {
            echo '<div class="card" style="max-width:760px"><h2>Opret din første menu</h2>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field(self::ACTION_CREATE);
            echo '<input type="hidden" name="action" value="' . esc_attr(self::ACTION_CREATE) . '"><p><label>Navn<br><input class="regular-text" name="menu_name" value="Hovedmenu" required></label></p><p><button class="button button-primary">Opret Hovedmenu</button></p></form></div></div>';
            return;
        }

        echo '<div class="card" style="max-width:none;display:flex;gap:16px;align-items:end;flex-wrap:wrap"><div><h2 style="margin-bottom:4px">Aktuel menu</h2><strong style="font-size:20px">' . esc_html($selected instanceof \WP_Term ? (string) $selected->name : 'Menu') . '</strong></div>';
        if (count($menus) > 1) {
            echo '<form method="get" style="margin-left:auto"><input type="hidden" name="page" value="' . esc_attr(self::MENU_SLUG) . '"><label>Skift menu<br><select name="menu_id" onchange="this.form.submit()">';
            foreach ($menus as $menu) {
                echo '<option value="' . esc_attr((string) $menu->term_id) . '"' . selected($selectedId, (int) $menu->term_id, false) . '>' . esc_html((string) $menu->name) . '</option>';
            }
            echo '</select></label></form>';
        }
        echo '</div>';

        if ($selected instanceof \WP_Term) {
            self::renderMenuEditor((int) $selected->term_id, (string) $selected->name);
        }
        self::renderAdvanced($menus, $selectedId);
        echo '</div>';
    }

    private static function renderMenuEditor(int $menuId, string $menuName): void
    {
        $items = wp_get_nav_menu_items($menuId, ['post_status' => 'any']);
        $items = is_array($items) ? $items : [];
        usort($items, static fn($a, $b): int => ((int) $a->menu_order) <=> ((int) $b->menu_order));
        $pages = get_pages(['post_status' => 'publish', 'sort_column' => 'post_title', 'sort_order' => 'ASC']);
        $usedPageIds = [];
        foreach ($items as $item) {
            if ((string) $item->type === 'post_type' && (string) $item->object === 'page') {
                $usedPageIds[(int) $item->object_id] = true;
            }
        }

        echo '<div style="display:grid;grid-template-columns:minmax(560px,1fr) minmax(300px,420px);gap:18px;align-items:start;margin-top:18px">';
        echo '<section class="card" style="max-width:none;margin:0"><h2>Menupunkter</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field(self::ACTION_SAVE . '_' . $menuId);
        echo '<input type="hidden" name="action" value="' . esc_attr(self::ACTION_SAVE) . '"><input type="hidden" name="menu_id" value="' . esc_attr((string) $menuId) . '">';
        echo '<p><label><strong>Menunavn</strong><br><input class="regular-text" name="menu_name" value="' . esc_attr($menuName) . '"></label></p>';
        if ($items === []) {
            echo '<p>Menuen er tom.</p>';
        } else {
            echo '<table class="widefat striped"><thead><tr><th style="width:70px">Rækkef.</th><th>Tekst</th><th>Parent</th><th>Type / mål</th><th>Handling</th></tr></thead><tbody>';
            foreach ($items as $item) {
                $id = (int) $item->ID;
                echo '<tr><td><input style="width:64px" type="number" min="1" name="item_order[' . esc_attr((string) $id) . ']" value="' . esc_attr((string) max(1, (int) $item->menu_order)) . '"></td>';
                echo '<td><input class="widefat" name="item_title[' . esc_attr((string) $id) . ']" value="' . esc_attr((string) $item->title) . '"></td>';
                echo '<td><select name="item_parent[' . esc_attr((string) $id) . ']" style="max-width:180px"><option value="0">Ingen</option>';
                foreach ($items as $candidate) {
                    if ((int) $candidate->ID === $id) {
                        continue;
                    }
                    echo '<option value="' . esc_attr((string) $candidate->ID) . '"' . selected((int) $item->menu_item_parent, (int) $candidate->ID, false) . '>' . esc_html((string) $candidate->title) . '</option>';
                }
                echo '</select></td><td><small>' . esc_html(self::itemTypeLabel($item)) . '</small><br><code style="word-break:break-all">' . esc_html((string) $item->url) . '</code></td><td>';
                echo '<button class="button-link-delete" type="submit" name="delete_item_id" value="' . esc_attr((string) $id) . '" onclick="return confirm(\'Fjern dette menupunkt?\');">Fjern</button></td></tr>';
            }
            echo '</tbody></table><p class="description">Parent laver underpunkter. Rækkefølge kan ændres numerisk; gemningen normaliserer WordPress menu_order.</p>';
        }
        echo '<p><button class="button button-primary" type="submit">Gem menu</button></p></form></section>';

        echo '<aside class="card" style="max-width:none;margin:0"><h2>Tilføj indhold</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field(self::ACTION_ADD . '_' . $menuId);
        echo '<input type="hidden" name="action" value="' . esc_attr(self::ACTION_ADD) . '"><input type="hidden" name="menu_id" value="' . esc_attr((string) $menuId) . '"><input type="hidden" name="item_kind" value="pages">';
        echo '<h3>Publicerede sider</h3><div style="max-height:260px;overflow:auto;border:1px solid #dcdcde;padding:8px;background:#fff">';
        $available = 0;
        foreach ($pages as $page) {
            if (!$page instanceof \WP_Post || isset($usedPageIds[(int) $page->ID])) {
                continue;
            }
            $available++;
            echo '<label style="display:block;padding:4px"><input type="checkbox" name="page_ids[]" value="' . esc_attr((string) $page->ID) . '"> ' . esc_html((string) $page->post_title) . '</label>';
        }
        echo $available === 0 ? '<p>Alle publicerede sider er allerede i menuen.</p>' : '';
        echo '</div><p><button class="button" type="submit"' . ($available === 0 ? ' disabled' : '') . '>Tilføj valgte sider</button></p></form>';

        echo '<hr><h3>Eksternt link</h3><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field(self::ACTION_ADD . '_' . $menuId);
        echo '<input type="hidden" name="action" value="' . esc_attr(self::ACTION_ADD) . '"><input type="hidden" name="menu_id" value="' . esc_attr((string) $menuId) . '"><input type="hidden" name="item_kind" value="link"><p><input class="widefat" name="custom_title" placeholder="Menutekst" required></p><p><input class="widefat" type="url" name="custom_url" placeholder="https://..." required></p><p><button class="button">Tilføj link</button></p></form>';

        echo '<hr><h3>Overskrift</h3><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field(self::ACTION_ADD . '_' . $menuId);
        echo '<input type="hidden" name="action" value="' . esc_attr(self::ACTION_ADD) . '"><input type="hidden" name="menu_id" value="' . esc_attr((string) $menuId) . '"><input type="hidden" name="item_kind" value="heading"><p><input class="widefat" name="custom_title" placeholder="Overskrift" required></p><p><button class="button">Tilføj overskrift</button></p></form></aside></div>';
    }

    private static function renderAdvanced(array $menus, int $selectedId): void
    {
        $registered = get_registered_nav_menus();
        $locations = get_nav_menu_locations();
        $history = self::history();

        echo '<details class="card" style="max-width:none;margin-top:18px"><summary style="cursor:pointer"><strong>Avancerede indstillinger</strong> · flere menuer, theme locations og versionshistorik</summary><div style="margin-top:18px">';
        echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:18px">';
        echo '<section><h2>Menuer</h2><table class="widefat striped"><thead><tr><th>Navn</th><th>Punkter</th><th></th></tr></thead><tbody>';
        foreach ($menus as $menu) {
            $items = wp_get_nav_menu_items((int) $menu->term_id);
            echo '<tr><td><strong>' . esc_html((string) $menu->name) . '</strong></td><td>' . esc_html((string) count(is_array($items) ? $items : [])) . '</td><td><a class="button button-small" href="' . esc_url(add_query_arg(['page' => self::MENU_SLUG, 'menu_id' => (int) $menu->term_id], admin_url('admin.php'))) . '">Åbn</a></td></tr>';
        }
        echo '</tbody></table><h3>Ny menu</h3><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field(self::ACTION_CREATE);
        echo '<input type="hidden" name="action" value="' . esc_attr(self::ACTION_CREATE) . '"><input name="menu_name" placeholder="Menunavn" required> <button class="button">Opret</button></form></section>';

        echo '<section><h2>Theme locations</h2>';
        if ($registered === []) {
            echo '<p>Det aktive tema registrerer ingen klassiske menu-locations. Navigation-elementet kan stadig vælge menu direkte.</p>';
        } else {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field(self::ACTION_LOCATIONS);
            echo '<input type="hidden" name="action" value="' . esc_attr(self::ACTION_LOCATIONS) . '"><table class="widefat striped"><tbody>';
            foreach ($registered as $key => $label) {
                echo '<tr><th>' . esc_html((string) $label) . '<br><code>' . esc_html((string) $key) . '</code></th><td><select name="locations[' . esc_attr((string) $key) . ']"><option value="0">Ingen</option>';
                foreach ($menus as $menu) {
                    echo '<option value="' . esc_attr((string) $menu->term_id) . '"' . selected((int) ($locations[$key] ?? 0), (int) $menu->term_id, false) . '>' . esc_html((string) $menu->name) . '</option>';
                }
                echo '</select></td></tr>';
            }
            echo '</tbody></table><p><button class="button">Gem locations</button></p></form>';
        }
        echo '</section></div>';

        echo '<hr><h2>Versionshistorik · seneste ' . esc_html((string) self::MAX_HISTORY) . '</h2>';
        if ($history === []) {
            echo '<p>Ingen snapshots endnu. VDM tager automatisk et snapshot før menustrukturelle ændringer.</p>';
        } else {
            echo '<table class="widefat striped"><thead><tr><th>Dato</th><th>Årsag</th><th>Menuer</th><th>Handling</th></tr></thead><tbody>';
            foreach ($history as $row) {
                if (!is_array($row)) {
                    continue;
                }
                echo '<tr><td>' . esc_html((string) ($row['createdUtc'] ?? '')) . '</td><td>' . esc_html((string) ($row['reason'] ?? '')) . '</td><td>' . esc_html((string) count((array) ($row['menus'] ?? []))) . '</td><td><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
                wp_nonce_field(self::ACTION_RESTORE);
                echo '<input type="hidden" name="action" value="' . esc_attr(self::ACTION_RESTORE) . '"><input type="hidden" name="fingerprint" value="' . esc_attr((string) ($row['fingerprint'] ?? '')) . '"><input type="hidden" name="menu_id" value="' . esc_attr((string) $selectedId) . '"><button class="button button-small" onclick="return confirm(\'Gendan menu-snapshot? Nuværende tilstand sikkerhedskopieres først.\');">Gendan</button></form></td></tr>';
            }
            echo '</tbody></table>';
        }
        echo '</div></details>';
    }

    public static function createMenu(): void
    {
        self::guard();
        check_admin_referer(self::ACTION_CREATE);
        $name = sanitize_text_field((string) wp_unslash($_POST['menu_name'] ?? ''));
        if ($name === '') {
            self::redirect(0, 'Menunavn mangler.', 'error');
        }
        self::snapshot('Før oprettelse af menu');
        $result = wp_create_nav_menu($name);
        if (is_wp_error($result)) {
            self::redirect(0, $result->get_error_message(), 'error');
        }
        self::redirect((int) $result, 'Menu oprettet.');
    }

    public static function addItem(): void
    {
        self::guard();
        $menuId = absint($_POST['menu_id'] ?? 0);
        check_admin_referer(self::ACTION_ADD . '_' . $menuId);
        self::requireMenu($menuId);
        $kind = sanitize_key((string) ($_POST['item_kind'] ?? 'pages'));

        if ($kind === 'pages') {
            $pageIds = array_values(array_unique(array_filter(array_map('absint', (array) ($_POST['page_ids'] ?? [])))));
            if ($pageIds === []) {
                self::redirect($menuId, 'Vælg mindst én side.', 'error');
            }
            $existing = wp_get_nav_menu_items($menuId, ['post_status' => 'any']);
            $used = [];
            foreach (is_array($existing) ? $existing : [] as $item) {
                if ((string) $item->type === 'post_type' && (string) $item->object === 'page') {
                    $used[(int) $item->object_id] = true;
                }
            }
            self::snapshot('Før tilføjelse af sider');
            $added = 0;
            foreach (array_slice($pageIds, 0, 100) as $pageId) {
                $page = get_post($pageId);
                if (!$page instanceof \WP_Post || $page->post_type !== 'page' || $page->post_status !== 'publish' || isset($used[$pageId])) {
                    continue;
                }
                $result = wp_update_nav_menu_item($menuId, 0, [
                    'menu-item-object-id' => $pageId,
                    'menu-item-object' => 'page',
                    'menu-item-type' => 'post_type',
                    'menu-item-status' => 'publish',
                ]);
                if (!is_wp_error($result)) {
                    $added++;
                }
            }
            self::redirect($menuId, $added . ' side(r) tilføjet.');
        }

        $title = sanitize_text_field((string) wp_unslash($_POST['custom_title'] ?? ''));
        if ($title === '') {
            self::redirect($menuId, 'Menutekst mangler.', 'error');
        }
        self::snapshot($kind === 'heading' ? 'Før tilføjelse af menuoverskrift' : 'Før tilføjelse af eksternt link');
        $args = [
            'menu-item-title' => $title,
            'menu-item-type' => 'custom',
            'menu-item-status' => 'publish',
        ];
        if ($kind === 'heading') {
            $args['menu-item-url'] = '#';
            $args['menu-item-classes'] = 'vdm-menu-heading';
        } else {
            $url = esc_url_raw((string) wp_unslash($_POST['custom_url'] ?? ''));
            if ($url === '') {
                self::redirect($menuId, 'URL mangler eller er ugyldig.', 'error');
            }
            $args['menu-item-url'] = $url;
        }
        $result = wp_update_nav_menu_item($menuId, 0, $args);
        if (is_wp_error($result)) {
            self::redirect($menuId, $result->get_error_message(), 'error');
        }
        self::redirect($menuId, $kind === 'heading' ? 'Overskrift tilføjet.' : 'Link tilføjet.');
    }

    public static function saveMenu(): void
    {
        self::guard();
        $menuId = absint($_POST['menu_id'] ?? 0);
        check_admin_referer(self::ACTION_SAVE . '_' . $menuId);
        $menu = self::requireMenu($menuId);
        $items = wp_get_nav_menu_items($menuId, ['post_status' => 'any']);
        $items = is_array($items) ? $items : [];

        $deleteId = absint($_POST['delete_item_id'] ?? 0);
        if ($deleteId > 0) {
            $belongs = false;
            foreach ($items as $item) {
                if ((int) $item->ID === $deleteId) {
                    $belongs = true;
                    break;
                }
            }
            if (!$belongs) {
                self::redirect($menuId, 'Menupunkt tilhører ikke menuen.', 'error');
            }
            self::snapshot('Før fjernelse af menupunkt');
            wp_delete_post($deleteId, true);
            self::redirect($menuId, 'Menupunkt fjernet.');
        }

        $titles = is_array($_POST['item_title'] ?? null) ? wp_unslash($_POST['item_title']) : [];
        $parents = is_array($_POST['item_parent'] ?? null) ? wp_unslash($_POST['item_parent']) : [];
        $orders = is_array($_POST['item_order'] ?? null) ? wp_unslash($_POST['item_order']) : [];
        $parentMap = [];
        foreach ($items as $item) {
            $id = (int) $item->ID;
            $parentMap[$id] = absint($parents[$id] ?? $item->menu_item_parent);
        }
        self::validateParentMap($parentMap);
        self::snapshot('Før ændring af menu ' . (string) $menu->name);

        $newName = sanitize_text_field((string) wp_unslash($_POST['menu_name'] ?? ''));
        if ($newName !== '' && $newName !== (string) $menu->name) {
            $renamed = wp_update_nav_menu_object($menuId, ['menu-name' => $newName]);
            if (is_wp_error($renamed)) {
                self::redirect($menuId, $renamed->get_error_message(), 'error');
            }
        }

        uasort($orders, static fn($a, $b): int => absint($a) <=> absint($b));
        $position = 1;
        foreach (array_keys($orders) as $rawId) {
            $id = absint($rawId);
            $item = null;
            foreach ($items as $candidate) {
                if ((int) $candidate->ID === $id) {
                    $item = $candidate;
                    break;
                }
            }
            if (!$item instanceof \WP_Post) {
                continue;
            }
            $args = [
                'menu-item-title' => sanitize_text_field((string) wp_unslash($titles[$id] ?? $item->title)),
                'menu-item-parent-id' => (int) ($parentMap[$id] ?? 0),
                'menu-item-position' => $position++,
                'menu-item-status' => 'publish',
                'menu-item-type' => (string) $item->type,
                'menu-item-object' => (string) $item->object,
                'menu-item-object-id' => (int) $item->object_id,
                'menu-item-target' => (string) $item->target,
                'menu-item-classes' => implode(' ', array_filter(array_map('sanitize_html_class', (array) $item->classes))),
            ];
            if ((string) $item->type === 'custom') {
                $args['menu-item-url'] = esc_url_raw((string) $item->url);
            }
            $result = wp_update_nav_menu_item($menuId, $id, $args);
            if (is_wp_error($result)) {
                self::redirect($menuId, $result->get_error_message(), 'error');
            }
        }
        self::redirect($menuId, 'Menu gemt.');
    }

    public static function deleteItem(): void
    {
        self::guard();
        $menuId = absint($_POST['menu_id'] ?? 0);
        $itemId = absint($_POST['item_id'] ?? 0);
        check_admin_referer(self::ACTION_DELETE . '_' . $menuId . '_' . $itemId);
        self::requireMenu($menuId);
        self::snapshot('Før sletning af menupunkt');
        wp_delete_post($itemId, true);
        self::redirect($menuId, 'Menupunkt slettet.');
    }

    public static function saveLocations(): void
    {
        self::guard();
        check_admin_referer(self::ACTION_LOCATIONS);
        self::snapshot('Før ændring af theme locations');
        $registered = get_registered_nav_menus();
        $posted = is_array($_POST['locations'] ?? null) ? wp_unslash($_POST['locations']) : [];
        $locations = get_nav_menu_locations();
        foreach ($registered as $key => $label) {
            $locations[$key] = absint($posted[$key] ?? 0);
        }
        set_theme_mod('nav_menu_locations', $locations);
        self::redirect(0, 'Theme locations gemt.');
    }

    public static function restoreSnapshot(): void
    {
        self::guard();
        check_admin_referer(self::ACTION_RESTORE);
        $fingerprint = sanitize_text_field((string) wp_unslash($_POST['fingerprint'] ?? ''));
        $selectedId = absint($_POST['menu_id'] ?? 0);
        $snapshot = null;
        foreach (self::history() as $row) {
            if (is_array($row) && hash_equals((string) ($row['fingerprint'] ?? ''), $fingerprint)) {
                $snapshot = $row;
                break;
            }
        }
        if ($snapshot === null) {
            self::redirect($selectedId, 'Snapshot findes ikke.', 'error');
        }
        self::snapshot('Før gendannelse af tidligere menu-snapshot');

        // Remove current menus and rebuild from snapshot with remapped menu/item IDs.
        foreach (wp_get_nav_menus() as $menu) {
            wp_delete_nav_menu((int) $menu->term_id);
        }
        $menuMap = [];
        foreach ((array) ($snapshot['menus'] ?? []) as $menuRow) {
            if (!is_array($menuRow)) {
                continue;
            }
            $newMenuId = wp_create_nav_menu(sanitize_text_field((string) ($menuRow['name'] ?? 'Menu')));
            if (is_wp_error($newMenuId)) {
                continue;
            }
            $menuMap[(int) ($menuRow['sourceId'] ?? 0)] = (int) $newMenuId;
            $itemMap = [];
            $pending = is_array($menuRow['items'] ?? null) ? array_values($menuRow['items']) : [];
            for ($pass = 0; $pass < 10 && $pending !== []; $pass++) {
                $next = [];
                foreach ($pending as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $sourceParent = (int) ($item['parent'] ?? 0);
                    if ($sourceParent > 0 && !isset($itemMap[$sourceParent])) {
                        $next[] = $item;
                        continue;
                    }
                    $args = [
                        'menu-item-title' => sanitize_text_field((string) ($item['title'] ?? '')),
                        'menu-item-url' => esc_url_raw((string) ($item['url'] ?? '#')),
                        'menu-item-status' => 'publish',
                        'menu-item-type' => sanitize_key((string) ($item['type'] ?? 'custom')),
                        'menu-item-object' => sanitize_key((string) ($item['object'] ?? 'custom')),
                        'menu-item-object-id' => absint($item['objectId'] ?? 0),
                        'menu-item-parent-id' => $sourceParent > 0 ? (int) ($itemMap[$sourceParent] ?? 0) : 0,
                        'menu-item-position' => max(1, (int) ($item['order'] ?? 1)),
                        'menu-item-classes' => implode(' ', array_filter(array_map('sanitize_html_class', (array) ($item['classes'] ?? [])))),
                    ];
                    $created = wp_update_nav_menu_item((int) $newMenuId, 0, $args);
                    if (!is_wp_error($created)) {
                        $itemMap[(int) ($item['sourceId'] ?? 0)] = (int) $created;
                    }
                }
                if (count($next) === count($pending)) {
                    break;
                }
                $pending = $next;
            }
        }

        $restoredLocations = [];
        foreach ((array) ($snapshot['locations'] ?? []) as $location => $oldMenuId) {
            $restoredLocations[sanitize_key((string) $location)] = (int) ($menuMap[(int) $oldMenuId] ?? 0);
        }
        if ($restoredLocations !== []) {
            set_theme_mod('nav_menu_locations', $restoredLocations);
        }
        $target = $menuMap[$selectedId] ?? (array_values($menuMap)[0] ?? 0);
        self::redirect((int) $target, 'Menu-snapshot gendannet.');
    }

    private static function snapshot(string $reason): void
    {
        $menus = [];
        foreach (wp_get_nav_menus() as $menu) {
            $items = [];
            foreach ((array) wp_get_nav_menu_items((int) $menu->term_id, ['post_status' => 'any']) as $item) {
                $items[] = [
                    'sourceId' => (int) $item->ID,
                    'title' => (string) $item->title,
                    'url' => (string) $item->url,
                    'type' => (string) $item->type,
                    'object' => (string) $item->object,
                    'objectId' => (int) $item->object_id,
                    'parent' => (int) $item->menu_item_parent,
                    'order' => (int) $item->menu_order,
                    'classes' => array_values(array_filter(array_map('strval', (array) $item->classes))),
                ];
            }
            $menus[] = ['sourceId' => (int) $menu->term_id, 'name' => (string) $menu->name, 'items' => $items];
        }
        $row = [
            'createdUtc' => gmdate('c'),
            'reason' => sanitize_text_field($reason),
            'menus' => $menus,
            'locations' => get_nav_menu_locations(),
        ];
        $json = wp_json_encode($row);
        $row['fingerprint'] = hash('sha256', is_string($json) ? $json : serialize($row));
        $history = self::history();
        array_unshift($history, $row);
        update_option(self::HISTORY_OPTION, array_slice($history, 0, self::MAX_HISTORY), false);
    }

    /** @return list<array<string,mixed>> */
    private static function history(): array
    {
        $value = get_option(self::HISTORY_OPTION, []);
        return is_array($value) ? array_values($value) : [];
    }

    /** @param array<int,int> $parentMap */
    private static function validateParentMap(array $parentMap): void
    {
        foreach ($parentMap as $id => $parent) {
            if ($parent === 0) {
                continue;
            }
            if ($parent === $id || !isset($parentMap[$parent])) {
                wp_die(esc_html__('Ugyldig menu-parent.', 'visual-designer-manager'));
            }
            $seen = [$id => true];
            $current = $parent;
            while ($current > 0) {
                if (isset($seen[$current])) {
                    wp_die(esc_html__('Menuhierarkiet indeholder en cirkel.', 'visual-designer-manager'));
                }
                $seen[$current] = true;
                $current = (int) ($parentMap[$current] ?? 0);
            }
        }
    }

    private static function requireMenu(int $menuId): \WP_Term
    {
        $menu = wp_get_nav_menu_object($menuId);
        if (!$menu instanceof \WP_Term) {
            wp_die(esc_html__('Menuen findes ikke.', 'visual-designer-manager'));
        }
        return $menu;
    }

    private static function itemTypeLabel(\WP_Post $item): string
    {
        if (in_array('vdm-menu-heading', (array) $item->classes, true)) {
            return 'Overskrift';
        }
        if ((string) $item->type === 'post_type' && (string) $item->object === 'page') {
            return 'Side';
        }
        return (string) $item->type === 'custom' ? 'Eksternt link' : (string) $item->type;
    }

    private static function notice(): void
    {
        $message = sanitize_text_field((string) wp_unslash($_GET['vdm_message'] ?? ''));
        if ($message === '') {
            return;
        }
        $class = sanitize_key((string) ($_GET['vdm_notice'] ?? 'success')) === 'error' ? 'notice-error' : 'notice-success';
        echo '<div class="notice ' . esc_attr($class) . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
    }

    private static function redirect(int $menuId, string $message, string $type = 'success'): void
    {
        $args = ['page' => self::MENU_SLUG, 'vdm_notice' => $type, 'vdm_message' => $message];
        if ($menuId > 0) {
            $args['menu_id'] = $menuId;
        }
        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')), 303);
        exit;
    }

    private static function guard(): void
    {
        if (!current_user_can('edit_theme_options')) {
            wp_die(esc_html__('Du har ikke adgang til Menu.', 'visual-designer-manager'));
        }
    }
}
