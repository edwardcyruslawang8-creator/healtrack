<?php
/**
 * =====================================================================
 * Chatbot — Decision Tree (Dialogflow output -> verified facts)
 * ---------------------------------------------------------------------
 * This is the "backend logic" layer of the Patient -> Dialogflow ->
 * Decision Tree -> Database -> Gemini -> Patient pipeline. It owns
 * every real decision: whether required info is missing, what to
 * search for, and what the real database says. It NEVER produces the
 * words shown to the patient — that's Gemini's job in
 * includes/public/gemini_client.php, working only from the
 * structured $decision array this file returns.
 *
 * Input: one Dialogflow detectIntent() result (see
 * firebase/DialogflowRestClient.php) — an intent name + extracted
 * parameters. Output: a $decision array whose 'outcome' key names
 * exactly what happened, plus only the real data needed to phrase it.
 * A null/empty outcome value always means "genuinely not found in the
 * database" — this file never invents a fact to fill a gap.
 * =====================================================================
 */

require_once __DIR__ . '/chatbot_engine.php'; // hc_chatbot_find_medicine_match(), hc_chatbot_find_vaccination_announcement(), hc_chatbot_levenshtein()
require_once __DIR__ . '/../firestore/medicines.php'; // hc_get_inventory_items(), hc_medicine_status_meta()
require_once __DIR__ . '/../shared/predictive_analytics_core.php'; // HC_COVERED_BARANGAYS
require_once __DIR__ . '/../../firebase/firestore.php'; // getAnnouncements()
require_once __DIR__ . '/../firestore/consultations.php'; // hc_get_physician_options()

if (!function_exists('hc_chatbot_find_item_by_name')) {
    /**
     * Resolves a medicine/vaccine name Dialogflow extracted (via the
     * @sys.any "medicine" parameter on ask_medicine_availability) to a
     * real inventory item, reusing the exact fuzzy match already
     * proven in the keyword-engine fallback so both paths agree on
     * what counts as a match.
     */
    function hc_chatbot_find_item_by_name(string $rawName): ?array
    {
        if (trim($rawName) === '') {
            return null;
        }

        return hc_chatbot_find_medicine_match(strtolower($rawName));
    }
}

