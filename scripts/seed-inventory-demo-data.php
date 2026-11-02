<?php
/**
 * =====================================================================
 * Demo Inventory Seed (scripts/seed-inventory-demo-data.php)
 * ---------------------------------------------------------------------
 * CLI-only. REPLACES every medicines/{id} and medicine_batches/{id} doc
 * with:
 *   - every medicine row of the facility's Medicines.xlsx (item names
 *     and on-hand quantities/units exactly as the spreadsheet records
 *     them — only whitespace, two misspellings ("Omemprazole",
 *     "Multivatimin") and three verified corrections are changed:
 *     "Clonidine 75mg" -> 75mcg (clonidine tablets are 75 micrograms),
 *     "Paracetamol 50mg" -> 500mg, and "Tetox" -> "Tetanus Toxoid
 *     (Tetox)", listed as a vaccine), and
 *   - a set of common community health center vaccines.
 *
 * SAMPLE / DEMO DATA: the spreadsheet only records item names and how
 * much is on hand (its EXPIRY DATE column is empty), and there is no
 * real vaccine inventory at all. Every category, dosage, description,
 * batch number, manufacturing/received/expiry date, threshold, the
 * stock of every spreadsheet row left blank, and every vaccine record
 * below is GENERATED for demonstration — not verified inventory. Every
 * doc written here carries isSampleData: true (plus dataSource and, on
 * medicines, generatedFields) so it can be told apart from real data.
 *
 * Consistency with the app's own inventory logic:
 *   - Each item's stock is split across 1-3 batches, each with its own
 *     unique batch number, expiry and quantity. The medicines/{id}
 *     rollup (currentStock = sum of batch quantities, expiryDate =
 *     soonest-expiring batch with stock, status) is then written by
 *     hc_recompute_medicine_rollup() itself, so FEFO, the main table's
 *     oldest-batch expiry and Low/In/Out of Stock all come from the
 *     same code a real restock or dispense runs.
 *   - minLevel/reorderLevel come from hc_auto_compute_thresholds() over
 *     the stock on hand right after the item's last restock (current
 *     stock + what FEFO has drawn off the oldest batch since) — the
 *     same moment api/restock-medicine.php computes them — so items
 *     that have since been drawn down below 20% read as Low Stock.
 *   - Expiry dates are relative to the day this runs: some items have
 *     an already-expired oldest batch, some expire within 30 days
 *     (Expiring Soon), some within 31-90 days, the rest further out.
 *
 * Generation is deterministic (seeded per item name), and doc ids are
 * derived from names, so re-running produces the same dataset instead
 * of duplicates — but it also resets any stock dispensed since.
 * Before deleting anything, --apply writes every existing medicines/
 * medicine_batches doc to storage/inventory_backups/.
 *
 * Dry run (default):  C:\xampp\php\php.exe scripts\seed-inventory-demo-data.php
 * Apply changes:      C:\xampp\php\php.exe scripts\seed-inventory-demo-data.php --apply
 * =====================================================================
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script is CLI-only.');
}

require_once __DIR__ . '/../config/firebase.php'; // $firestore
require_once __DIR__ . '/../includes/firestore/medicines.php'; // hc_auto_compute_thresholds(), hc_inventory_status_for_stock()
require_once __DIR__ . '/../includes/firestore/medicine_batches.php'; // hc_recompute_medicine_rollup()
require_once __DIR__ . '/inventory-unit-profiles.php'; // hc_demo_unit_profile()

$apply = in_array('--apply', $argv, true);
$today = new DateTimeImmutable('today');

// ---------------------------------------------------------------------
// Medicines.xlsx — [name, category, dosage, description, quantity, unit]
// quantity/unit are the spreadsheet's own value ("65 boxes" -> 65, Box;
// "60 tablets" -> 60, Tablet; a bare "1" on an inhaler row -> 1,
// Inhaler; a bare "0" -> 0). null = the spreadsheet cell is blank, so
// a sample quantity is generated.
// ---------------------------------------------------------------------
$excelMedicines = [
    ['Amlodipine 5mg Tablet', 'Antihypertensive', '5 mg once daily', 'Calcium channel blocker for hypertension and stable angina.', 65, 'Box'],
    ['Amlodipine 10mg', 'Antihypertensive', '10 mg once daily', 'Calcium channel blocker for hypertension and stable angina.', 20, 'Box'],
    ['Amoxicillin 500mg capsule', 'Antibiotic', '500 mg every 8 hours', 'Penicillin-class antibiotic for respiratory, ear and urinary tract infections.', 29, 'Box'],
    ['Amoxicillin Drop', 'Antibiotic', '100 mg/mL oral drops', 'Pediatric amoxicillin oral drops for bacterial infections in infants.', 20, 'Box'],
    ['Amoxicillin Suspension', 'Antibiotic', '250 mg/5 mL', 'Pediatric amoxicillin oral suspension for bacterial infections.', 100, 'Box'],
    ['Ascorbic Drop', 'Vitamin & Supplement', '100 mg/mL oral drops', 'Vitamin C supplement drops for infants.', 31, 'Box'],
    ['Ascorbic Syrup', 'Vitamin & Supplement', '100 mg/5 mL', 'Vitamin C supplement syrup for children.', null, 'Box'],
    ['Ascorbic Tablet 500mg', 'Vitamin & Supplement', '500 mg once daily', 'Vitamin C supplement for adults.', null, 'Box'],
    ['Aspirin Tablet 80mg', 'Antiplatelet', '80 mg once daily', 'Low-dose aspirin for prevention of heart attack and stroke.', 8, 'Box'],
    ['B complex Tablet', 'Vitamin & Supplement', '1 tablet once daily', 'Vitamin B1, B6 and B12 supplement for neuropathy and nutritional deficiency.', null, 'Box'],
    ['Azithromycin syrup', 'Antibiotic', '200 mg/5 mL', 'Macrolide antibiotic suspension for pediatric respiratory infections.', 40, 'Box'],
    ['Azithromycin 500mg tab', 'Antibiotic', '500 mg once daily', 'Macrolide antibiotic for respiratory and skin infections.', null, 'Box'],
    ['Carvedilol 6.25mg', 'Antihypertensive', '6.25 mg twice daily', 'Beta blocker for hypertension and heart failure.', 4, 'Box'],
    ['Cefalexin 500mg capsule', 'Antibiotic', '500 mg every 6 hours', 'First-generation cephalosporin for skin, bone and urinary tract infections.', 16, 'Box'],
    ['Cefalexin Drop', 'Antibiotic', '100 mg/mL oral drops', 'Pediatric cephalosporin oral drops for bacterial infections in infants.', 18, 'Box'],
    ['Cefalexin Suspension', 'Antibiotic', '250 mg/5 mL', 'Pediatric cephalosporin oral suspension for bacterial infections.', 29, 'Box'],
    ['Cefuroxime 500mg Tablet', 'Antibiotic', '500 mg twice daily', 'Second-generation cephalosporin for respiratory and urinary tract infections.', null, 'Box'],
    ['Celecoxib 200mg capsule', 'NSAID', '200 mg once to twice daily', 'COX-2 selective anti-inflammatory for arthritis and acute pain.', null, 'Box'],
    ['Cetirizine Drop', 'Antihistamine', '10 mg/mL oral drops', 'Antihistamine drops for allergic rhinitis and urticaria in infants.', 9, 'Box'],
    ['Cetirizine Syrup', 'Antihistamine', '5 mg/5 mL', 'Antihistamine syrup for allergic rhinitis and urticaria in children.', null, 'Box'],
    ['Cetirizine Tablet', 'Antihistamine', '10 mg once daily', 'Non-drowsy antihistamine for allergic rhinitis and urticaria.', null, 'Box'],
    ['Cinnarizine Tablet 25mg', 'Antivertigo', '25 mg three times daily', 'Antihistamine for vertigo, motion sickness and inner-ear disorders.', null, 'Box'],
    ['Ciprofloxacin 500mg Tablet', 'Antibiotic', '500 mg every 12 hours', 'Fluoroquinolone antibiotic for urinary tract and gastrointestinal infections.', 60, 'Tablet'],
    ['Clarithromycin suspension', 'Antibiotic', '250 mg/5 mL', 'Macrolide antibiotic suspension for pediatric respiratory infections.', 15, 'Box'],
    ['Clonidine 75mcg Tablet', 'Antihypertensive', '75 mcg twice daily', 'Centrally acting antihypertensive for hypertension.', 80, 'Tablet'],
    ['Clopidogrel', 'Antiplatelet', '75 mg once daily', 'Antiplatelet for prevention of heart attack and stroke.', 6, 'Box'],
    ['Cloxacillin Capsule 500mg', 'Antibiotic', '500 mg every 6 hours', 'Penicillinase-resistant penicillin for staphylococcal skin infections.', 5, 'Box'],
    ['Cloxacillin suspension', 'Antibiotic', '250 mg/5 mL', 'Pediatric cloxacillin suspension for skin and soft-tissue infections.', 17, 'Box'],
    ['Coamoxiclav 625mg tablet', 'Antibiotic', '625 mg every 12 hours', 'Amoxicillin with clavulanic acid for resistant bacterial infections.', null, 'Box'],
    ['Cotrimoxazole Tablet 800mg', 'Antibiotic', '800 mg/160 mg every 12 hours', 'Sulfamethoxazole-trimethoprim for urinary tract and respiratory infections.', null, 'Box'],
    ['Dicycloverin Syrup', 'Antispasmodic', '10 mg/5 mL', 'Antispasmodic syrup for abdominal cramps and colic.', 7, 'Box'],
    ['Dicycloverin Tablet', 'Antispasmodic', '10 mg three to four times daily', 'Antispasmodic for abdominal cramps and irritable bowel syndrome.', 2, 'Box'],
    ['Fenofibrate 200mg Capsule', 'Lipid-lowering', '200 mg once daily', 'Fibrate for high triglycerides and mixed dyslipidemia.', 4, 'Box'],
    ['Gliclazide 30mg', 'Antidiabetic', '30 mg once daily', 'Modified-release sulfonylurea for type 2 diabetes.', null, 'Box'],
    ['Gliclazide 80mg', 'Antidiabetic', '80 mg once to twice daily', 'Sulfonylurea for type 2 diabetes.', 40, 'Box'],
    ['Lagundi Syrup 120 ml', 'Herbal Medicine', '300 mg/5 mL', 'Vitex negundo herbal syrup for cough and mild asthma.', 66, 'Box'],
    ['Lagundi Tablet', 'Herbal Medicine', '600 mg three times daily', 'Vitex negundo herbal tablet for cough and mild asthma.', 5, 'Box'],
    ['Losartan 50mg Tablet', 'Antihypertensive', '50 mg once daily', 'Angiotensin receptor blocker for hypertension.', 80, 'Box'],
    ['Losartan plus Thiazide', 'Antihypertensive', '50 mg/12.5 mg once daily', 'Losartan with hydrochlorothiazide for hypertension.', 2, 'Box'],
    ['Mefenamic 500mg Capsule', 'NSAID', '500 mg every 8 hours as needed', 'Anti-inflammatory analgesic for pain and dysmenorrhea.', 22, 'Box'],
    ['Metformin 500mg', 'Antidiabetic', '500 mg twice daily with meals', 'First-line oral medicine for type 2 diabetes.', 110, 'Box'],
    ['Metronidazole suspension', 'Antibiotic', '125 mg/5 mL', 'Pediatric suspension for amoebiasis, giardiasis and anaerobic infections.', 25, 'Box'],
    ['Metronidazole tablet 500mg', 'Antibiotic', '500 mg every 8 hours', 'Antibiotic/antiprotozoal for amoebiasis and anaerobic infections.', null, 'Box'],
    ['Multivitamin Drop', 'Vitamin & Supplement', '0.6 mL once daily', 'Multivitamin drops for infants.', null, 'Box'],
    ['Multivitamin syrup', 'Vitamin & Supplement', '5 mL once daily', 'Multivitamin syrup for children.', 0, 'Box'],
    ['Omeprazole 20mg capsule', 'Gastrointestinal', '20 mg once daily before breakfast', 'Proton pump inhibitor for ulcer, GERD and hyperacidity.', 80, 'Capsule'],
    ['Oral Rehydration Salt', 'Electrolyte', '1 sachet in 200 mL water', 'Oral rehydration solution for diarrhea and dehydration.', 6, 'Box'],
    ['Paracetamol Drop', 'Analgesic', '100 mg/mL oral drops', 'Pain reliever and fever reducer for infants.', 130, 'Box'],
    ['Paracetamol Syrup', 'Analgesic', '250 mg/5 mL', 'Pain reliever and fever reducer for children.', 10, 'Box'],
    ['Paracetamol 500mg Tablet', 'Analgesic', '500 mg every 4-6 hours as needed (max 4 g/day)', 'Pain reliever and fever reducer for adults.', null, 'Box'],
    ['Salbutamol Nebule', 'Respiratory', '2.5 mg/2.5 mL via nebulizer', 'Bronchodilator nebule for asthma and acute bronchospasm.', 4, 'Box'],
    ['Salbutamol Syrup', 'Respiratory', '2 mg/5 mL', 'Bronchodilator syrup for asthma and bronchospasm in children.', 0, 'Box'],
    ['Salbutamol Tablet', 'Respiratory', '2 mg three to four times daily', 'Bronchodilator tablet for asthma and bronchospasm.', null, 'Box'],
    ['Simvastatin 20mg tablet', 'Lipid-lowering', '20 mg once daily at night', 'Statin for high cholesterol and cardiovascular risk reduction.', 4, 'Box'],
    ['Simvastatin 40mg', 'Lipid-lowering', '40 mg once daily at night', 'Statin for high cholesterol and cardiovascular risk reduction.', 5, 'Box'],
    ['Tetanus Toxoid (Tetox)', 'Toxoid Vaccine', '0.5 mL IM', 'Tetanus toxoid (TT) vaccine for wound management and tetanus immunization of pregnant women; 10-dose vial. Store at 2-8°C.', null, 'Vial'],
    ['Tranexamic 500mg Capsule', 'Hemostatic', '500 mg every 8 hours', 'Antifibrinolytic for heavy menstrual bleeding and bleeding control.', 1, 'Box'],
    ['Salbutamol + Ipratropium nebule', 'Respiratory', '2.5 mg/0.5 mg via nebulizer', 'Combination bronchodilator nebule for asthma and COPD exacerbations.', 2, 'Box'],
    ['Metoprolol 50mg', 'Antihypertensive', '50 mg twice daily', 'Beta blocker for hypertension, angina and arrhythmia.', 2, 'Box'],
    ['Metoprolol 100mg', 'Antihypertensive', '100 mg once to twice daily', 'Beta blocker for hypertension, angina and arrhythmia.', 2, 'Box'],
    ['Zinc Sulfate Syrup', 'Vitamin & Supplement', '20 mg/5 mL', 'Zinc supplement given with ORS for childhood diarrhea.', 13, 'Box'],
    ['Losartan 100mg', 'Antihypertensive', '100 mg once daily', 'Angiotensin receptor blocker for hypertension.', 10, 'Box'],
    ['Rosuvastatin 10mg', 'Lipid-lowering', '10 mg once daily', 'Statin for high cholesterol and cardiovascular risk reduction.', 9, 'Box'],
    ['Rosuvastatin 20mg', 'Lipid-lowering', '20 mg once daily', 'Statin for high cholesterol and cardiovascular risk reduction.', 8, 'Box'],
    ['Telmisartan 40mg', 'Antihypertensive', '40 mg once daily', 'Angiotensin receptor blocker for hypertension.', 17, 'Box'],
    ['Telmisartan 80mg', 'Antihypertensive', '80 mg once daily', 'Angiotensin receptor blocker for hypertension.', 10, 'Box'],
    ['Tobramycin Drops', 'Ophthalmic', '1-2 drops every 4 hours', 'Antibiotic eye drops for bacterial conjunctivitis.', 8, 'Box'],
    ['Atorvastatin 10mg', 'Lipid-lowering', '10 mg once daily', 'Statin for high cholesterol and cardiovascular risk reduction.', 5, 'Box'],
    ['Atorvastatin 20mg', 'Lipid-lowering', '20 mg once daily', 'Statin for high cholesterol and cardiovascular risk reduction.', 2, 'Box'],
    ['Atorvastatin 40mg', 'Lipid-lowering', '40 mg once daily', 'Statin for high cholesterol and cardiovascular risk reduction.', 8, 'Box'],
    ['Captopril', 'Antihypertensive', '25 mg two to three times daily', 'ACE inhibitor for hypertension and hypertensive urgency.', 8, 'Box'],
    ['Cefixime Drops', 'Antibiotic', '100 mg/5 mL', 'Third-generation cephalosporin suspension for pediatric infections.', 17, 'Box'],
    ['Clindamycin 150mg', 'Antibiotic', '150 mg every 6 hours', 'Lincosamide antibiotic for skin, dental and bone infections.', 80, 'Capsule'],
    ['Clindamycin 300mg', 'Antibiotic', '300 mg every 6 hours', 'Lincosamide antibiotic for skin, dental and bone infections.', 140, 'Capsule'],
    ['Diphenhydramine Capsule', 'Antihistamine', '50 mg every 6 hours as needed', 'Sedating antihistamine for allergic reactions and itching.', 80, 'Capsule'],
    ['Diphenhydramine Syrup', 'Antihistamine', '12.5 mg/5 mL', 'Antihistamine syrup for allergic reactions and itching in children.', 8, 'Box'],
    ['Enalapril', 'Antihypertensive', '5 mg once to twice daily', 'ACE inhibitor for hypertension and heart failure.', 1, 'Box'],
    ['Gabapentin', 'Neurologic', '300 mg once to three times daily', 'Anticonvulsant for neuropathic pain and seizures.', 3, 'Box'],
    ['Ibuprofen Tablet', 'NSAID', '400 mg every 6-8 hours as needed', 'Anti-inflammatory analgesic for pain, fever and inflammation.', 3, 'Box'],
    ['Ibuprofen Suspension', 'NSAID', '100 mg/5 mL', 'Pediatric anti-inflammatory for pain and fever.', 28, 'Box'],
    ['Isosorbide 5mg', 'Antianginal', '5 mg sublingual as needed', 'Nitrate for acute angina relief.', 1, 'Box'],
    ['Isosorbide 10mg', 'Antianginal', '10 mg two to three times daily', 'Nitrate for angina prevention.', 2, 'Box'],
    ['Loratadine', 'Antihistamine', '10 mg once daily', 'Non-drowsy antihistamine for allergic rhinitis and urticaria.', 2, 'Box'],
    ['Methyldopa', 'Antihypertensive', '250 mg two to three times daily', 'Antihypertensive commonly used for hypertension in pregnancy.', 3, 'Box'],
    ['Montelukast', 'Respiratory', '10 mg once daily at night', 'Leukotriene receptor antagonist for asthma and allergic rhinitis.', 6, 'Box'],
    ['Valsartan', 'Antihypertensive', '80 mg once daily', 'Angiotensin receptor blocker for hypertension and heart failure.', 5, 'Box'],
    ['Salbutamol Inhaler', 'Respiratory', '100 mcg/puff, 1-2 puffs as needed', 'Metered-dose bronchodilator inhaler for asthma relief.', 1, 'Inhaler'],
    ['Salmeterol + Fluticasone (Seretide) 25mcg/50mcg', 'Respiratory', '2 puffs twice daily', 'Long-acting bronchodilator with inhaled corticosteroid for asthma and COPD maintenance.', 3, 'Inhaler'],
    ['Salmeterol + Fluticasone (Seretide) 25mcg/125mcg', 'Respiratory', '2 puffs twice daily', 'Long-acting bronchodilator with inhaled corticosteroid for asthma and COPD maintenance.', 1, 'Inhaler'],
];

// Sample quantities for the spreadsheet rows whose stock cell is blank.
// Mostly comfortable levels, two deliberately low.
$generatedQuantities = [
    'Ascorbic Syrup' => 24, 'Ascorbic Tablet 500mg' => 35, 'B complex Tablet' => 42,
    'Azithromycin 500mg tab' => 18, 'Cefuroxime 500mg Tablet' => 14, 'Celecoxib 200mg capsule' => 12,
    'Cetirizine Syrup' => 3, 'Cetirizine Tablet' => 26, 'Cinnarizine Tablet 25mg' => 11,
    'Coamoxiclav 625mg tablet' => 16, 'Cotrimoxazole Tablet 800mg' => 20, 'Gliclazide 30mg' => 2,
    'Metronidazole tablet 500mg' => 22, 'Multivitamin Drop' => 30, 'Paracetamol 500mg Tablet' => 48,
    'Salbutamol Tablet' => 15, 'Tetanus Toxoid (Tetox)' => 12,
];

// Spreadsheet rows that are really vaccines — listed under the Vaccines
// tab (type "vaccine") while still sourced from Medicines.xlsx.
$excelVaccineRows = ['Tetanus Toxoid (Tetox)'];

// Recently delivered above their configured Maximum Level, so the
// Overstocked status/alert has real examples.
$overstocked = ['Amoxicillin Suspension', 'Paracetamol Drop'];

// ---------------------------------------------------------------------
// Vaccines (all sample data) — [name, category, dosage, description,
// quantity, unit, expiry profile, shelf life in months]
// ---------------------------------------------------------------------
$vaccines = [
    ['BCG Vaccine', 'Live Attenuated Vaccine', '0.05 mL intradermal (infants)', 'Bacillus Calmette-Guérin vaccine against tuberculosis; 20-dose lyophilized vial with diluent. Store at 2-8°C.', 45, 'Vial', 'far', 24],
    ['Hepatitis B Vaccine', 'Recombinant Vaccine', '0.5 mL IM (birth dose within 24 hours)', 'Recombinant hepatitis B vaccine for newborns and catch-up doses; single-dose vial. Store at 2-8°C.', 120, 'Vial', 'expired', 36],
    ['Pentavalent Vaccine (DPT-HepB-Hib)', 'Combination Vaccine', '0.5 mL IM at 6, 10 and 14 weeks', 'Combined diphtheria, tetanus, pertussis, hepatitis B and Haemophilus influenzae type b vaccine; single-dose vial. Store at 2-8°C.', 160, 'Vial', 'far', 24],
    ['Oral Polio Vaccine (OPV)', 'Live Attenuated Vaccine', '2 drops orally per dose', 'Bivalent oral polio vaccine; 20-dose dropper vial with vaccine vial monitor. Store frozen or at 2-8°C.', 30, 'Vial', 'soon', 24],
    ['Inactivated Polio Vaccine (IPV)', 'Inactivated Vaccine', '0.5 mL IM at 14 weeks and 9 months', 'Inactivated poliovirus vaccine; single-dose vial. Store at 2-8°C.', 0, 'Vial', 'far', 36],
    ['Measles, Mumps and Rubella (MMR) Vaccine', 'Live Attenuated Vaccine', '0.5 mL subcutaneous', 'Live attenuated measles, mumps and rubella vaccine; single-dose lyophilized vial with diluent. Store at 2-8°C.', 8, 'Vial', 'medium', 24],
    ['Measles-Rubella (MR) Vaccine', 'Live Attenuated Vaccine', '0.5 mL subcutaneous at 9 and 12 months', 'Live attenuated measles-rubella vaccine; 10-dose lyophilized vial with diluent. Store at 2-8°C.', 40, 'Vial', 'medium', 24],
    ['Tetanus-Diphtheria (Td) Vaccine', 'Toxoid Vaccine', '0.5 mL IM', 'Tetanus and diphtheria toxoid for pregnant women, school-based immunization and wound care; 10-dose vial. Store at 2-8°C.', 35, 'Vial', 'far', 36],
    ['Pneumococcal Conjugate Vaccine (PCV13)', 'Conjugate Vaccine', '0.5 mL IM at 6, 10 and 14 weeks', '13-valent pneumococcal conjugate vaccine for infants; single-dose prefilled syringe. Store at 2-8°C.', 90, 'Dose', 'far', 36],
    ['Pneumococcal Polysaccharide Vaccine (PPSV23)', 'Polysaccharide Vaccine', '0.5 mL IM or subcutaneous', '23-valent pneumococcal polysaccharide vaccine for senior citizens; single-dose prefilled syringe. Store at 2-8°C.', 25, 'Dose', 'far', 24],
    ['Influenza Vaccine', 'Inactivated Vaccine', '0.5 mL IM once yearly', 'Quadrivalent seasonal influenza vaccine for senior citizens and high-risk groups; single-dose prefilled syringe. Store at 2-8°C.', 200, 'Dose', 'soon', 12],
    ['Human Papillomavirus (HPV) Vaccine', 'Recombinant Vaccine', '0.5 mL IM, 2 doses 6 months apart', 'HPV vaccine for school-based immunization of girls aged 9-14; single-dose vial. Store at 2-8°C.', 6, 'Dose', 'far', 36],
];
$lowStockVaccines = ['Measles, Mumps and Rubella (MMR) Vaccine', 'Human Papillomavirus (HPV) Vaccine'];

// ---------------------------------------------------------------------
// Generation helpers
// ---------------------------------------------------------------------

function seed_slug(string $s): string
{
    return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($s)), '-');
}

function seed_rand_float(float $min, float $max): float
{
    return $min + mt_rand() / mt_getrandmax() * ($max - $min);
}

/** Shelf life in months by dosage form, inferred from the item name. */
function seed_shelf_life_months(string $name): int
{
    $n = strtolower($name);
    if (preg_match('/drop|syrup|suspension|nebule|inhaler/', $n)) {
        return 24;
    }

    return 36;
}

