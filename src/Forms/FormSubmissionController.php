<?php

declare(strict_types=1);

namespace VisualDesignerManager\Forms;

use VisualDesignerManager\Model\NodeSchema;
use VisualDesignerManager\Storage\LayoutRepository;

final class FormSubmissionController
{
    private function __construct()
    {
    }

    public static function register(): void
    {
        add_action('admin_post_vdm_submit_form', [self::class, 'handle']);
        add_action('admin_post_nopriv_vdm_submit_form', [self::class, 'handle']);
    }

    public static function handle(): void
    {
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
            self::redirect(0, '', 'error');
        }

        $type = isset($_POST['vdm_form_type']) ? sanitize_key((string) wp_unslash($_POST['vdm_form_type'])) : '';
        $formId = isset($_POST['vdm_form_id']) ? sanitize_key((string) wp_unslash($_POST['vdm_form_id'])) : '';
        $pageId = absint($_POST['vdm_page_id'] ?? 0);
        $nonce = isset($_POST['vdm_form_nonce']) ? sanitize_text_field((string) wp_unslash($_POST['vdm_form_nonce'])) : '';

        if (!in_array($type, [NodeSchema::CONTACT_FORM, NodeSchema::MEMBERSHIP_FORM], true) || $formId === '' || $pageId <= 0) {
            self::redirect($pageId, $formId, 'error');
        }

        if (!wp_verify_nonce($nonce, 'vdm_submit_form_' . $formId)) {
            self::redirect($pageId, $formId, 'error');
        }

        $honeypot = isset($_POST['vdm_website']) ? trim((string) wp_unslash($_POST['vdm_website'])) : '';
        if ($honeypot !== '') {
            self::redirect($pageId, $formId, 'success');
        }

        $node = self::storedNode($pageId, $formId, $type);
        if ($node === null) {
            self::redirect($pageId, $formId, 'error');
        }

        $props = is_array($node['props'] ?? null) ? $node['props'] : [];
        $rawFields = isset($_POST['vdm_fields']) && is_array($_POST['vdm_fields']) ? wp_unslash($_POST['vdm_fields']) : [];
        if (!is_array($rawFields)) {
            $rawFields = [];
        }

        $fields = self::sanitizeFields($rawFields);
        if (!self::valid($type, $props, $fields)) {
            self::redirect($pageId, $formId, 'error');
        }

        $recipient = sanitize_email((string) get_option('vdm_contact_email', ''));
        if (!is_email($recipient)) {
            $recipient = sanitize_email((string) get_option('admin_email', ''));
        }
        if (!is_email($recipient)) {
            self::redirect($pageId, $formId, 'error');
        }

        $isMembership = $type === NodeSchema::MEMBERSHIP_FORM;
        $siteName = sanitize_text_field((string) get_bloginfo('name'));
        $mailSubject = $isMembership ? 'Ny indmeldelse' : 'Ny kontaktformular';
        if (!$isMembership && $fields['subject'] !== '') {
            $mailSubject .= ': ' . $fields['subject'];
        }
        if ($siteName !== '') {
            $mailSubject .= ' – ' . $siteName;
        }

        $body = self::mailBody($type, $props, $fields, $pageId);
        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        if (is_email($fields['email'])) {
            $replyName = str_replace(["\r", "\n"], '', $fields['name']);
            $headers[] = 'Reply-To: ' . $replyName . ' <' . $fields['email'] . '>';
        }

