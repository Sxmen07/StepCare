
<?php
// nurse_leave_request.php
// Front-end demo. MySQL submission is not connected yet.

session_start();

// Demo nurse details
$nurseName = $_SESSION['nurse_name'] ?? 'Fiona Lee';
$nurseId = $_SESSION['nurse_id'] ?? 'N001';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StepCare | Nurse Leave Request</title>

    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-50 text-gray-800">

<div class="flex min-h-screen">

    <!-- SIDEBAR -->
    <aside class="fixed top-0 left-0 z-40 flex flex-col w-64 h-screen bg-white border-r border-gray-200 shadow-sm">

        <!-- StepCare Logo -->
        <a href="aboutUs.html"
           class="flex items-center gap-2 px-5 py-6 text-3xl font-bold border-b border-gray-100">

            <img src="../../images/StepCare.png"
                 alt="StepCare"
                 class="object-contain w-10 h-10"
                 onerror="this.style.display='none'">

            <span>
                <span class="text-[#3C52C3]">Step</span><span class="text-[#10B981]">Care</span>
            </span>
        </a>

        <!-- Sidebar Navigation -->
        <nav class="flex-1 px-4 py-6">

            <p class="px-3 mb-3 text-xs font-semibold tracking-wider text-gray-400 uppercase">
                Main Menu
            </p>

            <ul class="space-y-2">
                <li>
                    <a href="../dahsaboard/dahsboard.html"
                       class="block px-4 py-3 text-gray-600 transition rounded-lg hover:bg-blue-50 hover:text-[#3C52C3]">
                        Dashboard
                    </a>
                </li>

                <li>
                    <a href="../jobMarket/jobMarket.html"
                       class="block px-4 py-3 text-gray-600 transition rounded-lg hover:bg-blue-50 hover:text-[#3C52C3]">
                        Job Markets
                    </a>
                </li>
            </ul>

            <hr class="my-6 border-gray-200">

            <p class="px-3 mb-3 text-xs font-semibold tracking-wider text-gray-400 uppercase">
                Account
            </p>

            <ul class="space-y-2">
                <li>
                    <a href="../profile/profile.html"
                       class="block px-4 py-3 text-gray-600 transition rounded-lg hover:bg-blue-50 hover:text-[#3C52C3]">
                        Profile
                    </a>
                </li>

                <li>
                    <a href="../settings/settings.html"
                       class="block px-4 py-3 text-gray-600 transition rounded-lg hover:bg-blue-50 hover:text-[#3C52C3]">
                        Settings
                    </a>
                </li>

                <li>
                    <a href="../customerService/customerService.html"
                       class="block px-4 py-3 text-gray-600 transition rounded-lg hover:bg-blue-50 hover:text-[#3C52C3]">
                        Customer Service
                    </a>
                </li>
            </ul>

        </nav>

        <!-- Log Out -->
        <div class="p-4 border-t border-gray-100">
            <a href="../../loginNsignup/login/login.html"
               class="block px-4 py-3 text-red-500 transition rounded-lg hover:bg-red-50">
                Log out
            </a>
        </div>

    </aside>

    <!-- MAIN CONTENT -->
    <main class="flex-1 min-w-0 ml-64">

        <!-- Header -->
        <header class="sticky top-0 z-30 flex items-center justify-between px-5 py-5 bg-white border-b border-gray-200 md:px-8">
            <div>
                <h1 class="text-xl font-bold text-[#3C52C3]">
                    Nurse Leave Request
                </h1>
                <p class="mt-1 text-sm text-gray-500">
                    Manage and submit your leave applications.
                </p>
            </div>

            <div class="flex items-center gap-3">
                <div class="hidden text-right sm:block">
                    <p class="font-semibold text-gray-700">
                        <?= htmlspecialchars($nurseName) ?>
                    </p>
                    <p class="text-xs text-gray-500">
                        Nurse ID: <?= htmlspecialchars($nurseId) ?>
                    </p>
                </div>

                <div class="flex items-center justify-center w-11 h-11 font-bold text-white rounded-full bg-[#3C52C3]">
                    <?= strtoupper(substr($nurseName, 0, 1)) ?>
                </div>
            </div>
        </header>

        <div class="p-5 md:p-8">

            <!-- Page Title -->
            <div class="mb-8">
                <h2 class="text-2xl font-bold text-gray-800">
                    Leave Applications
                </h2>
                <p class="mt-2 text-gray-500">
                    Submit a leave request and track its status.
                </p>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 gap-5 mb-8 sm:grid-cols-3">

                <div class="p-5 bg-white border border-gray-100 shadow-sm rounded-xl">
                    <p class="text-sm text-gray-500">Total Requests</p>
                    <p id="totalRequests" class="mt-2 text-3xl font-bold text-[#3C52C3]">2</p>
                </div>

                <div class="p-5 bg-white border border-gray-100 shadow-sm rounded-xl">
                    <p class="text-sm text-gray-500">Pending Requests</p>
                    <p id="pendingRequests" class="mt-2 text-3xl font-bold text-amber-500">1</p>
                </div>

                <div class="p-5 bg-white border border-gray-100 shadow-sm rounded-xl">
                    <p class="text-sm text-gray-500">Approved Requests</p>
                    <p id="approvedRequests" class="mt-2 text-3xl font-bold text-emerald-500">1</p>
                </div>

            </div>

            <!-- Leave Request Form -->
            <section class="p-6 mb-8 bg-white border border-gray-100 shadow-sm md:p-8 rounded-xl">

                <div class="pb-5 mb-6 border-b border-gray-100">
                    <h3 class="text-lg font-bold text-gray-800">
                        Submit a Leave Request
                    </h3>
                    <p class="mt-1 text-sm text-gray-500">
                        Fill in the details below.
                    </p>
                </div>

                <form id="leaveForm" class="space-y-5">

                    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">

                        <div>
                            <label for="nurseName" class="block mb-2 text-sm font-semibold">
                                Nurse Name
                            </label>
                            <input type="text" id="nurseName"
                                   value="<?= htmlspecialchars($nurseName) ?>"
                                   readonly
                                   class="w-full px-4 py-3 bg-gray-100 border border-gray-200 rounded-lg">
                        </div>

                        <div>
                            <label for="nurseId" class="block mb-2 text-sm font-semibold">
                                Nurse ID
                            </label>
                            <input type="text" id="nurseId"
                                   value="<?= htmlspecialchars($nurseId) ?>"
                                   readonly
                                   class="w-full px-4 py-3 bg-gray-100 border border-gray-200 rounded-lg">
                        </div>

                        <div>
                            <label for="leaveType" class="block mb-2 text-sm font-semibold">
                                Leave Type <span class="text-red-500">*</span>
                            </label>
                            <select id="leaveType" required
                                    class="w-full px-4 py-3 bg-white border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3C52C3]">
                                <option value="">Select leave type</option>
                                <option value="Annual Leave">Annual Leave</option>
                                <option value="Medical Leave">Medical Leave</option>
                                <option value="Emergency Leave">Emergency Leave</option>
                                <option value="Unpaid Leave">Unpaid Leave</option>
                            </select>
                        </div>

                        <div>
                            <label for="reason" class="block mb-2 text-sm font-semibold">
                                Reason for Leave <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="reason" required maxlength="200"
                                   placeholder="Enter your reason"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3C52C3]">
                        </div>

                        <div>
                            <label for="startDate" class="block mb-2 text-sm font-semibold">
                                Start Date <span class="text-red-500">*</span>
                            </label>
                            <input type="date" id="startDate" required
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3C52C3]">
                        </div>

                        <div>
                            <label for="endDate" class="block mb-2 text-sm font-semibold">
                                End Date <span class="text-red-500">*</span>
                            </label>
                            <input type="date" id="endDate" required
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3C52C3]">
                        </div>

                    </div>

                    <div class="flex flex-col justify-end gap-3 pt-3 sm:flex-row">
                        <button type="reset"
                                class="px-6 py-3 font-semibold text-gray-600 transition border border-gray-300 rounded-lg hover:bg-gray-100">
                            Clear Form
                        </button>

                        <button type="submit"
                                class="px-6 py-3 font-semibold text-white transition rounded-lg bg-[#3C52C3] hover:bg-[#3043A5]">
                            Submit Request
                        </button>
                    </div>

                </form>
            </section>

            <!-- Leave History -->
            <section class="p-6 bg-white border border-gray-100 shadow-sm md:p-8 rounded-xl">

                <h3 class="text-lg font-bold text-gray-800">
                    Leave Request History
                </h3>
                <p class="mt-1 mb-6 text-sm text-gray-500">
                    View your previous leave applications.
                </p>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead>
                            <tr class="text-xs tracking-wider text-gray-500 uppercase bg-gray-50">
                                <th class="px-4 py-4">Leave Type</th>
                                <th class="px-4 py-4">Leave Dates</th>
                                <th class="px-4 py-4">Reason</th>
                                <th class="px-4 py-4">Status</th>
                            </tr>
                        </thead>

                        <tbody id="leaveHistory" class="divide-y divide-gray-100">
                            <tr>
                                <td class="px-4 py-5 font-medium">Annual Leave</td>
                                <td class="px-4 py-5 whitespace-nowrap">10 Nov 2026 – 12 Nov 2026</td>
                                <td class="px-4 py-5">Family matters</td>
                                <td class="px-4 py-5">
                                    <span class="status-pending inline-block px-3 py-1 text-xs font-semibold text-amber-700 bg-amber-100 rounded-full">
                                        Pending
                                    </span>
                                </td>
                            </tr>

                            <tr>
                                <td class="px-4 py-5 font-medium">Medical Leave</td>
                                <td class="px-4 py-5 whitespace-nowrap">05 Oct 2026 – 05 Oct 2026</td>
                                <td class="px-4 py-5">Medical appointment</td>
                                <td class="px-4 py-5">
                                    <span class="status-approved inline-block px-3 py-1 text-xs font-semibold text-emerald-700 bg-emerald-100 rounded-full">
                                        Approved
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p id="emptyMessage" class="hidden py-8 text-center text-gray-500">
                    No leave requests found.
                </p>

            </section>

            <!-- Footer -->
            <footer class="py-6 text-sm text-center text-gray-400">
                © 2026 StepCare. All rights reserved.
            </footer>

        </div>
    </main>
