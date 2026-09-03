<?php

declare(strict_types=1);

namespace VisualDesignerManager\Storage;

final class SiteSettingsRepository
{
    public const ORGANIZATION_OPTION = 'vdm_organization_name';
    public const CONTACT_EMAIL_OPTION = 'vdm_contact_email';
    public const CONTACT_PHONE_OPTION = 'vdm_contact_phone';
    public const LOGO_OPTION = 'vdm_site_logo_id';

    /** @return array<string,mixed> */
    public static function get(): array
    {
        return [
            'siteTitle' => sanitize_text_field((string) get_option('blogname', '')),
            'tagline' => sanitize_text_field((string) get_option('blogdescription', '')),
            'organizationName' => sanitize_text_field((string) get_option(self::ORGANIZATION_OPTION, '')),
            'contactEmail' => sanitize_email((string) get_option(self::CONTACT_EMAIL_OPTION, '')),
            'contactPhone' => sanitize_text_field((string) get_option(self::CONTACT_PHONE_OPTION, '')),
            'logoId' => absint(get_option(self::LOGO_OPTION, 0)),
            'siteIconId' => absint(get_option('site_icon', 0)),
            'homeUrl' => home_url('/'),
            'siteUrl' => site_url('/'),
        ];
    }

    /** @param array<string,mixed> $raw */
    public static function save(array $raw): void
    {
        $title = sanitize_text_field((string) ($raw['siteTitle'] ?? ''));
        $tagline = sanitize_text_field((string) ($raw['tagline'] ?? ''));
        $organization = sanitize_text_field((string) ($raw['organizationName'] ?? ''));
        $email = sanitize_email((string) ($raw['contactEmail'] ?? ''));
        $phone = sanitize_text_field((string) ($raw['contactPhone'] ?? ''));
        $logoId = self::imageAttachmentId($raw['logoId'] ?? 0);
        $siteIconId = self::imageAttachmentId($raw['siteIconId'] ?? 0);

        update_option('blogname', $title, true);
        update_option('blogdescription', $tagline, true);
        update_option(self::ORGANIZATION_OPTION, $organization, true);
        update_option(self::CONTACT_EMAIL_OPTION, is_email($email) ? $email : '', true);
        update_option(self::CONTACT_PHONE_OPTION, $phone, true);
        update_option(self::LOGO_OPTION, $logoId, true);
        update_option('site_icon', $siteIconId, true);
    }

    public static function logoUrl(string $size = 'medium'): string
    {
        $id = absint(get_option(self::LOGO_OPTION, 0));
        if ($id <= 0) {
            return '';
        }
        $url = wp_get_attachment_image_url($id, $size);
        return is_string($url) ? $url : '';
    }

    private static function imageAttachmentId(mixed $value): int
    {
        $id = absint($value);
        if ($id <= 0 || !wp_attachment_is_image($id)) {
            return 0;
        }
        return $id;
    }

    private function __construct()
    {
    }
}
