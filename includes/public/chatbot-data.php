<?php
/**
 * =====================================================================
 * Talk with NSHC (Chatbot) — Suggestion/Sidebar Config
 * ---------------------------------------------------------------------
 * These question titles and sidebar links are app config, not
 * Firestore content — clicking one still gets a real answer, though:
 * both this page's chat form and these suggestion cards send the
 * question text to api/chatbot-ask.php
 * (includes/public/chatbot_engine.php), which answers from real data
 * where one exists (medicine/vaccine availability, real vaccination
 * announcements) — see that engine file's own header comment.
 * =====================================================================
 */

// Future: could be dynamic/popular queries pulled from a chat_logs collection/table.
$chatSuggestionCards = [
    [
        'icon'  => 'bi-chat-square-text', 'color' => 'blue',
        'title' => 'What services are available?',
        'desc'  => 'Learn about the services we offer at NSHC.',
    ],
    [
        'icon'  => 'bi-capsule', 'color' => 'blue',
        'title' => 'Is Paracetamol available?',
        'desc'  => 'Check medicine availability and stock status.',
    ],
    [
        'icon'  => 'bi-calendar-event', 'color' => 'red',
        'title' => 'When is the next vaccination schedule?',
        'desc'  => 'View upcoming vaccination dates and reminders.',
    ],
    [
        'icon'  => 'bi-heart-pulse', 'color' => 'orange',
        'title' => 'How can I stay healthy?',
        'desc'  => 'Get tips and advice for a healthier lifestyle.',
    ],
];

// Right sidebar — Other Actions
$otherActions = [
    [
        'key'   => 'contact',
        'icon'  => 'bi-telephone-fill', 'color' => 'maroon',
        'title' => 'Contact NSHC directly',
        'desc'  => 'Call or email our office.',
        'href'  => '#', // handled by the Contact modal instead of a page link
    ],
    [
        'key'   => 'report',
        'icon'  => 'bi-flag-fill', 'color' => 'purple',
        'title' => 'Report a concern',
        'desc'  => 'Submit a feedback or concern.',
        'href'  => 'report-concern.php',
    ],
];
