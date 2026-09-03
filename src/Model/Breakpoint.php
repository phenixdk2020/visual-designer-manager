<?php

declare(strict_types=1);

namespace VisualDesignerManager\Model;

final class Breakpoint
{
    public const DESKTOP = 'desktop';
    public const LAPTOP = 'laptop';
    public const TABLET = 'tablet';
    public const MOBILE = 'mobile';

    /** @return array<string,int> */
    public static function widths(): array
    {
        return [
            self::DESKTOP => 1440,
            self::LAPTOP => 1180,
            self::TABLET => 980,
            self::MOBILE => 782,
        ];
    }

    /** @return list<string> */
    public static function ordered(): array
    {
        return [self::DESKTOP, self::LAPTOP, self::TABLET, self::MOBILE];
    }

    private function __construct()
    {
    }
}
