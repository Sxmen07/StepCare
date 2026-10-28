
<?php
require_once 'db_connect.php';

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function statusStyle($status) {
    switch ($status) {
        case 'confirmed':
            return 'bg-blue-50 text-blue-700';
        case 'pending':
            return 'bg-amber-50 text-amber-700';
        case 'in_progress':
            return 'bg-purple-50 text-purple-700';
        case 'completed':
            return 'bg-green-50 text-green-700';
        case 'cancelled':
        case 'declined':
            return 'bg-red-50 text-red-700';
        default:
            return 'bg-gray-100 text-gray-700';
    }
}

function displayStatus($status) {
    return ucwords(str_replace('_', ' ', $status));
}

// Search and filter
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? 'all');

// Pagination
$perPage = 6;
$page = max(1, (int)($_GET['page'] ?? 1));

// Summary counts
$summaryQuery = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(appointmentStatus IN ('pending', 'confirmed')) AS upcoming,
        SUM(appointmentStatus = 'in_progress') AS ongoing,
        SUM(appointmentStatus = 'completed') AS completed
    FROM appointments
");
$summary = $summaryQuery->fetch(PDO::FETCH_ASSOC);

// Build search conditions
$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(
        CONCAT(u.firstName, ' ', u.lastName) LIKE ?
        OR s.serviceName LIKE ?
        OR a.locationAddress LIKE ?
        OR CAST(a.appointmentID AS CHAR) LIKE ?
    )";

    $keyword = "%{$search}%";
    array_push($params, $keyword, $keyword, $keyword, $keyword);
}

$allowedStatuses = [
    'pending', 'confirmed', 'in_progress',
    'completed', 'cancelled', 'declined'
];

