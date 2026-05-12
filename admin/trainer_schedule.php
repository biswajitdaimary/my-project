<?php
require_once '../config/config.php';
require_once '../helpers/auth_check.php';
require_admin();

$pageTitle = 'Trainer Schedule';

$tid = (int)($_GET['trainer_id'] ?? 0);
if ($tid <= 0) { header('Location: trainers.php'); exit; }

// Load trainer info
$trainerStmt = $pdo->prepare("SELECT * FROM trainers WHERE trainer_id = ?");
$trainerStmt->execute([$tid]);
$trainer = $trainerStmt->fetch();
if (!$trainer) { header('Location: trainers.php'); exit; }

// Week navigation
$wOffset = (int)($_GET['week'] ?? 0);
$monday  = new DateTime('monday this week');
$monday->modify("$wOffset weeks");
$wStart  = $monday->format('Y-m-d');
$sunday  = clone $monday; $sunday->modify('+6 days');
$wEnd    = $sunday->format('Y-m-d');

$days = [];
for ($i = 0; $i < 7; $i++) {
    $d = clone $monday; $d->modify("$i days");
    $days[$d->format('D')] = $d->format('Y-m-d');
}

// Load slots for this week
$slotStmt = $pdo->prepare("
    SELECT * FROM availability_slots
    WHERE trainer_id = ? AND date BETWEEN ? AND ?
    ORDER BY date ASC, start_time ASC
");
$slotStmt->execute([$tid, $wStart, $wEnd]);
$allSlots = $slotStmt->fetchAll();

$avail = [];
foreach ($allSlots as $s) { $avail[$s['date']][] = $s; }

$holidays = [];
$hq = $pdo->prepare("SELECT * FROM holidays WHERE holiday_date BETWEEN ? AND ?");
$hq->execute([$wStart, $wEnd]);
foreach ($hq->fetchAll() as $h) {
    if ($h['target_type'] === 'all' || in_array($tid, json_decode($h['trainer_ids'], true) ?: [])) {
        $holidays[$h['holiday_date']] = $h;
    }
}

// Load bookings for this week
$bkStmt = $pdo->prepare("
    SELECT tb.*, u.full_name AS cn, u.email AS ce, u.phone AS cp, u.profile_photo AS cph
    FROM trainer_bookings tb
    JOIN users u ON u.user_id = tb.user_id
    WHERE tb.trainer_id = ? AND tb.session_date BETWEEN ? AND ?
      AND tb.status NOT IN ('cancelled')
");
$bkStmt->execute([$tid, $wStart, $wEnd]);
$bookings = [];
foreach ($bkStmt->fetchAll() as $b) {
    $bookings[$b['session_date']][substr($b['start_time'],0,5)] = $b;
}

// Stats
$totAv = 0; $totBk = 0; $totBl = 0;
foreach ($allSlots as $s) {
    $bk = $bookings[$s['date']][substr($s['start_time'],0,5)] ?? null;
    if ($bk) $totBk++;
    elseif ($s['status'] === 'available') $totAv++;
    else $totBl++;
}

require_once 'includes/admin_header.php';
?>
<style>
.ts-page{padding:1.5rem 2rem;}
.ts-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;}
.ts-trainer-info{display:flex;align-items:center;gap:1rem;}
.ts-avatar{width:54px;height:54px;border-radius:50%;object-fit:cover;border:2px solid #e2e8f0;}
.ts-avatar-init{width:54px;height:54px;border-radius:50%;background:linear-gradient(135deg,#FF6B35,#f59e0b);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:1.3rem;flex-shrink:0;}
.ts-name{font-size:1.1rem;font-weight:800;color:#1e293b;}
.ts-spec{font-size:.8rem;color:#64748b;margin-top:.1rem;}
.ts-actions{display:flex;align-items:center;gap:.75rem;}
.ts-week-nav{display:flex;align-items:center;gap:.5rem;}
.ts-week-nav button{background:#f1f5f9;border:none;width:32px;height:32px;border-radius:8px;cursor:pointer;color:#475569;display:flex;align-items:center;justify-content:center;transition:all .15s;}
.ts-week-nav button:hover{background:#FF6B35;color:#fff;}
.ts-week-label{font-size:.85rem;font-weight:700;color:#1e293b;min-width:130px;text-align:center;}

/* Stats */
.ts-stats{display:flex;gap:.65rem;margin-bottom:1.25rem;flex-wrap:wrap;}
.ts-stat{display:flex;align-items:center;gap:.45rem;padding:.4rem .85rem;border-radius:100px;font-size:.78rem;font-weight:700;}

/* Board (matching trainer portal) */
.ts-board{display:grid;grid-template-columns:repeat(7,minmax(145px,1fr));gap:.75rem;overflow-x:auto;padding-bottom:1rem;}
.ts-day-col{display:flex;flex-direction:column;gap:.5rem;}
.ts-day-head{text-align:center;padding:.5rem .25rem .6rem;border-radius:10px;background:#fff;border:1.5px solid #e2e8f0;}
.ts-day-head .ts-dname{font-size:.7rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:#94a3b8;}
.ts-day-head .ts-ddate{font-size:1.05rem;font-weight:800;color:#1e293b;line-height:1;}
.ts-day-head.today-col{background:linear-gradient(135deg,#0ea5e9,#38bdf8);border-color:#0ea5e9;}
.ts-day-head.today-col .ts-dname,.ts-day-head.today-col .ts-ddate{color:#fff;}
.ts-slot{background:#fff;border:1.5px solid #e2e8f0;border-radius:10px;padding:.65rem .75rem;position:relative;border-left:4px solid #e2e8f0;overflow:hidden;}
.ts-slot.available{border-left-color:#22c55e;}
.ts-slot.booked{border-left-color:#ef4444;background:linear-gradient(135deg,#fef2f2,#fff);}
.ts-slot.blocked{border-left-color:#94a3b8;background:#f8fafc;opacity:.8;}
.ts-time{font-size:.78rem;font-weight:800;color:#1e293b;white-space:nowrap;}
.ts-badge{display:inline-flex;align-items:center;gap:.25rem;font-size:.6rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;padding:.18rem .5rem;border-radius:100px;margin-top:.25rem;}
.ts-badge.available{background:rgba(34,197,94,.12);color:#16a34a;}
.ts-badge.booked{background:rgba(239,68,68,.12);color:#dc2626;}
.ts-badge.blocked{background:#f1f5f9;color:#64748b;}
.ts-client{font-size:.65rem;color:#ef4444;font-weight:700;margin-top:.4rem;padding-top:.3rem;border-top:1px solid rgba(239,68,68,0.2);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.ts-no-slots{text-align:center;padding:3rem 1rem;color:#94a3b8;width:100%;grid-column:1/-1;}
.ts-back-btn{display:inline-flex;align-items:center;gap:.4rem;padding:.45rem 1rem;background:#f1f5f9;border-radius:8px;color:#475569;font-size:.82rem;font-weight:600;text-decoration:none;border:none;cursor:pointer;transition:all .15s;}
.ts-back-btn:hover{background:#FF6B35;color:#fff;}
</style>

<div class="ts-page">

  <!-- Header -->
  <div class="ts-header">
    <div class="ts-trainer-info">
      <a href="trainers.php" class="ts-back-btn"><i class="fa-solid fa-arrow-left"></i> Back</a>
      <?php
      $photoSrc = '';
      if (!empty($trainer['photo'])) {
        $photoSrc = filter_var($trainer['photo'], FILTER_VALIDATE_URL) ? $trainer['photo'] : SITE_URL . '/' . ltrim($trainer['photo'], '/');
      }
      ?>
      <?php if($photoSrc): ?>
        <img src="<?= htmlspecialchars($photoSrc) ?>" alt="" class="ts-avatar">
      <?php else: ?>
        <div class="ts-avatar-init"><?= strtoupper(substr($trainer['full_name'],0,1)) ?></div>
      <?php endif; ?>
      <div>
        <div class="ts-name"><?= htmlspecialchars($trainer['full_name']) ?></div>
        <div class="ts-spec"><?= htmlspecialchars($trainer['specialization'] ?? '') ?></div>
      </div>
    </div>

    <div class="ts-actions">
      <form method="POST" action="impersonate_trainer.php" class="m-0">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="id" value="<?= (int)$tid ?>">
        <input type="hidden" name="dest" value="availability">
        <button type="submit" class="btn btn-sm" style="background:#FF6B35; color:#fff; font-weight:600; border-radius:8px; display:flex; align-items:center; gap:0.4rem; padding:0.45rem 1rem; text-decoration:none; box-shadow:0 4px 12px rgba(255,107,53,0.3);">
          <i class="fa-solid fa-pen-to-square"></i> Edit Schedule (Full Control)
        </button>
      </form>
      <div class="ts-week-nav">
        <a href="?trainer_id=<?= $tid ?>&week=<?= $wOffset-1 ?>"><button type="button"><i class="fa-solid fa-chevron-left"></i></button></a>
        <span class="ts-week-label"><?= date('M j', strtotime($wStart)) ?> – <?= date('M j', strtotime($wEnd)) ?></span>
        <a href="?trainer_id=<?= $tid ?>&week=<?= $wOffset+1 ?>"><button type="button"><i class="fa-solid fa-chevron-right"></i></button></a>
        <?php if($wOffset !== 0): ?>
        <a href="?trainer_id=<?= $tid ?>&week=0"><button type="button" title="This Week"><i class="fa-solid fa-rotate-left"></i></button></a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Stats -->
  <div class="ts-stats">
    <div class="ts-stat" style="background:rgba(34,197,94,.1);color:#16a34a;">
      <i class="fa-solid fa-circle-check"></i> <?= $totAv ?> Available
    </div>
    <div class="ts-stat" style="background:rgba(239,68,68,.1);color:#dc2626;">
      <i class="fa-solid fa-calendar-check"></i> <?= $totBk ?> Booked
    </div>
    <div class="ts-stat" style="background:#f1f5f9;color:#64748b;">
      <i class="fa-solid fa-ban"></i> <?= $totBl ?> Blocked
    </div>
    <div class="ts-stat" style="background:rgba(99,102,241,.1);color:#6366f1;">
      <i class="fa-solid fa-calendar-week"></i> <?= count($allSlots) ?> Total Slots
    </div>
  </div>

  <!-- Weekly Board -->
  <?php if(empty($allSlots)): ?>
  <div class="ts-no-slots">
    <i class="fa-regular fa-calendar-xmark fa-3x mb-3 d-block" style="opacity:.3;"></i>
    <strong>No slots scheduled</strong> for this week.<br>
    <span style="font-size:.85rem;color:#94a3b8;">The trainer hasn't set up any availability for this period.</span>
  </div>
  <?php else: ?>
  <div class="ts-board">
    <?php
    $today = date('Y-m-d');
    foreach ($days as $dName => $date):
      $isToday  = $date === $today;
      $daySlots = $avail[$date] ?? [];
      $isHoliday = isset($holidays[$date]);
      $hTitle = $isHoliday ? htmlspecialchars($holidays[$date]['title']) : '';
    ?>
    <div class="ts-day-col">
      <div class="ts-day-head <?= $isToday ? 'today-col' : '' ?>" <?= $isHoliday ? 'style="border-color:#ef4444; background:linear-gradient(135deg, #fef2f2, #fff);"' : '' ?>>
        <div class="ts-dname" <?= $isHoliday ? 'style="color:#ef4444;"' : '' ?>><?= $dName ?></div>
        <div class="ts-ddate" <?= $isHoliday ? 'style="color:#ef4444;"' : '' ?>><?= date('j', strtotime($date)) ?></div>
        <div style="font-size:.65rem;color:<?= $isToday ? 'rgba(255,255,255,.75)' : ($isHoliday?'#f87171':'#94a3b8') ?>;margin-top:.1rem;"><?= date('M', strtotime($date)) ?></div>
        <?php if ($isHoliday): ?>
          <div style="font-size:0.6rem; font-weight:800; color:#ef4444; margin-top:0.4rem; background:rgba(239,68,68,0.1); border-radius:6px; padding:0.25rem; text-transform:uppercase; letter-spacing: 0.05em;">Holiday</div>
        <?php endif; ?>
      </div>

      <?php if ($isHoliday): ?>
        <div style="background: linear-gradient(135deg, #fef2f2, #fee2e2); border: 1.5px dashed #fca5a5; border-radius: 12px; padding: 1.5rem 1rem; text-align: center; color: #ef4444; margin-top: 0.25rem; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 140px;">
          <i class="fa-solid fa-umbrella-beach mb-2" style="font-size: 2rem; opacity: 0.7;"></i>
          <span style="font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em;"><?= $hTitle ?></span>
        </div>
      <?php else: ?>
        <?php if (empty($daySlots)): ?>
          <div style="text-align:center;padding:1.5rem .5rem;color:#cbd5e1;font-size:.75rem; border: 1.5px dashed #e2e8f0; border-radius: 12px; margin-top: 0.25rem;">
              <i class="fa-regular fa-calendar-xmark mb-2 fs-4 d-block"></i> No slots
          </div>
        <?php else: ?>
          <?php foreach($daySlots as $slot):
            $stime = substr($slot['start_time'],0,5);
            $etime = substr($slot['end_time'],0,5);
            $bk    = $bookings[$date][$stime] ?? null;
            $state = $bk ? 'booked' : $slot['status'];
          ?>
          <div class="ts-slot <?= $state ?>">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:.35rem;">
                <div style="min-width:0;">
                    <div class="ts-time" style="overflow:hidden;text-overflow:ellipsis;"><?= date('h:i A', strtotime($stime)) ?></div>
                    <div style="font-size:.65rem;color:#64748b;margin-bottom:.2rem;">to <?= date('h:i A', strtotime($etime)) ?></div>
                    <div class="ts-badge <?= $state ?>">
                        <i class="fa-solid fa-circle" style="font-size:.35rem;"></i> <?= ucfirst($state) ?>
                    </div>
                </div>
                <?php if ($state === 'available'): ?>
                <div style="width:30px;height:16px;background:#22c55e;border-radius:34px;position:relative;flex-shrink:0;">
                    <div style="position:absolute;width:12px;height:12px;background:#fff;border-radius:50%;right:2px;top:2px;"></div>
                </div>
                <?php endif; ?>
            </div>
            <?php if ($bk): ?>
                <div class="ts-client">
                    <i class="fa-solid fa-user me-1"></i> <?= htmlspecialchars($bk['cn']) ?>
                </div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

</div>

<?php require_once 'includes/admin_footer.php'; ?>
