<?php
session_start();
require __DIR__ . '/../../config/db_connect.php';

if (empty($_SESSION['userID'])) {
    header('Location: ../../loginNsignup/login/login.html');
    exit;
}

$userID = (int)$_SESSION['userID'];
$appointmentID = (int)($_GET['id'] ?? 0);

if ($appointmentID <= 0) {
    header('Location: services.php');
    exit;
}

// Fetch appointment and payment info
$stmt = $pdo->prepare("
    SELECT a.appointmentID, a.patientName, a.startDateTime, a.locationAddress,
           a.appointmentStatus, s.serviceName, s.price, s.price_unit,
           p.paymentID, p.paymentStatus
    FROM appointments a
    JOIN services s ON s.serviceID = a.serviceID
    JOIN payments p ON p.appointmentID = a.appointmentID
    WHERE a.appointmentID = ? AND a.userID = ?
");
$stmt->execute([$appointmentID, $userID]);
$appt = $stmt->fetch();

if (!$appt || $appt['paymentStatus'] !== 'pending') {
    // If already uploaded or invalid, send back
    header('Location: services.php');
    exit;
}

$dt = strtotime($appt['startDateTime']);
$prettyDate = date('D, d M Y', $dt);
$prettyTime = date('g:i A', $dt);
$ref = '#SC' . str_pad((string)$appt['appointmentID'], 6, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
  <link rel="stylesheet" href="../headerNfooter/header/header.css" />
  <title>Complete Payment — StepCare</title>
</head>
<body class="min-h-dvh bg-slate-50 antialiased flex flex-col">

  <?php include __DIR__ . '/../headerNfooter/header/header.php'; ?>

  <main class="flex-1 flex items-center justify-center px-6 py-12">
    <div class="w-full max-w-4xl bg-white rounded-3xl shadow-xl ring-1 ring-slate-200 overflow-hidden flex flex-col md:flex-row">

      <!-- LEFT: ORDER SUMMARY -->
      <div class="md:w-2/5 bg-slate-900 text-white p-8 md:p-10 flex flex-col justify-between">
        <div>
          <p class="text-xs font-bold uppercase tracking-[0.18em] text-[#10B981] mb-4">Order Summary</p>
          <h2 class="text-2xl font-extrabold leading-tight mb-6"><?= htmlspecialchars($appt['serviceName']) ?></h2>
          
          <div class="space-y-4 text-sm">
            <div>
              <p class="text-white/50 text-xs mb-1">Reference</p>
              <p class="font-bold"><?= $ref ?></p>
            </div>
            <div>
              <p class="text-white/50 text-xs mb-1">Patient</p>
              <p class="font-medium"><?= htmlspecialchars($appt['patientName']) ?></p>
            </div>
            <div>
              <p class="text-white/50 text-xs mb-1">Schedule</p>
              <p class="font-medium"><?= $prettyDate ?> • <?= $prettyTime ?></p>
            </div>
            <div>
              <p class="text-white/50 text-xs mb-1">Address</p>
              <p class="font-medium leading-relaxed"><?= htmlspecialchars($appt['locationAddress']) ?></p>
            </div>
          </div>
        </div>

        <div class="pt-6 mt-8 border-t border-white/15">
          <p class="text-white/50 text-xs mb-1">Total Amount</p>
          <p class="text-4xl font-extrabold tracking-tight">
            RM<?= number_format((float)$appt['price'], 2) ?>
            <span class="text-sm font-medium text-white/50">/<?= htmlspecialchars($appt['price_unit']) ?></span>
          </p>
        </div>
      </div>

      <!-- RIGHT: UPLOAD FORM -->
      <div class="md:w-3/5 p-8 md:p-10 flex flex-col justify-center">
        <h1 class="text-2xl font-extrabold text-slate-800 mb-2">Payment Verification</h1>
        <p class="text-sm text-slate-500 mb-8">
          Please transfer <strong class="text-slate-800">RM<?= number_format((float)$appt['price'], 2) ?></strong> to:<br>
          <span class="inline-block mt-2 rounded bg-slate-100 px-3 py-1 font-mono text-slate-700">Maybank 5140 1234 5678 (StepCare)</span>
        </p>

        <form action="processPayment.php" method="POST" enctype="multipart/form-data" class="flex flex-col gap-6" id="uploadForm">
          <input type="hidden" name="appointmentID" value="<?= $appointmentID ?>" />
          
          <div>
            <p class="text-sm font-semibold text-slate-700 mb-2">Upload Payment Proof <span class="text-red-500">*</span></p>
            
            <!-- Drag and Drop Area -->
            <div id="dropZone" class="relative border-2 border-dashed border-slate-300 rounded-2xl bg-slate-50 p-8 text-center hover:bg-slate-100 hover:border-[#3C52C3] transition cursor-pointer group overflow-hidden">
              <input type="file" name="paymentProof" id="fileInput" accept="image/png, image/jpeg, image/jpg" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" required />
              
              <div id="dropContent" class="flex flex-col items-center pointer-events-none">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-10 h-10 text-slate-400 group-hover:text-[#3C52C3] transition mb-3">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0l3 3m-3-3l-3 3M6.75 19.5a4.5 4.5 0 01-1.41-8.775 5.25 5.25 0 0110.233-2.33 3 3 0 013.758 3.848A3.752 3.752 0 0118 19.5H6.75z" />
                </svg>
                <p class="text-sm font-semibold text-slate-700">Click to upload or drag & drop</p>
                <p class="text-xs text-slate-500 mt-1">PNG, JPG up to 5MB</p>
              </div>

              <!-- Image Preview -->
              <div id="previewContainer" class="hidden absolute inset-0 bg-slate-100 flex items-center justify-center p-2 z-20">
                <img id="imagePreview" class="max-w-full max-h-full object-contain rounded-xl" />
                <button type="button" id="removeFile" class="absolute top-2 right-2 bg-white text-red-500 rounded-full p-1 shadow-md hover:bg-red-50 z-30">
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
              </div>
            </div>
          </div>

          <div class="flex flex-col sm:flex-row gap-3 mt-4">
            <a href="cancelPayment.php?id=<?= $appointmentID ?>" class="flex-1 text-center rounded-xl border border-slate-300 bg-white py-3.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">
              Cancel Booking
            </a>
            <button type="submit" id="submitBtn" disabled class="flex-1 rounded-xl bg-[#007FFF] py-3.5 text-sm font-bold text-white hover:bg-[#0066cc] active:scale-[0.98] transition disabled:opacity-50 disabled:cursor-not-allowed">
              Confirm Payment
            </button>
          </div>
        </form>
      </div>

    </div>
  </main>

  <script>
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');
    const previewContainer = document.getElementById('previewContainer');
    const imagePreview = document.getElementById('imagePreview');
    const removeFileBtn = document.getElementById('removeFile');
    const submitBtn = document.getElementById('submitBtn');

    fileInput.addEventListener('change', function(e) {
      const file = e.target.files[0];
      if (file) {
        if (file.size > 5 * 1024 * 1024) {
          alert('File is too large. Max 5MB.');
          fileInput.value = '';
          return;
        }
        const reader = new FileReader();
        reader.onload = function(e) {
          imagePreview.src = e.target.result;
          previewContainer.classList.remove('hidden');
          submitBtn.disabled = false;
        }
        reader.readAsDataURL(file);
      }
    });

    removeFileBtn.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      fileInput.value = '';
      previewContainer.classList.add('hidden');
      imagePreview.src = '';
      submitBtn.disabled = true;
    });

    // Drag and drop visual feedback
    ['dragenter', 'dragover'].forEach(eventName => {
      dropZone.addEventListener(eventName, (e) => {
        e.preventDefault();
        dropZone.classList.add('border-[#3C52C3]', 'bg-blue-50');
      }, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
      dropZone.addEventListener(eventName, (e) => {
        e.preventDefault();
        dropZone.classList.remove('border-[#3C52C3]', 'bg-blue-50');
      }, false);
    });
  </script>
</body>
</html>