if (in_array($statusFilter, $allowedStatuses, true)) {
    $where[] = "a.appointmentStatus = ?";
    $params[] = $statusFilter;
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Count matching visits
$countSQL = "
    SELECT COUNT(*)
    FROM appointments a
    INNER JOIN users u ON a.userID = u.userID
    INNER JOIN services s ON a.serviceID = s.serviceID
    $whereSQL
";

$countStmt = $pdo->prepare($countSQL);
$countStmt->execute($params);
$totalResults = (int)$countStmt->fetchColumn();

$totalPages = max(1, (int)ceil($totalResults / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

// Get visits for the current page only
$sql = "
    SELECT
        a.appointmentID,
        a.startDateTime,
        a.endDateTime,
        a.locationAddress,
        a.appointmentStatus,
        a.checkInTime,
        a.checkOutTime,
        u.firstName AS patientFirstName,
        u.lastName AS patientLastName,
        s.serviceName,
        s.category
    FROM appointments a
    INNER JOIN users u ON a.userID = u.userID
    INNER JOIN services s ON a.serviceID = s.serviceID
    $whereSQL
    ORDER BY a.startDateTime DESC
    LIMIT $perPage OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$firstResult = $totalResults > 0 ? $offset + 1 : 0;
$lastResult = min($offset + $perPage, $totalResults);

function pageUrl($pageNumber) {
    $query = $_GET;
    $query['page'] = $pageNumber;
    return '?' . http_build_query($query);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visit History | StepCare</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="min-h-screen bg-gray-50 text-gray-800">

<!-- Header -->
<header class="bg-white border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-5 lg:px-8 py-4
                flex flex-wrap items-center justify-between gap-4">

        <a href="nurse_dashboard.php"
           class="text-2xl font-bold text-[#3C52C3]">
            StepCare
        </a>

        <nav class="flex flex-wrap items-center gap-5 text-sm font-medium">
            <a href="nurse_dashboard.php"
               class="text-gray-600 hover:text-[#3C52C3]">
                Dashboard
            </a>
            <a href="nurse_notifications.php"
               class="text-gray-600 hover:text-[#3C52C3]">
                Notifications
            </a>
            <a href="visit_history.php"
               class="text-[#3C52C3] font-semibold">
                Visit History
            </a>
        </nav>
    </div>
</header>

<main class="max-w-7xl mx-auto px-5 lg:px-8 py-7">

    <!-- Page heading -->
    <div class="flex flex-col sm:flex-row sm:items-center
                sm:justify-between gap-3 mb-6">
        <div>
            <p class="text-sm text-gray-500 mb-1">Nurse Platform</p>
            <h1 class="text-2xl md:text-3xl font-bold text-gray-900">
                Visit History
            </h1>
            <p class="text-sm text-gray-500 mt-1">
                View and manage your patient visits.
            </p>
        </div>

        <div class="text-sm text-gray-500">
            Showing
            <span class="font-semibold text-gray-800">
                <?= $firstResult ?>–<?= $lastResult ?>
            </span>
            of
            <span class="font-semibold text-gray-800">
                <?= $totalResults ?>
            </span>
            visits
        </div>
    </div>

    <!-- Summary cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">

        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-500">Total Visits</p>
            <p class="text-2xl font-bold mt-1">
                <?= (int)($summary['total'] ?? 0) ?>
            </p>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-500">Upcoming</p>
            <p class="text-2xl font-bold text-blue-600 mt-1">
                <?= (int)($summary['upcoming'] ?? 0) ?>
            </p>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-500">In Progress</p>
            <p class="text-2xl font-bold text-purple-600 mt-1">
                <?= (int)($summary['ongoing'] ?? 0) ?>
            </p>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-500">Completed</p>
            <p class="text-2xl font-bold text-green-600 mt-1">
                <?= (int)($summary['completed'] ?? 0) ?>
            </p>
        </div>

    </div>

    <!-- Search and filter -->
    <form method="GET"
          class="bg-white border border-gray-200 rounded-xl p-4 mb-6
                 grid grid-cols-1 md:grid-cols-[1fr_210px_auto] gap-3">

        <input
            type="text"
            name="search"
            value="<?= e($search) ?>"
            placeholder="Search patient, service, location or ID..."
            class="w-full rounded-lg border border-gray-300 px-4 py-2.5
                   text-sm outline-none focus:border-[#3C52C3]
                   focus:ring-2 focus:ring-blue-100"
        >

        <select
            name="status"
            class="w-full rounded-lg border border-gray-300 px-3 py-2.5
                   text-sm outline-none focus:border-[#3C52C3]"
        >
            <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>
                All Statuses
            </option>
            <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>
                Pending
            </option>
            <option value="confirmed" <?= $statusFilter === 'confirmed' ? 'selected' : '' ?>>
                Confirmed
            </option>
            <option value="in_progress" <?= $statusFilter === 'in_progress' ? 'selected' : '' ?>>
                In Progress
            </option>
            <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>
                Completed
            </option>
            <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>
                Cancelled
            </option>
            <option value="declined" <?= $statusFilter === 'declined' ? 'selected' : '' ?>>
                Declined
            </option>
        </select>

        <div class="flex gap-2">
            <button type="submit"
                class="flex-1 rounded-lg bg-[#3C52C3] px-5 py-2.5
                       text-sm font-semibold text-white
                       hover:bg-[#3043A8] transition">
                Search
            </button>

            <a href="visit_history.php"
               class="rounded-lg border border-gray-300 px-4 py-2.5
                      text-sm text-gray-600 hover:bg-gray-100 transition">
                Reset
            </a>
        </div>
    </form>

    <!-- Visit cards: two columns on larger screens -->
    <?php if (empty($appointments)): ?>

        <div class="bg-white border border-gray-200 rounded-xl
                    py-14 px-5 text-center">
            <div class="text-4xl mb-3">📋</div>
            <h2 class="font-semibold text-gray-800">No visits found</h2>
            <p class="text-sm text-gray-500 mt-2">
                Try changing your search or status filter.
            </p>
        </div>

    <?php else: ?>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

            <?php foreach ($appointments as $appointment): ?>
                <?php
                    $patientName = trim(
                        ($appointment['patientFirstName'] ?? '') . ' ' .
                        ($appointment['patientLastName'] ?? '')
                    );

                    $initials = strtoupper(
                        substr($appointment['patientFirstName'] ?? 'P', 0, 1) .
                        substr($appointment['patientLastName'] ?? '', 0, 1)
                    );
                ?>

                <article class="bg-white border border-gray-200 rounded-xl
                                p-4 md:p-5 hover:shadow-md transition">

                    <!-- Patient and status -->
                    <div class="flex items-start justify-between gap-3">

                        <div class="flex items-center gap-3 min-w-0">
                            <div class="h-11 w-11 shrink-0 rounded-full
                                        bg-blue-50 text-[#3C52C3]
                                        flex items-center justify-center
                                        font-bold text-sm">
                                <?= e($initials) ?>
                            </div>

                            <div class="min-w-0">
                                <h2 class="font-semibold text-gray-900 truncate">
                                    <?= e($patientName ?: 'Patient') ?>
                                </h2>
                                <p class="text-xs text-gray-500 mt-1">
                                    Visit #<?= (int)$appointment['appointmentID'] ?>
                                </p>
                            </div>
                        </div>

                        <span class="shrink-0 rounded-full px-2.5 py-1
                                     text-xs font-medium
                                     <?= statusStyle($appointment['appointmentStatus']) ?>">
                            <?= e(displayStatus($appointment['appointmentStatus'])) ?>
                        </span>

                    </div>

                    <div class="border-t border-gray-100 my-4"></div>

                    <!-- Service -->
                    <div class="mb-4">
                        <p class="text-xs text-gray-500 mb-1">Service</p>
                        <p class="text-sm font-semibold text-gray-800">
                            <?= e($appointment['serviceName']) ?>
                        </p>
                        <?php if (!empty($appointment['category'])): ?>
                            <p class="text-xs text-gray-500 mt-1">
                                <?= e($appointment['category']) ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <!-- Date and location -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">

                        <div>
                            <p class="text-xs text-gray-500 mb-1">Visit Date</p>
                            <p class="font-medium">
                                <?= $appointment['startDateTime']
                                    ? e(date('d M Y', strtotime($appointment['startDateTime'])))
                                    : 'Not set' ?>
                            </p>
                            <p class="text-xs text-gray-500 mt-1">
                                <?= $appointment['startDateTime']
                                    ? e(date('g:i A', strtotime($appointment['startDateTime'])))
                                    : '' ?>

                                <?php if (!empty($appointment['endDateTime'])): ?>
                                    – <?= e(date('g:i A', strtotime($appointment['endDateTime']))) ?>
                                <?php endif; ?>
                            </p>
                        </div>

                        <div>
                            <p class="text-xs text-gray-500 mb-1">Location</p>
                            <p class="text-sm text-gray-700 line-clamp-2">
                                <?= e($appointment['locationAddress'] ?: 'Not provided') ?>
                            </p>
                        </div>

                    </div>

                    <!-- Check-in/out information -->
                    <?php if (!empty($appointment['checkInTime']) ||
                              !empty($appointment['checkOutTime'])): ?>

                        <div class="mt-4 rounded-lg bg-gray-50 p-3
                                    grid grid-cols-2 gap-3">

                            <div>
                                <p class="text-xs text-gray-500">Check In</p>
                                <p class="text-xs font-medium mt-1">
                                    <?= !empty($appointment['checkInTime'])
                                        ? e(date('d M, g:i A', strtotime($appointment['checkInTime'])))
                                        : 'Not checked in' ?>
                                </p>
                            </div>

                            <div>
                                <p class="text-xs text-gray-500">Check Out</p>
                                <p class="text-xs font-medium mt-1">
                                    <?= !empty($appointment['checkOutTime'])
                                        ? e(date('d M, g:i A', strtotime($appointment['checkOutTime'])))
                                        : 'Not checked out' ?>
                                </p>
                            </div>

                        </div>

                    <?php endif; ?>

                    <!-- View details -->
                    <div class="mt-4 flex justify-end">
                        <a href="patient_details.php?id=<?= (int)$appointment['appointmentID'] ?>"
                           class="inline-flex items-center gap-2 rounded-lg
                                  bg-[#3C52C3] px-4 py-2.5
                                  text-sm font-semibold text-white
                                  hover:bg-[#3043A8] transition">
                            View Details
                            <span aria-hidden="true">&rarr;</span>
                        </a>
                    </div>

                </article>

            <?php endforeach; ?>

        </div>

        <!-- Pagination -->
        <div class="mt-7 flex flex-col sm:flex-row
                    items-center justify-between gap-4">

            <p class="text-sm text-gray-500">
                Page <?= $page ?> of <?= $totalPages ?>
            </p>

            <div class="flex items-center gap-2">

                <?php if ($page > 1): ?>
                    <a href="<?= e(pageUrl($page - 1)) ?>"
                       class="rounded-lg border border-gray-300 bg-white
                              px-4 py-2 text-sm font-medium
                              hover:bg-gray-100 transition">
                        &larr; Previous
                    </a>
                <?php else: ?>
                    <span class="rounded-lg border border-gray-200
                                 bg-gray-100 px-4 py-2 text-sm
                                 text-gray-400 cursor-not-allowed">
                        &larr; Previous
                    </span>
                <?php endif; ?>

                <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                ?>

                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                    <a href="<?= e(pageUrl($i)) ?>"
                       class="h-10 min-w-10 rounded-lg px-3
                              flex items-center justify-center text-sm
                              font-medium transition
                              <?= $i === $page
                                  ? 'bg-[#3C52C3] text-white'
                                  : 'border border-gray-300 bg-white hover:bg-gray-100' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="<?= e(pageUrl($page + 1)) ?>"
                       class="rounded-lg border border-gray-300 bg-white
                              px-4 py-2 text-sm font-medium
                              hover:bg-gray-100 transition">
                        Next &rarr;
                    </a>
                <?php else: ?>
                    <span class="rounded-lg border border-gray-200
                                 bg-gray-100 px-4 py-2 text-sm
                                 text-gray-400 cursor-not-allowed">
                        Next &rarr;
                    </span>
                <?php endif; ?>

            </div>
        </div>

    <?php endif; ?>

</main>

<!-- Footer -->
<footer class="mt-10 bg-[#3C52C3] text-white">
    <div class="max-w-7xl mx-auto px-5 lg:px-8 py-5
                flex flex-col sm:flex-row justify-between
                items-center gap-2 text-sm">
        <p class="font-semibold">StepCare</p>
        <p class="text-blue-100">
            Compassionate care, delivered to your home.
        </p>
    </div>
</footer>

</body>
</html>