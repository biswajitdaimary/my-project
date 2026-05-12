<?php
$pageTitle = 'Our Trainers';
require_once 'config/config.php';
require_once 'includes/header.php';
require_once 'includes/nav.php';

// Fetch all active trainers
$trainers = $pdo->query("SELECT * FROM trainers WHERE is_active = 1 ORDER BY rating DESC")->fetchAll();

// Fetch upcoming availability_slots for all active trainers (next 7 days)
$availStmt = $pdo->query("
    SELECT a.trainer_id, DATE_FORMAT(a.date, '%a') as day_of_week, a.start_time, a.end_time, a.status, a.date
    FROM availability_slots a
    JOIN trainers t ON t.trainer_id = a.trainer_id
    WHERE t.is_active = 1 AND a.date >= CURRENT_DATE AND a.date <= DATE_ADD(CURRENT_DATE, INTERVAL 7 DAY)
    ORDER BY a.trainer_id, a.date ASC, a.start_time ASC
");
$allAvail = $availStmt->fetchAll();

// Index availability by trainer_id → day → slots[]
$availByTrainer = [];
foreach ($allAvail as $a) {
    $availByTrainer[$a['trainer_id']][$a['day_of_week']][] = $a;
}

$days = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
?>

<style>
/* ── Trainer Card ── */
.tc-card {
    background: #fff;
    border-radius: 1.5rem;
    overflow: hidden;
    box-shadow: 0 8px 30px rgba(0,0,0,0.07);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    height: 100%;
    display: flex; flex-direction: column;
}
.tc-card:hover { transform: translateY(-8px); box-shadow: 0 20px 50px rgba(0,0,0,0.12); }

.tc-photo-wrap { position: relative; height: 240px; overflow: hidden; background: linear-gradient(135deg,#1A1A2E,#2a2a4a); display: flex; align-items: center; justify-content: center; }
.tc-photo { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform 0.5s ease; }
.tc-card:hover .tc-photo { transform: scale(1.06); }
.tc-initials { font-size: 5rem; font-weight: 800; color: rgba(255,107,53,0.6); }

.tc-rating { position: absolute; bottom: 12px; left: 12px; background: rgba(0,0,0,0.6); backdrop-filter: blur(5px); color: #fff; font-size: 0.8rem; font-weight: 700; padding: 5px 12px; border-radius: 100px; }
.tc-rating i { color: #ffc107; }

.tc-body { padding: 1.5rem; flex: 1; display: flex; flex-direction: column; }
.tc-name { font-weight: 800; font-size: 1.15rem; color: #1a1a2e; margin-bottom: 0.2rem; }
.tc-spec { font-size: 0.82rem; font-weight: 700; color: #FF6B35; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.75rem; }
.tc-bio { font-size: 0.88rem; color: #666; flex: 1; line-height: 1.6; }

/* ── Availability Schedule ── */
.tc-availability { margin-top: 1.25rem; border-top: 1px solid #f0f0f5; padding-top: 1.25rem; }
.tc-avail-title { font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; color: #aaa; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }

.avail-day-row { margin-bottom: 0.75rem; }
.avail-day-name { font-size: 0.75rem; font-weight: 700; color: #888; min-width: 36px; }
.avail-slots { display: flex; flex-wrap: wrap; gap: 0.3rem; }
.avail-slot {
    font-size: 0.72rem; font-weight: 600;
    padding: 3px 9px; border-radius: 100px;
}
.avail-slot.available { background: #e8f8ef; color: #1a6b31; border: 1px solid #c3e6cb; }
.avail-slot.unavailable { background: #fff1f0; color: #a61d2c; border: 1px solid #f5c6cb; text-decoration: line-through; }

.no-avail-msg { font-size: 0.82rem; color: #bbb; font-style: italic; }

/* ── Book Button ── */
.btn-book {
    margin-top: 1.25rem;
    background: linear-gradient(135deg,#FF6B35,#ff8c61);
    color: #fff; border: none; border-radius: 100px;
    font-weight: 700; font-size: 0.88rem; padding: 0.65rem 1.5rem;
    width: 100%; transition: all 0.25s;
    box-shadow: 0 4px 15px rgba(255,107,53,0.25);
}
.btn-book:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(255,107,53,0.35); color: #fff; }

/* ── Last updated badge ── */
.last-updated { font-size: 0.7rem; color: #bbb; margin-top: 0.5rem; text-align: right; }

/* ── Section header ── */
.section-eyebrow { font-size: 0.78rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.12em; color: #FF6B35; margin-bottom: 0.5rem; }
</style>

<section class="section-padding" style="background: linear-gradient(180deg,#f4f6fb 0%, #fff 100%); min-height: 100vh;">
    <div class="container">

        <div class="section-title text-center mb-5" data-aos="fade-up">
            <p class="section-eyebrow">Our Team</p>
            <h2 class="fw-bold" style="color:#1a1a2e;">Meet Our Expert Trainers</h2>
            <p class="text-muted">Push your limits with our certified professionals. Schedules are live — updated in real time.</p>
        </div>

        <div class="row gy-5">
            <?php if (empty($trainers)): ?>
                <div class="col-12 text-center text-muted py-5">
                    <i class="fa-solid fa-person-running fa-3x d-block mb-3 opacity-25"></i>
                    No trainers available at the moment.
                </div>
            <?php else: ?>
            <?php $delay = 0; foreach ($trainers as $t):
                $photo = $t['photo'] ?? '';
                $isUrl = str_starts_with($photo, 'http');
                $photoSrc = $photo ? ($isUrl ? $photo : SITE_URL . '/' . ltrim($photo, '/')) : null;
                $trainerAvail = $availByTrainer[$t['trainer_id']] ?? [];
                $hasAvail = !empty($trainerAvail);
            ?>
            <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="<?= $delay ?>">
                <div class="tc-card">
                    <!-- Photo -->
                    <div class="tc-photo-wrap">
                        <?php if ($photoSrc): ?>
                            <img src="<?= htmlspecialchars($photoSrc) ?>" alt="<?= htmlspecialchars($t['full_name']) ?>" class="tc-photo" loading="lazy">
                        <?php else: ?>
                            <div class="tc-initials"><?= strtoupper(substr($t['full_name'], 0, 1)) ?></div>
                        <?php endif; ?>
                        <div class="tc-rating">
                            <i class="fa-solid fa-star"></i> <?= number_format((float)$t['rating'], 1) ?>
                        </div>
                    </div>

                    <div class="tc-body">
                        <!-- Info -->
                        <h4 class="tc-name"><?= htmlspecialchars($t['full_name']) ?></h4>
                        <p class="tc-spec"><?= htmlspecialchars($t['specialization']) ?></p>
                        <p class="tc-bio"><?= nl2br(htmlspecialchars(mb_substr($t['bio'] ?? '', 0, 160))) ?><?= mb_strlen($t['bio'] ?? '') > 160 ? '…' : '' ?></p>

                        <!-- Availability Schedule -->
                        <div class="tc-availability">
                            <div class="tc-avail-title">
                                <i class="fa-solid fa-calendar-week text-primary-custom"></i>
                                Weekly Availability
                                <!-- Live indicator: no-cache means any PHP reload will show fresh data -->
                                <span class="badge bg-success bg-opacity-10 text-success ms-auto" style="font-size:0.65rem;">● Live</span>
                            </div>

                            <?php if (!$hasAvail): ?>
                                <p class="no-avail-msg"><i class="fa-regular fa-calendar-xmark me-1"></i>No schedule set yet.</p>
                            <?php else: ?>
                                <?php foreach ($days as $d): ?>
                                    <?php if (!empty($trainerAvail[$d])): ?>
                                    <div class="avail-day-row d-flex align-items-start gap-2">
                                        <span class="avail-day-name"><?= $d ?></span>
                                        <div class="avail-slots">
                                            <?php 
                                                foreach ($trainerAvail[$d] as $slot): 
                                                    $startStr = date('h.i', strtotime($slot['start_time']));
                                                    $endStr = date('h.i', strtotime($slot['end_time']));
                                                    $startAmPm = date('A', strtotime($slot['start_time']));
                                                    $endAmPm = date('A', strtotime($slot['end_time']));
                                                    
                                                    if ($startAmPm === $endAmPm) {
                                                        $timeDisplay = "$startStr - $endStr ($startAmPm)";
                                                    } else {
                                                        $timeDisplay = "$startStr ($startAmPm) - $endStr ($endAmPm)";
                                                    }
                                                    // Map new status values to CSS classes
                                                    $rawStatus = $slot['status'] ?? 'available';
                                                    $slotStatus = ($rawStatus === 'available') ? 'available' : 'unavailable';
                                            ?>
                                            <span class="avail-slot <?= htmlspecialchars($slotStatus) ?>">
                                                <?= $timeDisplay ?>
                                                <?php if ($slotStatus === 'unavailable'): ?>
                                                    <i class="fa-solid fa-ban ms-1"></i>
                                                <?php endif; ?>
                                            </span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Book Button -->
                        <a href="<?= SITE_URL ?>/user/book-trainer.php?trainer_id=<?= $t['trainer_id'] ?>" class="btn-book d-block text-center text-decoration-none mt-3">
                            <i class="fa-solid fa-calendar-check me-2"></i>Book a Session
                        </a>
                    </div>
                </div>
            </div>
            <?php $delay += 100; endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
