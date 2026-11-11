<?php
/**
 * =====================================================================
 * Medicine/Vaccine name spelling reference
 * ---------------------------------------------------------------------
 * Backs the Add/Edit Medicine modal's "Medicine Name" spellcheck (see
 * includes/pharmacist/add_medicine_modal.php and api/check-medicine-name.php).
 * hc_known_medicine_and_vaccine_names() is a static list of real generic
 * drug names (Philippine National Drug Formulary / WHO Essential
 * Medicines List — the same real-world names a city health office
 * actually stocks) and real EPI vaccine names, used only to CATCH LIKELY
 * TYPOS of a well-known name — never as a whitelist of every drug a
 * pharmacist is allowed to add. A name that doesn't resemble anything on
 * this list is simply unknown (new/brand/combination drug), not wrong.
 * =====================================================================
 */

if (!function_exists('hc_known_medicine_and_vaccine_names')) {
    /**
     * Real generic medicine + EPI vaccine names used purely as spelling
     * reference points — see this file's header comment.
     */
    function hc_known_medicine_and_vaccine_names(): array
    {
        return [
            // Analgesics / Antipyretics / NSAIDs
            'Paracetamol', 'Acetaminophen', 'Ibuprofen', 'Mefenamic Acid', 'Aspirin',
            'Naproxen', 'Celecoxib', 'Diclofenac', 'Ketorolac', 'Tramadol',
            // Antibiotics
            'Amoxicillin', 'Amoxicillin/Clavulanic Acid', 'Co-Amoxiclav', 'Ampicillin',
            'Penicillin', 'Cloxacillin', 'Cefalexin', 'Cefuroxime', 'Cefixime',
            'Ceftriaxone', 'Azithromycin', 'Erythromycin', 'Clarithromycin',
            'Ciprofloxacin', 'Levofloxacin', 'Ofloxacin', 'Metronidazole',
            'Clindamycin', 'Doxycycline', 'Tetracycline', 'Co-Trimoxazole',
            'Trimethoprim', 'Sulfamethoxazole', 'Gentamicin', 'Vancomycin',
            'Isoniazid', 'Rifampicin', 'Pyrazinamide', 'Ethambutol', 'Streptomycin',
            // Antifungals / Antivirals / Antiparasitics
            'Fluconazole', 'Ketoconazole', 'Griseofulvin', 'Clotrimazole',
            'Nystatin', 'Metronidazole', 'Albendazole', 'Mebendazole',
            'Praziquantel', 'Ivermectin', 'Oseltamivir', 'Acyclovir',
            // Antihistamines / Allergy / Respiratory
            'Cetirizine', 'Loratadine', 'Diphenhydramine', 'Chlorphenamine',
            'Chlorpheniramine', 'Fexofenadine', 'Desloratadine',
            'Salbutamol', 'Albuterol', 'Budesonide', 'Beclomethasone',
            'Theophylline', 'Montelukast', 'Dextromethorphan', 'Carbocisteine',
            'Bromhexine', 'Guaifenesin', 'Ambroxol',
            // Gastrointestinal
            'Omeprazole', 'Esomeprazole', 'Lansoprazole', 'Pantoprazole',
            'Ranitidine', 'Famotidine', 'Antacid', 'Aluminum Hydroxide',
            'Magnesium Hydroxide', 'Simethicone', 'Domperidone', 'Metoclopramide',
            'Loperamide', 'Oral Rehydration Salts', 'Bisacodyl', 'Lactulose',
            'Hyoscine', 'Butylscopolamine', 'Mebeverine',
            // Cardiovascular
            'Amlodipine', 'Nifedipine', 'Losartan', 'Valsartan', 'Telmisartan',
            'Captopril', 'Enalapril', 'Lisinopril', 'Metoprolol', 'Atenolol',
            'Bisoprolol', 'Carvedilol', 'Furosemide', 'Hydrochlorothiazide',
            'Spironolactone', 'Digoxin', 'Isosorbide Dinitrate', 'Clopidogrel',
            'Atorvastatin', 'Simvastatin', 'Rosuvastatin', 'Gemfibrozil',
            'Warfarin', 'Heparin',
            // Diabetes / Endocrine
            'Metformin', 'Glibenclamide', 'Gliclazide', 'Glimepiride',
            'Insulin', 'Pioglitazone', 'Sitagliptin', 'Levothyroxine',
            'Methimazole', 'Propylthiouracil',
            // Vitamins / Minerals / Supplements
            'Ferrous Sulfate', 'Ferrous Fumarate', 'Folic Acid', 'Multivitamins',
            'Ascorbic Acid', 'Vitamin A', 'Vitamin B Complex', 'Vitamin B1',
            'Vitamin B6', 'Vitamin B12', 'Vitamin C', 'Vitamin D', 'Calcium Carbonate',
            'Zinc Sulfate', 'Iron', 'Thiamine', 'Pyridoxine', 'Cyanocobalamin',
            // CNS / Psychiatric / Anesthetics
            'Diazepam', 'Lorazepam', 'Phenobarbital', 'Phenytoin',
            'Carbamazepine', 'Valproic Acid', 'Amitriptyline', 'Fluoxetine',
            'Sertraline', 'Haloperidol', 'Risperidone', 'Chlorpromazine',
            'Lidocaine', 'Morphine', 'Codeine',
            // OB-Gyn / Reproductive
            'Oxytocin', 'Methylergometrine', 'Misoprostol', 'Magnesium Sulfate',
            'Medroxyprogesterone', 'Ethinylestradiol', 'Levonorgestrel',
            'Clomiphene',
            // Dermatologic / Topical / Antiseptic
            'Hydrocortisone', 'Betamethasone', 'Mupirocin', 'Silver Sulfadiazine',
            'Povidone Iodine', 'Chlorhexidine', 'Calamine', 'Benzyl Benzoate',
            'Permethrin', 'Salicylic Acid',
            // Ophthalmic / ENT
            'Chloramphenicol', 'Tobramycin', 'Timolol', 'Sodium Chloride',
            // IV Fluids / Electrolytes
            'Dextrose', 'Potassium Chloride', 'Sodium Bicarbonate',
            'Calcium Gluconate', 'Ringer\'s Lactate',
            // EPI / routine vaccines
            'BCG Vaccine', 'Hepatitis B Vaccine', 'Pentavalent Vaccine',
            'Pentavalent (DPT-HepB-Hib) Vaccine', 'Oral Polio Vaccine',
            'Inactivated Polio Vaccine', 'Measles, Mumps, Rubella Vaccine',
            'Measles-Rubella Vaccine', 'Pneumococcal Conjugate Vaccine',
            'Rotavirus Vaccine', 'Tetanus Toxoid Vaccine',
            'Tetanus-Diphtheria Vaccine', 'Japanese Encephalitis Vaccine',
            'Influenza Vaccine', 'Human Papillomavirus Vaccine',
            'Rabies Vaccine', 'COVID-19 Vaccine', 'Varicella Vaccine',
            'Hepatitis A Vaccine', 'Typhoid Vaccine', 'Yellow Fever Vaccine',
        ];
    }
}

