<?php
/**
 * =====================================================================
 * Firestore Helper Functions
 * ---------------------------------------------------------------------
 * Reads the content that includes/data.php used to hardcode as
 * placeholder arrays. Schema (one Firestore doc/collection per
 * function) follows the "Future:" comments that used to live here:
 *
 *   - dashboard/stats  (doc)        totalPatients, consultationsThisMonth, vaccinesThisMonth
 *   - medicines        (collection) name, icon, status, color, desc,
 *                                   category, form, type ("medicine" or
 *                                   "vaccine" — getMedicines() below
 *                                   shows both on index.php's combined
 *                                   widget; getAllMedicines() filters to
 *                                   "medicine" for patient/medicine.php's
 *                                   Medicines tab, since its Vaccines tab
 *                                   isn't built yet)
 *   - announcements    (collection) icon, color, title, desc,
 *                                   postedAt (timestamp), category
 *                                   ("general"/"alert"/"event"), important (bool)
 *
 * Card icon/title/sub labels for dashboard stats are UI concerns, not
 * data, so they stay defined here rather than in Firestore.
 *
 * Each function fails soft (logs + returns []) if Firestore is
 * unreachable or the doc/collection doesn't exist yet — the pages that
 * consume these just render an empty state rather than erroring.
 *
 * getMedicines()/getAnnouncements() power the small "latest N" widgets
 * on index.php; getAllMedicines()/getAllAnnouncements() below power the
 * full listing/search/filter pages (patient/medicine.php,
 * patient/announcements.php) — separate functions rather than one with
 * a $limit=null option, since the two call sites want differently
 * shaped return arrays (the widgets only need a few display fields,
 * the full pages need every field plus the doc id).
 * =====================================================================
 */

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/firestore/consultations.php'; // hc_get_all_consultations()
require_once __DIR__ . '/../includes/firestore/vaccination_records.php'; // hc_get_all_vaccination_record_docs()

/**
 * getDashboardStats() used to read a single dashboard/stats summary
 * doc — real, but never updated by anything, so it sat at 0/0/0
 * forever regardless of real patient/consultation/vaccination
 * activity elsewhere in the app. Computed live from those real
 * collections instead: totalPatients is a straight count;
 * consultationsThisMonth/vaccinesThisMonth filter to the current
 * calendar month by real date fields, so they'll correctly show 0
 * when nothing genuinely happened this month (matching the current
 * seed data's dates) rather than always showing a stale placeholder.
 */
function getDashboardStats(): array
{
    global $firestore;

    $totalPatients = count($firestore->listDocuments('patients'));

    $thisMonth = date('Y-m');

    $consultationsThisMonth = count(array_filter(
        hc_get_all_consultations(),
        fn ($c) => str_starts_with($c['dateTime'], $thisMonth)
    ));

    $vaccinesThisMonth = count(array_filter(
        hc_get_all_vaccination_record_docs(),
        fn ($doc) => ($doc['fields']['status'] ?? '') === 'completed'
            && str_starts_with($doc['fields']['dateAdministered'] ?? '', $thisMonth)
    ));

    return [
        [
            'icon' => 'bi-people-fill',
            'value' => number_format($totalPatients),
            'title' => 'Total Patients',
            'sub' => 'This Year',
        ],
        [
            'icon' => 'bi-calendar-check-fill',
            'value' => number_format($consultationsThisMonth),
            'title' => 'Consultations',
            'sub' => 'This Month',
        ],
        [
            'icon' => 'bi-droplet-half', // "bi-syringe" isn't a real Bootstrap Icons 1.11.3 class — rendered blank. Same real vaccine icon includes/firestore/medicines.php already uses.
            'value' => number_format($vaccinesThisMonth),
            'title' => 'Vaccines Administered',
            'sub' => 'This Month',
        ],
    ];
}

function getMedicines(int $limit = 4): array
{
    global $firestore;

    $medicines = array_map(
        fn ($doc) => [
            'name' => $doc['fields']['name'] ?? '',
            'icon' => $doc['fields']['icon'] ?? 'bi-capsule',
            'status' => $doc['fields']['status'] ?? 'unavailable',
        ],
        $firestore->listDocuments('medicines'),
    );

    usort($medicines, fn ($a, $b) => $a['name'] <=> $b['name']);

    return array_slice($medicines, 0, $limit);
}

function getAnnouncements(int $limit = 3): array
{
    global $firestore;

    $announcements = array_map(
        fn ($doc) => [
            'icon' => $doc['fields']['icon'] ?? 'bi-megaphone-fill',
            'color' => $doc['fields']['color'] ?? 'blue',
            'title' => $doc['fields']['title'] ?? '',
            'desc' => $doc['fields']['desc'] ?? '',
            'postedAt' => $doc['fields']['postedAt'] ?? '',
            // Missing status = posted before the admin-approval workflow
            // existed — grandfathered in as already-approved rather than
            // retroactively hiding something that was already public.
            'status' => $doc['fields']['status'] ?? 'approved',
            'date' => isset($doc['fields']['postedAt']) && $doc['fields']['postedAt'] !== ''
                ? date('M j, Y', strtotime($doc['fields']['postedAt']))
                : '',
        ],
        $firestore->listDocuments('announcements'),
    );

    // Public-facing widget — a pending/rejected announcement must never
    // appear here, same rule includes/public/announcements-data.php's
    // own copy of this filter enforces for the full listing page.
    $announcements = array_values(array_filter($announcements, fn ($a) => $a['status'] === 'approved'));

    usort($announcements, fn ($a, $b) => $b['postedAt'] <=> $a['postedAt']);

    return array_map(
        fn ($a) => array_diff_key($a, ['postedAt' => true, 'status' => true]),
        array_slice($announcements, 0, $limit),
    );
}

