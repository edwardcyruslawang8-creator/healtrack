<?php
/**
 * =====================================================================
 * Admin i18n helper (English / Filipino)
 * ---------------------------------------------------------------------
 * t('key') looks up $_SESSION['language'] (English/Filipino — set at
 * login in api/session-login.php, kept in sync by
 * api/update-profile-settings.php) and returns the matching string,
 * falling back to English for any key not yet translated.
 *
 * Scope: the shared shell every admin/*.php page has in common — the
 * sidebar brand/nav/logout, "Back to ..." links, and each page's
 * title/subtitle — not the full content of every card/table/form on
 * every page. That's a much larger effort (hundreds of individual
 * strings across facility analytics, audit log filters, profile
 * settings forms, ...) intentionally left in English for now; this
 * still makes switching languages visibly, functionally real for the
 * chrome every page shares.
 *
 * Assumes session_start() already ran, same as every other $_SESSION
 * read in this codebase.
 * =====================================================================
 */

$GLOBALS['ADMIN_I18N'] = [
    'English' => [
        'app.tagline' => 'Health Information System',

        'nav.dashboard' => 'Dashboard',
        'nav.facility_management' => 'Facility Management',
        'nav.subadmin_management' => 'Sub-Administrator Management',
        'nav.gis_mapping' => 'GIS Mapping',
        'nav.account_monitoring' => 'Account Monitoring',
        'nav.audit_logs' => 'Audit Logs',
        'nav.profile_settings' => 'Profile / Settings',
        'nav.logout' => 'Logout',

        'back.dashboard' => 'Back to Dashboard',
        'back.facilities' => 'Back to Facilities',

        'page.dashboard.title' => 'ADMINISTRATOR DASHBOARD',
        'page.dashboard.subtitle' => 'Manage healthcare facilities, sub-administrators, and system activities.',
        'page.facility_management.title' => 'Facility Management',
        'page.facility_management.subtitle' => 'Manage, monitor, and organize all healthcare facilities in the system.',
        'page.facility_analytics.title' => 'Facility Analytics & Reports',
        'page.facility_analytics.subtitle' => 'Comprehensive overview and performance reports for the selected healthcare facility.',
        'page.subadmin_management.title' => 'Sub-Administrator Management',
        'page.subadmin_management.subtitle' => 'Manage sub-administrator accounts, permissions, and assigned facilities.',
        'page.gis_mapping.title' => 'GIS Mapping',
        'page.gis_mapping.subtitle' => 'View real-time disease case density and health trends across covered barangays.',
        'page.account_monitoring.title' => 'Account Monitoring',
        'page.account_monitoring.subtitle' => 'Monitor and manage all system accounts and their activities.',
        'page.audit_logs.title' => 'Audit Logs',
        'page.audit_logs.subtitle' => 'Track and review all system activities and changes within the administrator system.',
        'page.profile_settings.title' => 'Profile / Settings',
        'page.profile_settings.subtitle' => 'Manage your profile information and application preferences.',

        // --- Sub-Administrator section (subadmin/*.php) — same scope
        // rule as the admin section above: the shared sidebar nav +
        // each page's own title/subtitle, not every card/table/form.
        'nav.patient_management' => 'Patient Management',
        'nav.medicine_vaccine_inventory' => 'Medicine & Vaccine Inventory',
        'nav.stock_requests' => 'Stock Requests',
        'nav.analytics_reports' => 'Analytics & Reports',
        'nav.analytics_reports_facility' => 'Facility Reports',
        'nav.analytics_reports_chatbot' => 'Chatbot Reports',
        'nav.user_management' => 'User Management',
        'nav.notifications' => 'Notifications',
        'nav.audit_logs_subadmin' => 'Sub Admin',
        'nav.audit_logs_doctor' => 'Doctor',
        'nav.audit_logs_medstaff' => 'Medical Staff',
        'nav.audit_logs_medtech' => 'Medical Technologist',
        'nav.audit_logs_radiotech' => 'Radiologic Technologist',
        'nav.audit_logs_pharmacist' => 'Pharmacist',
        'nav.consent_management' => 'Consent Management',
        'nav.announcements' => 'Announcements',
        'nav.homepage_settings' => 'Homepage Settings',

        'page.homepage_settings.subtitle' => 'Manage the content shown on the public homepage — mission & vision, contact info, and operating hours.',

        'page.subadmin_dashboard.welcome_prefix' => 'Welcome back, ',
        'page.subadmin_dashboard.welcome_suffix' => '! Here\'s what\'s happening today.',
        'page.sa_audit_logs.subtitle' => 'Track and review all system activities and changes within your facility.',
        'page.sa_patient_management.subtitle' => 'Manage and view patients registered in the system.',
        'page.announcements.subtitle' => 'Post announcements to the public patient homepage.',
        'page.consent_management.subtitle' => 'Manage patient consent records for data sharing.',
        'page.notifications.subtitle' => 'View and manage all system notifications and alerts.',
        'page.user_management.subtitle' => 'Manage system users, roles, and permissions.',

        'page.allergy_details.title' => 'Allergy Details',
        'page.laboratory_result_details.title' => 'Laboratory Result Details',
        'page.vaccination_record_details.title' => 'Vaccination Record Details',

        // "Patient Management / <child>" composite breadcrumb titles
        // (allergies.php, documents.php, history.php, medical_records.php,
        // medications.php, patient_laboratory_xray.php,
        // vaccination_records.php, patient_profile.php) — the "Patient
        // Management" half reuses nav.patient_management above.
        'page.pm_child.allergies' => 'Allergies',
        'page.pm_child.documents' => 'Documents',
        'page.pm_child.history' => 'History',
        'page.pm_child.medical_records' => 'Medical Records',
        'page.pm_child.medications' => 'Medications',
        'page.pm_child.lab_xray' => 'Laboratory & X-ray',
        'page.pm_child.vaccination_records' => 'Vaccination Records',
        'page.pm_child.patient_profile' => 'Patient Profile',
    ],
    'Filipino' => [
        'app.tagline' => 'Sistema ng Impormasyong Pangkalusugan',

        'nav.dashboard' => 'Dashboard',
        'nav.facility_management' => 'Pamamahala ng Pasilidad',
        'nav.subadmin_management' => 'Pamamahala ng Sub-Administrator',
        'nav.gis_mapping' => 'GIS Mapping',
        'nav.account_monitoring' => 'Pagmomonitor ng Account',
        'nav.audit_logs' => 'Talaan ng Aktibidad',
        'nav.profile_settings' => 'Profile / Setting',
        'nav.logout' => 'Mag-log Out',

        'back.dashboard' => 'Bumalik sa Dashboard',
        'back.facilities' => 'Bumalik sa mga Pasilidad',

        'page.dashboard.title' => 'DASHBOARD NG ADMINISTRATOR',
        'page.dashboard.subtitle' => 'Pamahalaan ang mga pasilidad pangkalusugan, sub-administrator, at aktibidad ng sistema.',
        'page.facility_management.title' => 'Pamamahala ng Pasilidad',
        'page.facility_management.subtitle' => 'Pamahalaan, subaybayan, at ayusin ang lahat ng pasilidad pangkalusugan sa sistema.',
        'page.facility_analytics.title' => 'Analytics at Ulat ng Pasilidad',
        'page.facility_analytics.subtitle' => 'Komprehensibong pangkalahatang-ideya at ulat ng performance para sa napiling pasilidad pangkalusugan.',
        'page.subadmin_management.title' => 'Pamamahala ng Sub-Administrator',
        'page.subadmin_management.subtitle' => 'Pamahalaan ang mga account, pahintulot, at itinalagang pasilidad ng mga sub-administrator.',
        'page.gis_mapping.title' => 'GIS Mapping',
        'page.gis_mapping.subtitle' => 'Tingnan ang real-time na dami ng kaso ng sakit at mga uso sa kalusugan sa mga saklaw na barangay.',
        'page.account_monitoring.title' => 'Pagmomonitor ng Account',
        'page.account_monitoring.subtitle' => 'Subaybayan at pamahalaan ang lahat ng account sa sistema at ang kanilang mga aktibidad.',
        'page.audit_logs.title' => 'Talaan ng Aktibidad',
        'page.audit_logs.subtitle' => 'Subaybayan at suriin ang lahat ng aktibidad at pagbabago sa loob ng sistema ng administrator.',
        'page.profile_settings.title' => 'Profile / Setting',
        'page.profile_settings.subtitle' => 'Pamahalaan ang iyong impormasyon sa profile at mga kagustuhan sa aplikasyon.',

        // --- Sub-Administrator section (subadmin/*.php) ---
        'nav.patient_management' => 'Pamamahala ng Pasyente',
        'nav.medicine_vaccine_inventory' => 'Imbentaryo ng Gamot at Bakuna',
        'nav.stock_requests' => 'Kahilingan sa Stock',
        'nav.analytics_reports' => 'Analytics at Ulat',
        'nav.analytics_reports_facility' => 'Ulat ng Pasilidad',
        'nav.analytics_reports_chatbot' => 'Ulat ng Chatbot',
        'nav.user_management' => 'Pamamahala ng User',
        'nav.notifications' => 'Mga Abiso',
        'nav.audit_logs_subadmin' => 'Sub Admin',
        'nav.audit_logs_doctor' => 'Doktor',
        'nav.audit_logs_medstaff' => 'Medical Staff',
        'nav.audit_logs_medtech' => 'Medical Technologist',
        'nav.audit_logs_radiotech' => 'Radiologic Technologist',
        'nav.audit_logs_pharmacist' => 'Parmasyutiko',
        'nav.consent_management' => 'Pamamahala ng Pahintulot',
        'nav.announcements' => 'Mga Anunsyo',
        'nav.homepage_settings' => 'Setting ng Homepage',

        'page.homepage_settings.subtitle' => 'Pamahalaan ang nilalaman ng pampublikong homepage — misyon at bisyon, impormasyon sa pakikipag-ugnayan, at oras ng operasyon.',

        'page.subadmin_dashboard.welcome_prefix' => 'Maligayang pagbabalik, ',
        'page.subadmin_dashboard.welcome_suffix' => '! Narito ang mga pangyayari ngayon.',
        'page.sa_audit_logs.subtitle' => 'Subaybayan at suriin ang lahat ng aktibidad at pagbabago sa loob ng iyong pasilidad.',
        'page.sa_patient_management.subtitle' => 'Pamahalaan at tingnan ang mga pasyenteng nakarehistro sa sistema.',
        'page.announcements.subtitle' => 'Mag-post ng mga anunsyo sa pampublikong homepage ng pasyente.',
        'page.consent_management.subtitle' => 'Pamahalaan ang mga talaan ng pahintulot ng pasyente para sa pagbabahagi ng datos.',
        'page.notifications.subtitle' => 'Tingnan at pamahalaan ang lahat ng abiso at alerto ng sistema.',
        'page.user_management.subtitle' => 'Pamahalaan ang mga user, tungkulin, at pahintulot ng sistema.',

        'page.allergy_details.title' => 'Detalye ng Allergy',
        'page.laboratory_result_details.title' => 'Detalye ng Resulta ng Laboratoryo',
        'page.vaccination_record_details.title' => 'Detalye ng Talaan ng Bakuna',

        'page.pm_child.allergies' => 'Allergies',
        'page.pm_child.documents' => 'Mga Dokumento',
        'page.pm_child.history' => 'Kasaysayan',
        'page.pm_child.medical_records' => 'Mga Medikal na Talaan',
        'page.pm_child.medications' => 'Mga Gamot',
        'page.pm_child.lab_xray' => 'Laboratoryo at X-ray',
        'page.pm_child.vaccination_records' => 'Mga Talaan ng Bakuna',
        'page.pm_child.patient_profile' => 'Profile ng Pasyente',
    ],
];

function t(string $key): string
{
    $lang = $_SESSION['language'] ?? 'English';

    return $GLOBALS['ADMIN_I18N'][$lang][$key]
        ?? $GLOBALS['ADMIN_I18N']['English'][$key]
        ?? $key;
}
