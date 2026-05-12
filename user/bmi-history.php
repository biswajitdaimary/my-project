<?php
require_once '../config/config.php';
require_once '../helpers/auth_check.php';
require_once '../helpers/bmi_helper.php';
require_user();
$pageTitle = 'BMI History';
$userId = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("SELECT bmi_value, category, age, gender, weight_kg, height_cm, recorded_at FROM bmi_records WHERE user_id = ? ORDER BY recorded_at DESC");
    $stmt->execute([$userId]);
    $records = $stmt->fetchAll();
} catch(PDOException $e) { $records = []; }

$chartLabels = array_reverse(array_column($records, 'recorded_at'));
$chartValues = array_reverse(array_column($records, 'bmi_value'));
$latest = $records[0] ?? null;

require_once '../includes/header.php';
require_once '../includes/nav.php';

function bmi_color_class(float $v): string {
    if ($v < 18.5) return 'info';
    if ($v < 25)   return 'success';
    if ($v < 30)   return 'warning';
    return 'danger';
}
?>
<style>
.bmi-latest-hero{background:linear-gradient(135deg,#1A1A2E,#0f3460);border-radius:1.5rem;padding:2rem;color:#fff;margin-bottom:1.5rem;position:relative;overflow:hidden}
.bmi-latest-hero::before{content:'';position:absolute;top:-40px;right:-40px;width:160px;height:160px;background:rgba(255,107,53,.1);border-radius:50%}
.bmi-big-num{font-size:4rem;font-weight:900;color:#FF6B35;line-height:1}
.bmi-range-pills{display:flex;flex-wrap:wrap;gap:.5rem;justify-content:center;margin-top:1.25rem}
.bmi-range-pill{border-radius:100px;padding:.35rem 1rem;font-size:.78rem;font-weight:700;color:#fff}
.bmi-record-card{background:#f8f9fc;border-radius:1rem;border:1px solid #eef0f7;padding:1rem 1.25rem;display:flex;align-items:center;gap:1rem;flex-wrap:wrap;margin-bottom:.6rem;transition:all .2s}
.bmi-record-card:hover{background:#fff;box-shadow:0 6px 20px rgba(0,0,0,.07)}
.bmi-val-badge{width:54px;height:54px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1rem;font-weight:800;flex-shrink:0;color:#fff}
.bmi-meta{font-size:.78rem;color:#9ca3af}
.empty-state{text-align:center;padding:3rem 1rem}
.empty-icon{width:80px;height:80px;border-radius:50%;background:#f4f6fb;display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;font-size:2rem;color:#d1d5db}

/* --- NEW PREMIUM STYLES --- */
.records-scroll {
    max-height: 420px; 
    overflow-y: auto;
    padding-right: 5px;
}
.records-scroll::-webkit-scrollbar { width: 5px; }
.records-scroll::-webkit-scrollbar-track { background: transparent; }
.records-scroll::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
.records-scroll::-webkit-scrollbar-thumb:hover { background: #cbd5e1; }

.chart-card-body { padding: 1.5rem !important; }
.bmi-range-pills { display: flex; flex-wrap: wrap; gap: 0.75rem; justify-content: center; margin-top: 1.5rem; }
.bmi-range-pill { 
    border-radius: 8px; padding: 0.4rem 0.8rem; font-size: 0.72rem; font-weight: 700; color: #fff;
    box-shadow: 0 4px 10px rgba(0,0,0,0.1); display: flex; align-items: center; gap: 0.4rem; text-transform: uppercase; letter-spacing: 0.05em;
}

.bmi-record-card {
    background: #fff; border-radius: 1.25rem; border: 1px solid #f1f5f9;
    padding: 1.25rem; display: flex; align-items: center; gap: 1.25rem;
    margin-bottom: 0.85rem; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.bmi-record-card:hover {
    transform: translateY(-3px); box-shadow: 0 12px 25px rgba(0,0,0,0.06); border-color: #e2e8f0;
}
.bmi-val-badge {
    width: 64px; height: 64px; border-radius: 1rem; display: flex; flex-direction: column; 
    align-items: center; justify-content: center; flex-shrink: 0; color: #fff;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}
.bmi-val-badge .val { font-size: 1.35rem; font-weight: 900; line-height: 1; }
.bmi-val-badge .lbl { font-size: 0.55rem; text-transform: uppercase; letter-spacing: 0.1em; opacity: 0.85; margin-top: 3px; }
</style>

<div class="up-wrap"><div class="container-fluid px-0"><div class="d-flex">
<?php require_once '../includes/sidebar-user.php'; ?>
<main class="up-main flex-grow-1" style="min-width:0;">

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div><h4 class="fw-bold mb-0" style="color:#1a1a2e;">BMI History</h4><p class="text-muted mb-0" style="font-size:.87rem;">Track your body mass index over time</p></div>
    <a href="<?= SITE_URL ?>/bmi-calculator.php" class="btn btn-sm rounded-pill fw-bold px-4" style="background:#FF6B35;color:#fff;"><i class="fa-solid fa-calculator me-1"></i>New Reading</a>
</div>

<?php if (empty($records)): ?>
<div class="empty-state"><div class="empty-icon"><i class="fa-solid fa-heart-pulse"></i></div>
<h5 class="fw-bold">No BMI Records Yet</h5>
<p class="text-muted mb-4" style="font-size:.9rem;">Use the BMI Calculator to log your first reading. Your full history will appear here.</p>
<a href="<?= SITE_URL ?>/bmi-calculator.php" class="btn rounded-pill px-5 fw-bold" style="background:#FF6B35;color:#fff;">Open BMI Calculator</a></div>
<?php else: ?>

<!-- Latest BMI Hero -->
<?php if ($latest):
    $lv = (float)$latest['bmi_value'];
    $cc = bmi_color_class($lv);
    $colors = ['info'=>'#17a2b8','success'=>'#22c55e','warning'=>'#f59e0b','danger'=>'#ef4444'];
    $hColor = $colors[$cc] ?? '#FF6B35';
?>
<div class="bmi-latest-hero mb-4">
    <div class="row align-items-center g-3">
        <div class="col-md-6">
            <div style="font-size:.8rem;color:rgba(255,255,255,.6);text-transform:uppercase;letter-spacing:.08em;margin-bottom:.5rem;">Latest Reading</div>
            <div class="bmi-big-num"><?= number_format($lv,1) ?></div>
            <div style="margin-top:.5rem;">
                <span class="badge" style="background:<?= $hColor ?>;font-size:.9rem;padding:.4rem 1rem;"><?= htmlspecialchars($latest['category']) ?></span>
            </div>
            <div style="font-size:.78rem;color:rgba(255,255,255,.5);margin-top:.75rem;">Recorded <?= date('M d, Y · h:i A', strtotime($latest['recorded_at'])) ?></div>
        </div>
        <div class="col-md-6">
            <div class="row g-2">
                <div class="col-6"><div style="background:rgba(255,255,255,.07);border-radius:.75rem;padding:.75rem;text-align:center;"><div style="font-size:1.3rem;font-weight:800;"><?= number_format($latest['weight_kg'],1) ?> <small style="font-size:.75rem;color:rgba(255,255,255,.5);">kg</small></div><div style="font-size:.72rem;color:rgba(255,255,255,.5);text-transform:uppercase;">Weight</div></div></div>
                <div class="col-6"><div style="background:rgba(255,255,255,.07);border-radius:.75rem;padding:.75rem;text-align:center;"><div style="font-size:1.3rem;font-weight:800;"><?= number_format($latest['height_cm'],1) ?> <small style="font-size:.75rem;color:rgba(255,255,255,.5);">cm</small></div><div style="font-size:.72rem;color:rgba(255,255,255,.5);text-transform:uppercase;">Height</div></div></div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Chart & Records Row -->
<?php if (count($records) >= 2): ?>
<div class="row g-4 mb-4">
    <!-- Chart Column -->
    <div class="col-md-7">
        <div class="up-card h-100 mb-0">
            <div class="up-card-header"><h6 class="up-card-title"><i class="fa-solid fa-chart-line me-2 text-success"></i>BMI Over Time</h6></div>
            <div class="up-card-body chart-card-body">
                <canvas id="bmiFullChart" height="105"></canvas>
                <div class="bmi-range-pills">
                    <span class="bmi-range-pill" style="background: linear-gradient(135deg, #17a2b8, #138496);"><i class="fa-solid fa-circle" style="font-size:0.4rem;opacity:0.7;"></i> Underweight &lt; 18.5</span>
                    <span class="bmi-range-pill" style="background: linear-gradient(135deg, #22c55e, #16a34a);"><i class="fa-solid fa-circle" style="font-size:0.4rem;opacity:0.7;"></i> Normal 18.5–24.9</span>
                    <span class="bmi-range-pill" style="background: linear-gradient(135deg, #f59e0b, #d97706);"><i class="fa-solid fa-circle" style="font-size:0.4rem;opacity:0.7;"></i> Overweight 25–29.9</span>
                    <span class="bmi-range-pill" style="background: linear-gradient(135deg, #ef4444, #dc2626);"><i class="fa-solid fa-circle" style="font-size:0.4rem;opacity:0.7;"></i> Obese 30+</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Record Cards Column -->
    <div class="col-md-5">
        <div class="up-card h-100 mb-0">
            <div class="up-card-header"><h6 class="up-card-title"><i class="fa-solid fa-list me-2" style="color:#9ca3af;"></i>All Records</h6></div>
            <div class="up-card-body records-scroll">
        <?php foreach ($records as $r):
            $bv = (float)$r['bmi_value'];
            $cc2 = bmi_color_class($bv);
            $bgMap = [
                'info'=>'linear-gradient(135deg, #17a2b8, #138496)',
                'success'=>'linear-gradient(135deg, #22c55e, #16a34a)',
                'warning'=>'linear-gradient(135deg, #f59e0b, #d97706)',
                'danger'=>'linear-gradient(135deg, #ef4444, #dc2626)'
            ];
            $bg = $bgMap[$cc2] ?? '#6b7280';
        ?>
        <div class="bmi-record-card">
            <div class="bmi-val-badge" style="background:<?= $bg ?>;">
                <div class="val"><?= number_format($bv,1) ?></div>
                <div class="lbl">BMI</div>
            </div>
            <div class="flex-grow-1">
                <div class="fw-bold" style="font-size:1rem;color:#1a1a2e;margin-bottom:0.15rem;"><?= htmlspecialchars($r['category']) ?></div>
                <div class="bmi-meta" style="font-size:0.8rem;">
                    <i class="fa-solid fa-weight-scale me-1" style="opacity:0.6;"></i><?= number_format($r['weight_kg'],1) ?> kg 
                    <span style="opacity:0.4;margin:0 4px;">|</span> 
                    <?= number_format($r['height_cm'],1) ?> cm
                </div>
            </div>
            <div class="text-end">
                <div style="font-size:0.85rem;font-weight:700;color:#374151;"><?= date('M d, Y', strtotime($r['recorded_at'])) ?></div>
                <div style="font-size:0.75rem;color:#9ca3af;margin-top:0.15rem;"><i class="fa-regular fa-clock me-1" style="opacity:0.6;"></i><?= date('h:i A', strtotime($r['recorded_at'])) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
        </div>
    </div>
</div>
<?php else: ?>
<!-- Record Cards (Fallback if < 2 records) -->
<div class="up-card mb-4">
    <div class="up-card-header"><h6 class="up-card-title"><i class="fa-solid fa-list me-2" style="color:#9ca3af;"></i>All Records</h6></div>
    <div class="up-card-body">
        <?php foreach ($records as $r):
            $bv = (float)$r['bmi_value'];
            $cc2 = bmi_color_class($bv);
            $bgMap = ['info'=>'#17a2b8','success'=>'#22c55e','warning'=>'#f59e0b','danger'=>'#ef4444'];
            $bg = $bgMap[$cc2] ?? '#6b7280';
        ?>
        <div class="bmi-record-card">
            <div class="bmi-val-badge" style="background:<?= $bg ?>;"><?= number_format($bv,1) ?></div>
            <div class="flex-grow-1">
                <div class="fw-bold" style="font-size:.9rem;color:#1a1a2e;"><?= htmlspecialchars($r['category']) ?></div>
                <div class="bmi-meta">
                    <?= number_format($r['weight_kg'],1) ?> kg · <?= number_format($r['height_cm'],1) ?> cm
                    · <?= htmlspecialchars(bmi_format_age($r['age'])) ?>
                    · <?= htmlspecialchars(bmi_format_gender($r['gender'])) ?>
                </div>
            </div>
            <div class="text-muted text-end" style="font-size:.78rem;">
                <div><?= date('M d, Y', strtotime($r['recorded_at'])) ?></div>
                <div><?= date('h:i A', strtotime($r['recorded_at'])) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>
</main></div></div></div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const bmiCtx = document.getElementById('bmiFullChart');
if (bmiCtx) {
    const vals = <?= json_encode(array_map('floatval', $chartValues)) ?>;
    new Chart(bmiCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode(array_map(fn($d) => date('M d', strtotime($d)), $chartLabels)) ?>,
            datasets: [{
                label: 'BMI', data: vals,
                borderColor: '#FF6B35', backgroundColor: 'rgba(255,107,53,0.06)',
                borderWidth: 3, fill: true, tension: 0.45,
                pointBackgroundColor: ctx => { const v = vals[ctx.dataIndex]; return v<18.5?'#17a2b8':v<25?'#22c55e':v<30?'#f59e0b':'#ef4444'; },
                pointRadius: 6, pointHoverRadius: 9, pointBorderColor: '#fff', pointBorderWidth: 2.5,
                pointHoverBorderWidth: 3, pointHoverBorderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: { min: 12, suggestedMax: 35, grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { stepSize: 5 } },
                x: { grid: { display: false } }
            }
        }
    });
}
</script>
<?php require_once '../includes/footer.php'; ?>
