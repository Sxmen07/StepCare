<?php
session_start();
require __DIR__ . '/../../../config/db_connect.php';
$appointmentID = (int)($_GET['id'] ?? 0);

$tStmt = $pdo->prepare("SELECT status, title, message, createdBy, createdAt FROM appointment_timeline WHERE appointmentID = ? ORDER BY createdAt ASC, timelineID ASC");
$tStmt->execute([$appointmentID]);
$timeline = $tStmt->fetchAll(PDO::FETCH_ASSOC);

// Re-use your tlColor helper logic here
function tlColor(string $s): array {
    return match ($s) {
        'pending' => ['amber-500', 'bg-amber-100'], 'confirmed', 'assigned' => ['blue-500', 'bg-blue-100'],
        'on_the_way' => ['violet-500', 'bg-violet-100'], 'arrived' => ['indigo-500', 'bg-indigo-100'],
        'in_progress' => ['indigo-600', 'bg-indigo-100'], 'completed' => ['emerald-500', 'bg-emerald-100'],
        'cancelled', 'declined' => ['rose-500', 'bg-rose-100'], default => ['slate-400', 'bg-slate-100'],
    };
}

$lastIdx = count($timeline) - 1;
foreach ($timeline as $i => $t) {
    [$dotColor] = tlColor($t['status']);
    $isLast = ($i === $lastIdx);
    $tDate = date('d M Y', strtotime($t['createdAt']));
    $tTime = date('g:i A', strtotime($t['createdAt']));
    ?>
    <div class="relative <?= $isLast ? '' : 'pb-8' ?>">
      <span class="absolute -left-[26px] top-1.5 w-3.5 h-3.5 rounded-full <?= $isLast ? "bg-{$dotColor} ring-4 ring-{$dotColor}/20" : 'bg-white border-2 border-slate-300' ?>"></span>
      <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2">
        <div class="flex-1 min-w-0">
          <p class="text-sm font-bold text-slate-800 leading-snug"><?= htmlspecialchars($t['title']) ?></p>
          <?php if (!empty($t['message'])): ?><p class="text-sm text-slate-600 mt-1 leading-relaxed"><?= nl2br(htmlspecialchars($t['message'])) ?></p><?php endif; ?>
          <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 mt-1.5"><?= htmlspecialchars(ucfirst($t['createdBy'] ?? 'system')) ?></p>
        </div>
        <div class="flex-shrink-0 text-left sm:text-right sm:pl-6">
          <p class="text-xs font-bold text-slate-700"><?= $tDate ?></p>
          <p class="text-xs text-slate-400"><?= $tTime ?></p>
        </div>
      </div>
    </div>
    <?php
}
?>