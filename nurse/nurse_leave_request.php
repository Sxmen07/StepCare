
<?php
require_once 'db_connect.php';

session_start();

// Temporary nurse ID until the login system is connected
$nurseID = 1;

$message = '';
$messageType = '';

// Get nurse information
$stmt = $pdo->prepare("
    SELECT nurseID, firstName, lastName, nursePhotoURL
    FROM nurses
    WHERE nurseID = :nurseID
");
$stmt->execute(['nurseID' => $nurseID]);
$nurse = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$nurse) {
    die("Nurse information could not be loaded.");
}

// Handle leave request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $leaveType = trim($_POST['leaveType'] ?? '');
    $startDate = $_POST['startDate'] ?? '';
    $endDate = $_POST['endDate'] ?? '';
    $reason = trim($_POST['reason'] ?? '');

    $allowedLeaveTypes = [
        'Annual Leave',
        'Medical Leave',
        'Personal Leave',
        'Emergency Leave',
        'Unpaid Leave'
    ];

    if (
        !in_array($leaveType, $allowedLeaveTypes, true) ||
        empty($startDate) ||
        empty($endDate) ||
        empty($reason)
    ) {
        $message = 'Please complete all required fields.';
        $messageType = 'error';
    } elseif (
        !DateTime::createFromFormat('!Y-m-d', $startDate) ||
        !DateTime::createFromFormat('!Y-m-d', $endDate) ||
        $startDate > $endDate
    ) {
        $message = 'Please select valid leave dates.';
        $messageType = 'error';
    } elseif ($startDate < date('Y-m-d')) {
        $message = 'The leave start date cannot be in the past.';
        $messageType = 'error';
    } else {
        try {
            // Check for overlapping pending or approved requests
            $checkStmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM nurserequestoffday
                WHERE nurseID = :nurseID
                  AND status IN ('pending', 'approved')
                  AND startTime <= :endDate
                  AND endTime >= :startDate
            ");

            $checkStmt->execute([
                'nurseID' => $nurseID,
                'startDate' => $startDate . ' 00:00:00',
                'endDate' => $endDate . ' 23:59:59'
            ]);

            $overlapCount = $checkStmt->fetchColumn();

            if ($overlapCount > 0) {
                $message = 'You already have a pending or approved leave request for these dates.';
                $messageType = 'error';
            } else {
                // Save the new leave request
                $insertStmt = $pdo->prepare("
                    INSERT INTO nurserequestoffday
                    (
                        nurseID,
                        startTime,
                        endTime,
                        requestDate,
                        status,
                        reason,
                        leaveType
                    )
                    VALUES
                    (
                        :nurseID,
                        :startTime,
                        :endTime,
                        :requestDate,
                        'pending',
                        :reason,
                        :leaveType
                    )
                ");

                $insertStmt->execute([
                    'nurseID' => $nurseID,
                    'startTime' => $startDate . ' 00:00:00',
                    'endTime' => $endDate . ' 23:59:59',
                    'requestDate' => date('Y-m-d'),
                    'reason' => $reason,
                    'leaveType' => $leaveType
                ]);

                $message = 'Your leave request has been submitted successfully.';
                $messageType = 'success';
            }
        } catch (PDOException $e) {
            $message = 'Unable to submit your request. Please try again.';
            $messageType = 'error';
        }
    }
}

