<?php

declare(strict_types=1);

namespace VisualDesignerManager\Diagnostics;

final class DiagnosticStore
{
    public const OPTION = 'vdm_diagnostics_v2';
    private const MAX_ENTRIES = 300;

    /** @param array<string,mixed> $context */
    public static function add(string $level, string $message, array $context = []): void
    {
        $level = strtolower($level);
        if (!in_array($level, ['info', 'warning', 'error'], true)) {
            $level = 'info';
        }
        $entries = self::all();
        array_unshift($entries, [
            'time' => gmdate('c'),
            'level' => $level,
            'message' => sanitize_text_field($message),
            'context' => self::sanitizeContext($context),
        ]);
        update_option(self::OPTION, array_slice($entries, 0, self::MAX_ENTRIES), false);
    }

    /** @return list<array<string,mixed>> */
    public static function all(): array
    {
        $value = get_option(self::OPTION, []);
        return is_array($value) ? array_values(array_filter($value, 'is_array')) : [];
    }

    public static function clear(): void
    {
        delete_option(self::OPTION);
    }

    public static function clearForPost(int $postId): void
    {
        if ($postId <= 0) {
            return;
        }
        $entries = array_values(array_filter(self::all(), static function (array $entry) use ($postId): bool {
            $context = is_array($entry['context'] ?? null) ? $entry['context'] : [];
            return absint($context['postid'] ?? 0) !== $postId;
        }));
        if ($entries === []) {
            delete_option(self::OPTION);
        } else {
            update_option(self::OPTION, array_slice($entries, 0, self::MAX_ENTRIES), false);
        }
    }

    public static function supportUrl(int $postId = 0): string
    {
        $args = ['page' => 'vdm-log'];
        if ($postId > 0) {
            $args['post_id'] = $postId;
        }
        return add_query_arg($args, admin_url('admin.php'));
    }

    /** @param array<string,mixed> $context @return array<string,string> */
    private static function sanitizeContext(array $context): array
    {
        $out = [];
        foreach (array_slice($context, 0, 30, true) as $key => $value) {
            if (!is_scalar($value) && $value !== null) {
                continue;
            }
            $out[sanitize_key((string) $key)] = sanitize_text_field((string) $value);
        }
        return $out;
    }

    private function __construct()
    {
    }
}
