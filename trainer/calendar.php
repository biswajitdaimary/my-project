<?php
require_once '../config/config.php';
require_once '../helpers/auth_check.php';
require_trainer();
$pageTitle = 'My Calendar';
$tid  = (int)$_SESSION['user_id'];
$csrf = $_SESSION['csrf_token'];

// Upcoming reminders for sidebar
$upReminders = [];
try {
    $ur = $pdo->prepare("
        SELECT title, reminder_date
        FROM calendar_reminders
        WHERE trainer_id = ?
          AND is_done = 0
        ORDER BY reminder_date ASC
        LIMIT 10
    ");
    $ur->execute([(int)$tid]);
    $dbReminders = $ur->fetchAll();

    $now = time();
    foreach ($dbReminders as $r) {
        $remTime = strtotime($r['reminder_date']);
        $r['is_overdue'] = ($remTime < $now);
        $upReminders[] = $r;
    }
} catch(Exception $e) {
    error_log("Reminder Query Error: " . $e->getMessage());
}

require_once 'includes/trainer_header.php';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css">
<style>
/* ═══════════════════════════════════════
   TRAINER CALENDAR — PREMIUM REDESIGN
   Theme: Navy #1a1a2e | Sky #0ea5e9
   ═══════════════════════════════════════ */

/* Page wrapper */
.cal-page { display: flex; gap: 1.5rem; height: calc(100vh - 110px); min-height: 660px; }

/* ── Sidebar ── */
.cal-sidebar {
  width: 270px; flex-shrink: 0;
  display: flex; flex-direction: column; gap: 1rem;
  overflow-y: auto;
}
.cal-sidebar::-webkit-scrollbar { width: 4px; }
.cal-sidebar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 4px; }

.cs-card {
  background: #fff;
  border-radius: 18px;
  border: 1px solid #edf2f7;
  box-shadow: 0 2px 12px rgba(0,0,0,0.04);
  padding: 1.1rem 1.2rem;
}

/* Quick action buttons */
.btn-add-reminder {
  display: flex; align-items: center; justify-content: center; gap: .5rem;
  width: 100%; padding: .7rem 1rem;
  background: linear-gradient(135deg, #0ea5e9, #0284c7);
  color: #fff; font-weight: 700; font-size: .85rem;
  border: none; border-radius: 12px; cursor: pointer;
  box-shadow: 0 4px 14px rgba(14,165,233,.35);
  transition: transform .15s, box-shadow .15s;
}
.btn-add-reminder:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(14,165,233,.45); }

