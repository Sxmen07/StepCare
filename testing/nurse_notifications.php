
<?php
session_start();
require_once 'db_connect.php';

// Temporary nurse ID for testing without login.
$nurseID = 1;

// Escape output safely.
function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Get the selected month.
$monthInput = $_GET['month'] ?? date('Y-m');
$monthDate = DateTime::createFromFormat('!Y-m-d', $monthInput . '-01');

if (!$monthDate || $monthDate->format('Y-m') !== $monthInput) {
    $monthDate = new DateTime('first day of this month');
}

$monthStart = $monthDate->format('Y-m-01');
$nextMonth = (clone $monthDate)->modify('+1 month');
$monthEnd = $nextMonth->format('Y-m-d');

$previousMonth = (clone $monthDate)->modify('-1 month')->format('Y-m');
$nextMonthString = $nextMonth->format('Y-m');

// Get selected date.
$today = date('Y-m-d');
$selectedDate = $_GET['date'] ?? $today;

$dateObject = DateTime::createFromFormat('!Y-m-d', $selectedDate);

if (!$dateObject || $dateObject->format('Y-m-d') !== $selectedDate) {
    $selectedDate = $today;
}

if (substr($selectedDate, 0, 7) !== $monthDate->format('Y-m')) {
    $selectedDate = $monthStart;
}

