<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config.php';

// Strict Authentication Guard
$isUserLoggedIn = (isset($_SESSION[$user_session_name]) && $_SESSION[$user_session_name] === true)
               || (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true);

if (!$isUserLoggedIn) {
    http_response_code(401);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['success' => false, 'error' => 'Unauthorized. Please sign in to book seats.']);
    exit;
}

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$currentUserId    = (int)($_SESSION[$user_session_name . '_user_id'] ?? 0);
$currentUserName  = (string)($_SESSION[$user_session_name . '_name'] ?? (!empty($_SESSION['admin_logged_in']) ? 'Administrator' : 'User'));
$currentUserEmail = (string)($_SESSION[$user_session_name . '_email'] ?? '');

// Strict CSRF Validation for state-changing POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedCsrf = (string)($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $submittedCsrf)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Security token (CSRF) validation failed. Please refresh the page and try again.']);
        exit;
    }
}

$action = trim((string)($_GET['action'] ?? $_POST['action'] ?? ''));

// Site configuration helper
function getSiteDetails(string $siteKey): array {
    $clean = strtolower(trim($siteKey));
    switch ($clean) {
        case 'khulna':
        case '476':
            return ['site' => 'khulna', 'testSite' => '476', 'cityTest' => 'KHULNA', 'name' => 'KHULNA'];
        case 'dhaka':
        case 'dhaka1':
        case '474':
        default:
            return ['site' => 'dhaka1', 'testSite' => '474', 'cityTest' => 'DHAKA SITE 1', 'name' => 'ULSAI (Dhaka)'];
    }
}

// Compute standard 14-digit examinee number formula: YYMMDDCCCQS0P0
function computeExamineeNumber(string $date, string $centerCode, string $degree, int $slot, int $pc): string {
    $time = strtotime($date);
    if (!$time) $time = time();
    $yy = date('y', $time);
    $mm = date('m', $time);
    $dd = date('d', $time);

    // Degree: 5Q -> 5, 4Q -> 4, etc.
    $degDigit = preg_replace('/[^0-9]/', '', $degree);
    if (empty($degDigit)) $degDigit = '5';

    // PC: 2-digit padded + trailing 0 (e.g. PC 1 -> 010, PC 3 -> 030, PC 12 -> 120)
    $pcPart = sprintf('%02d0', $pc);

    return sprintf('%s%s%s%s%s%d%s', $yy, $mm, $dd, $centerCode, $degDigit, $slot, $pcPart);
}

/**
 * Check if a seat hold record belongs to the current user/session
 */
function isHoldOwnedByCurrentSession(array $holdRow): bool {
    global $currentUserId, $currentUserEmail;

    $examineeId = (string)($holdRow['examinee_number'] ?? '');

    // 1. Check PHP session-stored hold keys for this browser session
    if (!empty($examineeId) && !empty($_SESSION['held_examinees'][$examineeId])) {
        return true;
    }

    // 2. Admin sessions can access / resume holds
    if (!empty($_SESSION['admin_logged_in'])) {
        return true;
    }

    // 3. Match by user_id
    if ($currentUserId > 0 && isset($holdRow['user_id']) && (int)$holdRow['user_id'] === $currentUserId) {
        return true;
    }

    // 4. Match by email
    if (!empty($currentUserEmail) && !empty($holdRow['user_email']) && strcasecmp(trim($holdRow['user_email']), trim($currentUserEmail)) === 0) {
        return true;
    }

    return false;
}

/**
 * Safely format test fee (e.g. 3500 -> '3,500 BDT', empty/null/0 -> 'N/A')
 */
function formatTestFee($rawFee): string {
    if ($rawFee === null || $rawFee === '') {
        return 'N/A';
    }
    $trimmed = trim((string)$rawFee);
    if ($trimmed === '' || $trimmed === '0' || strcasecmp($trimmed, 'null') === 0 || strcasecmp($trimmed, 'n/a') === 0) {
        return 'N/A';
    }
    if (stripos($trimmed, 'BDT') !== false) {
        return $trimmed;
    }
    $num = preg_replace('/[^\d.]/', '', $trimmed);
    if (is_numeric($num) && (float)$num > 0) {
        return number_format((float)$num) . ' BDT';
    }
    return !empty($trimmed) ? $trimmed : 'N/A';
}