</div>

<script>
    const leaveForm = document.getElementById("leaveForm");
    const leaveHistory = document.getElementById("leaveHistory");
    const startDate = document.getElementById("startDate");
    const endDate = document.getElementById("endDate");

    // Use the current local date as the earliest selectable date.
    const now = new Date();
    const today = new Date(
        now.getTime() - now.getTimezoneOffset() * 60000
    ).toISOString().split("T")[0];

    startDate.min = today;
    endDate.min = today;

    startDate.addEventListener("change", function () {
        endDate.min = startDate.value || today;

        if (endDate.value && endDate.value < startDate.value) {
            endDate.value = startDate.value;
        }
    });

    function updateSummary() {
        const rows = Array.from(leaveHistory.querySelectorAll("tr"));

        const pending = rows.filter(row =>
            row.querySelector(".status-pending")
        ).length;

        const approved = rows.filter(row =>
            row.querySelector(".status-approved")
        ).length;

        document.getElementById("totalRequests").textContent = rows.length;
        document.getElementById("pendingRequests").textContent = pending;
        document.getElementById("approvedRequests").textContent = approved;

        document.getElementById("emptyMessage").classList.toggle(
            "hidden", rows.length > 0
        );
    }

    function formatDate(dateValue) {
        return new Date(dateValue + "T00:00:00").toLocaleDateString(
            "en-GB",
            { day: "2-digit", month: "short", year: "numeric" }
        );
    }

    leaveForm.addEventListener("submit", function (event) {
        event.preventDefault();

        if (endDate.value < startDate.value) {
            alert("End date cannot be earlier than start date.");
            return;
        }

        const type = document.getElementById("leaveType").value;
        const reason = document.getElementById("reason").value.trim();

        const row = document.createElement("tr");

        const typeCell = document.createElement("td");
        typeCell.className = "px-4 py-5 font-medium";
        typeCell.textContent = type;

        const dateCell = document.createElement("td");
        dateCell.className = "px-4 py-5 whitespace-nowrap";
        dateCell.textContent =
            formatDate(startDate.value) + " – " + formatDate(endDate.value);

        const reasonCell = document.createElement("td");
        reasonCell.className = "px-4 py-5";
        reasonCell.textContent = reason;

        const statusCell = document.createElement("td");
        statusCell.className = "px-4 py-5";

        const badge = document.createElement("span");
        badge.className =
            "status-pending inline-block px-3 py-1 text-xs font-semibold text-amber-700 bg-amber-100 rounded-full";
        badge.textContent = "Pending";

        statusCell.appendChild(badge);
        row.append(typeCell, dateCell, reasonCell, statusCell);

        leaveHistory.prepend(row);
        updateSummary();
        leaveForm.reset();

        endDate.min = today;

        alert("Leave request added to the demo history.");
    });

    updateSummary();
</script>

</body>
</html>