// Mark notification as read.
if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['notificationID'])) {

    $notificationID = filter_var(
        $_POST['notificationID'],
        FILTER_VALIDATE_INT
    );

    if ($notificationID && $notificationID > 0) {
        $update = $pdo->prepare("
            UPDATE notifications
            SET isRead = 1
            WHERE notificationID = :notificationID
              AND recipientType = 'nurse'
              AND recipientID = :nurseID
        ");

        $update->execute([
            'notificationID' => $notificationID,
            'nurseID' => $nurseID
        ]);
    }

    header(
        'Location: nurse_notifications.php?month=' .
        urlencode($monthDate->format('Y-m')) .
        '&date=' . urlencode($selectedDate) .
        '&notification_page=' .
        max(1, (int)($_GET['notification_page'] ?? 1))
    );
    exit;
}

// Get appointments for the selected month.
$appointmentQuery = $pdo->prepare("
    SELECT
        a.appointmentID,
        a.serviceID,
        a.userID,
        a.startDateTime,
        a.endDateTime,
        a.locationAddress,
        a.appointmentStatus,
        s.serviceName
    FROM appointments a
    LEFT JOIN services s ON s.serviceID = a.serviceID
    WHERE a.nurseID = :nurseID
      AND a.startDateTime >= :monthStart
      AND a.startDateTime < :monthEnd
    ORDER BY a.startDateTime ASC
");

$appointmentQuery->execute([
    'nurseID' => $nurseID,
    'monthStart' => $monthStart,
    'monthEnd' => $monthEnd
]);

$monthAppointments = $appointmentQuery->fetchAll(PDO::FETCH_ASSOC);

// Group appointments by date.
$appointmentsByDate = [];

foreach ($monthAppointments as $appointment) {
    $appointmentDate = date(
        'Y-m-d',
        strtotime($appointment['startDateTime'])
    );

    $appointmentsByDate[$appointmentDate][] = $appointment;
}

// Appointments on the selected date.
$dailyAppointments = $appointmentsByDate[$selectedDate] ?? [];

// Count upcoming appointments.
$upcomingQuery = $pdo->prepare("
    SELECT COUNT(*)
    FROM appointments
    WHERE nurseID = :nurseID
      AND startDateTime >= NOW()
      AND appointmentStatus NOT IN
          ('cancelled', 'declined', 'completed')
");

$upcomingQuery->execute(['nurseID' => $nurseID]);
$upcomingCount = (int) $upcomingQuery->fetchColumn();

// Count appointments today.
$todayQuery = $pdo->prepare("
    SELECT COUNT(*)
    FROM appointments
    WHERE nurseID = :nurseID
      AND startDateTime >= CURDATE()
      AND startDateTime < CURDATE() + INTERVAL 1 DAY
      AND appointmentStatus NOT IN
          ('cancelled', 'declined', 'completed')
");

$todayQuery->execute(['nurseID' => $nurseID]);
$todayCount = (int) $todayQuery->fetchColumn();

// Count unread notifications.
$unreadQuery = $pdo->prepare("
    SELECT COUNT(*)
    FROM notifications
    WHERE recipientType = 'nurse'
      AND recipientID = :nurseID
      AND isRead = 0
");

$unreadQuery->execute(['nurseID' => $nurseID]);
$unreadCount = (int) $unreadQuery->fetchColumn();

// Notification pagination.
$perPage = 5;
$notificationPage = max(1, (int)($_GET['notification_page'] ?? 1));

// Count all notifications.
$countNotifications = $pdo->prepare("
    SELECT COUNT(*)
    FROM notifications
    WHERE recipientType = 'nurse'
      AND recipientID = :nurseID
");

$countNotifications->execute(['nurseID' => $nurseID]);

$totalNotifications = (int) $countNotifications->fetchColumn();

$totalNotificationPages = max(
    1,
    (int) ceil($totalNotifications / $perPage)
);

$notificationPage = min(
    $notificationPage,
    $totalNotificationPages
);

$notificationOffset = ($notificationPage - 1) * $perPage;

// Get notifications for the current page.
$notificationQuery = $pdo->prepare("
    SELECT
        notificationID,
        title,
        message,
        type,
        linkURL,
        isRead,
        createdAt
    FROM notifications
    WHERE recipientType = 'nurse'
      AND recipientID = :nurseID
    ORDER BY createdAt DESC
    LIMIT :limit OFFSET :offset
");

$notificationQuery->bindValue(':nurseID', $nurseID, PDO::PARAM_INT);
$notificationQuery->bindValue(':limit', $perPage, PDO::PARAM_INT);
$notificationQuery->bindValue(':offset', $notificationOffset, PDO::PARAM_INT);

$notificationQuery->execute();
$notifications = $notificationQuery->fetchAll(PDO::FETCH_ASSOC);

// Build calendar cells, starting Monday.
$daysInMonth = (int) $monthDate->format('t');
$firstDayOffset = (int) $monthDate->format('N') - 1;
$calendarCells = [];

for ($i = 0; $i < $firstDayOffset; $i++) {
    $calendarCells[] = null;
}

for ($day = 1; $day <= $daysInMonth; $day++) {
    $calendarCells[] = $day;
}

while (count($calendarCells) % 7 !== 0) {
    $calendarCells[] = null;
}

$monthLabel = $monthDate->format('F Y');

// Notification page URL helper.
function notificationPageUrl($page, $month, $date) {
    return '?' . http_build_query([
        'month' => $month,
        'date' => $date,
        'notification_page' => $page
    ]);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Nurse Notifications | StepCare</title>

    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="min-h-screen bg-gray-50 text-gray-800">

<!-- HEADER -->
<header class="bg-[#3C52C3] text-white shadow">
    <div class="max-w-7xl mx-auto px-5 py-4
                flex flex-wrap items-center justify-between gap-4">

        <div>
            <h1 class="text-2xl font-bold">StepCare</h1>
            <p class="text-xs text-blue-100 mt-1">
                Nurse Schedule & Notifications
            </p>
        </div>

        <nav class="flex flex-wrap gap-4 text-sm">
            <a href="nurse_dashboard.php"
               class="hover:text-blue-200 transition">
                Dashboard
            </a>

            <a href="nurse_availability.php"
               class="hover:text-blue-200 transition">
                Availability
            </a>

            <a href="nurse_leave_request.php"
               class="hover:text-blue-200 transition">
                Leave Requests
            </a>
        </nav>
    </div>
</header>

<main class="max-w-7xl mx-auto px-5 py-6">

    <!-- PAGE TITLE -->
    <div class="mb-5">
        <h2 class="text-2xl md:text-3xl font-bold">
            My Notifications
        </h2>

        <p class="text-gray-500 text-sm mt-1">
            View assigned visits and stay updated on your schedule.
        </p>
    </div>

    <!-- SUMMARY CARDS -->
    <section class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-6">

        <div class="bg-white border border-gray-100 rounded-xl
                    p-4 shadow-sm">
            <p class="text-sm text-gray-500">Unread Notifications</p>
            <p class="text-2xl font-bold mt-1"><?= $unreadCount ?></p>
            <p class="text-xs text-gray-400 mt-1">
                Awaiting your attention
            </p>
        </div>

        <div class="bg-white border border-gray-100 rounded-xl
                    p-4 shadow-sm">
            <p class="text-sm text-gray-500">Upcoming Appointments</p>
            <p class="text-2xl font-bold mt-1"><?= $upcomingCount ?></p>
            <p class="text-xs text-gray-400 mt-1">
                Not cancelled or completed
            </p>
        </div>

        <div class="bg-white border border-gray-100 rounded-xl
                    p-4 shadow-sm">
            <p class="text-sm text-gray-500">Appointments Today</p>
            <p class="text-2xl font-bold mt-1"><?= $todayCount ?></p>
            <p class="text-xs text-gray-400 mt-1">
                Scheduled for today
            </p>
        </div>

    </section>

    <!-- CALENDAR AND DAILY SCHEDULE -->
    <section class="grid grid-cols-1 lg:grid-cols-5 gap-4 mb-7">

        <!-- SMALLER CALENDAR -->
        <div class="lg:col-span-3 bg-white rounded-xl border
                    border-gray-100 shadow-sm p-4">

            <div class="flex items-center justify-between gap-3 mb-3">

                <a href="?month=<?= e($previousMonth) ?>&date=<?= e($previousMonth . '-01') ?>&notification_page=<?= $notificationPage ?>"
                   class="border rounded-lg px-3 py-1.5
                          hover:bg-gray-50 transition"
                   aria-label="Previous month">
                    &larr;
                </a>

                <h3 class="text-lg font-bold"><?= e($monthLabel) ?></h3>

                <a href="?month=<?= e($nextMonthString) ?>&date=<?= e($nextMonthString . '-01') ?>&notification_page=<?= $notificationPage ?>"
                   class="border rounded-lg px-3 py-1.5
                          hover:bg-gray-50 transition"
                   aria-label="Next month">
                    &rarr;
                </a>

            </div>

            <div class="grid grid-cols-7 gap-1 text-center mb-1">
                <?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $weekday): ?>
                    <div class="text-xs font-semibold text-gray-500 py-1">
                        <?= e($weekday) ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="grid grid-cols-7 gap-1">

                <?php foreach ($calendarCells as $day): ?>

                    <?php if ($day === null): ?>

                        <div class="h-10 sm:h-12"></div>

                    <?php else: ?>

                        <?php
                        $cellDate = $monthDate->format('Y-m-') .
                            str_pad((string)$day, 2, '0', STR_PAD_LEFT);

                        $hasAppointments = !empty($appointmentsByDate[$cellDate]);
                        $isSelected = $cellDate === $selectedDate;
                        $isToday = $cellDate === $today;
                        ?>

                        <a href="?month=<?= e($monthDate->format('Y-m')) ?>&date=<?= e($cellDate) ?>&notification_page=<?= $notificationPage ?>"
                           class="h-10 sm:h-12 rounded-lg border
                                  flex flex-col items-center justify-center
                                  gap-0.5 transition
                           <?= $isSelected
                               ? 'bg-[#3C52C3] text-white border-[#3C52C3]'
                               : 'bg-white border-gray-100 hover:bg-blue-50' ?>
                           <?= $isToday && !$isSelected
                               ? 'ring-1 ring-blue-300'
                               : '' ?>">

                            <span class="text-xs sm:text-sm font-medium">
                                <?= $day ?>
                            </span>

                            <?php if ($hasAppointments): ?>
                                <span class="w-1.5 h-1.5 rounded-full
                                    <?= $isSelected ? 'bg-white' : 'bg-[#3C52C3]' ?>">
                                </span>
                            <?php endif; ?>

                        </a>

                    <?php endif; ?>

                <?php endforeach; ?>

            </div>

            <div class="flex items-center gap-2 text-xs
                        text-gray-500 mt-3 pt-3 border-t border-gray-100">
                <span class="w-2 h-2 rounded-full bg-[#3C52C3]"></span>
                Dates with assigned appointments
            </div>

        </div>

        <!-- DAILY SCHEDULE -->
        <div class="lg:col-span-2 bg-white rounded-xl border
                    border-gray-100 shadow-sm p-4">

            <h3 class="text-lg font-bold">Daily Schedule</h3>

            <p class="text-xs text-gray-500 mt-1 mb-4">
                <?= e(date('l, d F Y', strtotime($selectedDate))) ?>
            </p>

            <?php if (empty($dailyAppointments)): ?>

                <div class="rounded-xl bg-gray-50 p-5 text-center">
                    <div class="text-2xl mb-2">📅</div>

                    <p class="font-semibold text-sm">
                        No assigned visits
                    </p>

                    <p class="text-xs text-gray-500 mt-1">
                        No appointments are assigned to this nurse on this date.
                    </p>
                </div>

            <?php else: ?>

                <div class="space-y-3">

                    <?php foreach ($dailyAppointments as $appointment): ?>

                        <div class="border border-gray-100 rounded-xl p-3">

                            <div class="flex items-start gap-3">

                                <div class="bg-blue-50 text-[#3C52C3]
                                            rounded-lg px-2 py-2 text-center
                                            min-w-[76px]">
                                    <p class="text-xs font-bold">
                                        <?= e(date(
                                            'h:i A',
                                            strtotime($appointment['startDateTime'])
                                        )) ?>
                                    </p>

                                    <p class="text-[10px] text-gray-500 mt-1">
                                        <?= e(date(
                                            'h:i A',
                                            strtotime($appointment['endDateTime'])
                                        )) ?>
                                    </p>
                                </div>

                                <div class="min-w-0 flex-1">

                                    <h4 class="text-sm font-semibold">
                                        <?= e($appointment['serviceName'] ?? 'Service') ?>
                                    </h4>

                                    <p class="text-xs text-gray-500 mt-1">
                                        Appointment #<?= (int)$appointment['appointmentID'] ?>
                                    </p>

                                    <p class="text-xs text-gray-500 mt-1 break-words">
                                        <?= e($appointment['locationAddress']) ?>
                                    </p>

                                    <span class="inline-block mt-2 rounded-full px-2 py-1
                                                 text-xs font-medium
                                        <?= $appointment['appointmentStatus'] === 'confirmed'
                                            ? 'bg-green-100 text-green-700'
                                            : 'bg-blue-100 text-blue-700' ?>">
                                        <?= e(ucwords(str_replace(
                                            '_',
                                            ' ',
                                            $appointment['appointmentStatus']
                                        ))) ?>
                                    </span>

                                </div>
                            </div>
                        </div>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </div>

    </section>

    <!-- NOTIFICATION HISTORY -->
    <section>

        <div class="flex flex-col sm:flex-row
                    sm:items-center sm:justify-between gap-2 mb-4">

            <div>
                <h3 class="text-xl font-bold">Notification History</h3>

                <p class="text-sm text-gray-500 mt-1">
                    View and manage your latest updates.
                </p>
            </div>

            <p class="text-xs text-gray-500">
                <?= $totalNotifications ?> total notifications
            </p>

        </div>

        <?php if (empty($notifications)): ?>

            <div class="bg-white rounded-xl border border-gray-100
                        shadow-sm p-8 text-center">

                <div class="text-3xl mb-3">🔔</div>

                <h4 class="font-semibold">
                    No notifications yet
                </h4>

                <p class="text-sm text-gray-500 mt-2">
                    New notifications will appear here when saved in the database.
                </p>

            </div>

        <?php else: ?>

            <!-- Notification cards -->
            <div class="space-y-3">

                <?php foreach ($notifications as $notification): ?>

                    <article class="bg-white rounded-xl border
                        <?= !$notification['isRead']
                            ? 'border-blue-200'
                            : 'border-gray-100' ?>
                        shadow-sm p-4">

                        <div class="flex flex-col sm:flex-row
                                    sm:items-start sm:justify-between gap-3">

                            <div class="flex gap-3 min-w-0">

                                <div class="shrink-0 rounded-xl p-2.5
                                    <?= !$notification['isRead']
                                        ? 'bg-blue-50 text-[#3C52C3]'
                                        : 'bg-gray-100 text-gray-500' ?>">
                                    🔔
                                </div>

                                <div class="min-w-0">

                                    <div class="flex flex-wrap items-center gap-2">

                                        <h4 class="font-bold text-sm sm:text-base">
                                            <?= e($notification['title']) ?>
                                        </h4>

                                        <?php if (!$notification['isRead']): ?>
                                            <span class="bg-blue-100 text-blue-700
                                                         text-xs px-2 py-0.5 rounded-full">
                                                New
                                            </span>
                                        <?php endif; ?>

                                    </div>

                                    <p class="text-gray-600 text-sm mt-1.5">
                                        <?= nl2br(e($notification['message'])) ?>
                                    </p>

                                    <p class="text-xs text-gray-400 mt-2">
                                        <?= e(date(
                                            'd M Y, h:i A',
                                            strtotime($notification['createdAt'])
                                        )) ?>
                                    </p>

                                    <?php if (!empty($notification['linkURL'])): ?>
                                        <a href="<?= e($notification['linkURL']) ?>"
                                           class="inline-block mt-2 text-sm
                                                  text-[#3C52C3] font-semibold
                                                  hover:underline">
                                            View Details →
                                        </a>
                                    <?php endif; ?>

                                </div>
                            </div>

                            <?php if (!$notification['isRead']): ?>

                                <form method="POST" class="shrink-0">

                                    <input type="hidden"
                                           name="notificationID"
                                           value="<?= (int)$notification['notificationID'] ?>">

                                    <button type="submit"
                                        class="text-xs sm:text-sm bg-[#3C52C3]
                                               text-white px-3 py-2 rounded-lg
                                               hover:bg-blue-800 transition">
                                        Mark as Read
                                    </button>

                                </form>

                            <?php else: ?>

                                <span class="text-xs text-green-700 shrink-0">
                                    Read
                                </span>

                            <?php endif; ?>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

            <!-- PAGINATION -->
            <?php
            $notificationStart = $totalNotifications > 0
                ? $notificationOffset + 1
                : 0;

            $notificationEnd = min(
                $notificationOffset + $perPage,
                $totalNotifications
            );

            $startPage = max(1, $notificationPage - 2);
            $endPage = min(
                $totalNotificationPages,
                $notificationPage + 2
            );
            ?>

            <div class="mt-5 flex flex-col sm:flex-row
                        items-center justify-between gap-3">

                <p class="text-sm text-gray-500">
                    Showing <?= $notificationStart ?>–<?= $notificationEnd ?>
                    of <?= $totalNotifications ?> notifications
                </p>

                <div class="flex flex-wrap items-center justify-center gap-2">

                    <!-- Previous -->
                    <?php if ($notificationPage > 1): ?>

                        <a href="<?= e(notificationPageUrl(
                            $notificationPage - 1,
                            $monthDate->format('Y-m'),
                            $selectedDate
                        )) ?>"
                           class="rounded-lg border border-gray-300 bg-white
                                  px-3 py-2 text-sm hover:bg-gray-100 transition">
                            &larr; Previous
                        </a>

                    <?php else: ?>

                        <span class="rounded-lg border border-gray-200
                                     bg-gray-100 px-3 py-2 text-sm text-gray-400">
                            &larr; Previous
                        </span>

                    <?php endif; ?>

                    <!-- Page numbers -->
                    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>

                        <a href="<?= e(notificationPageUrl(
                            $i,
                            $monthDate->format('Y-m'),
                            $selectedDate
                        )) ?>"
                           class="h-9 min-w-9 px-3 rounded-lg
                                  flex items-center justify-center
                                  text-sm font-medium transition
                           <?= $i === $notificationPage
                               ? 'bg-[#3C52C3] text-white'
                               : 'bg-white border border-gray-300 hover:bg-gray-100' ?>">
                            <?= $i ?>
                        </a>

                    <?php endfor; ?>

                    <!-- Next -->
                    <?php if ($notificationPage < $totalNotificationPages): ?>

                        <a href="<?= e(notificationPageUrl(
                            $notificationPage + 1,
                            $monthDate->format('Y-m'),
                            $selectedDate
                        )) ?>"
                           class="rounded-lg border border-gray-300 bg-white
                                  px-3 py-2 text-sm hover:bg-gray-100 transition">
                            Next &rarr;
                        </a>

                    <?php else: ?>

                        <span class="rounded-lg border border-gray-200
                                     bg-gray-100 px-3 py-2 text-sm text-gray-400">
                            Next &rarr;
                        </span>

                    <?php endif; ?>

                </div>

            </div>

        <?php endif; ?>

    </section>

</main>

<!-- FOOTER -->
<footer class="mt-8 bg-white border-t border-gray-200">
    <div class="max-w-7xl mx-auto px-5 py-4
                text-center text-sm text-gray-500">
        &copy; <?= date('Y') ?> StepCare. All rights reserved.
    </div>
</footer>

</body>
</html>