if (!function_exists('hc_chatbot_decide')) {
    /**
     * @param string $intent Dialogflow's matched intent display name, or '' if none/low-confidence.
     * @param array<string, mixed> $parameters Dialogflow's extracted entities for that intent.
     * @param string $rawMessage The patient's original message (used only by the legacy keyword fallback branch).
     * @return array{outcome: string, intent: string, data: array<string, mixed>}
     */
    function hc_chatbot_decide(string $intent, array $parameters, string $rawMessage): array
    {
        switch ($intent) {
            case 'emergency':
                // Safety-critical — same fixed instruction regardless of
                // what Dialogflow extracted, never left to Gemini.
                return ['outcome' => 'emergency', 'intent' => $intent, 'data' => []];

            case 'confidential_info_request':
                // Privacy-critical — same fixed refusal regardless of what
                // Dialogflow extracted, never left to Gemini to improvise.
                return ['outcome' => 'confidential_info_denied', 'intent' => $intent, 'data' => []];

            case 'ask_medicine_availability':
                $name = trim((string) ($parameters['medicine'] ?? ''));

                // No specific name extracted — a general "what
                // medicines/vaccines are available?" rather than "do you
                // have X?". Real inventory list, not a clarifying
                // question, since that's what was actually asked.
                if ($name === '') {
                    return ['outcome' => 'list_available_medicines', 'intent' => $intent, 'data' => hc_chatbot_list_available_items()];
                }

                $item = hc_chatbot_find_item_by_name($name);
                if ($item === null) {
                    return ['outcome' => 'medicine_not_found', 'intent' => $intent, 'data' => ['queried' => $name]];
                }

                hc_chatbot_remember_item($item);

                return [
                    'outcome' => 'medicine_found',
                    'intent' => $intent,
                    // Deliberately NO currentStock/unit here — exact stock
                    // counts aren't disclosed to patients, only available/
                    // low/unavailable. Not just a wording choice: this data
                    // is what gets handed to Gemini as VERIFIED_FACTS
                    // (gemini_client.php), so leaving the number out here
                    // means Gemini is structurally unable to mention one,
                    // not merely instructed not to.
                    'data' => [
                        'name' => $item['name'],
                        'itemType' => $item['itemType'] ?? 'medicine',
                        'status' => $item['status'],
                        'statusLabel' => hc_medicine_status_meta()[$item['status']]['label'] ?? ucfirst($item['status']),
                    ],
                ];

            case 'ask_vaccination_schedule':
                $ann = hc_chatbot_find_vaccination_announcement();
                if ($ann === null) {
                    return ['outcome' => 'no_vaccination_announcement', 'intent' => $intent, 'data' => []];
                }

                $annData = [
                    'title' => $ann['title'] ?? '',
                    'desc' => $ann['desc'] ?? '',
                    'postedAt' => isset($ann['postedAt']) && $ann['postedAt'] !== '' ? date('M j, Y', strtotime($ann['postedAt'])) : '',
                ];
                hc_chatbot_remember_topic('vaccination_announcement', $annData);

                return ['outcome' => 'vaccination_announcement_found', 'intent' => $intent, 'data' => $annData];

            case 'ask_covered_barangays':
                return [
                    'outcome' => 'covered_barangays_info',
                    'intent' => $intent,
                    'data' => ['barangays' => HC_COVERED_BARANGAYS],
                ];

            // ONE intent for all 10 health-education topics (cold, flu,
            // dengue, fever, headache, diarrhea, dehydration, sore
            // throat, infection prevention, cough), same shape as
            // ask_medicine_availability's "medicine" entity: Dialogflow
            // extracts whatever free-text topic phrase the patient used
            // (e.g. "the flu", "dengue fever"), and this resolves it
            // against the SAME regex list hc_chatbot_answer()'s own
            // keyword matching uses (hc_chatbot_health_topics() in
            // chatbot_engine.php) — one shared source of truth either
            // way, so Dialogflow training can never drift from what the
            // keyword-fallback engine already says.
            case 'ask_health_symptom_info':
                $topicText = strtolower(trim((string) ($parameters['topic'] ?? '')));
                $topicKey = null;
                if ($topicText !== '') {
                    foreach (hc_chatbot_health_topics() as [$key, $pattern, $answer]) {
                        if (preg_match($pattern, $topicText) === 1) {
                            $topicKey = $key;
                            break;
                        }
                    }
                }

                if ($topicKey === null) {
                    return ['outcome' => 'health_topic_not_recognized', 'intent' => $intent, 'data' => []];
                }

                hc_chatbot_remember_topic('health_topic', ['topic' => $topicKey]);

                return ['outcome' => 'health_topic_info', 'intent' => $intent, 'data' => ['topic' => $topicKey]];

            // Always the single real LATEST announcement (never a
            // bulleted list of several) — same reasoning and same
            // remembered-list mechanism as the legacy engine's own
            // identical branch in chatbot_engine.php.
            case 'ask_announcements':
                $list = getAnnouncements(10);
                if ($list === []) {
                    return ['outcome' => 'no_announcements', 'intent' => $intent, 'data' => []];
                }
                hc_chatbot_remember_topic('announcement_list', ['list' => $list, 'index' => 0]);

                return ['outcome' => 'announcement_found', 'intent' => $intent, 'data' => $list[0]];

            case 'ask_appointment':
                // Honest about what this system can/can't do online —
                // there's no patient login/booking flow anywhere in this
                // app, so this never pretends otherwise.
                return ['outcome' => 'appointment_not_supported_online', 'intent' => $intent, 'data' => []];

            case 'ask_insurance':
                // No real insurance/PhilHealth data exists in this
                // system — deflects to staff rather than guessing.
                return ['outcome' => 'insurance_deflect', 'intent' => $intent, 'data' => []];

            case 'ask_prescription_refill':
                return ['outcome' => 'refill_not_supported_online', 'intent' => $intent, 'data' => []];

            case 'ask_registration':
                return ['outcome' => 'registration_deflect', 'intent' => $intent, 'data' => []];

            case 'ask_pricing':
                return ['outcome' => 'pricing_deflect', 'intent' => $intent, 'data' => []];

            case 'ask_about_system':
                return ['outcome' => 'about_system_info', 'intent' => $intent, 'data' => []];

            case 'ask_about_facility':
                return ['outcome' => 'about_facility_info', 'intent' => $intent, 'data' => []];

            case 'ask_doctors':
                return ['outcome' => 'doctors_info', 'intent' => $intent, 'data' => ['doctors' => hc_chatbot_list_doctors()]];

            case 'ask_services':
                return [
                    'outcome' => 'services_info',
                    'intent' => $intent,
                    'data' => [
                        'services' => [
                            'Patient consultations',
                            'Medicine & vaccine dispensing',
                            'Laboratory & X-ray testing',
                            'Vaccination records',
                            'Real-time disease mapping for covered barangays',
                        ],
                    ],
                ];

            case 'ask_hours_contact':
                // Dialogflow's single intent doesn't distinguish "phone"
                // from "hours" from "address" — hc_chatbot_contact_reply()
                // scopes the answer to exactly what $rawMessage asked
                // (shared with the legacy engine's own identical check,
                // so both paths agree). The scoped reply is computed
                // HERE, in the decision tree, not left for Gemini to
                // guess at from raw facts — matches this whole pipeline's
                // rule that Gemini only ever phrases already-verified
                // output, never picks which facts matter.
                $contactReply = hc_chatbot_contact_reply(strtolower($rawMessage));
                hc_chatbot_remember_topic('contact', ['reply' => $contactReply]);

                return ['outcome' => 'contact_info_scoped', 'intent' => $intent, 'data' => ['reply' => $contactReply]];

            case 'ask_health_tips':
                return [
                    'outcome' => 'health_tips_info',
                    'intent' => $intent,
                    'data' => [
                        'tips' => [
                            'Stay hydrated',
                            'Eat a balanced diet',
                            'Get regular exercise',
                            'Keep your vaccinations up to date',
                            'Don\'t skip your check-ups',
                        ],
                    ],
                ];

            case 'greeting':
                return ['outcome' => 'greeting', 'intent' => $intent, 'data' => []];

            default:
                // No intent matched, or Dialogflow isn't configured yet —
                // the caller (api/chatbot-ask.php) falls back to the
                // legacy keyword engine entirely in this case, so this
                // branch should never actually reach Gemini.
                return ['outcome' => 'unresolved', 'intent' => $intent, 'data' => ['rawMessage' => $rawMessage]];
        }
    }
}