.btn-manage-slots {
  display: flex; align-items: center; justify-content: center; gap: .5rem;
  width: 100%; padding: .7rem 1rem; margin-top: .6rem;
  background: #f8fafc; color: #475569; font-weight: 600; font-size: .85rem;
  border: 1.5px solid #e2e8f0; border-radius: 12px; cursor: pointer;
  text-decoration: none; transition: all .15s;
}
.btn-manage-slots:hover { background: #f1f5f9; border-color: #cbd5e1; color: #0f172a; }

/* Section heading */
.cs-heading {
  font-size: .68rem; font-weight: 800; text-transform: uppercase;
  letter-spacing: .1em; color: #94a3b8;
  margin-bottom: .8rem; display: flex; align-items: center; gap: .4rem;
}

/* Legend */
.legend-item {
  display: flex; align-items: center; gap: .6rem;
  font-size: .82rem; font-weight: 500; color: #475569;
  padding: .3rem 0;
}
.legend-pip {
  width: 10px; height: 10px; border-radius: 3px; flex-shrink: 0;
}

/* Mini stat row */
.stat-chip {
  display: flex; align-items: center; justify-content: space-between;
  padding: .5rem .7rem;
  background: #f8fafc; border-radius: 10px;
  font-size: .8rem; color: #334155; font-weight: 600;
  margin-bottom: .4rem;
}
.stat-chip .chip-num {
  background: #1a1a2e; color: #fff;
  border-radius: 6px; padding: .1rem .45rem;
  font-size: .72rem; font-weight: 800;
}

/* Reminder list */
.rem-item {
  display: flex; align-items: flex-start; gap: .7rem;
  padding: .55rem 0; border-bottom: 1px dashed #f1f5f9;
}
.rem-item:last-child { border-bottom: none; }
.rem-icon {
  width: 28px; height: 28px; border-radius: 8px; flex-shrink: 0;
  background: linear-gradient(135deg,#8b5cf6,#7c3aed);
  display: flex; align-items: center; justify-content: center;
  font-size: .7rem; color: #fff;
}
.rem-title { font-size: .82rem; font-weight: 700; color: #1e293b; line-height: 1.3; }
.rem-date  { font-size: .73rem; color: #94a3b8; margin-top: .1rem; font-weight: 500; }

.empty-state {
  text-align: center; padding: 1.2rem .5rem;
  font-size: .8rem; color: #94a3b8; font-weight: 500;
}
.empty-state i { display: block; font-size: 1.4rem; margin-bottom: .4rem; opacity: .4; }

/* ── Main Calendar ── */
.cal-main {
  flex: 1; min-width: 0;
  background: #fff;
  border-radius: 20px;
  border: 1px solid #edf2f7;
  box-shadow: 0 2px 12px rgba(0,0,0,0.04);
  padding: 1.25rem 1.5rem;
  overflow: hidden;
  display: flex; flex-direction: column;
}

/* ── FullCalendar overrides ── */
#trainerCal { flex: 1; min-height: 0; }

.fc .fc-toolbar { margin-bottom: 1rem; }
.fc .fc-toolbar-title {
  font-size: 1.25rem; font-weight: 800;
  color: #1a1a2e; letter-spacing: -.025em;
}

/* Toolbar buttons */
.fc .fc-button-group .fc-button,
.fc .fc-button {
  background: #fff !important;
  border: 1.5px solid #e2e8f0 !important;
  color: #475569 !important;
  font-weight: 600 !important;
  font-size: .8rem !important;
  border-radius: 10px !important;
  padding: .4rem .85rem !important;
  box-shadow: none !important;
  transition: all .15s !important;
}
.fc .fc-button:hover {
  background: #f8fafc !important;
  border-color: #0ea5e9 !important;
  color: #0ea5e9 !important;
}
.fc .fc-button-primary:not(:disabled).fc-button-active {
  background: #1a1a2e !important;
  border-color: #1a1a2e !important;
  color: #fff !important;
}
.fc .fc-today-button {
  background: linear-gradient(135deg,#0ea5e9,#0284c7) !important;
  border-color: transparent !important;
  color: #fff !important;
  box-shadow: 0 4px 10px rgba(14,165,233,.3) !important;
}

/* Grid */
.fc-theme-standard td, .fc-theme-standard th { border-color: #f1f5f9; }
.fc-theme-standard .fc-scrollgrid { border: none; }
.fc-col-header-cell-cushion {
  color: #64748b; font-weight: 700; font-size: .8rem;
  text-decoration: none !important; padding: .7rem 0 !important;
}
.fc-day-today .fc-col-header-cell-cushion { color: #0ea5e9; }
.fc-day-today { background: rgba(14,165,233,.04) !important; }
.fc-timegrid-slot-label-cushion,
.fc-timegrid-axis-cushion {
  font-size: .72rem; color: #94a3b8; font-weight: 600;
}

/* Events — translucent pill style */
.fc-event { cursor: pointer; transition: filter .15s, transform .15s !important; }
.fc-event:hover { filter: brightness(1.07); transform: translateY(-1px); }

.fc-timegrid-event {
  border-radius: 8px !important;
  border: none !important;
  overflow: visible !important;
  margin: 0 2px !important;
}
.fc-timegrid-event .fc-event-main {
  background: transparent !important;
  padding: .25rem .4rem !important;
  border-left: 3px solid currentColor !important;
}
.fc-timegrid-event .fc-event-main-frame { flex-direction: column; }
.fc-event-title { font-weight: 700; font-size: .72rem; line-height: 1.3; }
.fc-event-time  { font-size: .65rem; font-weight: 600; opacity: .8; }

.fc-daygrid-event {
  border-radius: 6px !important;
  border: none !important;
  font-size: .75rem !important;
  font-weight: 700 !important;
  padding: .1rem .35rem !important;
}

/* Today's daygrid */
.fc-daygrid-day.fc-day-today .fc-daygrid-day-number {
  background: #0ea5e9;
  color: #fff;
  border-radius: 8px;
  width: 26px; height: 26px;
  display: flex; align-items: center; justify-content: center;
  font-weight: 800; font-size: .85rem;
}

/* ── Event Popover ── */
.ev-popover {
  position: fixed; z-index: 2000;
  background: #fff;
  border-radius: 18px;
  box-shadow: 0 20px 60px rgba(0,0,0,.15), 0 0 0 1px #edf2f7;
  padding: 1.2rem;
  width: 290px;
  display: none;
}
.ev-popover.open {
  display: block;
  animation: popIn .2s cubic-bezier(.175,.885,.32,1.275);
}
@keyframes popIn {
  from { opacity:0; transform:scale(.93) translateY(8px); }
  to   { opacity:1; transform:scale(1)  translateY(0); }
}
.ep-badge {
  display: inline-flex; align-items: center; gap: .3rem;
  font-size: .68rem; font-weight: 800; text-transform: uppercase;
  letter-spacing: .06em; padding: .2rem .6rem;
  border-radius: 999px; margin-bottom: .6rem;
}
.ep-title {
  font-size: 1rem; font-weight: 800; color: #0f172a;
  line-height: 1.3; margin-bottom: .5rem; padding-right: 1.5rem;
}
.ep-row {
  display: flex; align-items: flex-start; gap: .5rem;
  font-size: .8rem; color: #475569; margin-bottom: .35rem;
}
.ep-row i { width: 14px; color: #94a3b8; margin-top: 2px; flex-shrink: 0; }
.ep-close {
  position: absolute; top: .75rem; right: .75rem;
  background: #f1f5f9; border: none; border-radius: 8px;
  width: 26px; height: 26px; cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  color: #64748b; font-size: .8rem; transition: background .15s;
}
.ep-close:hover { background: #e2e8f0; }

/* ── Reminder Modal ── */
.rem-modal-header {
  background: linear-gradient(135deg, #1a1a2e, #0f172a);
  border-radius: 18px 18px 0 0 !important;
  padding: 1.2rem 1.5rem !important;
}
.rem-modal-header .modal-title { color: #fff; font-weight: 800; font-size: 1rem; }
.rem-modal-header .btn-close { filter: invert(1); opacity: .7; }
.modal-content { border-radius: 18px !important; border: none !important; overflow: hidden; }
.modal-form-sm .form-label { font-size: .78rem; font-weight: 700; color: #334155; margin-bottom: .25rem; }
.modal-form-sm .form-control {
  font-size: .88rem; border-radius: 10px;
  border: 1.5px solid #e2e8f0; padding: .55rem .8rem;
  transition: all .2s;
}
.modal-form-sm .form-control:focus {
  border-color: #0ea5e9;
  box-shadow: 0 0 0 4px rgba(14,165,233,.1);
}
.btn-save-rem {
  background: linear-gradient(135deg,#0ea5e9,#0284c7);
  color: #fff; font-weight: 700; border: none;
  border-radius: 10px; padding: .55rem 1.4rem;
  box-shadow: 0 4px 14px rgba(14,165,233,.3);
  transition: all .15s;
}
.btn-save-rem:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(14,165,233,.4); }

/* ── Toast ── */
.cal-toast {
  position: fixed; bottom: 1.5rem; right: 1.5rem;
  background: #1a1a2e; color: #fff;
  padding: .7rem 1.1rem; border-radius: 12px;
  font-size: .82rem; font-weight: 600;
  z-index: 9999; box-shadow: 0 8px 24px rgba(0,0,0,.2);
  display: flex; align-items: center; gap: .5rem;
  animation: slideUp .25s ease;
}
@keyframes slideUp {
  from { opacity: 0; transform: translateY(12px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* ── Responsive ── */
@media (max-width: 900px) {
  .cal-page { flex-direction: column; height: auto; }
  .cal-sidebar { width: 100%; flex-direction: row; flex-wrap: wrap; overflow-y: visible; }
  .cs-card { flex: 1; min-width: 220px; }
  .cal-main { height: 580px; }
}
</style>

<div class="cal-page">

  <!-- ── SIDEBAR ── -->
  <aside class="cal-sidebar">

    <!-- Quick Actions -->
    <div class="cs-card">
      <button class="btn-add-reminder" onclick="openReminderModal()">
        <i class="fa-solid fa-bell"></i> Add Reminder
      </button>
      <a href="<?= SITE_URL ?>/trainer/availability.php" class="btn-manage-slots">
        <i class="fa-solid fa-clock"></i> Manage Availability
      </a>
    </div>

    <!-- Legend -->
    <div class="cs-card">
      <div class="cs-heading"><i class="fa-solid fa-circle-info"></i> Legend</div>
      <div class="legend-item"><span class="legend-pip" style="background:#ef4444"></span> Holiday</div>
      <div class="legend-item"><span class="legend-pip" style="background:#FF6B35"></span> Gym Event</div>
      <div class="legend-item"><span class="legend-pip" style="background:#0ea5e9"></span> Booked Session</div>
      <div class="legend-item"><span class="legend-pip" style="background:#8b5cf6"></span> Reminder</div>
      <div class="legend-item"><span class="legend-pip" style="background:#f59e0b"></span> Announcement</div>
    </div>

    <!-- Upcoming Reminders – rendered by JS -->
    <div class="cs-card" style="flex:1; overflow:hidden; display:flex; flex-direction:column;">
      <div class="cs-heading"><i class="fa-solid fa-bell"></i> Upcoming Reminders</div>
      <div id="remSidebarList" style="overflow-y:auto; flex:1;">
        <div class="empty-state"><i class="fa-regular fa-bell-slash"></i> Loading…</div>
      </div>
    </div>

  </aside>

  <!-- ── MAIN CALENDAR ── -->
  <div class="cal-main">
    <div id="trainerCal"></div>
  </div>

</div>

<!-- ── Event Popover ── -->
<div class="ev-popover" id="evPopover">
  <button class="ep-close" onclick="closePopover()"><i class="fa-solid fa-xmark"></i></button>
  <div id="epBadge"></div>
  <div id="epTitle" class="ep-title"></div>
  <div id="epBody"></div>
  <div id="epActions" class="d-flex gap-2 mt-3 flex-wrap"></div>
</div>
<div id="evPopOverlay" onclick="closePopover()" style="display:none;position:fixed;inset:0;z-index:1999;"></div>

<!-- ── Reminder Modal ── -->
<div class="modal fade" id="remModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content modal-form-sm">
      <div class="modal-header rem-modal-header border-0">
        <h5 class="modal-title"><i class="fa-solid fa-bell me-2" style="color:#0ea5e9"></i>New Reminder</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body px-4 py-3">
        <div class="mb-3">
          <label class="form-label">Reminder Title *</label>
          <input type="text" class="form-control" id="rem_title" placeholder="e.g. Review client progress">
        </div>
        <div class="mb-1">
          <label class="form-label">Date &amp; Time *</label>
          <input type="datetime-local" class="form-control" id="rem_dt">
        </div>
      </div>
      <div class="modal-footer border-0 px-4 pb-4 gap-2">
        <button class="btn btn-light rounded-pill px-3 fw-bold" data-bs-dismiss="modal" style="font-size:.85rem;">Cancel</button>
        <button class="btn-save-rem" onclick="saveReminder()">Save Reminder</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<script>
const CSRF = '<?= $csrf ?>';
const API  = '<?= SITE_URL ?>/api/calendar.php';
let calendar, remModal;

const TYPE_COLORS = {
  holiday:      { bg:'#fef2f2', text:'#ef4444', label:'Holiday',      icon:'fa-umbrella-beach' },
  gym_event:    { bg:'#fff7ed', text:'#FF6B35', label:'Gym Event',    icon:'fa-dumbbell' },
  reminder:     { bg:'#f5f3ff', text:'#8b5cf6', label:'Reminder',     icon:'fa-bell' },
  announcement: { bg:'#fffbeb', text:'#f59e0b', label:'Announcement', icon:'fa-bullhorn' },
  booking:      { bg:'#f0f9ff', text:'#0ea5e9', label:'Session',      icon:'fa-user-clock' },
};

document.addEventListener('DOMContentLoaded', () => {
  remModal = new bootstrap.Modal(document.getElementById('remModal'));

  calendar = new FullCalendar.Calendar(document.getElementById('trainerCal'), {
    initialView: 'dayGridMonth',
    headerToolbar: { left:'prev,next today', center:'title', right:'dayGridMonth,timeGridWeek,timeGridDay' },
    height: '100%',
    nowIndicator: true,
    eventSources: [{ url: API + '?action=events', method:'GET' }],
    eventClick(info) { showPopover(info.event, info.jsEvent); },
    eventDidMount(info) {
      // Apply translucent styling for timed events
      const ep = info.event.extendedProps;
      const tc = TYPE_COLORS[ep.type] || {};
      if (!info.event.allDay && tc.bg) {
        info.el.style.backgroundColor = tc.bg;
        info.el.style.color = tc.text;
      }
    }
  });
  calendar.render();
  loadReminders(); // ← load sidebar reminders via AJAX

  // set default reminder time
  const now = new Date(); now.setMinutes(0,0,0);
  document.getElementById('rem_dt').value = now.toISOString().slice(0,16);
});

function showPopover(event, jsEvent) {
  const ep = event.extendedProps;
  const tc = TYPE_COLORS[ep.type] || { bg:'#f1f5f9', text:'#64748b', label:ep.type||'Event', icon:'fa-calendar' };

  // Badge
  document.getElementById('epBadge').innerHTML =
    `<span class="ep-badge" style="background:${tc.bg};color:${tc.text}">
       <i class="fa-solid ${tc.icon}"></i>${tc.label}
     </span>`;

  // Title
  document.getElementById('epTitle').textContent = event.title.replace(/^[^\w\s]+\s*/,'');

  // Body
  let body = '';
  const fmt = { hour:'numeric', minute:'2-digit' };
  const s = event.start ? event.start.toLocaleTimeString('en-US', fmt) : '';
  const e = event.end   ? event.end.toLocaleTimeString('en-US', fmt)   : '';
  const d = event.start ? event.start.toLocaleDateString('en-US',{weekday:'short',month:'short',day:'numeric'}) : '';
  if(d)  body += `<div class="ep-row"><i class="fa-regular fa-calendar"></i>${d}</div>`;
  if(s)  body += `<div class="ep-row"><i class="fa-regular fa-clock"></i>${s}${e?' – '+e:''}</div>`;
  if(ep.description) body += `<div class="ep-row"><i class="fa-regular fa-file-lines"></i>${esc(ep.description)}</div>`;
  if(ep.hol_type)    body += `<div class="ep-row"><i class="fa-solid fa-tag"></i>Type: ${ep.hol_type}</div>`;
  document.getElementById('epBody').innerHTML = body || '<div class="ep-row" style="color:#94a3b8">No additional details</div>';

  // Actions
  let actions = '';
  if(ep.type==='reminder') {
    actions = `<button class="btn btn-sm fw-bold rounded-pill" 
      style="background:#eef2ff;color:#6366f1;border:none;font-size:.78rem;"
      onclick="toggleReminder(${ep.reminder_id})">
      <i class="fa-solid fa-check me-1"></i>Mark Done</button>`;
  }
  document.getElementById('epActions').innerHTML = actions;

  const x = Math.min(jsEvent.clientX+14, window.innerWidth-305);
  const y = Math.min(jsEvent.clientY+14, window.innerHeight-300);
  const pop = document.getElementById('evPopover');
  pop.style.left = x+'px'; pop.style.top = y+'px';
  pop.classList.add('open');
  document.getElementById('evPopOverlay').style.display = 'block';
}

function closePopover() {
  document.getElementById('evPopover').classList.remove('open');
  document.getElementById('evPopOverlay').style.display = 'none';
}

function openReminderModal() {
  document.getElementById('rem_title').value = '';
  const now = new Date(); now.setMinutes(0,0,0);
  document.getElementById('rem_dt').value = now.toISOString().slice(0,16);
  remModal.show();
}

function saveReminder() {
  const title = document.getElementById('rem_title').value.trim();
  const dt    = document.getElementById('rem_dt').value;
  if(!title || !dt){ showToast('Please fill all fields','warn'); return; }
  fetch(API, {
    method:'POST',
    body: new URLSearchParams({action:'add_reminder', csrf_token:CSRF, title, reminder_date: dt.replace('T',' ')})
  }).then(r=>r.json()).then(res=>{
    if(res.success){
      remModal.hide();
      calendar.refetchEvents();
      loadReminders();
      showToast('✓ Reminder added!');
    } else {
      showToast(res.error||'Error saving reminder','danger');
    }
  }).catch(()=>showToast('Network error','danger'));
}

function toggleReminder(id) {
  closePopover();
  fetch(API,{method:'POST',body:new URLSearchParams({action:'toggle_reminder',csrf_token:CSRF,reminder_id:id})})
    .then(()=>{ calendar.refetchEvents(); loadReminders(); showToast('✓ Reminder marked as done!'); });
}

// ── Load sidebar reminders via AJAX (bypasses any PHP session issues) ──────────
function loadReminders() {
  const box = document.getElementById('remSidebarList');
  if (!box) return;

  fetch(API + '?action=my_reminders', {credentials:'same-origin'})
    .then(r => r.text())
    .then(text => {
      let data;
      try {
        data = JSON.parse(text);
      } catch(e) {
        box.innerHTML = '<div style="color:red; font-size:10px;">JSON Parse Error: ' + esc(text) + '</div>';
        return;
      }
      
      if (!data.success || !data.reminders || data.reminders.length === 0) {
        box.innerHTML = '<div class="empty-state"><i class="fa-regular fa-bell-slash"></i> No upcoming reminders</div>';
        return;
      }
      const now = Date.now();
      box.innerHTML = data.reminders.map(r => {
        const dt      = new Date(r.reminder_date.replace(' ', 'T'));
        const overdue = dt.getTime() < now;
        const iconStyle = overdue ? 'background:linear-gradient(135deg,#ef4444,#dc2626);box-shadow:0 4px 10px rgba(239,68,68,.25);' : '';
        const icon      = overdue ? 'fa-clock-rotate-left' : 'fa-bell';
        const titleStyle = overdue ? 'color:#ef4444;' : '';
        const badge     = overdue ? '<span class="badge bg-danger-subtle text-danger" style="font-size:.6rem;vertical-align:middle;margin-left:2px;">OVERDUE</span>' : '';
        const dateStr   = dt.toLocaleDateString('en-US',{month:'short',day:'numeric'}) + ' · ' + dt.toLocaleTimeString('en-US',{hour:'numeric',minute:'2-digit'});
        return `
          <div class="rem-item">
            <div class="rem-icon" style="${iconStyle}"><i class="fa-solid ${icon}"></i></div>
            <div>
              <div class="rem-title" style="${titleStyle}">${esc(r.title)} ${badge}</div>
              <div class="rem-date"><i class="fa-regular fa-calendar me-1"></i>${dateStr}</div>
            </div>
          </div>`;
      }).join('');
    })
    .catch((err) => {
      box.innerHTML = '<div class="empty-state"><i class="fa-regular fa-bell-slash"></i> Fetch Error: ' + err + '</div>';
    });
}

function showToast(msg, type='success') {
  const icon = type==='success' ? 'fa-check-circle' : type==='warn' ? 'fa-triangle-exclamation' : 'fa-circle-xmark';
  const bg   = type==='success' ? '#1a1a2e' : type==='warn' ? '#f59e0b' : '#ef4444';
  const t = document.createElement('div');
  t.className = 'cal-toast';
  t.style.background = bg;
  t.innerHTML = `<i class="fa-solid ${icon}"></i>${msg}`;
  document.body.appendChild(t);
  setTimeout(()=>t.remove(), 3000);
}

function esc(s){ const d=document.createElement('div'); d.textContent=s||''; return d.innerHTML; }
</script>
<?php require_once 'includes/trainer_footer.php'; ?>
