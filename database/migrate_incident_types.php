<?php
// ============================================================
// IRS Migration: Replace category list with specific incident
// types grouped under each ICT service.
//
// SAFE to re-run. It does NOT delete existing categories (they are
// referenced by incidents.category_id), it only:
//   1. Deactivates every existing category (is_active = 0)
//   2. Inserts/reactivates the incident types listed below
//
// Run from a terminal:  php database/migrate_incident_types.php
// ============================================================

require __DIR__ . '/../includes/functions.php';

// name => short description. Names MUST match $serviceCategoryMap in
// public/incidents/report.php.
$incidentTypes = [
    'Password recovering'               => 'Difficulty recovering or resetting an account password.',
    'ISMS overloading'                  => 'ISMS becomes overloaded or unresponsive under heavy use.',
    'Poor interface'                    => 'Confusing, broken, or poorly laid-out user interface.',
    'Poor responsiveness'               => 'Pages or controls respond slowly or not at all.',
    'Internet connection problem'       => 'General internet connectivity problems affecting access.',
    'Wi-Fi problem'                     => 'Campus Wi-Fi connection or authentication problems.',
    'Moodle delaying'                   => 'The eLearning platform (Moodle) is slow or delayed.',
    'Payment not appearing'             => 'A completed payment is not reflected in the system.',
    'Examination ticket not appearing'  => 'Examination ticket/permit is missing or not generated.',
    'Result problem'                    => 'Missing, incorrect, or unavailable examination results.',
    'Wi-Fi slow speed'                  => 'Connected to campus Wi-Fi but the speed is very slow.',
    'File upload failing'               => 'A general file upload fails or does not complete.',
    'Wrong programme details'           => 'Incorrect programme, semester, or academic details shown.',
    'Password reset email not received' => 'The password reset email never arrives in the inbox.',
    'Mobile upload failure'             => 'File upload fails specifically on a mobile device.',
    '500 Internal Server Error'         => 'The server returns a 500 internal server error.',
    'eLearning portal inaccessible'     => 'The eLearning (Moodle) portal cannot be reached.',
    'ISMS portal slow loading'          => 'The ISMS portal loads very slowly.',
    'Timetable not visible'             => 'The class or examination timetable is not displayed.',
    'Quiz not loading'                  => 'An online quiz fails to load or open.',
    'Assignment upload failure'         => 'An assignment submission upload fails.',
    'File format rejected'              => 'The uploaded file format or size is rejected.',
    'Missing joining group link'        => 'The group/class joining link is missing.',
    'Unable to access course material'  => 'Course materials cannot be opened or downloaded.',
    'Enrolled in wrong course'          => 'The student is enrolled in an incorrect course.',
    'Account locked'                    => 'The account is locked after failed logins or for other reasons.',
    'Unable to log in to eLearning'     => 'Cannot sign in to the eLearning (Moodle) platform.',
    'Unable to log in to ISMS'          => 'Cannot sign in to ISMS.',
    'Student account not activated'     => 'The student account has not yet been activated.',
];

$pdo = db();
$pdo->beginTransaction();

try {
    // 1. Hide every existing category from the dropdowns.
    $pdo->exec('UPDATE categories SET is_active = 0');

    $check  = $pdo->prepare('SELECT id FROM categories WHERE name = ? LIMIT 1');
    $insert = $pdo->prepare('INSERT INTO categories (name, description, is_active) VALUES (?, ?, 1)');
    $update = $pdo->prepare('UPDATE categories SET description = ?, is_active = 1 WHERE name = ?');

    $added = 0;
    $reactivated = 0;

    foreach ($incidentTypes as $name => $description) {
        $check->execute([$name]);
        if ($check->fetchColumn()) {
            $update->execute([$description, $name]);
            $reactivated++;
        } else {
            $insert->execute([$name, $description]);
            $added++;
        }
    }

    $pdo->commit();

    echo "Migration complete.\n";
    echo "  Inserted new incident types : {$added}\n";
    echo "  Reactivated existing ones    : {$reactivated}\n";
    echo "  Total active incident types  : " . count($incidentTypes) . "\n";
} catch (\Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'Migration failed: ' . $e->getMessage() . "\n");
    exit(1);
}
