<?php
$pageTitle = 'Class Schedule';
require_once 'includes/header.php';
require_once 'includes/nav.php';

$days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
?>
<style>
    .schedule-wrapper {
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.06);
        overflow: hidden;
        border: 1px solid #f1f5f9;
        margin-bottom: 2rem;
    }
    .schedule-table {
        width: 100%;
        border-collapse: collapse;
        margin: 0;
    }
    .schedule-table th {
        background: #f8fafc;
        color: #475569;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-size: 0.8rem;
        padding: 1.25rem 1rem;
        border-bottom: 2px solid #e2e8f0;
        text-align: center;
        white-space: nowrap;
    }
    .schedule-table th:first-child {
        text-align: left;
        padding-left: 2rem;
    }
    .schedule-table td {
        padding: 1rem;
        border-bottom: 1px solid #f1f5f9;
        border-right: 1px solid #f1f5f9;
        vertical-align: top;
        text-align: center;
    }
    .schedule-table td:first-child {
        padding-left: 2rem;
    }
    .schedule-table td:last-child {
        border-right: none;
    }
    .schedule-table tr:last-child td {
        border-bottom: none;
    }
    .schedule-table tr:hover td {
        background: rgba(248, 250, 252, 0.5);
    }
    
    .trainer-cell {
        display: flex;
        align-items: center;
        gap: 1.25rem;
        text-align: left !important;
        min-width: 220px;
    }
    .trainer-avatar {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: linear-gradient(135deg, #0ea5e9, #38bdf8);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 1.2rem;
        flex-shrink: 0;
        box-shadow: 0 4px 12px rgba(14, 165, 233, 0.2);
    }
    .trainer-info {
        min-width: 0;
    }
    .trainer-name {
        font-weight: 800;
        color: #1e293b;
        font-size: 1.05rem;
        margin-bottom: 0.15rem;
    }
    .trainer-spec {
        font-size: 0.72rem;
        color: #FF6B35;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    
    .slot-pill {
        display: inline-flex;
        flex-direction: column;
        align-items: center;
        padding: 0.5rem;
        border-radius: 8px;
        margin-bottom: 0.4rem;
        width: 100%;
        text-decoration: none;
        transition: all 0.2s;
        border: 1px solid transparent;
        min-width: 130px;
    }
    .slot-pill.available {
        background: #f0fdf4;
        color: #16a34a;
        border-color: #bbf7d0;
    }
    .slot-pill.available:hover {
        background: #dcfce7;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(22, 163, 74, 0.15);
    }
    .slot-pill.booked {
        background: #f8fafc;
        color: #94a3b8;
        border-color: #e2e8f0;
        cursor: not-allowed;
    }
    .slot-time {
        font-size: 0.78rem;
        font-weight: 800;
        white-space: nowrap;
    }
    .slot-status {
        font-size: 0.65rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-top: 0.2rem;
    }
    .slot-pill.available .slot-status {
        color: #22c55e;
    }
    .slot-pill.booked .slot-status {
        color: #94a3b8;
    }
    .day-off {
        color: #cbd5e1;
        font-size: 0.8rem;
        font-weight: 700;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.35rem;
        padding: 1rem 0;
    }
    .day-off i {
        font-size: 1.3rem;
        opacity: 0.5;
    }
</style>

<section class="section-padding bg-light min-vh-100">
    <div class="container-fluid px-4 px-lg-5">
        <div class="section-title text-center" data-aos="fade-up">
            <span class="text-primary-custom fw-bold text-uppercase" style="letter-spacing: 0.1em; color: #FF6B35;">Timetable</span>
            <h2 class="mb-3 fw-bolder" style="font-size:2.5rem; color:#1e293b;">Trainer Availability Schedule</h2>
            <p class="text-muted" style="font-size:1.1rem;">Find the perfect time to book your personalized session</p>
        </div>

        <div class="row mt-5" data-aos="fade-up" data-aos-delay="100">
            <div class="col-12">
                <div class="schedule-wrapper table-responsive">
                    <table class="schedule-table">
                        <thead>
                            <tr>
                                <th>Trainer Profile</th>
                                <?php foreach($days as $day): ?>
                                    <th><?= $day ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            try {
                                $tStmt = $pdo->query("SELECT trainer_id, full_name, specialization, photo FROM trainers WHERE is_active = 1");
                                $trainers = $tStmt->fetchAll();
                                
                                $aStmt = $pdo->query("SELECT * FROM trainer_availability");
                                $availabilities = [];
                                while ($row = $aStmt->fetch()) {
                                    $availabilities[$row['trainer_id']][$row['day_of_week']][] = $row;
                                }

                                foreach($trainers as $trainer):
                                    $initials = strtoupper(substr($trainer['full_name'], 0, 1));
                                    $photoSrc = '';
                                    if (!empty($trainer['photo'])) {
                                        $photoSrc = filter_var($trainer['photo'], FILTER_VALIDATE_URL) ? $trainer['photo'] : ltrim($trainer['photo'], '/');
                                    }
                            ?>
                                <tr>
                                    <td>
                                        <div class="trainer-cell">
                                            <?php if ($photoSrc): ?>
                                                <img src="<?= htmlspecialchars($photoSrc) ?>" alt="<?= htmlspecialchars($trainer['full_name']) ?>" class="trainer-avatar" style="display: block; object-fit: cover; border: 2px solid #fff; box-shadow: 0 4px 12px rgba(14, 165, 233, 0.3);">
                                            <?php else: ?>
                                                <div class="trainer-avatar"><?= $initials ?></div>
                                            <?php endif; ?>
                                            <div class="trainer-info">
                                                <div class="trainer-name"><?= htmlspecialchars($trainer['full_name']) ?></div>
                                                <div class="trainer-spec"><?= htmlspecialchars($trainer['specialization']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <?php foreach($days as $day): ?>
                                        <td>
                                            <?php 
                                            if(isset($availabilities[$trainer['trainer_id']][$day])) {
                                                foreach($availabilities[$trainer['trainer_id']][$day] as $slot) {
                                                    $startStr = date('h:i', strtotime($slot['start_time']));
                                                    $endStr = date('h:i', strtotime($slot['end_time']));
                                                    $startAmPm = date('A', strtotime($slot['start_time']));
                                                    $endAmPm = date('A', strtotime($slot['end_time']));
                                                    
                                                    if ($startAmPm === $endAmPm) {
                                                        $timeDisplay = "$startStr - $endStr $startAmPm";
                                                    } else {
                                                        $timeDisplay = "$startStr $startAmPm - $endStr $endAmPm";
                                                    }

                                                    $isBooked = $slot['is_booked'];
                                                    
                                                    if ($isBooked) {
                                                        echo "<div class='slot-pill booked'>
                                                                <span class='slot-time'>$timeDisplay</span>
                                                                <span class='slot-status'><i class='fa-solid fa-lock me-1'></i> Booked</span>
                                                              </div>";
                                                    } else {
                                                        echo "<a href='user/book-trainer.php?trainer_id={$trainer['trainer_id']}&day=$day' class='slot-pill available'>
                                                                <span class='slot-time'>$timeDisplay</span>
                                                                <span class='slot-status'><i class='fa-regular fa-circle-check me-1'></i> Available</span>
                                                              </a>";
                                                    }
                                                }
                                            } else {
                                                echo "<div class='day-off'>
                                                        <i class='fa-solid fa-mug-hot'></i>
                                                        Off
                                                      </div>";
                                            }
                                            ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php 
                                endforeach; 
                            } catch(Exception $e) {
                                echo "<tr><td colspan='8' class='text-danger py-5 text-center fw-bold'>Error loading schedule data.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="col-12 text-center mt-2">
                <p class="text-muted small" style="font-weight: 600;"><i class="fa-solid fa-circle-info me-1" style="color: #0ea5e9;"></i> Click on any available green slot to book a session. You must have active trainer sessions remaining on your membership to book.</p>
            </div>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