// Get leave request history
$historyStmt = $pdo->prepare("
    SELECT
        requestID,
        leaveType,
        startTime,
        endTime,
        requestDate,
        status,
        reason
    FROM nurserequestoffday
    WHERE nurseID = :nurseID
    ORDER BY createdAt DESC
");

$historyStmt->execute(['nurseID' => $nurseID]);
$leaveRequests = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

function escapeHtml($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function formatDate($date) {
    return date('d M Y', strtotime($date));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Request | StepCare</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8fafc;
        }

        .stepcare-blue {
            background-color: #3C52C3;
        }

        .stepcare-text {
            color: #3C52C3;
        }

        .primary-button {
            background-color: #3C52C3;
            transition: background-color 0.2s ease;
        }

        .primary-button:hover {
            background-color: #2f419f;
        }
    </style>
</head>

<body class="min-h-screen text-gray-800">

    <!-- Navigation -->
    <header class="bg-white shadow-sm">
        <div class="max-w-7xl mx-auto px-6 py-4 flex flex-wrap items-center justify-between gap-4">
            <a href="nurse_dashboard.php"
               class="text-2xl font-bold stepcare-text">
                StepCare
            </a>

            <nav class="flex flex-wrap items-center gap-6 text-sm font-medium">
                <a href="nurse_dashboard.php"
                   class="text-gray-600 hover:text-indigo-700">
                    Dashboard
                </a>

                <a href="nurse_availability.php"
                   class="text-gray-600 hover:text-indigo-700">
                    Availability
                </a>

                <a href="nurse_leave_request.php"
                   class="stepcare-text font-semibold">
                    Leave Request
                </a>
            </nav>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-6 py-10">

        <!-- Page heading -->
        <div class="mb-8">
            <p class="text-sm stepcare-text font-semibold mb-2">
                NURSE MANAGEMENT
            </p>

            <h1 class="text-3xl font-bold text-gray-900">
                Leave Request
            </h1>

            <p class="text-gray-500 mt-2">
                Submit a leave request and keep track of its approval status.
            </p>
        </div>

        <!-- Nurse information -->
        <section class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-8">
            <div class="flex items-center gap-4">
                <?php if (!empty($nurse['nursePhotoURL'])): ?>
                    <img
                        src="<?= escapeHtml($nurse['nursePhotoURL']) ?>"
                        alt="Nurse profile"
                        class="w-16 h-16 rounded-full object-cover"
                    >
                <?php else: ?>
                    <div class="w-16 h-16 rounded-full stepcare-blue text-white flex items-center justify-center text-xl font-bold">
                        <?= escapeHtml(
                            strtoupper(
                                substr($nurse['firstName'], 0, 1) .
                                substr($nurse['lastName'], 0, 1)
                            )
                        ) ?>
                    </div>
                <?php endif; ?>

                <div>
                    <h2 class="text-xl font-semibold">
                        <?= escapeHtml($nurse['firstName'] . ' ' . $nurse['lastName']) ?>
                    </h2>

                    <p class="text-sm text-gray-500">
                        Nurse
                    </p>
                </div>
            </div>
        </section>

        <!-- Feedback message -->
        <?php if ($message !== ''): ?>
            <div class="mb-6 rounded-lg p-4 border
                <?= $messageType === 'success'
                    ? 'bg-green-50 border-green-200 text-green-700'
                    : 'bg-red-50 border-red-200 text-red-700' ?>">
                <?= escapeHtml($message) ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            <!-- Leave request form -->
            <section class="lg:col-span-1">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <h2 class="text-xl font-bold mb-2">
                        Submit a Request
                    </h2>

                    <p class="text-sm text-gray-500 mb-6">
                        Fill in the details below to request leave.
                    </p>

                    <form method="POST" action="" class="space-y-5">

                        <div>
                            <label for="leaveType"
                                   class="block text-sm font-medium mb-2">
                                Leave Type
                            </label>

                            <select
                                id="leaveType"
                                name="leaveType"
                                required
                                class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-indigo-300"
                            >
                                <option value="">Select leave type</option>
                                <?php
                                $leaveTypes = [
                                    'Annual Leave',
                                    'Medical Leave',
                                    'Personal Leave',
                                    'Emergency Leave',
                                    'Unpaid Leave'
                                ];

                                foreach ($leaveTypes as $type):
                                ?>
                                    <option
                                        value="<?= escapeHtml($type) ?>"
                                        <?= (($_POST['leaveType'] ?? '') === $type)
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        <?= escapeHtml($type) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label for="startDate"
                                   class="block text-sm font-medium mb-2">
                                Start Date
                            </label>

                            <input
                                type="date"
                                id="startDate"
                                name="startDate"
                                min="<?= date('Y-m-d') ?>"
                                value="<?= escapeHtml($_POST['startDate'] ?? '') ?>"
                                required
                                class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-indigo-300"
                            >
                        </div>

                        <div>
                            <label for="endDate"
                                   class="block text-sm font-medium mb-2">
                                End Date
                            </label>

                            <input
                                type="date"
                                id="endDate"
                                name="endDate"
                                min="<?= date('Y-m-d') ?>"
                                value="<?= escapeHtml($_POST['endDate'] ?? '') ?>"
                                required
                                class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-indigo-300"
                            >
                        </div>

                        <div>
                            <label for="reason"
                                   class="block text-sm font-medium mb-2">
                                Reason for Leave
                            </label>

                            <textarea
                                id="reason"
                                name="reason"
                                rows="4"
                                required
                                placeholder="Enter your reason for requesting leave..."
                                class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-indigo-300"
                            ><?= escapeHtml($_POST['reason'] ?? '') ?></textarea>
                        </div>

                        <button
                            type="submit"
                            class="primary-button w-full text-white font-semibold py-3 rounded-lg"
                        >
                            Submit Leave Request
                        </button>

                    </form>
                </div>
            </section>

            <!-- Leave request history -->
            <section class="lg:col-span-2">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">

                    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
                        <div>
                            <h2 class="text-xl font-bold">
                                Leave Request History
                            </h2>

                            <p class="text-sm text-gray-500 mt-1">
                                View your submitted leave requests and their statuses.
                            </p>
                        </div>

                        <span class="text-sm bg-indigo-50 stepcare-text px-3 py-2 rounded-full font-medium">
                            <?= count($leaveRequests) ?> request(s)
                        </span>
                    </div>

                    <?php if (empty($leaveRequests)): ?>

                        <div class="text-center py-12">
                            <div class="text-4xl mb-3">📅</div>

                            <h3 class="font-semibold text-gray-800">
                                No Leave Requests Yet
                            </h3>

                            <p class="text-sm text-gray-500 mt-2">
                                Your submitted leave requests will appear here.
                            </p>
                        </div>

                    <?php else: ?>

                        <div class="space-y-4">
                            <?php foreach ($leaveRequests as $request): ?>

                                <?php
                                $status = strtolower($request['status']);

                                $statusClass = 'bg-yellow-100 text-yellow-800';

                                if ($status === 'approved') {
                                    $statusClass = 'bg-green-100 text-green-800';
                                } elseif ($status === 'rejected') {
                                    $statusClass = 'bg-red-100 text-red-800';
                                }
                                ?>

                                <article class="border border-gray-200 rounded-xl p-5">

                                    <div class="flex flex-wrap justify-between items-start gap-3 mb-4">
                                        <div>
                                            <h3 class="font-semibold text-lg">
                                                <?= escapeHtml($request['leaveType']) ?>
                                            </h3>

                                            <p class="text-sm text-gray-500 mt-1">
                                                Submitted on
                                                <?= formatDate($request['requestDate']) ?>
                                            </p>
                                        </div>

                                        <span class="text-xs font-semibold px-3 py-1.5 rounded-full <?= $statusClass ?>">
                                            <?= escapeHtml(ucfirst($status)) ?>
                                        </span>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                                        <div>
                                            <p class="text-xs text-gray-500 mb-1">
                                                Start Date
                                            </p>

                                            <p class="font-medium">
                                                <?= formatDate($request['startTime']) ?>
                                            </p>
                                        </div>

                                        <div>
                                            <p class="text-xs text-gray-500 mb-1">
                                                End Date
                                            </p>

                                            <p class="font-medium">
                                                <?= formatDate($request['endTime']) ?>
                                            </p>
                                        </div>
                                    </div>

                                    <div class="border-t border-gray-100 pt-4">
                                        <p class="text-xs text-gray-500 mb-1">
                                            Reason
                                        </p>

                                        <p class="text-sm text-gray-700 whitespace-pre-line">
                                            <?= escapeHtml($request['reason'] ?? 'No reason provided.') ?>
                                        </p>
                                    </div>

                                </article>

                            <?php endforeach; ?>
                        </div>

                    <?php endif; ?>

                </div>
            </section>

        </div>
    </main>

    <!-- Footer -->
    <footer class="mt-12 stepcare-blue text-white px-6 py-6">
        <div class="max-w-7xl mx-auto flex flex-col sm:flex-row justify-between gap-3 text-sm">
            <p>&copy; <?= date('Y') ?> StepCare. All rights reserved.</p>
            <p>Compassionate care, right at home.</p>
        </div>
    </footer>

    <script>
        const startInput = document.getElementById('startDate');
        const endInput = document.getElementById('endDate');

        startInput.addEventListener('change', function () {
            endInput.min = this.value;

            if (endInput.value && endInput.value < this.value) {
                endInput.value = this.value;
            }
        });
    </script>

</body>
</html>