// Helper to make external cURL POST requests to senmonkyouiku API
function callExternalApi(string $url, array $payload): array {
    $ch = curl_init($url);
    $json = json_encode($payload);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'Content-Length: ' . strlen($json)
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($response === false || !empty($error)) {
        return [
            'success'   => false,
            'http_code' => $httpCode,
            'error'     => 'Failed to connect to external examination server: ' . $error
        ];
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        return [
            'success'   => false,
            'http_code' => $httpCode,
            'error'     => 'Invalid response from examination server. ' . substr($response, 0, 150)
        ];
    }

    return [
        'success'   => true,
        'http_code' => $httpCode,
        'data'      => $decoded
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// 1. ACTION: Get Active Locks for a given site & date
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'get_active_locks') {
    $siteKey = trim((string)($_GET['site'] ?? 'dhaka1'));
    $siteInfo = getSiteDetails($siteKey);
    $date = trim((string)($_GET['date'] ?? ''));
    if (!empty($date)) {
        $date = str_replace('/', '-', $date);
    }

    $pdo = getUserPortalPDO();
    $locks = [];

    if ($pdo && !empty($date)) {
        try {
            // A. Fetch completed registrations (PENDING status awaiting payment, or PAID)
            $compStmt = $pdo->prepare("
                SELECT test_site, date_of_test, time_slot, pc_number, examinee_number,
                       candidate_name, degree_of_test, status, user_id
                FROM booking_completed
                WHERE test_site = ? AND date_of_test = ?
            ");
            $compStmt->execute([$siteInfo['testSite'], $date]);
            while ($cRow = $compStmt->fetch()) {
                $locks[] = [
                    'testSite'         => $cRow['test_site'],
                    'date'             => $cRow['date_of_test'],
                    'timeSlot'         => (int)$cRow['time_slot'],
                    'pc'               => (int)$cRow['pc_number'],
                    'examineeNumber'   => $cRow['examinee_number'],
                    'candidateName'    => $cRow['candidate_name'] ?? '',
                    'status'           => 'PENDING', // Registered pending payment!
                    'degree'           => $cRow['degree_of_test'],
                    'remainingSeconds' => 0,
                    'isMine'           => ($currentUserId > 0 && (int)$cRow['user_id'] === $currentUserId)
                ];
            }

            // B. Fetch temporary holds & requested seats from seat_bookings
            $holdStmt = $pdo->prepare("
                SELECT test_site, date_of_test, time_slot, pc_number, examinee_number, status, degree_of_test,
                       TIMESTAMPDIFF(SECOND, NOW(), expires_at) AS remaining_seconds,
                       user_id, user_name
                FROM seat_bookings
                WHERE test_site = ? AND date_of_test = ?
            ");
            $holdStmt->execute([$siteInfo['testSite'], $date]);
            while ($row = $holdStmt->fetch()) {
                $rem = (int)($row['remaining_seconds'] ?? 0);
                // Active 15-minute hold vs. expired hold waiting to be taken over
                $lockStatus = ($rem > 0) ? 'HOLDING' : 'HOLD_EXPIRED';

                $locks[] = [
                    'testSite'         => $row['test_site'],
                    'date'             => $row['date_of_test'],
                    'timeSlot'         => (int)$row['time_slot'],
                    'pc'               => (int)$row['pc_number'],
                    'examineeNumber'   => $row['examinee_number'],
                    'status'           => $lockStatus,
                    'degree'           => $row['degree_of_test'],
                    'remainingSeconds' => max(0, $rem),
                    'isMine'           => isHoldOwnedByCurrentSession($row)
                ];
            }
        } catch (PDOException $e) {
            error_log("get_active_locks DB error: " . $e->getMessage());
        }
    }

    echo json_encode(['success' => true, 'locks' => $locks]);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// 2. ACTION: Check single seat availability / lock status
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'check_seat') {
    $siteKey  = trim((string)($_POST['site'] ?? $_GET['site'] ?? ''));
    $date     = str_replace('/', '-', trim((string)($_POST['date'] ?? $_GET['date'] ?? '')));
    $timeSlot = (int)($_POST['time_slot'] ?? $_GET['time_slot'] ?? 1);
    $pc       = (int)($_POST['pc'] ?? $_GET['pc'] ?? 0);
    $siteInfo = getSiteDetails($siteKey);

    if (empty($date) || $pc <= 0 || $timeSlot <= 0) {
        echo json_encode(['success' => false, 'error' => 'Missing date, time slot, or workstation information.']);
        exit;
    }

    $pdo = getUserPortalPDO();
    if (!$pdo) {
        echo json_encode(['success' => false, 'error' => 'Database connection failed. Unable to reserve workstation.']);
        exit;
    }
        try {
            // 1. Check if registered in booking_completed
            $compStmt = $pdo->prepare("
                SELECT examinee_number, candidate_name, degree_of_test, status
                FROM booking_completed
                WHERE test_site = ? AND date_of_test = ? AND time_slot = ? AND pc_number = ?
                LIMIT 1
            ");
            $compStmt->execute([$siteInfo['testSite'], $date, $timeSlot, $pc]);
            $completed = $compStmt->fetch();
            if ($completed) {
                echo json_encode([
                    'success'   => true,
                    'available' => false,
                    'status'    => 'PENDING',
                    'message'   => 'This workstation has already been registered and is pending fee confirmation.'
                ]);
                exit;
            }

            // 2. Check if held in seat_bookings
            $holdStmt = $pdo->prepare("
                SELECT examinee_number, status, TIMESTAMPDIFF(SECOND, NOW(), expires_at) as remaining_seconds,
                       user_id, user_name, degree_of_test, test_fee
                FROM seat_bookings
                WHERE test_site = ? AND date_of_test = ? AND time_slot = ? AND pc_number = ?
                ORDER BY id DESC LIMIT 1
            ");
            $holdStmt->execute([$siteInfo['testSite'], $date, $timeSlot, $pc]);
            $lock = $holdStmt->fetch();

            if ($lock) {
                $rem = (int)($lock['remaining_seconds'] ?? 0);
                $isMine = isHoldOwnedByCurrentSession($lock);

                if ($rem > 0) {
                    echo json_encode([
                        'success'           => true,
                        'available'         => $isMine,
                        'status'            => 'HOLDING',
                        'isMine'            => $isMine,
                        'remainingSeconds'  => $rem,
                        'examineeNumber'    => $lock['examinee_number'],
                        'degree'            => $lock['degree_of_test'],
                        'testFee'           => formatTestFee($lock['test_fee'] ?? null),
                        'message'           => $isMine
                            ? 'You have an active 15-minute hold on this seat.'
                            : 'This seat is currently on a 15-minute hold by another applicant.'
                    ]);
                    exit;
                } else {
                    // Expired hold: seat remains requested for that degree and can be taken over
                    echo json_encode([
                        'success'          => true,
                        'available'        => true,
                        'canTakeover'      => true,
                        'status'           => 'HOLD_EXPIRED',
                        'examineeNumber'   => $lock['examinee_number'],
                        'degree'           => $lock['degree_of_test'],
                        'testFee'          => formatTestFee($lock['test_fee'] ?? null),
                        'message'          => "Seat was requested for level {$lock['degree_of_test']}. Anyone can now complete registration for {$lock['degree_of_test']}."
                    ]);
                    exit;
                }
            }
        } catch (PDOException $e) {
            error_log("check_seat DB error: " . $e->getMessage());
        }

    echo json_encode([
        'success'   => true,
        'available' => true,
        'status'    => 'AVAILABLE',
        'message'   => 'Seat is vacant and available to lock.'
    ]);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. ACTION: Step 1 — Confirm Level
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'confirm_level' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $siteKey  = trim((string)($_POST['site'] ?? ''));
    $date     = str_replace('/', '-', trim((string)($_POST['date'] ?? '')));
    $timeSlot = (int)($_POST['time_slot'] ?? 1);
    $pc       = (int)($_POST['pc'] ?? 0);
    $degree   = strtoupper(trim((string)($_POST['degree'] ?? '5Q')));
    $siteInfo = getSiteDetails($siteKey);

    if (empty($date) || $pc <= 0 || $timeSlot <= 0 || empty($degree)) {
        echo json_encode(['success' => false, 'error' => 'Please provide site, date, time slot, PC station, and exam level.']);
        exit;
    }

    if (!in_array($degree, ['5Q', '4Q', '3Q'], true)) {
        echo json_encode(['success' => false, 'error' => 'Invalid exam level. Only 5Q, 4Q, and 3Q are available for registration.']);
        exit;
    }

    $pdo = getUserPortalPDO();
    if (!$pdo) {
        echo json_encode(['success' => false, 'error' => 'Database connection failed. Unable to reserve workstation.']);
        exit;
    }
        // Step 1A: Check if seat is already registered in booking_completed
        try {
            $compCheck = $pdo->prepare("
                SELECT examinee_number, candidate_name
                FROM booking_completed
                WHERE test_site = ? AND date_of_test = ? AND time_slot = ? AND pc_number = ?
                LIMIT 1
            ");
            $compCheck->execute([$siteInfo['testSite'], $date, $timeSlot, $pc]);
            if ($compCheck->fetch()) {
                echo json_encode([
                    'success' => false,
                    'error'   => 'This workstation has already completed registration and is pending fee confirmation.'
                ]);
                exit;
            }
        } catch (PDOException $e) {
            error_log("confirm_level booking_completed check error: " . $e->getMessage());
        }

        // Step 1B: Check if seat already exists in seat_bookings
        try {
            $holdCheck = $pdo->prepare("
                SELECT id, examinee_number, status, degree_of_test, test_fee,
                       TIMESTAMPDIFF(SECOND, NOW(), expires_at) AS remaining_seconds,
                       user_id
                FROM seat_bookings
                WHERE test_site = ? AND date_of_test = ? AND time_slot = ? AND pc_number = ?
                ORDER BY id DESC LIMIT 1
            ");
            $holdCheck->execute([$siteInfo['testSite'], $date, $timeSlot, $pc]);
            $existing = $holdCheck->fetch();

            if ($existing) {
                $rem = (int)($existing['remaining_seconds'] ?? 0);
                $isMine = isHoldOwnedByCurrentSession($existing);

                // Enforce degree restriction: Once requested for e.g. N5, can ONLY be registered for N5
                if ($degree !== $existing['degree_of_test']) {
                    echo json_encode([
                        'success' => false,
                        'error'   => "This workstation was requested for {$existing['degree_of_test']}. It can only be registered for level {$existing['degree_of_test']}."
                    ]);
                    exit;
                }

                // If active hold by another applicant
                if ($rem > 0 && !$isMine) {
                    $mins = ceil($rem / 60);
                    echo json_encode([
                        'success'          => false,
                        'held'             => true,
                        'remainingSeconds' => $rem,
                        'error'            => "This workstation is currently on a 15-minute hold by another applicant ({$mins} minute(s) remaining)."
                    ]);
                    exit;
                }

                // Either same user extending hold OR expired hold being taken over for SAME degree!
                // Refresh hold in database with fresh 15-minute window for current user
                $updStmt = $pdo->prepare("
                    UPDATE seat_bookings SET
                        user_id    = ?,
                        user_name  = ?,
                        user_email = ?,
                        status     = 'HOLDING',
                        expires_at = DATE_ADD(NOW(), INTERVAL 15 MINUTE)
                    WHERE id = ?
                ");
                $updStmt->execute([
                    $currentUserId ?: null,
                    $currentUserName,
                    $currentUserEmail,
                    $existing['id']
                ]);

                // Track ownership in this session
                $_SESSION['held_examinees'][$existing['examinee_number']] = time();

                $existingPc = (int)($existing['pc_number'] ?? $pc);
                $switchedDesk = ($existingPc !== $pc);

                echo json_encode([
                    'success'          => true,
                    'examineeNumber'   => $existing['examinee_number'],
                    'testFee'          => formatTestFee($existing['test_fee'] ?? null),
                    'testSite'         => $siteInfo['testSite'],
                    'siteName'         => $siteInfo['name'],
                    'date'             => $date,
                    'timeSlot'         => $timeSlot,
                    'pc'               => $existingPc,
                    'requestedPc'      => $pc,
                    'switchedDesk'     => $switchedDesk,
                    'degree'           => $existing['degree_of_test'],
                    'remainingSeconds' => 900
                ]);
                exit;
            }
        } catch (PDOException $e) {
            error_log("confirm_level seat_bookings check error: " . $e->getMessage());
        }

    // Step 1C: Seat is BRAND NEW (does NOT exist in seat_bookings)
    // We MUST call Official API 1 (/checkPlaceAvailable) to allocate the workstation and retrieve the official Examinee ID & Test Fee
    $time = trim((string)($_POST['time'] ?? ''));

    $api1Payload = [
        'countryTest'  => 'BANGLADESH',
        'cityTest'     => $siteInfo['cityTest'],
        'testSite'     => $siteInfo['testSite'],
        'dateOfTest'   => $date,
        'timeSlot'     => $timeSlot,
        'time'         => $time,
        'degreeOfTest' => $degree
    ];

    $checkApiUrl = 'http://www.senmonkyouiku.com:9000/checkPlaceAvailable';
    $api1Res     = callExternalApi($checkApiUrl, $api1Payload);

    if (!$api1Res['success']) {
        echo json_encode([
            'success' => false,
            'error'   => 'Failed to connect to official examination server: ' . ($api1Res['error'] ?? 'Please retry.')
        ]);
        exit;
    }

    $api1Data = $api1Res['data'] ?? [];
    $api1Msg  = strtolower((string)($api1Data['message'] ?? ''));

    if ($api1Msg !== 'success') {
        $slots = !empty($api1Data['slotAvailable']) ? implode(', ', $api1Data['slotAvailable']) : '';
        $slotMsg = $slots ? " (Available slot(s): {$slots})" : '';
        echo json_encode([
            'success' => false,
            'error'   => 'Workstation is unavailable on the official exam server' . $slotMsg . '. Please select another session or seat.'
        ]);
        exit;
    }

    // Authentic official Examinee ID & official Test Fee returned directly from Senmonkyouiku
    $examineeId = trim((string)($api1Data['ID'] ?? ''));
    if (empty($examineeId)) {
        // Fallback formula only if remote message is success but ID field was blank
        $examineeId = computeExamineeNumber($date, $siteInfo['testSite'], $degree, $timeSlot, $pc);
    }
    $testFee = formatTestFee($api1Data['testFee'] ?? null);

    // Extract authentic workstation assigned by the official examination server (encoded in last 3 digits: e.g. '010' -> 1)
    $assignedPc = $pc;
    if (strlen($examineeId) >= 3) {
        $extractedPc = (int)substr($examineeId, -3, 2);
        if ($extractedPc > 0) {
            $assignedPc = $extractedPc;
        }
    }
    $switchedDesk = ($assignedPc !== $pc);

    if ($pdo) {
        try {
            // Clean any older unconfirmed records for this examinee number or this workstation
            $cleanStmt = $pdo->prepare("
                DELETE FROM seat_bookings
                WHERE (examinee_number = ? OR (test_site = ? AND date_of_test = ? AND time_slot = ? AND pc_number = ?))
                  AND status = 'HOLDING'
            ");
            $cleanStmt->execute([$examineeId, $siteInfo['testSite'], $date, $timeSlot, $assignedPc]);

            // Insert official examinee number & official test fee into seat_bookings with 15-minute hold
            $insStmt = $pdo->prepare("
                INSERT INTO seat_bookings (
                    examinee_number, user_id, user_name, user_email,
                    country_test, city_test, test_site, date_of_test, time_slot, pc_number, degree_of_test,
                    test_fee, status, expires_at
                ) VALUES (
                    ?, ?, ?, ?,
                    'BANGLADESH', ?, ?, ?, ?, ?, ?,
                    ?, 'HOLDING', DATE_ADD(NOW(), INTERVAL 15 MINUTE)
                ) ON DUPLICATE KEY UPDATE
                    user_id        = VALUES(user_id),
                    user_name      = VALUES(user_name),
                    user_email     = VALUES(user_email),
                    country_test   = VALUES(country_test),
                    city_test      = VALUES(city_test),
                    test_site      = VALUES(test_site),
                    date_of_test   = VALUES(date_of_test),
                    time_slot      = VALUES(time_slot),
                    pc_number      = VALUES(pc_number),
                    degree_of_test = VALUES(degree_of_test),
                    test_fee       = VALUES(test_fee),
                    status         = 'HOLDING',
                    expires_at     = DATE_ADD(NOW(), INTERVAL 15 MINUTE)
            ");
            $insStmt->execute([
                $examineeId,
                $currentUserId ?: null,
                $currentUserName,
                $currentUserEmail,
                $siteInfo['cityTest'],
                $siteInfo['testSite'],
                $date,
                $timeSlot,
                $assignedPc,
                $degree,
                $testFee
            ]);

            // Track ownership in this session
            $_SESSION['held_examinees'][$examineeId] = time();
        } catch (PDOException $e) {
            error_log("confirm_level lock insert DB error: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error'   => 'Database error reserving seat: ' . $e->getMessage()
            ]);
            exit;
        }
    } else {
        echo json_encode([
            'success' => false,
            'error'   => 'Database connection unavailable. Cannot record seat hold.'
        ]);
        exit;
    }

    echo json_encode([
        'success'          => true,
        'examineeNumber'   => $examineeId,
        'testFee'          => $testFee,
        'testSite'         => $siteInfo['testSite'],
        'siteName'         => $siteInfo['name'],
        'date'             => $date,
        'timeSlot'         => $timeSlot,
        'pc'               => $assignedPc,
        'requestedPc'      => $pc,
        'switchedDesk'     => $switchedDesk,
        'degree'           => $degree,
        'remainingSeconds' => 900
    ]);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// 4. ACTION: Step 2 — Submit Candidate Personal Information (Final Booking)
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'submit_registration' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $examineeId = trim((string)($_POST['examineeNumber'] ?? ''));
    $name       = mb_strtoupper(trim((string)($_POST['name'] ?? '')), 'UTF-8');
    $birthday   = trim((string)($_POST['birthday'] ?? ''));
    $address    = trim((string)($_POST['address'] ?? ''));
    $phone      = trim((string)($_POST['phoneNumber'] ?? ''));
    $zipcode    = trim((string)($_POST['zipcode'] ?? ''));
    $country    = trim((string)($_POST['country'] ?? 'BANGLADESH'));
    if (empty($country)) {
        $country = 'BANGLADESH';
    }
    $email      = trim((string)($_POST['email'] ?? ''));
    $picture    = !empty($_POST['personalPicture']) ? trim((string)$_POST['personalPicture']) : null;

    if (empty($examineeId)) {
        echo json_encode(['success' => false, 'error' => 'Missing Examinee ID. Please lock your seat first.']);
        exit;
    }
    if (empty($name) || empty($birthday) || empty($phone) || empty($address) || empty($email)) {
        echo json_encode(['success' => false, 'error' => 'Please fill in all required candidate fields (Name, Birthday, Phone, Address, Email).']);
        exit;
    }

    // Validate Bangladeshi Mobile Number: 11 digits starting with 013-019
    if (!preg_match('/^01[3-9]\d{8}$/', $phone)) {
        echo json_encode([
            'success' => false,
            'error'   => 'Invalid mobile number. Must be 11 digits starting with 013, 014, 015, 016, 017, 018, or 019 (e.g. 01712345678).'
        ]);
        exit;
    }

    // Validate Email Address
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode([
            'success' => false,
            'error'   => 'Invalid email address. Please enter a valid email (e.g. candidate@example.com).'
        ]);
        exit;
    }

    $pdo = getUserPortalPDO();
    $hold = null;

    // A. Verify that the seat hold exists in seat_bookings and has not expired
    if ($pdo) {
        try {
            // First check if already registered in booking_completed
            $compStmt = $pdo->prepare("SELECT id FROM booking_completed WHERE examinee_number = ? LIMIT 1");
            $compStmt->execute([$examineeId]);
            if ($compStmt->fetch()) {
                echo json_encode(['success' => false, 'error' => 'This seat registration has already been submitted and is pending payment.']);
                exit;
            }

            $stmt = $pdo->prepare("
                SELECT id, status, degree_of_test, test_fee, country_test, city_test, test_site, date_of_test, time_slot, pc_number,
                       TIMESTAMPDIFF(SECOND, NOW(), expires_at) as remaining_seconds, user_id, user_name, user_email
                FROM seat_bookings
                WHERE examinee_number = ?
                LIMIT 1
            ");
            $stmt->execute([$examineeId]);
            $hold = $stmt->fetch();

            if (!$hold) {
                echo json_encode(['success' => false, 'error' => 'Seat lock record not found. Please re-select your workstation.']);
                exit;
            }

            $rem = (int)($hold['remaining_seconds'] ?? 0);
            $isAdmin = !empty($_SESSION['admin_logged_in']);

            // If the seat is actively held ($rem > 0) by another applicant, only the hold owner (or admin) can register
            if ($rem > 0 && !$isAdmin && !isHoldOwnedByCurrentSession($hold)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'This workstation is currently on a 15-minute hold by another applicant.']);
                exit;
            }

            // If timer ended ($rem <= 0), the hold has expired and no one is holding it -> proceed directly to API 2 registration!
        } catch (PDOException $e) {
            error_log("submit_registration check DB error: " . $e->getMessage());
        }
    }

    // B. Direct Call to External API 2: personalInformation
    $payload = [
        'name'            => $name,
        'birthday'        => $birthday,
        'address'         => $address,
        'phoneNumber'     => $phone,
        'zipcode'         => $zipcode,
        'country'         => $country,
        'email'           => $email,
        'personalPicture' => $picture,
        'examineeNumber'  => $examineeId
    ];

    $apiUrl = 'http://www.senmonkyouiku.com:9000/personalInformation';
    $apiRes = callExternalApi($apiUrl, $payload);

    $data = $apiRes['data'] ?? [];
    $message = strtolower((string)($data['message'] ?? ''));
    $isSuccess = ($apiRes['success'] && $message === 'success');

    // If 2nd API returns any error, fallback to /checkPlaceAvailable
    if (!$isSuccess) {
        $siteDetails = getSiteDetails((string)($hold['test_site'] ?? ''));
        $checkPayload = [
            'countryTest'  => 'BANGLADESH',
            'cityTest'     => !empty($hold['city_test']) ? $hold['city_test'] : $siteDetails['cityTest'],
            'testSite'     => !empty($hold['test_site']) ? $hold['test_site'] : $siteDetails['testSite'],
            'dateOfTest'   => $hold['date_of_test'] ?? '',
            'timeSlot'     => (int)($hold['time_slot'] ?? 1),
            'time'         => '',
            'degreeOfTest' => $hold['degree_of_test'] ?? '5Q'
        ];

        $checkApiUrl = 'http://www.senmonkyouiku.com:9000/checkPlaceAvailable';
        $checkRes    = callExternalApi($checkApiUrl, $checkPayload);

        $checkData   = $checkRes['data'] ?? [];
        $checkMsg    = strtolower((string)($checkData['message'] ?? ''));
        $returnedId  = trim((string)($checkData['ID'] ?? ''));

        // If checkPlaceAvailable succeeded AND returned ID matches the user's selected ID, call API 2
        if ($checkRes['success'] && $checkMsg === 'success' && !empty($returnedId) && $returnedId === $examineeId) {
            $payload['examineeNumber'] = $returnedId;

            // Attempt 1 to call API 2 after re-generating on main server
            $retryRes  = callExternalApi($apiUrl, $payload);
            $retryData = $retryRes['data'] ?? [];
            $retryMsg  = strtolower((string)($retryData['message'] ?? ''));

            if ($retryRes['success'] && $retryMsg === 'success') {
                $data = $retryData;
            } else {
                // If attempt 1 failed, wait 500ms and try API 2 a 2nd time (so user doesn't have to manually click twice)
                usleep(500000);
                $retryRes2  = callExternalApi($apiUrl, $payload);
                $retryData2 = $retryRes2['data'] ?? [];
                $retryMsg2  = strtolower((string)($retryData2['message'] ?? ''));

                if ($retryRes2['success'] && $retryMsg2 === 'success') {
                    $data = $retryData2;
                } else {
                    echo json_encode([
                        'success' => false,
                        'error'   => 'Unable to register for this seat'
                    ]);
                    exit;
                }
            }
        } else {
            // If checkPlaceAvailable returned another status (e.g. place already re-activated on main server),
            // try API 2 directly one more time before giving up
            usleep(500000);
            $retryDirectRes  = callExternalApi($apiUrl, $payload);
            $retryDirectData = $retryDirectRes['data'] ?? [];
            $retryDirectMsg  = strtolower((string)($retryDirectData['message'] ?? ''));

            if ($retryDirectRes['success'] && $retryDirectMsg === 'success') {
                $data = $retryDirectData;
            } else {
                echo json_encode([
                    'success' => false,
                    'error'   => 'Unable to register for this seat'
                ]);
                exit;
            }
        }
    }

    // C. Registration Complete:
    // Insert into booking_completed with status = 'PENDING'
    // AND delete from seat_bookings!
    $rawFee = !empty($data['testFee']) ? (string)$data['testFee'] : (!empty($checkData['testFee']) ? (string)$checkData['testFee'] : ($hold['test_fee'] ?? 'N/A'));
    $fee = formatTestFee($rawFee);
    $marketUrl = $data['testPassMarketUrl'] ?? null;

    if ($pdo && $hold) {
        try {
            $pdo->beginTransaction();

            $insComp = $pdo->prepare("
                INSERT INTO booking_completed (
                    examinee_number, user_id, user_name, user_email,
                    country_test, city_test, test_site, date_of_test, time_slot, pc_number, degree_of_test,
                    test_fee, candidate_name, birthday, address, phone_number, zipcode, country, email,
                    personal_picture, market_url, status
                ) VALUES (
                    ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, 'PENDING'
                )
            ");
            $insComp->execute([
                $examineeId,
                $currentUserId ?: ($hold['user_id'] ?? null),
                $currentUserName ?: ($hold['user_name'] ?? null),
                $currentUserEmail ?: ($hold['user_email'] ?? null),
                $hold['country_test'] ?? 'BANGLADESH',
                $hold['city_test'] ?? '',
                $hold['test_site'] ?? '',
                $hold['date_of_test'] ?? '',
                (int)($hold['time_slot'] ?? 1),
                (int)($hold['pc_number'] ?? 0),
                $hold['degree_of_test'] ?? '',
                $fee,
                $name,
                $birthday,
                $address,
                $phone,
                $zipcode,
                $country,
                $email,
                $picture,
                $marketUrl
            ]);

            // Delete temporary hold from seat_bookings
            $delHold = $pdo->prepare("DELETE FROM seat_bookings WHERE examinee_number = ?");
            $delHold->execute([$examineeId]);

            $pdo->commit();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("submit_registration completed DB transaction error: " . $e->getMessage());
        }
    }

    echo json_encode([
        'success'        => true,
        'message'        => 'Candidate registration completed successfully! Status: PENDING fee payment.',
        'status'         => 'PENDING',
        'examineeNumber' => $examineeId,
        'candidateName'  => $name,
        'testFee'        => $fee,
        'marketUrl'      => $marketUrl
    ]);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// 5. ACTION: Release lock (No deletion of seat_bookings as requested)
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'release_lock' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Keep seat in seat_bookings so the allocated Examinee ID is not lost
    echo json_encode(['success' => true]);
    exit;
}

// Fallback
echo json_encode(['success' => false, 'error' => 'Unknown action.']);
exit;