if (!function_exists('hc_chatbot_phrase_decision')) {
    /**
     * Fixed, non-LLM phrasing of a $decision from hc_chatbot_decide().
     * Used whenever Gemini isn't configured or its call fails — so a
     * verified decision-tree answer always reaches the patient in
     * words, Gemini or not. Mirrors the tone of the legacy keyword
     * engine's own replies in chatbot_engine.php.
     */
    function hc_chatbot_phrase_decision(array $decision): string
    {
        $d = $decision['data'] ?? [];

        return match ($decision['outcome'] ?? 'unresolved') {
            'list_available_medicines' => (function () use ($d): string {
                $all = array_merge($d['medicines'] ?? [], $d['vaccines'] ?? []);
                if ($all === []) {
                    return 'We don\'t have any medicines or vaccines marked as available right now. Please contact our pharmacy directly for the latest status.';
                }

                return hc_chatbot_bullet_list('💊 Here\'s what we currently have available:', $all);
            })(),
            // Deliberately no exact quantity — only available/low/
            // unavailable, never a count (see this outcome's own
            // 'data' comment above for why). Shared with the legacy
            // engine's own identical wording via hc_chatbot_item_status_reply().
            'medicine_found' => hc_chatbot_item_status_reply($d),
            'medicine_not_found' => "I couldn't find \"{$d['queried']}\" in our medicine/vaccine list. Could you double-check the spelling, or ask about something else?",
            'vaccination_announcement_found' => '💉 Here' . '\'s our latest vaccination announcement'
                . ($d['postedAt'] !== '' ? " (posted {$d['postedAt']})" : '') . ": \"{$d['title']}\" — {$d['desc']}",
            'no_vaccination_announcement' => 'There\'s no upcoming vaccination announcement on file right now. Please check our Announcements page or contact us directly.',
            'services_info' => hc_chatbot_bullet_list('🏥 NSHC offers:', $d['services']),
            'covered_barangays_info' => hc_chatbot_bullet_list('📍 NSHC covers the following barangays:', $d['barangays']),
            'health_topic_info' => (hc_chatbot_health_topic_answer($d['topic']) ?? 'I don\'t have verified information on that topic yet.')
                . ' This is general information, not a diagnosis — for anything specific to you, please consult our healthcare staff directly.',
            // Dialogflow matched the intent but extracted a topic phrase
            // that doesn't resolve to any of the 10 known topics — never
            // guess; api/chatbot-ask.php's own pipeline falls through to
            // the legacy keyword engine and Gemini for anything this
            // specific outcome can't answer.
            'health_topic_not_recognized' => 'I\'m not sure which health topic you mean — I can share general information on colds, flu, dengue, fever, headaches, diarrhea, dehydration, sore throat, infectious disease prevention, or persistent cough. Could you rephrase?',
            'announcement_found' => hc_chatbot_announcement_reply($d),
            'no_announcements' => 'There are no announcements posted right now. Please check our Announcements page later or contact us directly.',
            'appointment_not_supported_online' => 'We don\'t currently support booking appointments online — walk-ins are welcome during our operating hours, or you can call us at (02) 8123-4567 to ask about scheduling a visit.',
            'insurance_deflect' => 'For insurance and PhilHealth coverage questions, please contact us directly at (02) 8123-4567 or ask our staff during your visit — that way you get the most accurate, up-to-date answer.',
            'refill_not_supported_online' => 'We don\'t currently support prescription refill requests online. Please visit us in person or call (02) 8123-4567, and bring your prescription or medicine record with you.',
            'registration_deflect' => 'To register as a new patient, please visit us in person at 123 Health Center Road, Barangay Central, or call (02) 8123-4567 for what to bring — we don\'t currently support registering online.',
            'pricing_deflect' => 'For the most accurate pricing and fees, please contact us at (02) 8123-4567 or ask our staff directly — costs can depend on the specific service.',
            'emergency' => 'If this is a medical emergency, please call your local emergency hotline or go to the nearest emergency room right away — do not wait for a reply here. For non-emergency concerns, I\'m happy to help.',
            'confidential_info_denied' => 'For your privacy and security, I can\'t access or share patient records, lab results, IDs, or any personal medical information through this chat — I don\'t have that data at all. To request your own records securely, please visit us in person or call (02) 8123-4567 and bring a valid ID.',
            'about_system_info' => 'HEALTrack is North Super Health Center\'s online patient portal. From here you can check medicine & vaccine availability, view our GIS disease map, read announcements, search our records, and chat with me (NSHC Assistant) — right from this site, no appointment needed.',
            'about_facility_info' => 'North Super Health Center is a community-based healthcare facility committed to providing accessible, reliable, and compassionate care to every barangay we serve — from general consultations and vaccinations to medicine availability and health advisories.',
            'doctors_info' => $d['doctors'] === []
                ? 'We don\'t have any doctor accounts on file right now. We don\'t have live schedule/duty data online yet, so for who\'s available today, please call us at (02) 8123-4567 or visit in person.'
                : hc_chatbot_bullet_list('👩‍⚕️ Our doctors are:', $d['doctors'], 'We don\'t have live schedule/duty data online yet, so for who\'s available today, please call us at (02) 8123-4567 or visit in person.'),
            'contact_info_scoped' => $d['reply'],
            'health_tips_info' => hc_chatbot_bullet_list('💡 A few general tips:', $d['tips'], 'For advice specific to you, please consult our healthcare staff directly.'),
            'greeting' => '👋 Hello! I can help you check medicine/vaccine availability, our services, vaccination announcements, or our operating hours — what would you like to know?',
            default => 'I\'m not sure I understood that. You can ask me things like "Is Paracetamol available?", "What services are available?", "When is the next vaccination?", or "What are your hours?".',
        };
    }
}
