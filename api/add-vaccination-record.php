<?php
/**
 * =====================================================================
 * Add Vaccination Record (api/add-vaccination-record.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/add-vaccination.js from the "Add Vaccination
 * Record" modal (includes/medical-staff/add_vaccination_modal.php) on
 * medical-staff/vaccination_records.php. Writes to the real
 * "vaccination_records" collection in the exact field shape
 * includes/firestore/vaccination_records.php's
 * hc_get_vaccination_records_for_patient() reads back — every string
 * field it accesses is required there (no "??" fallback), so unknown
 * values are stored as "—" rather than omitted.
 *
 * Three kinds of entries ("recordType"):
 *   - "historical": a vaccine received before registration, entered
 *     from a vaccination card, old record, or patient-provided history.
 *   - "administered_elsewhere": a vaccine given at another hospital,
 *     clinic, or provider (not this facility), at any time.
 *     Administered By / Facility are taken as entered (blank =
 *     unknown) for both of the above — the signed-in staff member is
 *     NEVER assumed to be the one who gave it.
 *   - "administered_here": given at the staff member's own facility
 *     on or after the patient's registration date.
 * dateAdministered is always the real administration date the user
 * entered; when the entry itself was made is kept separately in
 * recordedOn/recordedAt/recordedBy.
 *
 * The legacy lotNumber/expiryDate/route/site fields, and manufacturer,
 * aren't collected by this form; they're stored as "—"/null so
 * existing readers and the details page still work.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/shared/document_storage.php'; // hc_user_can_access_patient_document()
require_once __DIR__ . '/../includes/shared/audit_log_writer.php'; // write_audit_log()
require_once __DIR__ . '/../includes/firestore/patient_history.php'; // hc_record_patient_history(), hc_record_patient_activity()

header('Content-Type: application/json');

function avx_fail(int $status, string $message): never
{
    http_response_code($status);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

if (($_SESSION['role'] ?? null) !== 'healthcare_staff') {
    avx_fail(403, 'Not authorized.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    avx_fail(405, 'Method not allowed.');
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$text = fn (string $key, int $maxLen = 160) => mb_substr(trim((string) ($body[$key] ?? '')), 0, $maxLen);

// Valid Y-m-d or null — rejects anything strtotime() would loosely accept.
$ymd = function (string $value): ?string {
    $d = DateTime::createFromFormat('!Y-m-d', $value);
    return ($d && $d->format('Y-m-d') === $value) ? $value : null;
};
// Y-m-d part of a stored date/ISO timestamp, or null.
$datePart = fn ($value) => is_string($value) && strtotime($value) ? date('Y-m-d', strtotime($value)) : null;

$patientId = $text('patientId', 128);
$recordType = $text('recordType');
$name = $text('name', 120);
$type = $text('type');
$dose = $text('dose', 60);
$dateRaw = $text('dateAdministered');
$nextRaw = $text('nextDoseDue');
$dateAdministered = $ymd($dateRaw);
$nextDoseDue = $ymd($nextRaw);
$manufacturer = $text('manufacturer', 120);
$givenBy = $text('givenBy', 120);
$facility = $text('facility', 160);
$notes = $text('notes', 500);
$today = date('Y-m-d');

if (!in_array($recordType, ['historical', 'administered_elsewhere', 'administered_here'], true)) {
    avx_fail(422, 'Please choose a record type.');
}
if ($patientId === '' || $name === '' || $type === '' || $dose === '' || $dateRaw === '') {
    avx_fail(422, 'Please fill in the vaccine name, type, dose / series, and date administered.');
}
if ($dateAdministered === null) {
    avx_fail(422, 'Please enter a valid date administered.');
}
if ($dateAdministered > $today) {
    avx_fail(422, 'Date administered cannot be in the future.');
}
if ($nextRaw !== '' && ($nextDoseDue === null || $nextDoseDue <= $dateAdministered)) {
    avx_fail(422, 'Next dose due must be a valid date after the date administered.');
}

$patient = $firestore->getDocument('patients', $patientId);
if ($patient === null) {
    avx_fail(404, 'This patient could not be found.');
}
$patient['id'] = $patientId;
if (!hc_user_can_access_patient_document($patient)) {
    avx_fail(404, 'This patient could not be found.');
}

$birthDate = $datePart($patient['birthDate'] ?? null);
if ($birthDate !== null && $dateAdministered < $birthDate) {
    avx_fail(422, 'Date administered cannot be before the patient\'s birth date.');
}

$staffName = $_SESSION['name'] ?? 'Medical Staff';
$myDoc = !empty($_SESSION['uid']) ? $firestore->getDocument('users', $_SESSION['uid']) : null;
$staffFacility = $myDoc['facility'] ?? ($patient['facility'] ?? '');

if ($recordType === 'administered_here') {
    $registeredOn = $datePart($patient['dateRegistered'] ?? null);
    if ($registeredOn !== null && $dateAdministered < $registeredOn) {
        avx_fail(422, 'This date is before the patient registered. Record it as a Historical Vaccination instead.');
    }
    if ($givenBy === '') {
        avx_fail(422, 'Please enter who administered the vaccine.');
    }
    // Always the staff member's own facility — not taken from the form.
    $facility = $staffFacility !== '' ? $staffFacility : '—';
}

// Same vaccine + dose + date already on file for this patient — almost
// always a double entry (e.g. the card was entered twice).
foreach ($firestore->listDocuments('vaccination_records') as $doc) {
    $f = $doc['fields'];
    if (($f['patientId'] ?? '') === $patientId
        && ($f['dateAdministered'] ?? '') === $dateAdministered
        && strcasecmp(trim($f['name'] ?? ''), $name) === 0
        && strcasecmp(trim($f['dose'] ?? ''), $dose) === 0) {
        avx_fail(409, 'This vaccination (' . $name . ', ' . $dose . ', ' . date('M j, Y', strtotime($dateAdministered)) . ') is already recorded for this patient.');
    }
}

$newId = $firestore->addDocument('vaccination_records', [
    'patientId' => $patientId,
    'recordType' => $recordType,
    'name' => $name,
    'type' => $type,
    'description' => '—',
    'status' => 'completed',
    'dateAdministered' => $dateAdministered,
    'dose' => $dose,
    'manufacturer' => $manufacturer !== '' ? $manufacturer : '—',
    'lotNumber' => '—',
    'expiryDate' => null,
    'givenBy' => $givenBy !== '' ? $givenBy : '—',
    'facility' => $facility !== '' ? $facility : '—',
    'route' => '—',
    'site' => '—',
    'notes' => $notes !== '' ? $notes : '—',
    'nextDoseDue' => $nextDoseDue,
    // When/by whom this entry was made — separate from dateAdministered.
    'recordedOn' => $today,
    'recordedAt' => date('c'),
    'recordedBy' => $staffName,
    'recordedByUid' => $_SESSION['uid'] ?? '',
]);

if ($newId === null) {
    avx_fail(500, 'Could not save this vaccination record. Please try again.');
}

$kind = match ($recordType) {
    'historical' => 'historical vaccination',
    'administered_elsewhere' => 'vaccination (administered elsewhere)',
    default => 'vaccination',
};
$when = date('M j, Y', strtotime($dateAdministered));
write_audit_log('created', 'Patient Management', 'Added a ' . $kind . ' record (' . $name . ', ' . $dose . ', given ' . $when . ') for ' . ($patient['name'] ?? 'a patient') . '.');
hc_record_patient_history($patientId, 'vaccination_record', 'added', 'Added ' . $kind . ' "' . $name . '" (' . $dose . ', given ' . $when . ').');
hc_record_patient_activity($patientId, ucfirst($kind) . ' "' . $name . '" (' . $dose . ') recorded.');

echo json_encode(['success' => true, 'id' => $newId]);
