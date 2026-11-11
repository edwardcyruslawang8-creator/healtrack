<?php
/**
 * =====================================================================
 * Medical Staff notifications — single source
 * ---------------------------------------------------------------------
 * The real list a healthcare-staff user sees, shared by:
 *   - medical-staff/notifications.php (includes/medical-staff/notifications_medstaff_firestore.php)
 *   - medical-staff/dashboard.php's "Recent Notifications" panel
 *     (includes/medical-staff/medical_staff_dashboard_firestore.php)
 * so the two can never disagree. Same composition the Notifications
 * page always used: the 2FA / phone / email-verification reminders,
 * plus facility follow-up consultations — gated by the user's own
 * notification preferences (includes/shared/notification_preferences.php).
 * =====================================================================
 */

require_once __DIR__ . '/../firestore/consultations.php'; // hc_get_followup_notifications()
require_once __DIR__ . '/../shared/two_fa_reminder.php'; // hc_get_2fa_reminder_notification()
require_once __DIR__ . '/../shared/phone_reminder.php'; // hc_get_phone_reminder_notification()
require_once __DIR__ . '/../shared/email_verification_reminder.php'; // hc_my_email_verification_notification()
require_once __DIR__ . '/../shared/notification_preferences.php'; // hc_notification_preference_enabled_for_doc()
require_once __DIR__ . '/../shared/consent_notifications.php'; // hc_get_consent_decision_notifications()

if (!function_exists('hc_medstaff_notification_list')) {
    /**
     * @param string $uid   the signed-in user's uid
     * @param array  $doc   their users/{uid} doc ([] if unknown)
     * @return array<int, array> raw notifications (id, title, description, type, date, time, icon, iconBg, iconColor, …)
     */
    function hc_medstaff_notification_list(string $uid, array $doc): array
    {
        if (!hc_notification_preference_enabled_for_doc($doc, 'system')) {
            return [];
        }

        return array_merge(
            array_filter([hc_get_2fa_reminder_notification($uid, $doc)]),
            array_filter([hc_get_phone_reminder_notification($uid, $doc)]),
            array_filter([hc_my_email_verification_notification()]),
            hc_get_consent_decision_notifications($uid),
            hc_notification_preference_enabled_for_doc($doc, 'consultation') ? hc_get_followup_notifications() : []
        );
    }
}