/**
 * Every "medicine"-typed doc in the medicines collection (vaccines are
 * the sibling getAllVaccines() below), with every field the full
 * listing page needs. patient/medicine.php's Medicines tab applies its
 * own search/category/form/availability filters and pagination over
 * the result of this, same as includes/announcements-data.php already
 * did for a static array before this collection existed.
 */
function getAllMedicines(): array
{
    global $firestore;

    $medicines = array_map(
        fn ($doc) => [
            'id' => $doc['id'],
            'name' => $doc['fields']['name'] ?? '',
            'icon' => $doc['fields']['icon'] ?? 'bi-capsule',
            'color' => $doc['fields']['color'] ?? 'green',
            'desc' => $doc['fields']['desc'] ?? '',
            'category' => $doc['fields']['category'] ?? '',
            'form' => $doc['fields']['form'] ?? '',
            'status' => $doc['fields']['status'] ?? 'unavailable',
            'type' => $doc['fields']['type'] ?? 'medicine',
            'archived' => $doc['fields']['archived'] ?? false,
        ],
        $firestore->listDocuments('medicines'),
    );

    // Archived (api/archive-medicine.php) means discontinued/no longer
    // carried — never shown to patients, same reasoning as every
    // staff-side inventory list excluding it from the default view.
    $medicines = array_values(array_filter($medicines, fn ($m) => $m['type'] === 'medicine' && !$m['archived']));

    usort($medicines, fn ($a, $b) => $a['name'] <=> $b['name']);

    return $medicines;
}

/**
 * Every "vaccine"-typed doc in the SAME real "medicines" collection
 * getAllMedicines() reads — medicines and vaccines aren't separate
 * collections, just split by a "type" field (see
 * includes/firestore/medicines.php's own header comment, which the
 * staff-side inventory pages already rely on for this same split).
 * patient/medicine.php's Vaccines tab applies its own search/form/
 * availability filters and pagination over the result of this — no
 * category filter there, since every real vaccine doc's "category" is
 * just the literal string "Vaccine" (not a useful filter dimension the
 * way it is for medicines).
 */
function getAllVaccines(): array
{
    global $firestore;

    $vaccines = array_map(
        fn ($doc) => [
            'id' => $doc['id'],
            'name' => $doc['fields']['name'] ?? '',
            'icon' => $doc['fields']['icon'] ?? 'bi-droplet-half',
            'color' => $doc['fields']['color'] ?? 'blue',
            'desc' => $doc['fields']['desc'] ?? '',
            'category' => $doc['fields']['category'] ?? '',
            'form' => $doc['fields']['form'] ?? '',
            'status' => $doc['fields']['status'] ?? 'unavailable',
            'type' => $doc['fields']['type'] ?? 'medicine',
            'archived' => $doc['fields']['archived'] ?? false,
        ],
        $firestore->listDocuments('medicines'),
    );

    // Archived (api/archive-medicine.php) — same reasoning as getAllMedicines() above.
    $vaccines = array_values(array_filter($vaccines, fn ($m) => $m['type'] === 'vaccine' && !$m['archived']));

    usort($vaccines, fn ($a, $b) => $a['name'] <=> $b['name']);

    return $vaccines;
}

/**
 * Every doc in the announcements collection, with every field the full
 * listing page needs (unlike getAnnouncements() above, which only
 * returns the newest few display fields for index.php's widget).
 * patient/announcements.php applies its own category/search filters
 * and pagination over the result of this.
 */
function getAllAnnouncements(): array
{
    global $firestore;

    $announcements = array_map(
        fn ($doc) => [
            'id' => $doc['id'],
            'icon' => $doc['fields']['icon'] ?? 'bi-megaphone-fill',
            'iconColor' => $doc['fields']['color'] ?? 'blue',
            'title' => $doc['fields']['title'] ?? '',
            'desc' => $doc['fields']['desc'] ?? '',
            'postedAt' => $doc['fields']['postedAt'] ?? '',
            'date' => isset($doc['fields']['postedAt']) && $doc['fields']['postedAt'] !== ''
                ? date('M j, Y', strtotime($doc['fields']['postedAt']))
                : '',
            'category' => $doc['fields']['category'] ?? 'general',
            'important' => (bool) ($doc['fields']['important'] ?? false),
            'postedBy' => $doc['fields']['postedBy'] ?? '',
            // Missing status = posted before the admin-approval workflow
            // existed — grandfathered in as already-approved (see
            // api/create-announcement.php, which now always stamps a
            // real status of its own on every NEW announcement) rather
            // than retroactively hiding something that was already
            // public. Every caller of this function decides for itself
            // whether pending/rejected rows belong in what it shows —
            // this returns the real, complete, unfiltered set:
            // includes/public/announcements-data.php (patients) keeps
            // only 'approved'; includes/subadmin/announcements_firestore.php
            // (a sub-admin reviewing their own posts) and
            // includes/admin/announcements_firestore.php (the approval
            // queue) both need every status.
            'status' => $doc['fields']['status'] ?? 'approved',
        ],
        $firestore->listDocuments('announcements'),
    );

    usort($announcements, fn ($a, $b) => $b['postedAt'] <=> $a['postedAt']);

    return $announcements;
}
