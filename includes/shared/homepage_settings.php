<?php
/**
 * =====================================================================
 * Shared — Public Homepage Settings
 * ---------------------------------------------------------------------
 * Real, subadmin-editable content for index.php's "About" section
 * (Mission/Vision/Community First) and footer (Contact Us/Operating
 * Hours) — previously all hardcoded HTML directly in index.php with no
 * way to change them without editing code. Stored as a single real
 * Firestore doc, site_settings/homepage. (Quick Links was part of this
 * too originally — removed by request; a stored doc from before that
 * may still carry a leftover "quickLinks" field, but nothing reads it
 * anymore.)
 *
 * hc_homepage_settings_defaults() is the CURRENT hardcoded content,
 * reused two ways: as the fallback hc_get_homepage_settings() returns
 * before anyone has ever saved real settings (so index.php never shows
 * blank/broken content on a fresh install), and as subadmin/
 * homepage_settings.php's own "Reset to Default" action.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore

const HC_HOMEPAGE_SETTINGS_DOC_ID = 'homepage';

if (!function_exists('hc_homepage_settings_defaults')) {
    /** @return array{missionText: string, visionText: string, communityFirstText: string, contactAddress: string, contactPhone: string, contactEmail: string, operatingHours: array<int, array{label: string, hours: string}>} */
    function hc_homepage_settings_defaults(): array
    {
        return [
            'missionText' => 'To deliver quality, accessible healthcare services to every resident, regardless of background or circumstance.',
            'visionText' => 'A healthier, well-informed community supported by responsive, modern healthcare services.',
            'communityFirstText' => 'We prioritize the barangays we serve, working closely with local leaders to reach those who need care most.',
            'contactAddress' => '123 Health Center Road, Barangay Central',
            'contactPhone' => '(02) 8123-4567',
            'contactEmail' => 'info@northsuperhealthcenter.gov.ph',
            'operatingHours' => [
                ['label' => 'Monday - Friday', 'hours' => '8:00 AM - 5:00 PM'],
                ['label' => 'Saturday', 'hours' => '8:00 AM - 12:00 PM'],
                ['label' => 'Sunday & Holidays', 'hours' => 'Closed'],
            ],
        ];
    }
}

if (!function_exists('hc_get_homepage_settings')) {
    /**
     * Real stored settings merged over the real defaults (array_merge,
     * not array_replace — a doc that's missing a field entirely, e.g.
     * before quickLinks/operatingHours ever got their own save, still
     * falls back to the default for just that field rather than the
     * whole page breaking) — index.php and subadmin/homepage_settings.php
     * both read through this same function, so they can never disagree
     * on what "the current real settings" are.
     */
    function hc_get_homepage_settings(): array
    {
        global $firestore;

        $stored = $firestore->getDocument('site_settings', HC_HOMEPAGE_SETTINGS_DOC_ID) ?? [];

        return array_merge(hc_homepage_settings_defaults(), $stored);
    }
}
