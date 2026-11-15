<?php
/**
 * =====================================================================
 * Medication duration backfill (scripts/backfill-medication-durations.php)
 * ---------------------------------------------------------------------
 * CLI-only, one-off. Medications prescribed during a consultation BEFORE
 * automatic Current -> Past classification existed were written with
 * endDate = null — the prescriber's Duration ("5 days") was only saved
 * on the consultation itself. Without an end date they can't move to
 * Past Medications on schedule.
 *
 * For every medications/{id} doc that has no consultationId yet and whose
 * notes say "Prescribed during consultation <CODE> on ...", this finds
 * that consultation (same patient, same code), the matching medicine row
 * (same name), and adds — never overwrites — consultationId,
 * consultationCode, duration, durationDays, isMaintenance and, only
 * when endDate is still empty, endDate (computed from the doc's own
 * original startDate exactly as api/complete-consultation.php now does).
 * Nothing else on the doc changes. Docs already carrying consultationId
 * are skipped, so re-running is safe. --apply backs up every doc it is
 * about to change to storage/medication_backups/ first.
 *
 * Dry run (default):  C:\xampp\php\php.exe scripts\backfill-medication-durations.php
 * Apply changes:      C:\xampp\php\php.exe scripts\backfill-medication-durations.php --apply
 * =====================================================================
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script is CLI-only.');
}

require_once __DIR__ . '/../config/firebase.php'; // $firestore
require_once __DIR__ . '/../includes/firestore/medications.php'; // hc_build_prescribed_medication_fields()

$apply = in_array('--apply', $argv, true);

$consultationsByPatient = [];
$plan = [];
foreach ($firestore->listDocuments('medications') as $doc) {
    $f = $doc['fields'];
    if (array_key_exists('consultationId', $f)) {
        continue;
    }
    if (!preg_match('/Prescribed during consultation (\S+) on/', (string) ($f['notes'] ?? ''), $m)) {
        continue;
    }
    $code = $m[1];
    $patientId = (string) ($f['patientId'] ?? '');
    $consultationsByPatient[$patientId] ??= $firestore->listDocumentsWhere('consultations', 'patientId', $patientId);

    $consultation = null;
    foreach ($consultationsByPatient[$patientId] as $c) {
        if (($c['fields']['consultationCode'] ?? '') === $code) {
            $consultation = $c;
            break;
        }
    }
    if ($consultation === null) {
        printf("SKIP  %-34s %s — consultation %s not found for patient\n", $f['name'] ?? '', $doc['id'], $code);
        continue;
    }

    $row = null;
    foreach ($consultation['fields']['medications'] ?? [] as $r) {
        if (($r['type'] ?? 'medication') === 'medication' && mb_strtolower(trim((string) ($r['name'] ?? ''))) === mb_strtolower(trim((string) ($f['name'] ?? '')))) {
            $row = $r;
            break;
        }
    }
    $duration = trim((string) ($row['duration'] ?? ''));
    $computed = hc_build_prescribed_medication_fields((string) ($f['startDate'] ?? ''), $duration);

    $updates = [
        'consultationId' => $consultation['id'],
        'consultationCode' => $code,
        'duration' => $computed['duration'],
        'durationDays' => $computed['durationDays'],
        'isMaintenance' => $computed['isMaintenance'],
    ];
    if (empty($f['endDate'])) {
        $updates['endDate'] = $computed['endDate'];
    }
    $plan[] = ['doc' => $doc, 'updates' => $updates];

    $after = hc_medication_classify($updates + $f);
    printf(
        "%-6s %-34s start %s  duration %-16s -> end %-10s  now: %s\n",
        $apply ? 'APPLY' : 'PLAN',
        mb_strimwidth((string) ($f['name'] ?? ''), 0, 34),
        $f['startDate'] ?? '?',
        $duration !== '' ? '"' . $duration . '"' : '(blank)',
        $updates['endDate'] ?? ($f['endDate'] ?: '—'),
        $after['label']
    );
}

echo "\n" . count($plan) . " medication doc(s) to update.\n";
if (!$apply || $plan === []) {
    echo $apply ? "Nothing to do.\n" : "Dry run — nothing written. Re-run with --apply to save.\n";
    exit(0);
}

$backupDir = __DIR__ . '/../storage/medication_backups';
if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true)) {
    exit("Could not create $backupDir — aborting before any write.\n");
}
$backupFile = $backupDir . '/medications-before-duration-backfill-' . date('Ymd-His') . '.json';
file_put_contents($backupFile, json_encode(array_map(fn ($p) => $p['doc'], $plan), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "Backup written to $backupFile\n";

$ok = 0;
foreach ($plan as $p) {
    if ($firestore->setDocument('medications', $p['doc']['id'], $p['updates'] + $p['doc']['fields'])) {
        $ok++;
    } else {
        echo "FAILED to update {$p['doc']['id']}\n";
    }
}
echo "Updated $ok of " . count($plan) . ".\n";
