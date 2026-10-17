<?php
/**
 * =====================================================================
 * Shared — Vital Signs range validation
 * ---------------------------------------------------------------------
 * The one real set of clinically-sane bounds every place in this app
 * that accepts a Vital Signs reading enforces — registration intake
 * (api/create-patient.php), medical staff's "Set Consultation" modal
 * (api/create-consultation.php / api/update-consultation.php), and the
 * doctor's own mid-consultation temperature/BP/heart-rate edit
 * (api/complete-consultation.php). Each of those also carries the same
 * min/max/step on its own real HTML inputs
 * (includes/medical-staff/add_patient_modal.php,
 * doctor/active_consultation.php) — this is the server-side half,
 * since a client-side-only check is trivially bypassable.
 * =====================================================================
 */

if (!function_exists('hc_validate_vitals')) {
    /**
     * Checks $vitals['temperature'|'bloodPressureSystolic'|'bloodPressureDiastolic'|'heartRate'|'weight'|'height']
     * (each a raw, already-trimmed string) against real ranges, returning
     * the first error message found, or null if everything present is
     * valid. Systolic/Diastolic/Heart Rate are whole numbers only — no
     * decimal reading is meaningful for any of the three, checked via
     * ctype_digit() on the raw string rather than is_numeric() (which
     * would accept "120.5").
     *
     * @param bool $requireNonBlank True for a full Vital Signs intake
     *             (registration, "Set Consultation") where every one of
     *             the six is mandatory; false for the doctor's own
     *             optional mid-consultation edit, where a blank
     *             temperature/BP/heart-rate just means "left unchanged"
     *             (weight/height aren't doctor-editable there at all).
     */
    function hc_validate_vitals(array $vitals, bool $requireNonBlank): ?string
    {
        $temperature = trim((string) ($vitals['temperature'] ?? ''));
        $systolic = trim((string) ($vitals['bloodPressureSystolic'] ?? ''));
        $diastolic = trim((string) ($vitals['bloodPressureDiastolic'] ?? ''));
        $heartRate = trim((string) ($vitals['heartRate'] ?? ''));
        $weight = trim((string) ($vitals['weight'] ?? ''));
        $height = trim((string) ($vitals['height'] ?? ''));

        if ($requireNonBlank && ($temperature === '' || $systolic === '' || $diastolic === ''
            || $heartRate === '' || $weight === '' || $height === '')) {
            return 'Please fill out every required field, including Vital Signs.';
        }

        if ($temperature !== '' && (!is_numeric($temperature) || $temperature < 25 || $temperature > 45)) {
            return 'Temperature must be between 25.0°C and 45.0°C.';
        }
        if ($systolic !== '' && (!ctype_digit($systolic) || (int) $systolic < 40 || (int) $systolic > 300)) {
            return 'Systolic blood pressure must be a whole number between 40 and 300 mmHg.';
        }
        if ($diastolic !== '' && (!ctype_digit($diastolic) || (int) $diastolic < 20 || (int) $diastolic > 200)) {
            return 'Diastolic blood pressure must be a whole number between 20 and 200 mmHg.';
        }
        if ($heartRate !== '' && (!ctype_digit($heartRate) || (int) $heartRate < 20 || (int) $heartRate > 250)) {
            return 'Heart rate must be a whole number between 20 and 250 bpm.';
        }
        if ($weight !== '' && (!is_numeric($weight) || $weight < 1 || $weight > 500)) {
            return 'Weight must be between 1 and 500 kg.';
        }
        if ($height !== '' && (!is_numeric($height) || $height < 30 || $height > 250)) {
            return 'Height must be between 30 and 250 cm.';
        }

        return null;
    }
}