        $sent = wp_mail($recipient, $mailSubject, $body, $headers);
        self::redirect($pageId, $formId, $sent ? 'success' : 'error');
    }

    /** @return array<string,mixed>|null */
    private static function storedNode(int $pageId, string $formId, string $type): ?array
    {
        if (get_post_type($pageId) !== 'page') {
            return null;
        }

        $document = LayoutRepository::get($pageId);
        $nodes = is_array($document['nodes'] ?? null) ? $document['nodes'] : [];
        foreach ($nodes as $node) {
            if (!is_array($node)) {
                continue;
            }
            if ((string) ($node['id'] ?? '') === $formId && (string) ($node['type'] ?? '') === $type) {
                return $node;
            }
        }

        return null;
    }

    /** @param array<string,mixed> $raw
     *  @return array{name:string,email:string,phone:string,address:string,postalCode:string,city:string,subject:string,message:string,consent:bool}
     */
    private static function sanitizeFields(array $raw): array
    {
        return [
            'name' => sanitize_text_field((string) ($raw['name'] ?? '')),
            'email' => sanitize_email((string) ($raw['email'] ?? '')),
            'phone' => sanitize_text_field((string) ($raw['phone'] ?? '')),
            'address' => sanitize_text_field((string) ($raw['address'] ?? '')),
            'postalCode' => sanitize_text_field((string) ($raw['postalCode'] ?? '')),
            'city' => sanitize_text_field((string) ($raw['city'] ?? '')),
            'subject' => sanitize_text_field((string) ($raw['subject'] ?? '')),
            'message' => sanitize_textarea_field((string) ($raw['message'] ?? '')),
            'consent' => !empty($raw['consent']),
        ];
    }

    /** @param array<string,mixed> $props
     *  @param array{name:string,email:string,phone:string,address:string,postalCode:string,city:string,subject:string,message:string,consent:bool} $fields
     */
    private static function valid(string $type, array $props, array $fields): bool
    {
        if ($fields['name'] === '' || !is_email($fields['email'])) {
            return false;
        }

        if (!empty($props['showMessage']) && $fields['message'] === '') {
            return false;
        }
        if (!empty($props['requireConsent']) && !$fields['consent']) {
            return false;
        }
        if ($type === NodeSchema::MEMBERSHIP_FORM && !empty($props['showAddress'])) {
            if ($fields['address'] === '' || $fields['postalCode'] === '' || $fields['city'] === '') {
                return false;
            }
        }

        return true;
    }

    /** @param array<string,mixed> $props
     *  @param array{name:string,email:string,phone:string,address:string,postalCode:string,city:string,subject:string,message:string,consent:bool} $fields
     */
    private static function mailBody(string $type, array $props, array $fields, int $pageId): string
    {
        $labels = [];
        $labels[] = 'Type: ' . ($type === NodeSchema::MEMBERSHIP_FORM ? 'Indmeldelse' : 'Kontakt');
        $labels[] = 'Navn: ' . $fields['name'];
        $labels[] = 'E-mail: ' . $fields['email'];

        if (!empty($props['showPhone']) && $fields['phone'] !== '') {
            $labels[] = 'Telefon: ' . $fields['phone'];
        }
        if ($type === NodeSchema::MEMBERSHIP_FORM && !empty($props['showAddress'])) {
            $labels[] = 'Adresse: ' . $fields['address'];
            $labels[] = 'Postnummer: ' . $fields['postalCode'];
            $labels[] = 'By: ' . $fields['city'];
        }
        if ($type === NodeSchema::CONTACT_FORM && !empty($props['showSubject']) && $fields['subject'] !== '') {
            $labels[] = 'Emne: ' . $fields['subject'];
        }
        if (!empty($props['showMessage'])) {
            $labels[] = '';
            $labels[] = ($type === NodeSchema::MEMBERSHIP_FORM ? 'Bemærkning:' : 'Besked:');
            $labels[] = $fields['message'];
        }
        if (!empty($props['requireConsent'])) {
            $labels[] = '';
            $labels[] = 'Samtykke: Ja';
        }

        $permalink = get_permalink($pageId);
        if (is_string($permalink) && $permalink !== '') {
            $labels[] = '';
            $labels[] = 'Side: ' . esc_url_raw($permalink);
        }

        return implode("\n", $labels);
    }

    private static function redirect(int $pageId, string $formId, string $status): void
    {
        $fallback = $pageId > 0 ? get_permalink($pageId) : home_url('/');
        if (!is_string($fallback) || $fallback === '') {
            $fallback = home_url('/');
        }

        $requested = isset($_POST['vdm_return_url']) ? esc_url_raw((string) wp_unslash($_POST['vdm_return_url'])) : '';
        $target = wp_validate_redirect($requested, $fallback);
        $target = remove_query_arg(['vdm_form_status', 'vdm_form_id'], $target);
        $target = add_query_arg([
            'vdm_form_status' => $status === 'success' ? 'success' : 'error',
            'vdm_form_id' => sanitize_key($formId),
        ], $target);

        nocache_headers();
        wp_safe_redirect($target, 303);
        exit;
    }
}
