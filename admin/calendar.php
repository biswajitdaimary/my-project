<?php
require_once '../config/config.php';
require_once '../helpers/auth_check.php';
require_admin();
$pageTitle = 'Calendar & Events';
$csrf = $_SESSION['csrf_token'];

// Fetch trainers for dropdowns
$trainers = $pdo->query("SELECT trainer_id, full_name FROM trainers WHERE is_active=1 ORDER BY full_name")->fetchAll();
require_once 'includes/admin_header.php';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css">
<style>
/* ── Page Header ─────────────────────────────────────────── */
.cal-page-header{
  background:linear-gradient(135deg,#1A1A2E 0%,#16213e 60%,#0f3460 100%);
  border-radius:18px;padding:1.5rem 1.75rem;margin-bottom:1.25rem;
  display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;
  box-shadow:0 8px 32px rgba(26,26,46,.35);
}
.cal-page-header h4{color:#fff;font-weight:800;margin:0;font-size:1.25rem;display:flex;align-items:center;gap:.6rem;}
.cal-page-header p{color:rgba(255,255,255,.55);font-size:.82rem;margin:0;}
.cal-page-header .header-accent{color:#FF6B35;}
.hdr-actions{display:flex;gap:.6rem;flex-wrap:wrap;}
.btn-cal-primary{
  background:linear-gradient(135deg,#FF6B35,#ff8c5a);color:#fff;border:none;
  font-weight:700;font-size:.85rem;padding:.55rem 1.1rem;border-radius:10px;
  display:inline-flex;align-items:center;gap:.4rem;cursor:pointer;
  box-shadow:0 4px 15px rgba(255,107,53,.35);transition:all .2s;
}
.btn-cal-primary:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(255,107,53,.45);color:#fff;}
.btn-cal-secondary{
  background:rgba(255,255,255,.1);color:#fff;border:1px solid rgba(255,255,255,.2);
  font-weight:600;font-size:.85rem;padding:.55rem 1.1rem;border-radius:10px;
  display:inline-flex;align-items:center;gap:.4rem;cursor:pointer;transition:all .2s;
}
.btn-cal-secondary:hover{background:rgba(255,255,255,.18);color:#fff;}
/* ── Layout ──────────────────────────────────────────────── */
.cal-wrap{display:flex;gap:1.1rem;height:calc(100vh - 210px);min-height:580px;}
.cal-sidebar{width:258px;flex-shrink:0;display:flex;flex-direction:column;gap:.7rem;}
.cal-main{flex:1;min-width:0;background:#fff;border-radius:16px;padding:1.1rem;
  box-shadow:0 4px 24px rgba(26,26,46,.1);overflow:hidden;border:1px solid #eef0f8;}
#adminCal{height:100%;}
/* ── FullCalendar Custom Theme ───────────────────────────── */
.fc .fc-toolbar-title{font-size:1.1rem;font-weight:800;color:#1A1A2E;}
.fc .fc-button-primary{
  background:#1A1A2E !important;border-color:#1A1A2E !important;
  font-weight:600;border-radius:8px !important;font-size:.78rem;
}
.fc .fc-button-primary:hover{background:#0f3460 !important;border-color:#0f3460 !important;}
.fc .fc-button-primary:not(:disabled).fc-button-active{
  background:#FF6B35 !important;border-color:#FF6B35 !important;
}
.fc-today-button{background:#FF6B35 !important;border-color:#FF6B35 !important;}
.fc .fc-daygrid-day.fc-day-today{background:rgba(255,107,53,.06) !important;}
.fc .fc-daygrid-day.fc-day-today .fc-daygrid-day-number{color:#FF6B35;font-weight:800;}
.fc-event{border-radius:5px !important;font-size:.74rem;font-weight:600;cursor:pointer;border:none !important;}
.fc-daygrid-event{margin:1px 2px !important;}
.fc .fc-col-header-cell{background:#f8f9fe;}
.fc .fc-col-header-cell-cushion{color:#1A1A2E;font-weight:700;font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;}
/* ── Sidebar Cards ───────────────────────────────────────── */
.sidebar-card{
  background:#fff;border-radius:14px;border:1px solid #eef0f8;
  padding:.9rem 1rem;box-shadow:0 2px 12px rgba(26,26,46,.06);
}
.sidebar-card .sc-title{
  font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.09em;
  color:#94a3b8;margin-bottom:.7rem;display:flex;align-items:center;gap:.4rem;
}
.sidebar-card .sc-title i{color:#FF6B35;}
/* ── Filter Chips ────────────────────────────────────────── */
.filter-chip{
  display:flex;align-items:center;gap:.55rem;padding:.38rem .65rem;
  border-radius:8px;font-size:.8rem;font-weight:600;cursor:pointer;
  margin-bottom:.25rem;transition:background .15s;
}
.filter-chip:hover{background:#f8f9fe;}
.filter-chip input{accent-color:#FF6B35;width:14px;height:14px;cursor:pointer;flex-shrink:0;}
.filter-dot{width:9px;height:9px;border-radius:50%;flex-shrink:0;}
/* ── Upcoming List ───────────────────────────────────────── */
.upcoming-item{
  display:flex;align-items:center;gap:.6rem;padding:.45rem .1rem;
  border-bottom:1px solid #f1f5f9;
}
.upcoming-item:last-child{border-bottom:none;}
.ev-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0;}
.ev-title{font-size:.79rem;font-weight:600;color:#1A1A2E;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.ev-date{font-size:.69rem;color:#94a3b8;margin-top:.05rem;}
/* ── Event Popover ───────────────────────────────────────── */
.ev-popover{
  position:fixed;z-index:2000;background:#fff;border-radius:16px;
  box-shadow:0 20px 60px rgba(26,26,46,.22);padding:1.25rem;width:300px;
  display:none;border:1px solid #eef0f8;
}
.ev-popover.open{display:block;animation:popIn .15s ease;}
@keyframes popIn{from{opacity:0;transform:scale(.95)}to{opacity:1;transform:scale(1)}}
.ev-pop-title{font-size:1rem;font-weight:800;color:#1A1A2E;margin-bottom:.2rem;}
.ev-pop-badge{
  font-size:.65rem;font-weight:800;text-transform:uppercase;
  padding:.18rem .55rem;border-radius:100px;display:inline-flex;
  align-items:center;gap:.3rem;margin-bottom:.65rem;
}
.ev-pop-row{font-size:.79rem;color:#64748b;margin-bottom:.25rem;display:flex;align-items:flex-start;gap:.5rem;}
.ev-pop-row i{width:14px;flex-shrink:0;margin-top:2px;color:#FF6B35;}
.ev-pop-divider{border:none;border-top:1px solid #f1f5f9;margin:.65rem 0;}
/* ── Modals ──────────────────────────────────────────────── */
.modal-form-sm .form-label{font-size:.79rem;font-weight:700;color:#374151;margin-bottom:.2rem;}
.modal-form-sm .form-control,
.modal-form-sm .form-select{
  font-size:.85rem;border-radius:9px;
  border:1.5px solid #e8ecf4;background:#f8f9fe;
  transition:border .15s;
}
.modal-form-sm .form-control:focus,
.modal-form-sm .form-select:focus{
  border-color:#FF6B35;box-shadow:0 0 0 3px rgba(255,107,53,.1);background:#fff;
}
.modal-cal-header{
  background:linear-gradient(135deg,#1A1A2E,#16213e);
  border-radius:12px 12px 0 0;padding:1.25rem 1.5rem;
}
.modal-cal-header h5{color:#fff;font-weight:800;margin:0;}
.modal-cal-header .btn-close{filter:invert(1) opacity(.6);}
@media(max-width:900px){
  .cal-wrap{flex-direction:column;height:auto;}
  .cal-sidebar{width:100%;flex-direction:row;flex-wrap:wrap;}
  .cal-main{height:480px;}
}

.cal-wrap{display:flex;gap:1.25rem;height:calc(100vh - 140px);min-height:600px;}
.cal-sidebar{width:260px;flex-shrink:0;display:flex;flex-direction:column;gap:.75rem;}
.cal-main{flex:1;min-width:0;background:#fff;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,.06);padding:1.25rem;overflow:hidden;}
#adminCal{height:100%;}
.fc .fc-toolbar-title{font-size:1.15rem;font-weight:800;color:#1e293b;}
.fc .fc-button{background:#0ea5e9;border-color:#0ea5e9;font-weight:600;border-radius:8px !important;font-size:.8rem;}
.fc .fc-button:hover{background:#0284c7;border-color:#0284c7;}
.fc .fc-button-active{background:#0284c7 !important;}
.fc-event{border-radius:6px !important;font-size:.75rem;font-weight:600;cursor:pointer;}
.fc-daygrid-dot-event .fc-event-title{font-weight:600;}
.sidebar-card{background:#fff;border-radius:14px;box-shadow:0 2px 10px rgba(0,0,0,.06);padding:1rem;}
.sidebar-card h6{font-size:.8rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#94a3b8;margin-bottom:.75rem;}
.filter-chip{display:flex;align-items:center;gap:.5rem;padding:.35rem .65rem;border-radius:8px;font-size:.8rem;font-weight:600;cursor:pointer;margin-bottom:.3rem;transition:all .15s;}
.filter-chip input{accent-color:currentColor;width:14px;height:14px;cursor:pointer;}
.upcoming-item{display:flex;align-items:center;gap:.6rem;padding:.45rem 0;border-bottom:1px solid #f1f5f9;}
.upcoming-item:last-child{border-bottom:none;}
.ev-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0;}
.ev-title{font-size:.8rem;font-weight:600;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.ev-date{font-size:.7rem;color:#94a3b8;}
/* Detail popover */
.ev-popover{position:fixed;z-index:2000;background:#fff;border-radius:14px;box-shadow:0 12px 40px rgba(0,0,0,.18);padding:1.25rem;width:300px;display:none;}
.ev-popover.open{display:block;}
.ev-pop-title{font-size:1rem;font-weight:800;color:#1e293b;margin-bottom:.25rem;}
.ev-pop-badge{font-size:.65rem;font-weight:700;text-transform:uppercase;padding:.18rem .55rem;border-radius:100px;display:inline-flex;align-items:center;gap:.3rem;margin-bottom:.65rem;}
.ev-pop-row{font-size:.8rem;color:#64748b;margin-bottom:.25rem;display:flex;align-items:flex-start;gap:.5rem;}
.ev-pop-row i{width:14px;flex-shrink:0;margin-top:2px;}
.modal-form-sm .form-label{font-size:.8rem;font-weight:700;color:#374151;margin-bottom:.25rem;}
.modal-form-sm .form-control,.modal-form-sm .form-select{font-size:.85rem;border-radius:8px;border:1.5px solid #e2e8f0;}
.modal-form-sm .form-control:focus,.modal-form-sm .form-select:focus{border-color:#0ea5e9;box-shadow:0 0 0 3px rgba(14,165,233,.1);}
.cat-badge{padding:.2rem .6rem;border-radius:100px;font-size:.7rem;font-weight:700;text-transform:uppercase;}
@media(max-width:900px){.cal-wrap{flex-direction:column;height:auto;}.cal-sidebar{width:100%;flex-direction:row;flex-wrap:wrap;overflow-x:auto;}.cal-main{height:500px;}}
</style>

<!-- Page Header -->
<div class="cal-page-header">
  <div>
    <h4><i class="fa-solid fa-calendar-days header-accent"></i> Calendar &amp; Events</h4>
    <p>Manage gym events, holidays, reminders and announcements in one place.</p>
  </div>
  <div class="hdr-actions">
    <button class="btn-cal-primary" onclick="openEventModal()">
      <i class="fa-solid fa-plus"></i> Add Event
    </button>
    <button class="btn-cal-secondary" onclick="openHolidayModal()">
      <i class="fa-solid fa-umbrella-beach"></i> Add Holiday
    </button>
  </div>
</div>

<div class="cal-wrap">
  <!-- ── Sidebar ─────────────────────────────── -->
  <div class="cal-sidebar">

    <!-- Quick Stats -->
    <div class="sidebar-card" style="background:linear-gradient(135deg,#1A1A2E,#0f3460);border:none;">
      <div class="sc-title" style="color:rgba(255,255,255,.4);"><i class="fa-solid fa-chart-simple"></i> This Month</div>
      <div class="d-flex justify-content-between">
        <div style="text-align:center;">
          <div style="font-size:1.4rem;font-weight:800;color:#FF6B35;" id="statEvents">—</div>
          <div style="font-size:.65rem;color:rgba(255,255,255,.45);font-weight:600;text-transform:uppercase;">Events</div>
        </div>
        <div style="text-align:center;">
          <div style="font-size:1.4rem;font-weight:800;color:#a78bfa;" id="statReminders">—</div>
          <div style="font-size:.65rem;color:rgba(255,255,255,.45);font-weight:600;text-transform:uppercase;">Reminders</div>
        </div>
        <div style="text-align:center;">
          <div style="font-size:1.4rem;font-weight:800;color:#ef4444;" id="statHolidays">—</div>
          <div style="font-size:.65rem;color:rgba(255,255,255,.45);font-weight:600;text-transform:uppercase;">Holidays</div>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="sidebar-card">
      <div class="sc-title"><i class="fa-solid fa-filter"></i> Filters</div>
      <?php
      $filters=[
        ['holiday','#ef4444','Holidays'],
        ['gym_event','#FF6B35','Gym Events'],
        ['reminder','#8b5cf6','Reminders'],
        ['announcement','#f59e0b','Announcements'],
      ];
      foreach($filters as [$k,$c,$l]):?>
      <label class="filter-chip">
        <input type="checkbox" checked id="flt_<?=$k?>" onchange="toggleFilter('<?=$k?>')">
        <span class="filter-dot" style="background:<?=$c?>"></span>
        <span style="color:#374151;"><?=$l?></span>
      </label>
      <?php endforeach;?>
    </div>

    <!-- Upcoming Events -->
    <div class="sidebar-card" style="flex:1;overflow:hidden;display:flex;flex-direction:column;">
      <div class="sc-title"><i class="fa-solid fa-clock-rotate-left"></i> Upcoming</div>
      <div id="upcomingList" style="overflow-y:auto;flex:1;"></div>
    </div>
  </div>

  <!-- ── Main Calendar ───────────────────────── -->
  <div class="cal-main">
    <div id="adminCal"></div>
  </div>
</div>


<!-- Event Detail Popover -->
<div class="ev-popover" id="evPopover">
  <button onclick="closePopover()" style="position:absolute;top:.75rem;right:.75rem;background:#f8f9fe;border:none;border-radius:50%;width:28px;height:28px;font-size:.85rem;color:#94a3b8;cursor:pointer;display:flex;align-items:center;justify-content:center;"><i class="fa-solid fa-xmark"></i></button>
  <div id="evPopTitle" class="ev-pop-title"></div>
  <div id="evPopBadge" class="ev-pop-badge"></div>
  <hr class="ev-pop-divider">
  <div id="evPopBody"></div>
  <div id="evPopActions" class="d-flex gap-2 mt-3"></div>
</div>
<div id="evPopOverlay" onclick="closePopover()" style="display:none;position:fixed;inset:0;z-index:1999;"></div>

<!-- Add/Edit Event Modal -->
<div class="modal fade" id="eventModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content border-0 shadow-lg modal-form-sm" style="border-radius:16px;overflow:hidden;">
      <div class="modal-cal-header d-flex align-items-center justify-content-between">
        <h5 class="modal-title" id="evModalTitle"><i class="fa-solid fa-calendar-plus me-2" style="color:#FF6B35;"></i>New Event</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body px-4">
        <input type="hidden" id="ev_id" value="0">
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label">Event Title *</label>
            <input type="text" class="form-control" id="ev_title" placeholder="e.g. Morning Yoga Class" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Date *</label>
            <input type="date" class="form-control" id="ev_date">
          </div>
          <div class="col-md-6">
            <label class="form-label">Category</label>
            <select class="form-select" id="ev_cat" onchange="syncColor()">
              <option value="announcement">📢 Announcement</option>
              <option value="class">🏋 Class</option>
              <option value="program">🎯 Program</option>
              <option value="special">⭐ Special Event</option>
              <option value="reminder">🔔 Reminder</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Visibility</label>
            <select class="form-select" id="ev_vis">
              <option value="all">All (Members + Trainers)</option>
              <option value="members">Members Only</option>
              <option value="trainers">Trainers Only</option>
              <option value="admin">Admin Only</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Color</label>
            <input type="color" class="form-control form-control-color w-100" id="ev_color" value="#0ea5e9">
          </div>
          <div class="col-md-3">
            <label class="form-label">Start Time</label>
            <input type="time" class="form-control" id="ev_start">
          </div>
          <div class="col-md-3">
            <label class="form-label">End Time</label>
            <input type="time" class="form-control" id="ev_end">
          </div>
          <div class="col-md-3">
            <label class="form-label">Max Capacity</label>
            <input type="number" class="form-control" id="ev_cap" placeholder="Unlimited">
          </div>
          <div class="col-md-3">
            <label class="form-label">Assign Trainer</label>
            <select class="form-select" id="ev_trainer">
              <option value="">— None —</option>
              <?php foreach($trainers as $t): ?>
              <option value="<?=$t['trainer_id']?>"><?=htmlspecialchars($t['full_name'])?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label">Description</label>
            <textarea class="form-control" id="ev_desc" rows="2" placeholder="Optional details..."></textarea>
          </div>
          <div class="col-12">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="ev_allday" checked>
              <label class="form-check-label fw-600" for="ev_allday">All-day event</label>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer border-0 px-4 pb-4 gap-2" style="background:#f8f9fe;">
        <button type="button" class="btn btn-light rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn rounded-pill px-4 fw-bold" style="background:linear-gradient(135deg,#FF6B35,#ff8c5a);color:#fff;border:none;box-shadow:0 4px 15px rgba(255,107,53,.3);" onclick="saveEvent()"><i class="fa-solid fa-save me-1"></i> Save Event</button>
      </div>
    </div>
  </div>
</div>

<!-- Holiday Modal -->
<div class="modal fade" id="holModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow-lg modal-form-sm" style="border-radius:16px;overflow:hidden;">
      <div class="modal-cal-header d-flex align-items-center justify-content-between">
        <h5 class="modal-title"><i class="fa-solid fa-umbrella-beach me-2" style="color:#ef4444;"></i>Add Holiday</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body px-4">
        <input type="hidden" id="hol_id" value="0">
        <div class="mb-3"><label class="form-label">Title *</label><input type="text" class="form-control" id="hol_title"></div>
        <div class="row g-2 mb-3">
          <div class="col-8"><label class="form-label">Date *</label><input type="date" class="form-control" id="hol_date"></div>
          <div class="col-4"><label class="form-label">Color</label><input type="color" class="form-control form-control-color w-100" id="hol_color" value="#ef4444"></div>
        </div>
        <div class="mb-3">
          <label class="form-label">Type</label>
          <select class="form-select" id="hol_type"><option value="full">Full Day</option><option value="partial">Partial Day</option></select>
        </div>
        <div class="mb-3">
          <label class="form-label">Applies To</label>
          <select class="form-select" id="hol_target" onchange="document.getElementById('holTrainersRow').style.display=this.value==='specific'?'block':'none'">
            <option value="all">All Trainers</option><option value="specific">Specific Trainers</option>
          </select>
        </div>
        <div class="mb-3" id="holTrainersRow" style="display:none">
          <label class="form-label">Select Trainers</label>
          <select class="form-select" id="hol_trainers" multiple style="height:100px">
            <?php foreach($trainers as $t): ?><option value="<?=$t['trainer_id']?>"><?=htmlspecialchars($t['full_name'])?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="mb-1"><label class="form-label">Description</label><textarea class="form-control" id="hol_desc" rows="2"></textarea></div>
      </div>
      <div class="modal-footer border-0 px-4 pb-4 gap-2" style="background:#f8f9fe;">
        <button class="btn btn-light rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-danger rounded-pill px-4 fw-bold" onclick="saveHoliday()"><i class="fa-solid fa-umbrella-beach me-1"></i> Save Holiday</button>
      </div>
    </div>
  </div>
</div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<script>
const CSRF     = '<?= $csrf ?>';
const API      = '<?= SITE_URL ?>/api/calendar.php';
const CAT_COLORS = {announcement:'#f59e0b',class:'#8b5cf6',program:'#22c55e',special:'#ec4899',reminder:'#6366f1'};
let calendar, evModal, holModal;
let hiddenTypes = new Set();

document.addEventListener('DOMContentLoaded', () => {
  evModal  = new bootstrap.Modal(document.getElementById('eventModal'));
  holModal = new bootstrap.Modal(document.getElementById('holModal'));

  const ALLOWED_TYPES = new Set(['holiday','gym_event','reminder','announcement']);

  calendar = new FullCalendar.Calendar(document.getElementById('adminCal'), {
    initialView: 'dayGridMonth',
    headerToolbar: { left:'prev,next today', center:'title', right:'dayGridMonth,timeGridWeek,timeGridDay,listWeek' },
    height: '100%',
    nowIndicator: true,
    editable: false,
    selectable: true,
    eventSources: [{
      url: API + '?action=events',
      method: 'GET',
      extraParams: {},
      success: function(data) {
        // Only return the 4 allowed types
        return data.filter(e => ALLOWED_TYPES.has(e.extendedProps?.type));
      }
    }],
    eventDidMount(info) {
      const t = info.event.extendedProps.type;
      const cat = info.event.extendedProps.category;
      if (hiddenTypes.has(t) || hiddenTypes.has(cat)) {
        info.el.style.display = 'none';
      }
    },
    eventClick(info) { showEventPopover(info.event, info.jsEvent); },
    select(info) { openEventModal(info.startStr); },
  });
  calendar.render();
  loadUpcoming();
  loadStats();
});

function loadUpcoming() {
  fetch(API + '?action=upcoming&limit=8')
    .then(r => r.json()).then(data => {
      const el = document.getElementById('upcomingList');
      if (!data.length) { el.innerHTML = '<div style="font-size:.8rem;color:#94a3b8;text-align:center;padding:1rem">No upcoming events</div>'; return; }
      el.innerHTML = data.map(e => `
        <div class="upcoming-item">
          <div class="ev-dot" style="background:${e.color||'#0ea5e9'}"></div>
          <div style="min-width:0">
            <div class="ev-title">${esc(e.title)}</div>
            <div class="ev-date">${fmtDate(e.event_date)}${e.start_time?' · '+fmtTime(e.start_time):''}</div>
          </div>
        </div>`).join('');
    });
}

function showEventPopover(event, jsEvent) {
  const ep = event.extendedProps;
  const pop = document.getElementById('evPopover');
  document.getElementById('evPopTitle').textContent = event.title;

  const typeLabel = ep.type === 'holiday' ? 'Holiday' : ep.type === 'booking' ? 'Booking' : ep.category || 'Event';
  const bg = event.backgroundColor || '#0ea5e9';
  document.getElementById('evPopBadge').innerHTML = `<span style="background:${bg}22;color:${bg}">${typeLabel.toUpperCase()}</span>`;

  let body = '';
  if (ep.description) body += `<div class="ev-pop-row"><i class="fa-regular fa-file-lines"></i>${esc(ep.description)}</div>`;
  if (ep.trainer_name) body += `<div class="ev-pop-row"><i class="fa-solid fa-user-tie"></i>${esc(ep.trainer_name)}</div>`;
  if (ep.client_name) body += `<div class="ev-pop-row"><i class="fa-solid fa-user"></i>${esc(ep.client_name)}</div>`;
  if (ep.max_capacity) body += `<div class="ev-pop-row"><i class="fa-solid fa-users"></i>Capacity: ${ep.reg_count||0} / ${ep.max_capacity}</div>`;
  if (ep.status) body += `<div class="ev-pop-row"><i class="fa-solid fa-circle-dot"></i>Status: ${ep.status}</div>`;
  document.getElementById('evPopBody').innerHTML = body;

  let actions = '';
  if (ep.type === 'gym_event') {
    actions = `<button class="btn btn-sm btn-outline-primary rounded-pill" onclick="editEvent(${ep.event_id})"><i class="fa-solid fa-pen me-1"></i>Edit</button>
               <button class="btn btn-sm btn-outline-danger rounded-pill" onclick="deleteEvent(${ep.event_id})"><i class="fa-solid fa-trash me-1"></i>Delete</button>`;
  } else if (ep.type === 'holiday') {
    actions = `<button class="btn btn-sm btn-outline-primary rounded-pill" onclick="editHoliday(${ep.holiday_id})"><i class="fa-solid fa-pen me-1"></i>Edit</button>
               <button class="btn btn-sm btn-outline-danger rounded-pill" onclick="deleteHoliday(${ep.holiday_id})"><i class="fa-solid fa-trash me-1"></i>Remove</button>`;
  }
  document.getElementById('evPopActions').innerHTML = actions;

  // Position popover
  const x = Math.min(jsEvent.clientX + 10, window.innerWidth - 320);
  const y = Math.min(jsEvent.clientY + 10, window.innerHeight - 300);
  pop.style.left = x + 'px';
  pop.style.top  = y + 'px';
  pop.classList.add('open');
  document.getElementById('evPopOverlay').style.display = 'block';
}

function closePopover() {
  document.getElementById('evPopover').classList.remove('open');
  document.getElementById('evPopOverlay').style.display = 'none';
}

function openEventModal(date = '') {
  closePopover();
  document.getElementById('ev_id').value    = '0';
  document.getElementById('ev_title').value = '';
  document.getElementById('ev_date').value  = date || new Date().toISOString().slice(0,10);
  document.getElementById('ev_cat').value   = 'announcement';
  document.getElementById('ev_vis').value   = 'all';
  document.getElementById('ev_color').value = '#f59e0b';
  document.getElementById('ev_start').value = '';
  document.getElementById('ev_end').value   = '';
  document.getElementById('ev_cap').value   = '';
  document.getElementById('ev_trainer').value = '';
  document.getElementById('ev_desc').value  = '';
  document.getElementById('ev_allday').checked = true;
  document.getElementById('evModalTitle').textContent = 'New Event';
  evModal.show();
}

function editEvent(id) {
  closePopover();
  fetch(API + '?action=events&start=2020-01-01&end=2030-12-31').then(r => r.json()).then(evs => {
    const ev = evs.find(e => e.extendedProps?.event_id == id);
    if (!ev) return;
    const ep = ev.extendedProps;
    document.getElementById('ev_id').value    = id;
    document.getElementById('ev_title').value = ev.title;
    document.getElementById('ev_date').value  = ev.start?.slice(0,10) || '';
    document.getElementById('ev_cat').value   = ep.category || 'announcement';
    document.getElementById('ev_vis').value   = ep.visibility || 'all';
    document.getElementById('ev_color').value = ev.backgroundColor || '#0ea5e9';
    document.getElementById('ev_desc').value  = ep.description || '';
    document.getElementById('evModalTitle').textContent = 'Edit Event';
    evModal.show();
  });
}

function saveEvent() {
  const id = document.getElementById('ev_id').value;
  const data = new URLSearchParams({
    action:'save_event', csrf_token:CSRF,
    event_id: id,
    title:       document.getElementById('ev_title').value,
    event_date:  document.getElementById('ev_date').value,
    category:    document.getElementById('ev_cat').value,
    visibility:  document.getElementById('ev_vis').value,
    color:       document.getElementById('ev_color').value,
    start_time:  document.getElementById('ev_start').value,
    end_time:    document.getElementById('ev_end').value,
    max_capacity:document.getElementById('ev_cap').value,
    trainer_id:  document.getElementById('ev_trainer').value,
    description: document.getElementById('ev_desc').value,
    all_day:     document.getElementById('ev_allday').checked ? '1' : '',
  });
  fetch(API, {method:'POST', body:data}).then(r=>r.json()).then(res => {
    if (res.success) { evModal.hide(); calendar.refetchEvents(); loadUpcoming(); showToast(id=='0'?'Event created!':'Event updated!'); }
    else showToast(res.error||'Error','danger');
  });
}

function deleteEvent(id) {
  if (!confirm('Delete this event?')) return;
  closePopover();
  fetch(API, {method:'POST', body:new URLSearchParams({action:'delete_event',csrf_token:CSRF,event_id:id})})
    .then(r=>r.json()).then(()=>{ calendar.refetchEvents(); loadUpcoming(); showToast('Event deleted'); });
}

function openHolidayModal() {
  closePopover();
  document.getElementById('hol_id').value='0';
  document.getElementById('hol_title').value='';
  document.getElementById('hol_date').value=new Date().toISOString().slice(0,10);
  document.getElementById('hol_color').value='#ef4444';
  document.getElementById('hol_type').value='full';
  document.getElementById('hol_target').value='all';
  document.getElementById('holTrainersRow').style.display='none';
  Array.from(document.getElementById('hol_trainers').options).forEach(opt => { opt.selected = false; });
  document.getElementById('hol_desc').value='';
  holModal.show();
}

function saveHoliday() {
  const tids = Array.from(document.getElementById('hol_trainers').selectedOptions).map(o=>o.value);
  const data = new URLSearchParams({
    action:'save_holiday', csrf_token:CSRF,
    holiday_id:   document.getElementById('hol_id').value,
    title:        document.getElementById('hol_title').value,
    holiday_date: document.getElementById('hol_date').value,
    color:        document.getElementById('hol_color').value,
    type:         document.getElementById('hol_type').value,
    target_type:  document.getElementById('hol_target').value,
    description:  document.getElementById('hol_desc').value,
  });
  tids.forEach(t => data.append('trainer_ids[]', t));
  fetch(API, {method:'POST', body:data}).then(r=>r.json()).then(res=>{
    if(res.success){ holModal.hide(); calendar.refetchEvents(); loadStats(); showToast('Holiday saved!'); }
    else showToast(res.error||'Error','danger');
  });
}

function editHoliday(id) {
  closePopover();
  fetch(API + '?action=events&start=2020-01-01&end=2030-12-31').then(r => r.json()).then(evs => {
    const ev = evs.find(e => e.extendedProps?.type === 'holiday' && e.extendedProps?.holiday_id == id);
    if (!ev) return;
    const ep = ev.extendedProps;
    document.getElementById('hol_id').value    = id;
    document.getElementById('hol_title').value = ev.title.replace('🏖 ', '');
    document.getElementById('hol_date').value  = ev.start?.slice(0,10) || '';
    document.getElementById('hol_color').value = ev.backgroundColor || '#ef4444';
    document.getElementById('hol_type').value  = ep.hol_type || 'full';
    document.getElementById('hol_target').value = ep.target_type || 'all';
    document.getElementById('holTrainersRow').style.display = (ep.target_type === 'specific') ? 'block' : 'none';
    const selectedTrainerIds = (Array.isArray(ep.trainer_ids) ? ep.trainer_ids : []).map(String);
    Array.from(document.getElementById('hol_trainers').options).forEach(opt => {
      opt.selected = selectedTrainerIds.includes(String(opt.value));
    });
    document.getElementById('hol_desc').value  = ep.description || '';
    holModal.show();
  });
}

function deleteHoliday(id) {
  if(!confirm('Remove this holiday?')) return;
  closePopover();
  fetch(API,{method:'POST',body:new URLSearchParams({action:'delete_holiday',csrf_token:CSRF,holiday_id:id})})
    .then(()=>{ calendar.refetchEvents(); showToast('Holiday removed'); });
}

function toggleFilter(key) {
  if(hiddenTypes.has(key)) hiddenTypes.delete(key); else hiddenTypes.add(key);
  calendar.refetchEvents();
}

function syncColor() {
  document.getElementById('ev_color').value = CAT_COLORS[document.getElementById('ev_cat').value]||'#0ea5e9';
}

function loadStats() {
  const now = new Date();
  const y = now.getFullYear(), m = String(now.getMonth()+1).padStart(2,'0');
  const start = `${y}-${m}-01`, end = `${y}-${m}-31`;
  fetch(`${API}?action=events&start=${start}&end=${end}`).then(r=>r.json()).then(evs=>{
    let ev=0,rem=0,hol=0;
    evs.forEach(e=>{
      const t=e.extendedProps?.type;
      if(t==='gym_event') ev++;
      else if(t==='reminder') rem++;
      else if(t==='holiday') hol++;
    });
    document.getElementById('statEvents').textContent=ev;
    document.getElementById('statReminders').textContent=rem;
    document.getElementById('statHolidays').textContent=hol;
  }).catch(()=>{});
}

function showToast(msg, type='success') {
  const t = document.createElement('div');
  const bg = type==='success' ? 'linear-gradient(135deg,#1A1A2E,#0f3460)' : 'linear-gradient(135deg,#ef4444,#dc2626)';
  t.style.cssText=`position:fixed;bottom:1.5rem;right:1.5rem;background:${bg};color:#fff;padding:.75rem 1.25rem;border-radius:12px;font-size:.84rem;font-weight:600;z-index:9999;box-shadow:0 8px 30px rgba(0,0,0,.25);display:flex;align-items:center;gap:.5rem;`;
  t.innerHTML=`<i class="fa-solid ${type==='success'?'fa-circle-check':'fa-circle-exclamation'}"></i>${msg}`;
  document.body.appendChild(t);
  setTimeout(()=>t.remove(), 3000);
}

function esc(s){ const d=document.createElement('div');d.textContent=s||'';return d.innerHTML; }
function fmtDate(s){ return new Date(s+'T00:00:00').toLocaleDateString('en-US',{month:'short',day:'numeric'}); }
function fmtTime(s){ return new Date('2000-01-01T'+s).toLocaleTimeString('en-US',{hour:'numeric',minute:'2-digit'}); }
</script>
<?php require_once 'includes/admin_footer.php'; ?>
