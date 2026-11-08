<?php
session_start();
require __DIR__ . '/../../config/db_connect.php';
$userID = (int)$_SESSION['userID'];

$stmt = $pdo->prepare("SELECT notificationID, title, message, linkURL, isRead, createdAt FROM notifications WHERE recipientType = 'user' AND recipientID = ? ORDER BY createdAt DESC");
$stmt->execute([$userID]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($notifications)) {
    echo '<div class="text-center py-20 bg-white rounded-2xl ring-1 ring-slate-200"><p class="text-slate-500">No notifications.</p></div>';
    exit;
}

foreach ($notifications as $notif) {
    $timeDiff = time() - strtotime($notif['createdAt']);
    if ($timeDiff < 60) $timeAgo = 'Just now';
    elseif ($timeDiff < 3600) $timeAgo = floor($timeDiff / 60) . ' mins ago';
    elseif ($timeDiff < 86400) $timeAgo = floor($timeDiff / 3600) . ' hours ago';
    else $timeAgo = date('d M Y, g:i A', strtotime($notif['createdAt']));

    $isRead = (int)$notif['isRead'];
    $bgClass = $isRead ? 'bg-white' : 'bg-blue-50/50 border-blue-100';
    $url = $notif['linkURL'] ?: '/StepCare/user/appointments/appointments.php';
    ?>
    <div class="notif-item flex items-center justify-between p-4 rounded-2xl border border-slate-200 <?= $bgClass ?> transition hover:shadow-md" data-status="<?= $isRead ? 'read' : 'unread' ?>">
      <a href="<?= htmlspecialchars($url) ?>" class="flex-1 flex items-start gap-4 min-w-0 pr-4" onclick="markAsReadBeforeNav(<?= (int)$notif['notificationID'] ?>)">
        <div class="flex-shrink-0 mt-1.5 unread-dot <?= $isRead ? 'hidden' : '' ?>"><span class="block w-2.5 h-2.5 rounded-full bg-[#3C52C3]"></span></div>
        <div class="flex-shrink-0 mt-1.5 read-dot <?= $isRead ? '' : 'hidden' ?>"><span class="block w-2.5 h-2.5 rounded-full bg-transparent"></span></div>
        <div class="flex-1 min-w-0">
          <h3 class="text-base font-bold text-slate-800 leading-snug truncate"><?= htmlspecialchars($notif['title']) ?></h3>
          <p class="text-sm text-slate-600 mt-1 leading-relaxed"><?= htmlspecialchars($notif['message']) ?></p>
          <p class="text-xs font-semibold text-slate-400 mt-2"><?= $timeAgo ?></p>
        </div>
      </a>
    </div>
    <?php
}
?>