
<?php
require_once 'db_connect.php';

date_default_timezone_set('Asia/Kuala_Lumpur');

function e($value)
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function redirectToPage($appointmentID, $message = '')
{
    $url = 'patient_details.php?id=' . (int)$appointmentID;

    if ($message !== '') {
        $url .= '&message=' . urlencode($message);
    }

    header('Location: ' . $url);
    exit;
}

// --------------------------------------------------
// 1. GET APPOINTMENT
// --------------------------------------------------

$appointmentID = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$appointmentID) {
    die('Invalid appointment ID.');
}

$sql = "
    SELECT
        a.appointmentID,
        a.userID,
        a.nurseID,
        a.startDateTime,
        a.endDateTime,
        a.checkInTime,
        a.checkOutTime,
        a.locationAddress,
        a.specialInstructions,
        a.appointmentStatus,

        u.firstName AS patientFirstName,
        u.lastName AS patientLastName,
        u.phoneNumber AS patientPhone,
        u.email AS patientEmail,

        s.serviceName,
        s.category,
        s.description AS serviceDescription,

        n.firstName AS nurseFirstName,
        n.lastName AS nurseLastName

    FROM appointments a

    INNER JOIN users u
        ON a.userID = u.userID

    INNER JOIN services s
        ON a.serviceID = s.serviceID

    LEFT JOIN nurses n
        ON a.nurseID = n.nurseID

    WHERE a.appointmentID = :appointmentID
    LIMIT 1
";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':appointmentID' => $appointmentID
]);

$appointment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$appointment) {
    die('Appointment not found.');
}

// --------------------------------------------------
// 2. CREATE CARE STAGES
// --------------------------------------------------

function getServiceStages($serviceName)
{
    $service = strtolower($serviceName);

    $stages = [
        'Visit Scheduled',
        'Nurse En Route',
        'Nurse Arrived',
        'Patient Assessment'
    ];

    if (
        strpos($service, 'wound') !== false ||
        strpos($service, 'ulcer') !== false ||
        strpos($service, 'debridement') !== false
    ) {
        $stages = array_merge($stages, [
            'Wound Inspection',
            'Wound Cleaning',
            'Wound Dressing',
            'Wound Care Documentation'
        ]);
    } elseif (
        strpos($service, 'vital') !== false ||
        strpos($service, 'mobility') !== false ||
        strpos($service, 'elderly') !== false
    ) {
        $stages = array_merge($stages, [
            'Vital Signs Monitoring',
            'Mobility Assessment',
            'Safety Assessment',
            'Care Recommendations'
        ]);
    } elseif (
        strpos($service, 'stroke') !== false ||
        strpos($service, 'rehabilitation') !== false
    ) {
        $stages = array_merge($stages, [
            'Mobility Assessment',
            'Rehabilitation Exercises',
            'Progress Assessment',
            'Recovery Recommendations'
        ]);
    } elseif (
        strpos($service, 'maternal') !== false ||
        strpos($service, 'newborn') !== false
    ) {
        $stages = array_merge($stages, [
            'Mother Assessment',
            'Newborn Assessment',
            'Health and Safety Check',
            'Family Guidance'
        ]);
    } elseif (
        strpos($service, 'palliative') !== false ||
        strpos($service, 'comfort') !== false
    ) {
        $stages = array_merge($stages, [
            'Comfort Assessment',
            'Symptom Monitoring',
            'Comfort Care',
            'Family Support'
        ]);
    } elseif (strpos($service, 'medication') !== false) {
        $stages = array_merge($stages, [
            'Medication Review',
            'Medication Support',
            'Patient Response Monitoring',
            'Medication Documentation'
        ]);
    } else {
        $stages = array_merge($stages, [
            'Nursing Care',
            'Patient Monitoring',
            'Care Documentation'
        ]);
    }

    $stages[] = 'Care Review';
    $stages[] = 'Checked Out';
    $stages[] = 'Visit Completed';

    return $stages;
}

$stages = getServiceStages($appointment['serviceName']);

$careReviewIndex = array_search(
    'Care Review',
    $stages,
    true
);

// --------------------------------------------------
// 3. ENSURE CARE PROGRESS TABLE EXISTS
// --------------------------------------------------