/** Rounds up to a tidy stock ceiling (multiple of 5, or 10 above 100). */
function seed_round_up(float $value): int
{
    $step = $value > 100 ? 10 : 5;

    return max($step, (int) (ceil($value / $step) * $step));
}

/** Splits $total into $parts positive integers (multiples of $step where possible), oldest batch first. */
function seed_split(int $total, int $parts, int $step): array
{
    if ($parts <= 1 || $total < $parts * $step) {
        return [$total];
    }
    $chunks = intdiv($total, $step);
    $sizes = [];
    $left = $chunks;
    for ($i = $parts; $i > 1; $i--) {
        $take = mt_rand(1, $left - ($i - 1));
        // Keep any one batch from swallowing nearly everything.
        $take = min($take, max(1, (int) ceil($left * 0.6)));
        $sizes[] = $take * $step;
        $left -= $take;
    }
    $sizes[] = $left * $step + ($total - $chunks * $step);

    return $sizes;
}

/** Days from today for an item's OLDEST batch, by expiry profile. */
function seed_oldest_expiry_offset(string $profile, int $maxOffset): int
{
    return match ($profile) {
        'expired' => -mt_rand(10, 120),
        'soon' => mt_rand(4, 28),
        'medium' => mt_rand(35, 88),
        default => mt_rand(min(150, $maxOffset - 60), $maxOffset - 30),
    };
}

