<?php

declare(strict_types=1);

namespace VisualDesignerManager\Frontend;

use VisualDesignerManager\Model\NodeSchema;

final class FormRenderer
{
    /** @param array<string,mixed> $props */
    public static function render(string $type, array $props, string $nodeId): string
    {
        if (!in_array($type, [NodeSchema::CONTACT_FORM, NodeSchema::MEMBERSHIP_FORM], true)) {
            return '';
        }

        $nodeId = sanitize_key($nodeId);
        if ($nodeId === '') {
            return '';
        }

        $pageId = absint(get_queried_object_id());
        $returnUrl = $pageId > 0 ? get_permalink($pageId) : home_url('/');
        if (!is_string($returnUrl) || $returnUrl === '') {
            $returnUrl = home_url('/');
        }

        $isMembership = $type === NodeSchema::MEMBERSHIP_FORM;
        $status = self::status($nodeId, $props);

        ob_start();
        echo '<div class="vdm-form-wrap">';
        if ($status !== '') {
            echo $status; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
        echo '<form class="vdm-form vdm-form--' . esc_attr($isMembership ? 'membership' : 'contact') . '" method="post" action="' . esc_url(admin_url('admin-post.php')) . '" data-vdm-form-id="' . esc_attr($nodeId) . '">';
        $heading = sanitize_text_field((string) ($props['heading'] ?? ($isMembership ? 'Bliv medlem' : 'Kontakt os')));
        $intro = sanitize_textarea_field((string) ($props['intro'] ?? ''));
        if ($heading !== '') {
            echo '<h2 class="vdm-form-heading">' . esc_html($heading) . '</h2>';
        }
        if ($intro !== '') {
            echo '<p class="vdm-form-intro">' . esc_html($intro) . '</p>';
        }
        echo '<input type="hidden" name="action" value="vdm_submit_form">';
        echo '<input type="hidden" name="vdm_form_type" value="' . esc_attr($type) . '">';
        echo '<input type="hidden" name="vdm_form_id" value="' . esc_attr($nodeId) . '">';
        echo '<input type="hidden" name="vdm_page_id" value="' . esc_attr((string) $pageId) . '">';
        echo '<input type="hidden" name="vdm_return_url" value="' . esc_url($returnUrl) . '">';
        echo wp_nonce_field('vdm_submit_form_' . $nodeId, 'vdm_form_nonce', false, false); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '<div class="vdm-form-honeypot" aria-hidden="true"><label>Website<input type="text" name="vdm_website" value="" tabindex="-1" autocomplete="off"></label></div>';
        echo '<div class="vdm-form-grid">';

        self::textField('name', 'Navn', 'text', true, 'name');
        self::textField('email', 'E-mail', 'email', true, 'email');

        if (!empty($props['showPhone'])) {
            self::textField('phone', 'Telefon', 'tel', false, 'tel');
        }

        if ($isMembership && !empty($props['showAddress'])) {
            self::textField('address', 'Adresse', 'text', true, 'street-address', true);
            self::textField('postalCode', 'Postnummer', 'text', true, 'postal-code');
            self::textField('city', 'By', 'text', true, 'address-level2');
        }

        if (!$isMembership && !empty($props['showSubject'])) {
            self::textField('subject', 'Emne', 'text', false, 'off', true);
        }

        if (!empty($props['showMessage'])) {
            echo '<label class="vdm-form-field vdm-form-field--full"><span class="vdm-form-label">' . esc_html($isMembership ? 'Bemærkning' : 'Besked') . ' <span aria-hidden="true">*</span></span>';
            echo '<textarea name="vdm_fields[message]" rows="' . esc_attr((string) max(3, min(12, (int) ($props['messageRows'] ?? 6)))) . '" required></textarea></label>';
        }

        if (!empty($props['requireConsent'])) {
            echo '<label class="vdm-form-consent vdm-form-field--full"><input type="checkbox" name="vdm_fields[consent]" value="1" required> <span>' . esc_html((string) ($props['consentText'] ?? 'Jeg accepterer, at oplysningerne bruges til at besvare min henvendelse.')) . ' <span aria-hidden="true">*</span></span></label>';
        }

        echo '<div class="vdm-form-actions vdm-form-field--full"><button class="vdm-form-submit" type="submit">' . esc_html((string) ($props['submitLabel'] ?? ($isMembership ? 'Send indmeldelse' : 'Send besked'))) . '</button></div>';
        echo '</div></form></div>';

        return (string) ob_get_clean();
    }

    private static function textField(string $name, string $label, string $type, bool $required, string $autocomplete, bool $full = false): void
    {
        $class = 'vdm-form-field' . ($full ? ' vdm-form-field--full' : '');
        echo '<label class="' . esc_attr($class) . '"><span class="vdm-form-label">' . esc_html($label);
        if ($required) {
            echo ' <span aria-hidden="true">*</span>';
        }
        echo '</span><input type="' . esc_attr($type) . '" name="vdm_fields[' . esc_attr($name) . ']" autocomplete="' . esc_attr($autocomplete) . '"' . ($required ? ' required' : '') . '></label>';
    }

    /** @param array<string,mixed> $props */
    private static function status(string $nodeId, array $props): string
    {
        $statusId = isset($_GET['vdm_form_id']) ? sanitize_key((string) wp_unslash($_GET['vdm_form_id'])) : '';
        if ($statusId !== $nodeId) {
            return '';
        }

        $status = isset($_GET['vdm_form_status']) ? sanitize_key((string) wp_unslash($_GET['vdm_form_status'])) : '';
        if ($status === 'success') {
            $message = sanitize_text_field((string) ($props['successMessage'] ?? 'Tak. Din henvendelse er sendt.'));
            return '<div class="vdm-form-status vdm-form-status--success" role="status">' . esc_html($message) . '</div>';
        }
        if ($status === 'error') {
            return '<div class="vdm-form-status vdm-form-status--error" role="alert">Formularen kunne ikke sendes. Kontroller felterne og prøv igen.</div>';
        }

        return '';
    }

    private function __construct()
    {
    }
}