$pdo->exec("
    CREATE TABLE IF NOT EXISTS care_progress (
        progressID INT AUTO_INCREMENT PRIMARY KEY,
        appointmentID INT NOT NULL,
        stageIndex INT NOT NULL,
        stageName VARCHAR(150) NOT NULL,
        stageNote TEXT NULL,
        updatedByNurseID INT NULL,
        createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

        UNIQUE KEY unique_appointment_stage
            (appointmentID, stageIndex),

        FOREIGN KEY (appointmentID)
            REFERENCES appointments(appointmentID)
            ON DELETE CASCADE
    )
");

// Save the initial stage
$initialStmt = $pdo->prepare("
    INSERT IGNORE INTO care_progress
        (
            appointmentID,
            stageIndex,
            stageName,
            updatedByNurseID
        )
    VALUES
        (
            :appointmentID,
            0,
            :stageName,
            :nurseID
        )
");

$initialStmt->execute([
    ':appointmentID' => $appointmentID,
    ':stageName' => $stages[0],
    ':nurseID' => $appointment['nurseID']
]);

$errorMessage = '';
$successMessage = '';

// --------------------------------------------------
// 4. HANDLE CHECK IN, CHECK OUT, AND CARE PROGRESS
// --------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    try {

        // ------------------------------------------
        // CHECK IN
        // ------------------------------------------

        if ($action === 'check_in') {

            if (!empty($appointment['checkInTime'])) {
                throw new Exception(
                    'This appointment has already been checked in.'
                );
            }

            if (!empty($appointment['checkOutTime'])) {
                throw new Exception(
                    'This appointment has already been checked out.'
                );
            }

            if (
                in_array(
                    $appointment['appointmentStatus'],
                    ['cancelled', 'declined', 'completed'],
                    true
                )
            ) {
                throw new Exception(
                    'This appointment cannot be checked in.'
                );
            }

            if (empty($appointment['nurseID'])) {
                throw new Exception(
                    'A nurse must be assigned before checking in.'
                );
            }

            $pdo->beginTransaction();

            $checkInTime = date('Y-m-d H:i:s');

            $checkInStmt = $pdo->prepare("
                UPDATE appointments
                SET
                    checkInTime = :checkInTime,
                    appointmentStatus = 'in_progress'
                WHERE appointmentID = :appointmentID
                  AND checkInTime IS NULL
                  AND checkOutTime IS NULL
                  AND appointmentStatus NOT IN (
                      'cancelled',
                      'declined',
                      'completed'
                  )
            ");

            $checkInStmt->execute([
                ':checkInTime' => $checkInTime,
                ':appointmentID' => $appointmentID
            ]);

            if ($checkInStmt->rowCount() !== 1) {
                throw new Exception(
                    'Check-in could not be saved. Please refresh the page.'
                );
            }

            $pdo->commit();

            redirectToPage(
                $appointmentID,
                'checked_in'
            );
        }

        // ------------------------------------------
        // CONFIRM NEXT CARE STAGE
        // ------------------------------------------

        elseif ($action === 'next_stage') {

            if (empty($appointment['checkInTime'])) {
                throw new Exception(
                    'Please check in before updating care progress.'
                );
            }

            if (!empty($appointment['checkOutTime'])) {
                throw new Exception(
                    'This visit has already been checked out.'
                );
            }

            $pdo->beginTransaction();

            $latestStmt = $pdo->prepare("
                SELECT stageIndex, stageName
                FROM care_progress
                WHERE appointmentID = :appointmentID
                ORDER BY stageIndex DESC
                LIMIT 1
                FOR UPDATE
            ");

            $latestStmt->execute([
                ':appointmentID' => $appointmentID
            ]);

            $latestStage = $latestStmt->fetch(PDO::FETCH_ASSOC);

            $currentIndex = $latestStage
                ? (int)$latestStage['stageIndex']
                : 0;

            // Stop the timeline at Care Review.
            // Check Out will add the final two stages.
            if ($currentIndex >= $careReviewIndex) {
                throw new Exception(
                    'Care Review is complete. You can now check out.'
                );
            }

            $nextIndex = $currentIndex + 1;
            $nextStageName = $stages[$nextIndex];

            $insertStmt = $pdo->prepare("
                INSERT INTO care_progress
                    (
                        appointmentID,
                        stageIndex,
                        stageName,
                        updatedByNurseID
                    )
                VALUES
                    (
                        :appointmentID,
                        :stageIndex,
                        :stageName,
                        :nurseID
                    )
            ");

            $insertStmt->execute([
                ':appointmentID' => $appointmentID,
                ':stageIndex' => $nextIndex,
                ':stageName' => $nextStageName,
                ':nurseID' => $appointment['nurseID']
            ]);

            $pdo->commit();

            redirectToPage(
                $appointmentID,
                'stage_updated'
            );
        }

        // ------------------------------------------
        // CHECK OUT
        // ------------------------------------------

        elseif ($action === 'check_out') {

            if (empty($appointment['checkInTime'])) {
                throw new Exception(
                    'You must check in before checking out.'
                );
            }

            if (!empty($appointment['checkOutTime'])) {
                throw new Exception(
                    'This appointment has already been checked out.'
                );
            }

            $pdo->beginTransaction();

            $latestStmt = $pdo->prepare("
                SELECT stageIndex
                FROM care_progress
                WHERE appointmentID = :appointmentID
                ORDER BY stageIndex DESC
                LIMIT 1
                FOR UPDATE
            ");

            $latestStmt->execute([
                ':appointmentID' => $appointmentID
            ]);

            $latestStage = $latestStmt->fetch(PDO::FETCH_ASSOC);

            $currentIndex = $latestStage
                ? (int)$latestStage['stageIndex']
                : 0;

            if ($currentIndex < $careReviewIndex) {
                throw new Exception(
                    'Please complete the care stages and Care Review before checking out.'
                );
            }

            $checkOutTime = date('Y-m-d H:i:s');

            // Prevent a checkout time earlier than check-in.
            if (
                strtotime($checkOutTime) <
                strtotime($appointment['checkInTime'])
            ) {
                throw new Exception(
                    'Check-out time cannot be earlier than check-in time.'
                );
            }

            // Save check-out and complete the appointment.
            $checkOutStmt = $pdo->prepare("
                UPDATE appointments
                SET
                    checkOutTime = :checkOutTime,
                    appointmentStatus = 'completed'
                WHERE appointmentID = :appointmentID
                  AND checkInTime IS NOT NULL
                  AND checkOutTime IS NULL
            ");

            $checkOutStmt->execute([
                ':checkOutTime' => $checkOutTime,
                ':appointmentID' => $appointmentID
            ]);

            if ($checkOutStmt->rowCount() !== 1) {
                throw new Exception(
                    'Check-out could not be saved. Please refresh the page.'
                );
            }

            // Record the final two stages automatically.
            $finalStages = [
                $careReviewIndex + 1 => 'Checked Out',
                $careReviewIndex + 2 => 'Visit Completed'
            ];

            $finalStmt = $pdo->prepare("
                INSERT IGNORE INTO care_progress
                    (
                        appointmentID,
                        stageIndex,
                        stageName,
                        updatedByNurseID
                    )
                VALUES
                    (
                        :appointmentID,
                        :stageIndex,
                        :stageName,
                        :nurseID
                    )
            ");

            foreach ($finalStages as $index => $stageName) {
                $finalStmt->execute([
                    ':appointmentID' => $appointmentID,
                    ':stageIndex' => $index,
                    ':stageName' => $stageName,
                    ':nurseID' => $appointment['nurseID']
                ]);
            }

            $pdo->commit();

            redirectToPage(
                $appointmentID,
                'checked_out'
            );
        }

    } catch (Throwable $error) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        // Show validation messages, not internal database errors.
        if ($error instanceof PDOException) {
            $errorMessage =
                'A database error occurred. Please refresh and try again.';
        } else {
            $errorMessage = $error->getMessage();
        }
    }
}

// --------------------------------------------------
// 5. REFRESH APPOINTMENT DATA
// --------------------------------------------------

$stmt->execute([
    ':appointmentID' => $appointmentID
]);

$appointment = $stmt->fetch(PDO::FETCH_ASSOC);

// Get saved care stages
$progressStmt = $pdo->prepare("
    SELECT stageIndex, stageName, createdAt
    FROM care_progress
    WHERE appointmentID = :appointmentID
    ORDER BY stageIndex ASC
");

$progressStmt->execute([
    ':appointmentID' => $appointmentID
]);

$savedProgress = $progressStmt->fetchAll(PDO::FETCH_ASSOC);

$completedStages = [];

foreach ($savedProgress as $progress) {
    $completedStages[(int)$progress['stageIndex']] = $progress;
}

$currentIndex = empty($completedStages)
    ? 0
    : max(array_keys($completedStages));

$currentStage = $stages[$currentIndex] ?? end($stages);

$progressPercent = count($stages) > 1
    ? round(
        ($currentIndex / (count($stages) - 1)) * 100
    )
    : 0;

$checkedIn = !empty($appointment['checkInTime']);
$checkedOut = !empty($appointment['checkOutTime']);

$canCheckIn = (
    !$checkedIn &&
    !$checkedOut &&
    !in_array(
        $appointment['appointmentStatus'],
        ['cancelled', 'declined', 'completed'],
        true
    ) &&
    !empty($appointment['nurseID'])
);

$canUpdateStages = (
    $checkedIn &&
    !$checkedOut &&
    $currentIndex < $careReviewIndex
);

$canCheckOut = (
    $checkedIn &&
    !$checkedOut &&
    $currentIndex >= $careReviewIndex
);

// Calculate actual visit duration
$visitDuration = null;

if ($checkedIn && $checkedOut) {
    $seconds = strtotime($appointment['checkOutTime'])
        - strtotime($appointment['checkInTime']);

    $hours = floor(max(0, $seconds) / 3600);
    $minutes = floor((max(0, $seconds) % 3600) / 60);

    $visitDuration = $hours > 0
        ? $hours . ' hr ' . $minutes . ' min'
        : $minutes . ' min';
}

// Page messages
$message = $_GET['message'] ?? '';

if ($message === 'checked_in') {
    $successMessage = 'Check-in saved successfully.';
} elseif ($message === 'stage_updated') {
    $successMessage = 'Care progress updated successfully.';
} elseif ($message === 'checked_out') {
    $successMessage = 'Check-out saved. The visit is now completed.';
} else {
    $successMessage = '';
}

$status = ucfirst(
    str_replace(
        '_',
        ' ',
        $appointment['appointmentStatus']
    )
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Patient Details | StepCare</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #F5F7FB;
        }

        .stepcare-blue {
            background-color: #3C52C3;
        }

        .stepcare-text {
            color: #3C52C3;
        }

        .stepcare-button {
            background-color: #3C52C3;
            transition: all 0.2s ease;
        }

        .stepcare-button:hover {
            background-color: #2D3FA4;
            transform: translateY(-1px);
        }

        .timeline-line {
            position: absolute;
            left: 15px;
            top: 34px;
            bottom: -5px;
            width: 2px;
            background: #E1E5F0;
        }

        .timeline-item:last-child .timeline-line {
            display: none;
        }
    </style>
</head>

<body class="min-h-screen flex flex-col">

    <!-- Header -->
    <header class="stepcare-blue text-white shadow-sm">
        <div class="max-w-7xl mx-auto px-5 sm:px-8 py-5
                    flex flex-wrap items-center justify-between gap-4">

            <a href="nurse_dashboard.php"
               class="text-2xl font-bold tracking-tight">
                StepCare
            </a>

            <nav class="flex flex-wrap gap-5 text-sm font-medium">
                <a href="nurse_dashboard.php"
                   class="hover:text-blue-200 transition">
                    Dashboard
                </a>

                <a href="nurse_notifications.php"
                   class="hover:text-blue-200 transition">
                    Notifications
                </a>

                <a href="visit_history.php"
                   class="hover:text-blue-200 transition">
                    Visit History
                </a>
            </nav>
        </div>
    </header>

    <!-- Main -->
    <main class="flex-1 w-full max-w-7xl mx-auto px-4 sm:px-8 py-8">

        <!-- Page heading -->
        <div class="mb-8">
            <a href="visit_history.php"
               class="inline-flex items-center gap-2 text-sm
                      stepcare-text hover:underline">
                &larr; Back to Visit History
            </a>

            <div class="mt-4">
                <h1 class="text-3xl font-bold text-gray-800">
                    Patient Details
                </h1>

                <p class="text-gray-500 mt-2">
                    Manage the patient's visit, attendance, and care progress.
                </p>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($successMessage !== ''): ?>
            <div class="mb-6 rounded-xl border border-green-200
                        bg-green-50 px-5 py-4 text-green-700">
                <div class="flex items-center gap-3">
                    <span class="text-lg">&#10003;</span>
                    <p><?= e($successMessage) ?></p>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="mb-6 rounded-xl border border-red-200
                        bg-red-50 px-5 py-4 text-red-700">
                <p class="font-medium">Unable to complete the action</p>
                <p class="text-sm mt-1"><?= e($errorMessage) ?></p>
            </div>
        <?php endif; ?>

        <!-- Patient summary -->
        <section class="bg-white rounded-2xl border border-gray-100
                        shadow-sm p-6 sm:p-8 mb-6">

            <div class="flex flex-col sm:flex-row sm:items-center
                        sm:justify-between gap-5">

                <div class="flex items-center gap-4">
                    <div class="w-16 h-16 rounded-2xl bg-indigo-50
                                flex items-center justify-center
                                text-2xl font-bold stepcare-text">
                        <?= e(
                            strtoupper(
                                substr(
                                    $appointment['patientFirstName'],
                                    0,
                                    1
                                ) .
                                substr(
                                    $appointment['patientLastName'],
                                    0,
                                    1
                                )
                            )
                        ) ?>
                    </div>

                    <div>
                        <p class="text-sm text-gray-500">
                            Patient
                        </p>

                        <h2 class="text-2xl font-bold text-gray-800">
                            <?= e(
                                $appointment['patientFirstName'] .
                                ' ' .
                                $appointment['patientLastName']
                            ) ?>
                        </h2>

                        <p class="text-sm text-gray-500 mt-1">
                            Appointment #<?= (int)$appointmentID ?>
                        </p>
                    </div>
                </div>

                <div class="sm:text-right">
                    <span class="inline-flex rounded-full px-4 py-2
                        text-sm font-semibold
                        <?= $checkedOut
                            ? 'bg-green-100 text-green-700'
                            : ($checkedIn
                                ? 'bg-blue-100 text-blue-700'
                                : 'bg-amber-100 text-amber-700') ?>">
                        <?= e($status) ?>
                    </span>

                    <p class="text-sm text-gray-500 mt-2">
                        <?= e(date(
                            'd M Y',
                            strtotime($appointment['startDateTime'])
                        )) ?>
                    </p>
                </div>

            </div>
        </section>

        <!-- Main content grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6
                    items-start">

            <!-- Left column -->
            <div class="lg:col-span-2 space-y-6">

                <!-- Visit attendance -->
                <section class="bg-white rounded-2xl border border-gray-100
                                shadow-sm p-6 sm:p-8">

                    <div class="flex items-start gap-3 mb-6">
                        <div class="w-11 h-11 rounded-xl bg-indigo-50
                                    flex items-center justify-center
                                    stepcare-text">
                            <span class="text-xl">&#128338;</span>
                        </div>

                        <div>
                            <h2 class="text-xl font-bold text-gray-800">
                                Visit Attendance
                            </h2>

                            <p class="text-sm text-gray-500 mt-1">
                                Record the actual arrival and departure times.
                            </p>
                        </div>
                    </div>

                    <!-- Time cards -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                        <div class="rounded-xl border border-gray-200
                                    bg-gray-50 p-5">

                            <div class="flex items-center gap-2 mb-3">
                                <span class="text-green-600">&#8594;</span>

                                <p class="text-sm font-semibold text-gray-600">
                                    Check In
                                </p>
                            </div>

                            <?php if ($checkedIn): ?>
                                <p class="text-lg font-bold text-gray-800">
                                    <?= e(date(
                                        'h:i A',
                                        strtotime($appointment['checkInTime'])
                                    )) ?>
                                </p>

                                <p class="text-sm text-gray-500 mt-1">
                                    <?= e(date(
                                        'd M Y',
                                        strtotime($appointment['checkInTime'])
                                    )) ?>
                                </p>
                            <?php else: ?>
                                <p class="text-lg font-semibold text-gray-400">
                                    Not checked in
                                </p>

                                <p class="text-sm text-gray-500 mt-1">
                                    Waiting for nurse arrival
                                </p>
                            <?php endif; ?>
                        </div>

                        <div class="rounded-xl border border-gray-200
                                    bg-gray-50 p-5">

                            <div class="flex items-center gap-2 mb-3">
                                <span class="text-purple-600">&#8592;</span>

                                <p class="text-sm font-semibold text-gray-600">
                                    Check Out
                                </p>
                            </div>

                            <?php if ($checkedOut): ?>
                                <p class="text-lg font-bold text-gray-800">
                                    <?= e(date(
                                        'h:i A',
                                        strtotime($appointment['checkOutTime'])
                                    )) ?>
                                </p>

                                <p class="text-sm text-gray-500 mt-1">
                                    <?= e(date(
                                        'd M Y',
                                        strtotime($appointment['checkOutTime'])
                                    )) ?>
                                </p>
                            <?php else: ?>
                                <p class="text-lg font-semibold text-gray-400">
                                    Not checked out
                                </p>

                                <p class="text-sm text-gray-500 mt-1">
                                    Waiting for visit completion
                                </p>
                            <?php endif; ?>
                        </div>

                    </div>

                    <!-- Attendance buttons -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-5">

                        <form method="POST"
                              action="patient_details.php?id=<?= (int)$appointmentID ?>"
                              onsubmit="return confirm('Confirm check-in for this visit?');">

                            <input type="hidden"
                                   name="action"
                                   value="check_in">

                            <button
                                type="submit"
                                <?= !$canCheckIn ? 'disabled' : '' ?>
                                class="stepcare-button w-full rounded-xl
                                       py-3.5 px-5 text-white font-semibold
                                       disabled:bg-gray-300
                                       disabled:text-gray-500
                                       disabled:cursor-not-allowed
                                       disabled:transform-none">

                                <?= $checkedIn
                                    ? 'Checked In'
                                    : 'Check In' ?>
                            </button>
                        </form>

                        <form method="POST"
                              action="patient_details.php?id=<?= (int)$appointmentID ?>"
                              onsubmit="return confirm('Confirm check-out? Make sure the care stages and Care Review are complete.');">

                            <input type="hidden"
                                   name="action"
                                   value="check_out">

                            <button
                                type="submit"
                                <?= !$canCheckOut ? 'disabled' : '' ?>
                                class="w-full rounded-xl py-3.5 px-5
                                       font-semibold border
                                       transition
                                       disabled:bg-gray-100
                                       disabled:text-gray-400
                                       disabled:border-gray-200
                                       disabled:cursor-not-allowed
                                       <?= $canCheckOut
                                           ? 'bg-white text-purple-700 border-purple-200 hover:bg-purple-50'
                                           : '' ?>">

                                <?= $checkedOut
                                    ? 'Checked Out'
                                    : 'Check Out' ?>
                            </button>
                        </form>

                    </div>

                    <?php if (!$checkedIn && !$checkedOut): ?>
                        <p class="text-xs text-gray-500 mt-4">
                            Check in when you arrive at the patient's location.
                        </p>
                    <?php elseif ($checkedIn && !$checkedOut && !$canCheckOut): ?>
                        <p class="text-xs text-gray-500 mt-4">
                            Complete the care stages and Care Review to enable Check Out.
                        </p>
                    <?php elseif ($checkedOut): ?>
                        <div class="mt-4 rounded-lg bg-green-50
                                    border border-green-100 p-4">

                            <p class="text-sm font-semibold text-green-700">
                                Visit completed
                            </p>

                            <p class="text-sm text-green-700 mt-1">
                                Actual visit duration:
                                <?= e($visitDuration ?? 'Unavailable') ?>
                            </p>
                        </div>
                    <?php endif; ?>

                </section>

                <!-- Patient information -->
                <section class="bg-white rounded-2xl border border-gray-100
                                shadow-sm p-6 sm:p-8">

                    <h2 class="text-xl font-bold text-gray-800 mb-6">
                        Patient Information
                    </h2>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-6">

                        <div>
                            <p class="text-sm text-gray-500 mb-1">
                                Full Name
                            </p>

                            <p class="font-semibold text-gray-800">
                                <?= e(
                                    $appointment['patientFirstName'] .
                                    ' ' .
                                    $appointment['patientLastName']
                                ) ?>
                            </p>
                        </div>

                        <div>
                            <p class="text-sm text-gray-500 mb-1">
                                Phone Number
                            </p>

                            <p class="font-semibold text-gray-800 break-words">
                                <?= e(
                                    $appointment['patientPhone']
                                    ?: 'Not provided'
                                ) ?>
                            </p>
                        </div>

                        <div class="sm:col-span-2">
                            <p class="text-sm text-gray-500 mb-1">
                                Email Address
                            </p>

                            <p class="font-semibold text-gray-800 break-words">
                                <?= e(
                                    $appointment['patientEmail']
                                    ?: 'Not provided'
                                ) ?>
                            </p>
                        </div>

                        <div class="sm:col-span-2">
                            <p class="text-sm text-gray-500 mb-1">
                                Home Visit Address
                            </p>

                            <p class="font-semibold text-gray-800 leading-relaxed">
                                <?= e($appointment['locationAddress']) ?>
                            </p>
                        </div>

                        <div class="sm:col-span-2">
                            <p class="text-sm text-gray-500 mb-1">
                                Special Instructions
                            </p>

                            <p class="text-gray-700 leading-relaxed">
                                <?= e(
                                    $appointment['specialInstructions']
                                    ?: 'No special instructions provided.'
                                ) ?>
                            </p>
                        </div>

                    </div>
                </section>

                <!-- Service information -->
                <section class="bg-white rounded-2xl border border-gray-100
                                shadow-sm p-6 sm:p-8">

                    <h2 class="text-xl font-bold text-gray-800 mb-6">
                        Service Information
                    </h2>

                    <div class="space-y-5">

                        <div>
                            <p class="text-sm text-gray-500 mb-1">
                                Service Name
                            </p>

                            <p class="font-semibold text-gray-800">
                                <?= e($appointment['serviceName']) ?>
                            </p>
                        </div>

                        <div>
                            <p class="text-sm text-gray-500 mb-1">
                                Service Category
                            </p>

                            <p class="font-medium text-gray-800">
                                <?= e(
                                    $appointment['category']
                                    ?: 'General Nursing'
                                ) ?>
                            </p>
                        </div>

                        <div>
                            <p class="text-sm text-gray-500 mb-1">
                                Description
                            </p>

                            <p class="text-gray-700 leading-relaxed">
                                <?= e(
                                    $appointment['serviceDescription']
                                    ?: 'No service description available.'
                                ) ?>
                            </p>
                        </div>

                        <div>
                            <p class="text-sm text-gray-500 mb-1">
                                Assigned Nurse
                            </p>

                            <p class="font-medium text-gray-800">
                                <?php if ($appointment['nurseFirstName']): ?>
                                    <?= e(
                                        $appointment['nurseFirstName'] .
                                        ' ' .
                                        $appointment['nurseLastName']
                                    ) ?>
                                <?php else: ?>
                                    Nurse not assigned
                                <?php endif; ?>
                            </p>
                        </div>

                        <div>
                            <p class="text-sm text-gray-500 mb-1">
                                Scheduled Time
                            </p>

                            <p class="font-medium text-gray-800">
                                <?= e(date(
                                    'd M Y, h:i A',
                                    strtotime($appointment['startDateTime'])
                                )) ?>
                                -
                                <?= e(date(
                                    'h:i A',
                                    strtotime($appointment['endDateTime'])
                                )) ?>
                            </p>
                        </div>

                    </div>
                </section>

            </div>

            <!-- Right column: Care progress -->
            <aside class="lg:sticky lg:top-6">

                <section class="bg-white rounded-2xl border border-gray-100
                                shadow-sm p-6 sm:p-7">

                    <div class="mb-6">
                        <h2 class="text-xl font-bold text-gray-800">
                            Care Progress
                        </h2>

                        <p class="text-sm text-gray-500 mt-2">
                            Follow each stage of the nursing visit.
                        </p>
                    </div>

                    <!-- Progress percentage -->
                    <div class="mb-7">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-sm text-gray-500">
                                Overall progress
                            </span>

                            <span class="font-bold stepcare-text">
                                <?= (int)$progressPercent ?>%
                            </span>
                        </div>

                        <div class="h-2.5 rounded-full bg-gray-100 overflow-hidden">
                            <div
                                class="h-full stepcare-blue rounded-full transition-all duration-300"
                                style="width: <?= (int)$progressPercent ?>%;">
                            </div>
                        </div>

                        <p class="text-sm text-gray-500 mt-3">
                            Current stage:
                        </p>

                        <p class="font-semibold text-gray-800 mt-1">
                            <?= e($currentStage) ?>
                        </p>
                    </div>

                    <!-- Timeline -->
                    <div class="space-y-0">

                        <?php foreach ($stages as $index => $stage): ?>

                            <?php
                            $stageDone = isset($completedStages[$index]);
                            $stageCurrent = ($index === $currentIndex);

                            if ($stageDone) {
                                $circleClass = 'bg-green-500 text-white';
                            } elseif ($stageCurrent) {
                                $circleClass = 'stepcare-blue text-white';
                            } else {
                                $circleClass = 'bg-gray-100 text-gray-400';
                            }
                            ?>

                            <div class="timeline-item relative flex gap-3 pb-6">

                                <div class="timeline-line"></div>

                                <div class="relative z-10 flex-shrink-0
                                            w-8 h-8 rounded-full
                                            flex items-center justify-center
                                            text-xs font-bold
                                            <?= $circleClass ?>">

                                    <?php if ($stageDone): ?>
                                        &#10003;
                                    <?php else: ?>
                                        <?= (int)($index + 1) ?>
                                    <?php endif; ?>

                                </div>

                                <div class="flex-1 pt-1 min-w-0">

                                    <p class="text-sm font-semibold
                                        <?= $stageCurrent
                                            ? 'stepcare-text'
                                            : 'text-gray-800' ?>">
                                        <?= e($stage) ?>
                                    </p>

                                    <?php if ($stageDone): ?>

                                        <p class="text-xs text-green-600 mt-1">
                                            Completed
                                        </p>

                                        <p class="text-xs text-gray-400 mt-1">
                                            <?= e(date(
                                                'd M, h:i A',
                                                strtotime(
                                                    $completedStages[$index]['createdAt']
                                                )
                                            )) ?>
                                        </p>

                                    <?php elseif ($stageCurrent): ?>

                                        <p class="text-xs stepcare-text mt-1">
                                            Current stage
                                        </p>

                                    <?php else: ?>

                                        <p class="text-xs text-gray-400 mt-1">
                                            Not started
                                        </p>

                                    <?php endif; ?>

                                </div>
                            </div>

                        <?php endforeach; ?>

                    </div>

                    <!-- Next stage button -->
                    <form method="POST"
                          action="patient_details.php?id=<?= (int)$appointmentID ?>"
                          onsubmit="return confirm('Confirm that this care stage has been completed?');">

                        <input type="hidden"
                               name="action"
                               value="next_stage">

                        <button
                            type="submit"
                            <?= !$canUpdateStages ? 'disabled' : '' ?>
                            class="stepcare-button w-full rounded-xl
                                   py-3.5 px-4 text-white font-semibold
                                   disabled:bg-gray-300
                                   disabled:text-gray-500
                                   disabled:cursor-not-allowed
                                   disabled:transform-none">

                            <?php if ($checkedOut): ?>
                                Visit Completed
                            <?php elseif (!$checkedIn): ?>
                                Check In to Begin
                            <?php elseif ($currentIndex >= $careReviewIndex): ?>
                                Care Review Completed
                            <?php else: ?>
                                Confirm Next Stage
                            <?php endif; ?>

                        </button>

                    </form>

                    <?php if (!$checkedIn): ?>

                        <p class="text-xs text-gray-500 text-center mt-3">
                            Check in before updating care progress.
                        </p>

                    <?php elseif ($currentIndex >= $careReviewIndex && !$checkedOut): ?>

                        <p class="text-xs text-gray-500 text-center mt-3">
                            Care Review is complete. You can check out now.
                        </p>

                    <?php elseif ($checkedOut): ?>

                        <p class="text-xs text-green-600 text-center mt-3">
                            All visit stages are complete.
                        </p>

                    <?php endif; ?>

                </section>

            </aside>

        </div>

    </main>

    <!-- Footer -->
    <footer class="stepcare-blue text-white mt-10">
        <div class="max-w-7xl mx-auto px-5 sm:px-8 py-7 text-center">

            <p class="text-xl font-bold">
                StepCare
            </p>

            <p class="text-sm text-blue-100 mt-2">
                Compassionate nursing care delivered to your home.
            </p>

            <p class="text-xs text-blue-200 mt-3">
                &copy; <?= date('Y') ?> StepCare. All rights reserved.
            </p>

        </div>
    </footer>

</body>
</html>