if (!function_exists('hc_medicine_name_lead_token')) {
    /**
     * The drug/vaccine-NAME portion of a full item name, e.g.
     * "Amoxicillin 500 mg Capsule" -> "Amoxicillin"; "Paracetamol" ->
     * "Paracetamol" (unchanged when there's no trailing dosage/form).
     * Stops at the first digit — dosage strengths and units never are
     * part of the spelling check, only the drug name itself is.
     *
     * @return array{0: string, 1: string} [leadName, remainder]
     */
    function hc_medicine_name_lead_token(string $rawName): array
    {
        $rawName = trim($rawName);
        if ($rawName === '' || !preg_match('/^([A-Za-z][A-Za-z\'\-\/ ]*)/', $rawName, $m)) {
            return [$rawName, ''];
        }
        $lead = rtrim($m[1]);
        // Sliced at strlen($lead), not strlen($m[1]) — $m[1] may include
        // trailing spaces trimmed off of $lead, and those spaces need to
        // stay as the separator before whatever follows (e.g. a dosage
        // number), not get silently swallowed.
        $rest = substr($rawName, strlen($lead));

        return [$lead, $rest];
    }
}

if (!function_exists('hc_medicine_name_spelling_check')) {
    /**
     * Flags a likely MISSPELLING of a well-known medicine/vaccine name —
     * never a "this drug doesn't exist" rejection. Compares only the
     * leading drug-name portion (hc_medicine_name_lead_token()) against
     * hc_known_medicine_and_vaccine_names() PLUS every name already saved
     * in the real "medicines" collection (so previously accepted names,
     * brand names included, are never retroactively flagged). An exact
     * case-insensitive match -> 'ok'. A name within a small Levenshtein
     * distance of exactly one known name but NOT an exact match -> 'suggest'
     * with the corrected full name. Anything else (too short to judge, or
     * not close to anything known — a genuinely new/uncommon drug) -> 'ok',
     * since this can only catch likely typos, not validate every possible
     * real medicine name.
     *
     * @return array{status: string, suggestion: ?string}
     */
    function hc_medicine_name_spelling_check(string $rawName): array
    {
        global $firestore;

        [$lead, $rest] = hc_medicine_name_lead_token($rawName);
        if (mb_strlen($lead) < 3) {
            return ['status' => 'ok', 'suggestion' => null];
        }

        $known = hc_known_medicine_and_vaccine_names();
        foreach ($firestore->listDocuments('medicines') as $doc) {
            $existingName = trim((string) ($doc['fields']['name'] ?? ''));
            if ($existingName === '') {
                continue;
            }
            [$existingLead] = hc_medicine_name_lead_token($existingName);
            if ($existingLead !== '') {
                $known[] = $existingLead;
            }
        }

        $leadLower = mb_strtolower($lead);
        $bestDistance = PHP_INT_MAX;
        $bestMatch = null;
        foreach ($known as $candidate) {
            $candidateLower = mb_strtolower($candidate);
            if ($candidateLower === $leadLower) {
                return ['status' => 'ok', 'suggestion' => null];
            }
            $d = levenshtein($leadLower, $candidateLower);
            if ($d < $bestDistance) {
                $bestDistance = $d;
                $bestMatch = $candidate;
            }
        }

        // Tighter for short names (a 1-letter slip on a 4-letter word is
        // already a different word), looser for long ones — capped so a
        // genuinely different drug is never forced into a "correction".
        $threshold = max(1, min(3, (int) floor(mb_strlen($lead) / 4)));
        if ($bestMatch !== null && $bestDistance > 0 && $bestDistance <= $threshold) {
            return ['status' => 'suggest', 'suggestion' => trim($bestMatch) . $rest];
        }

        return ['status' => 'ok', 'suggestion' => null];
    }
}
