<?php

declare(strict_types=1);

namespace VisualDesignerManager\Navigation;

final class NavigationRepository
{
    /** @return list<array{id:int,name:string,count:int}> */
    public static function choices(): array
    {
        $menus = wp_get_nav_menus(['orderby' => 'name']);
        if (!is_array($menus)) {
            return [];
        }

        $result = [];
        foreach ($menus as $menu) {
            if (!$menu instanceof \WP_Term) {
                continue;
            }
            $items = wp_get_nav_menu_items((int) $menu->term_id, ['post_status' => 'publish']);
            $result[] = [
                'id' => (int) $menu->term_id,
                'name' => sanitize_text_field((string) $menu->name),
                'count' => is_array($items) ? count($items) : 0,
            ];
        }

        return $result;
    }

    public static function exists(int $menuId): bool
    {
        if ($menuId <= 0) {
            return false;
        }

        return wp_get_nav_menu_object($menuId) instanceof \WP_Term;
    }

    private function __construct()
    {
    }
}
