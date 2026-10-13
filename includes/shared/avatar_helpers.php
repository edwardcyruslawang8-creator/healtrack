<?php
/**
 * =====================================================================
 * Shared avatar/date helpers
 * ---------------------------------------------------------------------
 * Used by every admin page that renders a list of people from live
 * Firestore data (sub-admins, audit log actors, account rows) so a
 * given person's initials/avatar color look the same wherever they
 * appear, and log timestamps render consistently. Guarded with
 * function_exists() since several admin *_data.php files require this
 * independently — safe to require_once from more than one place either
 * way, but this also protects against a future accidental double-define.
 * =====================================================================
 */

if (!function_exists('derive_initials')) {
    /** Deterministic 1-2 letter initials from a display name, for a row avatar. */
    function derive_initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $initials = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $initials .= mb_strtoupper(mb_substr($part, 0, 1));
        }

        return $initials !== '' ? $initials : '?';
    }
}

if (!function_exists('derive_avatar_color')) {
    /**
     * Deterministic avatar color from a seed string (typically a Firebase
     * uid), so the same person always gets the same color across page
     * loads without needing to store a color choice anywhere.
     */
    function derive_avatar_color(string $seed): string
    {
        $palette = ['purple', 'orange', 'green', 'blue', 'red'];

        return $palette[crc32($seed) % count($palette)];
    }
}

if (!function_exists('format_log_datetime')) {
    /** Formats an ISO 8601 timestamp (as written by includes/audit_log_writer.php) for display. */
    function format_log_datetime(?string $iso): string
    {
        if (!$iso) {
            return '—';
        }

        try {
            return (new DateTime($iso))->format('M j, Y h:i A');
        } catch (\Exception $e) {
            return '—';
        }
    }
}