$usedBatchNumbers = [];

function seed_batch_number(string $name, DateTimeImmutable $mfg, array &$used): string
{
    $letters = preg_replace('/[^A-Z]/', '', strtoupper($name));
    $prefix = substr($letters, 0, 3);
    do {
        $number = $prefix . $mfg->format('ym') . '-' . str_pad((string) mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
    } while (isset($used[$number]));
    $used[$number] = true;

    return $number;
}

/**
 * One item's batches plus the thresholds its last restock would have
 * produced. $lowStock forces current stock to <= 20% of the post-
 * restock level (the hc_auto_compute_thresholds() minimum), so the
 * item reads as Low Stock the same way a really drawn-down one would.
 */
function seed_build_item(string $name, int $quantity, string $unit, string $profile, int $shelfMonths, bool $lowStock, bool $overstock, DateTimeImmutable $today, array &$usedBatchNumbers): array
{
    mt_srand(crc32($name));

    $maxOffset = $shelfMonths * 30 - 45; // manufactured at least ~6 weeks ago
    $step = in_array($unit, ['Tablet', 'Capsule'], true) ? 10 : 1;

    if ($quantity === 0) {
        $sizes = [0];
    } elseif ($quantity <= 2) {
        $sizes = [$quantity];
    } else {
        $sizes = seed_split($quantity, mt_rand(1, 10) <= 3 ? 1 : mt_rand(2, 3), $step);
    }

    // Stock drawn off (FEFO: from the oldest batch) since the last restock.
    if ($overstock) {
        $consumed = 0;
    } elseif ($lowStock || $quantity === 0) {
        $consumed = $quantity === 0 ? seed_round_up(mt_rand(8, 30) * ($step > 1 ? 10 : 1)) : (int) ceil($quantity * seed_rand_float(4.2, 6.0));
    } else {
        $consumed = (int) round($quantity * seed_rand_float(0.15, 1.6));
    }
    $baseline = $quantity + $consumed;

    $thresholds = hc_auto_compute_thresholds(max(1, $baseline));
    $maxLevel = $overstock ? seed_round_up($quantity * 0.8) : seed_round_up($baseline * 1.5);
    if ($overstock && $maxLevel >= $quantity) {
        $maxLevel = $quantity - 1;
    }

    $batches = [];
    $expiryOffset = seed_oldest_expiry_offset($profile, $maxOffset);
    foreach ($sizes as $i => $size) {
        if ($i > 0) {
            $expiryOffset = min($maxOffset, $expiryOffset + mt_rand(60, 300));
        }
        $expiry = $today->modify(($expiryOffset >= 0 ? '+' : '') . $expiryOffset . ' days');
        $mfg = $expiry->modify("-{$shelfMonths} months");
        $received = $mfg->modify('+' . mt_rand(30, 120) . ' days');
        $latestReceived = $today->modify('-' . mt_rand(2, 20) . ' days');
        if ($received > $latestReceived) {
            $received = max($mfg->modify('+1 day'), $latestReceived);
        }

        $batches[] = [
            'batchNumber' => seed_batch_number($name, $mfg, $usedBatchNumbers),
            'quantity' => $size,
            'originalQuantity' => $size + ($i === 0 ? $consumed : 0),
            'expiryDate' => $expiry->format('Y-m-d'),
            'manufacturingDate' => $mfg->format('Y-m-d'),
            'receivedDate' => $received->format('Y-m-d'),
        ];
    }

    return [
        'batches' => $batches,
        'minLevel' => $thresholds['minLevel'],
        'reorderLevel' => $thresholds['reorderLevel'],
        'maxLevel' => $maxLevel,
    ];
}

// ---------------------------------------------------------------------
// Build the full dataset
// ---------------------------------------------------------------------
$items = [];
$profilesByIndex = [0 => 'expired', 5 => 'soon', 2 => 'medium', 9 => 'medium'];
$typoFixes = ['Omemprazole' => 'Omeprazole', 'Multivatimin' => 'Multivitamin'];

foreach ($excelMedicines as $i => [$name, $category, $dosage, $desc, $qty, $unit]) {
    $name = strtr(preg_replace('/\s+/', ' ', trim($name)), $typoFixes);
    $generatedFields = ['category', 'dosage', 'desc', 'batches', 'expiryDate', 'minLevel', 'reorderLevel', 'maxLevel'];
    if ($qty === null) {
        $qty = $generatedQuantities[$name];
        $generatedFields[] = 'currentStock';
    }
    $profile = $profilesByIndex[$i % 12] ?? 'far';
    $isOverstock = in_array($name, $overstocked, true);
    $build = seed_build_item($name, $qty, $unit, $profile, seed_shelf_life_months($name), $qty > 0 && $qty <= 3, $isOverstock, $today, $usedBatchNumbers);

    // Spreadsheet quantities are mostly boxes — moved onto the item's
    // dispensing unit (scripts/inventory-unit-profiles.php), the same
    // conversion scripts/migrate-dispensing-units.php applied live.
    $unitProfile = hc_demo_unit_profile($name, $unit);
    $unit = $unitProfile['unit'];
    foreach ($build['batches'] as &$batch) {
        $batch['quantity'] *= $unitProfile['factor'];
        $batch['originalQuantity'] *= $unitProfile['factor'];
    }
    unset($batch);
    foreach (['minLevel', 'reorderLevel', 'maxLevel'] as $level) {
        $build[$level] *= $unitProfile['factor'];
    }
    $build['piecesPerBox'] = $unitProfile['piecesPerBox'];

    $isVaccine = in_array($name, $excelVaccineRows, true);
    $items[] = [
        'id' => ($isVaccine ? 'vac-' : 'med-') . seed_slug($name),
        'type' => $isVaccine ? 'vaccine' : 'medicine', 'name' => $name, 'category' => $category, 'unit' => $unit,
        'dosage' => $dosage, 'desc' => $desc,
        'dataSource' => 'Medicines.xlsx', 'generatedFields' => $generatedFields,
    ] + $build;
}

foreach ($vaccines as [$name, $category, $dosage, $desc, $qty, $unit, $profile, $shelfMonths]) {
    $build = seed_build_item($name, $qty, $unit, $profile, $shelfMonths, in_array($name, $lowStockVaccines, true), false, $today, $usedBatchNumbers);
    $items[] = [
        'id' => 'vac-' . seed_slug($name),
        'type' => 'vaccine', 'name' => $name, 'category' => $category, 'unit' => $unit,
        'dosage' => $dosage, 'desc' => $desc,
        'dataSource' => 'generated-sample', 'generatedFields' => ['all'],
    ] + $build;
}

// Duplicate check — the same normalized name (or doc id) twice within a
// type would show as two catalog rows for one medicine.
$seen = [];
foreach ($items as $item) {
    foreach ([$item['type'] . ':' . strtolower($item['name']), 'id:' . $item['id']] as $key) {
        if (isset($seen[$key])) {
            fwrite(STDERR, "Duplicate record in seed data: {$key}\n");
            exit(1);
        }
        $seen[$key] = true;
    }
}

// ---------------------------------------------------------------------
// Report
// ---------------------------------------------------------------------
$counts = ['expired' => 0, 'soon' => 0, 'low' => 0, 'unavailable' => 0, 'overstocked' => 0, 'batches' => 0];
foreach ($items as $item) {
    $stock = array_sum(array_column($item['batches'], 'quantity'));
    $withStock = array_filter($item['batches'], fn ($b) => $b['quantity'] > 0);
    $oldest = $withStock ? min(array_column($withStock, 'expiryDate')) : '';
    $status = hc_inventory_status_for_stock($stock, $item['minLevel'], $item['maxLevel']);
    $expiryState = hc_inventory_expiry_status($oldest);
    $counts['batches'] += count($item['batches']);
    $counts[$status] = ($counts[$status] ?? 0) + 1;
    if ($expiryState !== 'ok') {
        $counts[$expiryState === 'expired' ? 'expired' : 'soon']++;
    }
    printf(
        "%-8s %-50s %5d %-8s min %-4d reorder %-4d max %-5d %-12s oldest %-10s %s  [%s]\n",
        $item['type'], $item['name'], $stock, $item['unit'], $item['minLevel'], $item['reorderLevel'], $item['maxLevel'],
        $status, $oldest !== '' ? $oldest : '—', $expiryState, implode(', ', array_column($item['batches'], 'batchNumber'))
    );
}
$medicineCount = count(array_filter($items, fn ($i) => $i['type'] === 'medicine'));
echo "\n{$medicineCount} medicines, " . (count($items) - $medicineCount) . " vaccines, {$counts['batches']} batches\n";
echo "Low Stock: {$counts['low']}, Out of Stock: {$counts['unavailable']}, Overstocked: {$counts['overstocked']}, oldest batch expired: {$counts['expired']}, expiring within 30 days: {$counts['soon']}\n";

$existingMedicines = $firestore->listDocuments('medicines');
$existingBatches = $firestore->listDocuments('medicine_batches');
echo "\nWill replace " . count($existingMedicines) . ' existing medicines docs and ' . count($existingBatches) . " existing medicine_batches docs.\n";

if (!$apply) {
    echo "\nDry run only — re-run with --apply to write these changes.\n";
    exit(0);
}

// ---------------------------------------------------------------------
// Apply
// ---------------------------------------------------------------------
$backupDir = __DIR__ . '/../storage/inventory_backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0777, true);
}
$backupPath = $backupDir . '/inventory-backup-' . date('Ymd-His') . '.json';
$written = file_put_contents($backupPath, json_encode(['medicines' => $existingMedicines, 'medicine_batches' => $existingBatches], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
if ($written === false) {
    fwrite(STDERR, "Could not write backup to {$backupPath} — nothing changed.\n");
    exit(1);
}
echo "\nBacked up existing inventory to " . realpath($backupPath) . "\n";

$seedBatchIds = [];
foreach ($items as $item) {
    foreach ($item['batches'] as $b) {
        $seedBatchIds[$item['id'] . '--' . strtolower($b['batchNumber'])] = true;
    }
}
$seedItemIds = array_flip(array_column($items, 'id'));

$failures = 0;
foreach ($existingBatches as $doc) {
    if (!isset($seedBatchIds[$doc['id']]) && !$firestore->deleteDocument('medicine_batches', $doc['id'])) {
        $failures++;
        echo "  ! could not delete medicine_batches/{$doc['id']}\n";
    }
}
foreach ($existingMedicines as $doc) {
    if (!isset($seedItemIds[$doc['id']]) && !$firestore->deleteDocument('medicines', $doc['id'])) {
        $failures++;
        echo "  ! could not delete medicines/{$doc['id']}\n";
    }
}
echo "Removed previous inventory docs.\n";

$now = date('c');
foreach ($items as $n => $item) {
    $isVaccine = $item['type'] === 'vaccine';
    $saved = $firestore->setDocument('medicines', $item['id'], [
        'type' => $item['type'],
        'name' => $item['name'],
        'category' => $item['category'],
        'unit' => $item['unit'],
        'form' => $item['unit'], // same as api/create-medicine.php
        'piecesPerBox' => $item['piecesPerBox'] ?? 0,
        'desc' => $item['desc'],
        'dosage' => $item['dosage'],
        'currentStock' => 0, // rollup below fills in the real total
        'minLevel' => $item['minLevel'],
        'reorderLevel' => $item['reorderLevel'],
        'maxLevel' => $item['maxLevel'],
        'expiryDate' => null,
        'status' => 'unavailable',
        'icon' => $isVaccine ? 'bi-droplet-half' : 'bi-capsule',
        'color' => $isVaccine ? 'cyan' : 'blue',
        'archived' => false,
        'lastUpdated' => $now,
        'createdBy' => 'Demo data seed',
        'isSampleData' => true,
        'dataSource' => $item['dataSource'],
        'generatedFields' => $item['generatedFields'],
    ]);
    if (!$saved) {
        $failures++;
        echo "  ! could not write medicines/{$item['id']}\n";
        continue;
    }

    foreach ($item['batches'] as $b) {
        $batchId = $item['id'] . '--' . strtolower($b['batchNumber']);
        $ok = $firestore->setDocument('medicine_batches', $batchId, [
            'medicineId' => $item['id'],
            'batchNumber' => $b['batchNumber'],
            'quantity' => $b['quantity'],
            'originalQuantity' => $b['originalQuantity'],
            'expiryDate' => $b['expiryDate'],
            'manufacturingDate' => $b['manufacturingDate'],
            'receivedDate' => $b['receivedDate'],
            'source' => 'restock',
            'sourceRequestId' => null,
            'createdAt' => $b['receivedDate'] . 'T09:00:00+08:00',
            'createdBy' => 'Demo data seed (sample)',
            'isSampleData' => true,
        ]);
        if (!$ok) {
            $failures++;
            echo "  ! could not write medicine_batches/{$batchId}\n";
        }
    }

    hc_recompute_medicine_rollup($item['id']);
    echo sprintf("  [%3d/%d] %s\n", $n + 1, count($items), $item['name']);
}

echo $failures === 0 ? "\nDone — inventory replaced with no errors.\n" : "\nFinished with {$failures} failed write(s) — see above; re-running is safe.\n";
exit($failures === 0 ? 0 : 1);
