<?php
require_once '../config/config.php';
require_once '../helpers/auth_check.php';
require_user();
$pageTitle = 'My Calendar';
$uid  = (int)$_SESSION['user_id'];
$csrf = $_SESSION['csrf_token'];

$upEvents = [];
try {
    $ue = $pdo->prepare("SELECT event_id,title,event_date,start_time,category,color FROM gym_events WHERE event_date>=CURDATE() AND visibility IN('all','members') ORDER BY event_date LIMIT 4");
    $ue->execute(); $upEvents = $ue->fetchAll();
} catch(Exception $e){}

$upRems = [];
try {
    $ur = $pdo->prepare("
        SELECT title, reminder_date
        FROM calendar_reminders
        WHERE user_id = ?
          AND is_done = 0
          AND reminder_date >= NOW()
        ORDER BY reminder_date ASC
        LIMIT 4
    ");
    $ur->execute([$uid]);
    $upRems = $ur->fetchAll();
} catch(Exception $e){}

$upSessions = [];
try {
    $us = $pdo->prepare("
        SELECT tb.session_date, tb.start_time, tb.status, t.full_name AS trainer_name 
        FROM trainer_bookings tb
        JOIN trainers t ON tb.trainer_id = t.trainer_id
        WHERE tb.user_id = ? AND tb.session_date >= CURDATE() AND tb.status != 'cancelled'
        ORDER BY tb.session_date ASC, tb.start_time ASC
        LIMIT 4
    ");
    $us->execute([$uid]);
    $upSessions = $us->fetchAll();
} catch(Exception $e){}

require_once '../includes/header.php';
require_once '../includes/nav.php';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css">
<style>
/* ═══════════════════════════════════════════
   CLIENT CALENDAR — matching portal theme
   Primary : #FF6B35  |  Dark : #1a1a2e
   ═══════════════════════════════════════════ */
:root{
  --p:#FF6B35; --p2:#ff8c5a; --dark:#1a1a2e;
  --bg:#f8fafc; --card:#fff;
  --border:#eef0f5; --text:#1a1a2e;
  --muted:#6b7280; --faint:#94a3b8;
}

.main-wrapper{display:flex;min-height:calc(100vh - 76px);background:var(--bg);}
.content-wrapper{flex:1;min-width:0;padding:1.75rem 1.5rem;}

/* Page heading */
.cc-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;gap:1rem;}
.cc-head h1{font-size:1.4rem;font-weight:800;color:var(--dark);letter-spacing:-.025em;margin:0;display:flex;align-items:center;gap:.55rem;}
.cc-head h1 i{color:var(--p);font-size:1.3rem;}
.cc-head p{font-size:.85rem;color:var(--muted);margin:.2rem 0 0;}

/* Primary CTA */
.btn-orange{display:inline-flex;align-items:center;gap:.45rem;padding:.65rem 1.4rem;background:linear-gradient(135deg,var(--p),var(--p2));color:#fff;font-weight:700;font-size:.85rem;border:none;border-radius:12px;cursor:pointer;box-shadow:0 4px 14px rgba(255,107,53,.3);transition:all .18s;white-space:nowrap;text-decoration:none;}
.btn-orange:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(255,107,53,.4);color:#fff;}

/* Layout */
.cc-layout{display:flex;gap:1.5rem;align-items:flex-start;width:100%;}
.cc-sidebar{width:265px;flex-shrink:0;display:flex;flex-direction:column;gap:1rem;}
.cc-right{flex:1;min-width:0;display:flex;flex-direction:column;gap:1rem;}

/* Cards */
.cc-card{background:var(--card);border-radius:16px;border:1px solid var(--border);box-shadow:0 1px 4px rgba(0,0,0,.04);padding:1.1rem 1.2rem;}
.cc-card-title{font-size:.67rem;font-weight:800;text-transform:uppercase;letter-spacing:.1em;color:var(--faint);margin-bottom:.85rem;display:flex;align-items:center;gap:.4rem;}

/* Manage Slots btn */
.btn-outline-p{display:flex;align-items:center;justify-content:center;gap:.45rem;width:100%;padding:.62rem;margin-top:.55rem;background:#fff5f1;color:var(--p);font-weight:700;font-size:.84rem;border:1.5px solid #fcd5c5;border-radius:11px;text-decoration:none;transition:all .15s;}
.btn-outline-p:hover{background:#ffeee7;border-color:var(--p);color:#e85c24;}

/* Legend */
.leg-row{display:flex;align-items:center;gap:.6rem;padding:.3rem 0;font-size:.82rem;font-weight:500;color:var(--muted);}
.leg-pip{width:11px;height:11px;border-radius:3px;flex-shrink:0;}

/* Sidebar list items */
.si-item{display:flex;align-items:flex-start;gap:.65rem;padding:.5rem 0;border-bottom:1px solid #f5f5f5;}
.si-item:last-child{border-bottom:none;}
.si-ico{width:30px;height:30px;border-radius:9px;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:.72rem;color:#fff;}
.si-name{font-size:.82rem;font-weight:700;color:var(--text);line-height:1.3;}
.si-sub{font-size:.71rem;color:var(--faint);margin-top:.1rem;}
.si-empty{text-align:center;padding:.9rem 0;font-size:.8rem;color:var(--faint);}
.si-empty i{display:block;font-size:1.2rem;margin-bottom:.35rem;opacity:.3;}

/* Calendar card */
.cc-cal-card{flex:1;min-width:0;background:var(--card);border-radius:16px;border:1px solid var(--border);box-shadow:0 1px 4px rgba(0,0,0,.04);padding:1.4rem 1.5rem;height:680px;display:flex;flex-direction:column;}
#clientCal{flex:1;min-height:0;}

/* ─── FullCalendar overrides — matching orange portal theme ─── */
.fc .fc-toolbar{margin-bottom:1rem;}
.fc .fc-toolbar-title{font-size:1.15rem;font-weight:800;color:var(--dark);letter-spacing:-.02em;}

.fc .fc-button-group .fc-button,.fc .fc-button{
  background:#fff !important;border:1.5px solid var(--border) !important;
  color:var(--muted) !important;font-weight:600 !important;font-size:.8rem !important;
  border-radius:9px !important;padding:.4rem .85rem !important;
  box-shadow:none !important;transition:all .15s !important;
}
.fc .fc-button:hover{background:#fff5f1 !important;border-color:var(--p) !important;color:var(--p) !important;}
.fc .fc-button-primary:not(:disabled).fc-button-active{
  background:linear-gradient(135deg,var(--p),var(--p2)) !important;
  border-color:var(--p) !important;color:#fff !important;
  box-shadow:0 3px 10px rgba(255,107,53,.25) !important;
}
.fc .fc-today-button{
  background:var(--dark) !important;border-color:var(--dark) !important;
  color:#fff !important;box-shadow:0 3px 10px rgba(26,26,46,.2) !important;
}
.fc .fc-today-button:hover{background:#2d2d52 !important;border-color:#2d2d52 !important;}

.fc-theme-standard td,.fc-theme-standard th{border-color:#f0f2f7;}
.fc-theme-standard .fc-scrollgrid{border:none;}
.fc-col-header-cell-cushion{color:var(--muted);font-weight:700;font-size:.8rem;text-decoration:none !important;padding:.65rem 0 !important;}
.fc-day-today{background:rgba(255,107,53,.04) !important;}
.fc-day-today .fc-col-header-cell-cushion{color:var(--p);font-weight:800;}
.fc-timegrid-slot-label-cushion,.fc-timegrid-axis-cushion{font-size:.72rem;color:var(--faint);font-weight:600;}
.fc-daygrid-day-number{font-size:.82rem;font-weight:600;color:var(--muted);text-decoration:none !important;}
.fc-day-today .fc-daygrid-day-number{
  background:var(--p);color:#fff;border-radius:7px;
  width:24px;height:24px;display:flex;align-items:center;justify-content:center;
  font-weight:800;font-size:.82rem;
}
.fc-event{cursor:pointer !important;transition:opacity .15s,transform .15s !important;}
.fc-event:hover{opacity:.88;transform:translateY(-1px);}
.fc-daygrid-event{border-radius:5px !important;border:none !important;font-size:.73rem !important;font-weight:700 !important;padding:.12rem .4rem !important;margin-bottom:2px !important;}

/* ─── Popover ─── */
.ev-pop{position:fixed;z-index:2000;background:#fff;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.13),0 0 0 1px var(--border);padding:1.15rem;width:285px;display:none;}
.ev-pop.open{display:block;animation:popIn .2s cubic-bezier(.175,.885,.32,1.275);}
@keyframes popIn{from{opacity:0;transform:scale(.93) translateY(6px);}to{opacity:1;transform:scale(1) translateY(0);}}
.ep-badge{display:inline-flex;align-items:center;gap:.3rem;font-size:.67rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em;padding:.18rem .55rem;border-radius:999px;margin-bottom:.55rem;}
.ep-title{font-size:.98rem;font-weight:800;color:var(--dark);line-height:1.3;margin-bottom:.45rem;padding-right:1.4rem;}
.ep-row{display:flex;align-items:flex-start;gap:.45rem;font-size:.79rem;color:var(--muted);margin-bottom:.3rem;}
.ep-row i{width:13px;color:var(--faint);margin-top:2px;flex-shrink:0;}
.ep-close{position:absolute;top:.7rem;right:.7rem;background:#f3f4f6;border:none;border-radius:7px;width:25px;height:25px;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--muted);font-size:.78rem;}
.ep-close:hover{background:#e5e7eb;}

/* ─── Reminder Modal ─── */
.modal-content{border-radius:16px !important;border:none !important;overflow:hidden;}
.rem-mh{background:linear-gradient(135deg,var(--dark),#2d2d52);padding:1.15rem 1.4rem !important;}
.rem-mh .modal-title{color:#fff;font-weight:800;font-size:.98rem;}
.rem-mh .btn-close{filter:invert(1);opacity:.7;}
.mf .form-label{font-size:.77rem;font-weight:700;color:#334155;margin-bottom:.22rem;}
.mf .form-control{font-size:.87rem;border-radius:9px;border:1.5px solid #e2e8f0;padding:.52rem .75rem;transition:all .2s;}
.mf .form-control:focus{border-color:var(--p);box-shadow:0 0 0 3px rgba(255,107,53,.12);}
.btn-save{background:linear-gradient(135deg,var(--p),var(--p2));color:#fff;font-weight:700;border:none;border-radius:9px;padding:.52rem 1.3rem;font-size:.85rem;box-shadow:0 3px 12px rgba(255,107,53,.25);}

/* ─── Toast ─── */
.cc-toast{position:fixed;bottom:1.5rem;right:1.5rem;background:var(--dark);color:#fff;padding:.65rem 1rem;border-radius:11px;font-size:.82rem;font-weight:600;z-index:9999;box-shadow:0 8px 24px rgba(0,0,0,.2);display:flex;align-items:center;gap:.45rem;animation:su .22s ease;}
@keyframes su{from{opacity:0;transform:translateY(10px);}to{opacity:1;transform:translateY(0);}}

@media(max-width:960px){.cc-layout{flex-direction:column;}.cc-sidebar{width:100%;}.cc-cal-card{height:540px;}}
</style>

<div class="main-wrapper">
  <?php require_once '../includes/sidebar-user.php'; ?>
  <div class="content-wrapper">

    <!-- Page Heading -->
    <div class="cc-head">
      <div>
        <h1><i class="fa-solid fa-calendar-days"></i>My Calendar</h1>
        <p>View gym events, holidays &amp; your personal reminders.</p>
      </div>
      <button class="btn-orange" onclick="openReminderModal()">
        <i class="fa-solid fa-bell"></i> Add Reminder
      </button>
    </div>

    <div class="cc-layout">

      <!-- ── SIDEBAR ── -->
      <aside class="cc-sidebar">

        <!-- Book a Trainer CTA -->
        <div class="cc-card" style="background:linear-gradient(135deg,#1a1a2e,#2d2d52);border-color:transparent;">
          <div style="font-size:.75rem;font-weight:700;color:rgba(255,255,255,.5);text-transform:uppercase;letter-spacing:.08em;margin-bottom:.6rem;">Quick Action</div>
          <a href="<?= SITE_URL ?>/user/book-trainer.php" style="display:flex;align-items:center;gap:.5rem;background:linear-gradient(135deg,#FF6B35,#ff8c5a);color:#fff;font-weight:700;font-size:.85rem;padding:.65rem 1rem;border-radius:11px;text-decoration:none;box-shadow:0 4px 14px rgba(255,107,53,.4);transition:all .15s;">
            <i class="fa-solid fa-calendar-plus"></i> Book a Trainer
          </a>
        </div>

        <!-- Legend -->
        <div class="cc-card">
          <div class="cc-card-title"><i class="fa-solid fa-circle-half-stroke"></i> Legend</div>
          <div class="leg-row"><span class="leg-pip" style="background:#ef4444"></span>Holiday</div>
          <div class="leg-row"><span class="leg-pip" style="background:#FF6B35"></span>Gym Event</div>
          <div class="leg-row"><span class="leg-pip" style="background:#0ea5e9"></span>Booked Session</div>
          <div class="leg-row"><span class="leg-pip" style="background:#8b5cf6"></span>Reminder</div>
          <div class="leg-row"><span class="leg-pip" style="background:#f59e0b"></span>Announcement</div>
        </div>

        <!-- Upcoming Sessions -->
        <div class="cc-card">
          <div class="cc-card-title"><i class="fa-solid fa-user-tie"></i> My Sessions</div>
          <?php if(empty($upSessions)): ?>
            <div class="si-empty"><i class="fa-regular fa-calendar-xmark"></i>No upcoming sessions</div>
          <?php else: foreach($upSessions as $s): ?>
            <div class="si-item">
              <div class="si-ico" style="background:#0ea5e9"><i class="fa-solid fa-user-tie"></i></div>
              <div>
                <div class="si-name">With <?=htmlspecialchars($s['trainer_name'])?></div>
                <div class="si-sub"><?=date('M j',strtotime($s['session_date']))?><?=$s['start_time']?' · '.date('h:i A',strtotime($s['start_time'])):''?></div>
              </div>
            </div>
          <?php endforeach; endif; ?>
        </div>

        <!-- Upcoming Gym Events -->
        <div class="cc-card">
          <div class="cc-card-title"><i class="fa-solid fa-dumbbell"></i> Upcoming Events</div>
          <?php if(empty($upEvents)): ?>
            <div class="si-empty"><i class="fa-regular fa-calendar-xmark"></i>No upcoming events</div>
          <?php else: foreach($upEvents as $ev): ?>
            <div class="si-item">
              <div class="si-ico" style="background:#FF6B35"><i class="fa-solid fa-dumbbell"></i></div>
              <div>
                <div class="si-name"><?=htmlspecialchars($ev['title'])?></div>
                <div class="si-sub"><?=date('M j',strtotime($ev['event_date']))?><?=$ev['start_time']?' · '.date('h:i A',strtotime($ev['start_time'])):''?></div>
              </div>
            </div>
          <?php endforeach; endif; ?>
        </div>

        <!-- Upcoming Reminders -->
        <div class="cc-card">
          <div class="cc-card-title"><i class="fa-solid fa-bell"></i> My Reminders</div>
          <?php if(empty($upRems)): ?>
            <div class="si-empty"><i class="fa-regular fa-bell-slash"></i>No reminders yet</div>
          <?php else: foreach($upRems as $r): ?>
            <div class="si-item">
              <div class="si-ico" style="background:#8b5cf6"><i class="fa-solid fa-bell"></i></div>
              <div>
                <div class="si-name"><?=htmlspecialchars($r['title'])?></div>
                <div class="si-sub"><?=date('M j · h:i A',strtotime($r['reminder_date']))?></div>
              </div>
            </div>
          <?php endforeach; endif; ?>
        </div>

      </aside>

      <!-- ── CALENDAR ── -->
      <div class="cc-cal-card">
        <div id="clientCal" class="position-relative">
          <div id="calLoader" class="position-absolute top-50 start-50 translate-middle text-center" style="z-index: 10;">
            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
              <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2 text-muted small fw-bold">Loading your calendar...</p>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- Popover -->
<div class="ev-pop" id="evPop">
  <button class="ep-close" onclick="closePop()"><i class="fa-solid fa-xmark"></i></button>
  <div id="epBadge"></div>
  <div id="epTitle" class="ep-title"></div>
  <div id="epBody"></div>
  <div id="epActs" class="d-flex gap-2 mt-3 flex-wrap"></div>
</div>
<div id="evOverlay" onclick="closePop()" style="display:none;position:fixed;inset:0;z-index:1999;"></div>

<!-- Reminder Modal -->
<div class="modal fade" id="remModal" tabindex="-1">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content mf">
      <div class="modal-header rem-mh border-0">
        <h5 class="modal-title"><i class="fa-solid fa-bell me-2" style="color:#FF6B35"></i>New Reminder</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body px-4 py-3">
        <div class="mb-3">
          <label class="form-label">Title *</label>
          <input type="text" class="form-control" id="rem_title" placeholder="e.g. Gym day!">
        </div>
        <div>
          <label class="form-label">Date &amp; Time *</label>
          <input type="datetime-local" class="form-control" id="rem_dt">
        </div>
      </div>
      <div class="modal-footer border-0 px-4 pb-4 gap-2">
        <button class="btn btn-light rounded-pill px-3 fw-bold" data-bs-dismiss="modal" style="font-size:.84rem">Cancel</button>
        <button class="btn-save" onclick="saveReminder()">Save Reminder</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<script>
const CSRF='<?=$csrf?>', API='<?=SITE_URL?>/api/calendar.php';
let cal, remModal;

// Colors matching the portal theme exactly
const TM={
  holiday:     {bg:'#fef2f2',text:'#ef4444',fc:'#ef4444',label:'Holiday',     icon:'fa-umbrella-beach'},
  gym_event:   {bg:'#fff5f1',text:'#e85c24',fc:'#FF6B35',label:'Gym Event',   icon:'fa-dumbbell'},
  booking:     {bg:'#e0f2fe',text:'#0284c7',fc:'#0ea5e9',label:'Session',     icon:'fa-user-tie'},
  reminder:    {bg:'#f5f3ff',text:'#7c3aed',fc:'#8b5cf6',label:'Reminder',    icon:'fa-bell'},
  announcement:{bg:'#fffbeb',text:'#b45309',fc:'#f59e0b',label:'Announcement',icon:'fa-bullhorn'},
};
const ALLOWED=['holiday','gym_event','booking','reminder','announcement'];

document.addEventListener('DOMContentLoaded',()=>{
  remModal=new bootstrap.Modal(document.getElementById('remModal'));
  cal=new FullCalendar.Calendar(document.getElementById('clientCal'),{
    initialView:'dayGridMonth',
    headerToolbar:{left:'prev,next today',center:'title',right:'dayGridMonth,timeGridWeek,listWeek'},
    height:'100%',
    nowIndicator:true,
    eventSources:[{url:API+'?action=events',method:'GET',
      success:data=>data.filter(e=>ALLOWED.includes(e.extendedProps?.type))
    }],
    eventClick(info){showPop(info.event,info.jsEvent);},
    eventDidMount(info){
      const m=TM[info.event.extendedProps.type];
      if(m){info.el.style.backgroundColor=m.fc;info.el.style.color='#fff';info.el.style.borderColor=m.fc;}
    }
  });
  cal.render();
  document.getElementById('calLoader').style.display = 'none';
  const n=new Date();n.setMinutes(0,0,0);
  document.getElementById('rem_dt').value=n.toISOString().slice(0,16);
});

function showPop(ev,je){
  const ep=ev.extendedProps,m=TM[ep.type]||{bg:'#f3f4f6',text:'#6b7280',label:'Event',icon:'fa-calendar'};
  document.getElementById('epBadge').innerHTML=`<span class="ep-badge" style="background:${m.bg};color:${m.text}"><i class="fa-solid ${m.icon}"></i>${m.label}</span>`;
  document.getElementById('epTitle').textContent=ev.title.replace(/^[^\w\s]+\s*/,'');
  const fmt={hour:'numeric',minute:'2-digit'};
  const d=ev.start?ev.start.toLocaleDateString('en-US',{weekday:'short',month:'short',day:'numeric'}):'';
  const s=ev.start&&!ev.allDay?ev.start.toLocaleTimeString('en-US',fmt):'';
  const e=ev.end&&!ev.allDay?ev.end.toLocaleTimeString('en-US',fmt):'';
  let body='';
  if(d)body+=`<div class="ep-row"><i class="fa-regular fa-calendar"></i>${d}</div>`;
  if(s)body+=`<div class="ep-row"><i class="fa-regular fa-clock"></i>${s}${e?' – '+e:''}</div>`;
  if(ep.type==='booking') {
      if(ep.trainer_name)body+=`<div class="ep-row"><i class="fa-solid fa-user-tie"></i>With ${esc(ep.trainer_name)}</div>`;
      if(ep.status)body+=`<div class="ep-row"><i class="fa-solid fa-circle-info"></i>Status: <span style="text-transform:capitalize">${esc(ep.status)}</span></div>`;
  }
  if(ep.description)body+=`<div class="ep-row"><i class="fa-regular fa-file-lines"></i>${esc(ep.description)}</div>`;
  if(ep.hol_type)body+=`<div class="ep-row"><i class="fa-solid fa-tag"></i>${ep.hol_type}</div>`;
  document.getElementById('epBody').innerHTML=body||'<div class="ep-row" style="color:#94a3b8">No additional details.</div>';
  let acts='';
  if(ep.type==='reminder')acts=`<button class="btn btn-sm fw-bold rounded-pill" style="background:#fff5f1;color:#FF6B35;border:1.5px solid #fcd5c5;font-size:.77rem" onclick="toggleRem(${ep.reminder_id})"><i class="fa-solid fa-check me-1"></i>Mark Done</button>`;
  document.getElementById('epActs').innerHTML=acts;
  const x=Math.min(je.clientX+14,window.innerWidth-300),y=Math.min(je.clientY+14,window.innerHeight-290);
  const pop=document.getElementById('evPop');
  pop.style.left=x+'px';pop.style.top=y+'px';
  pop.classList.add('open');
  document.getElementById('evOverlay').style.display='block';
}
function closePop(){document.getElementById('evPop').classList.remove('open');document.getElementById('evOverlay').style.display='none';}

function openReminderModal(){document.getElementById('rem_title').value='';remModal.show();}
function saveReminder(){
  const t=document.getElementById('rem_title').value.trim(),dt=document.getElementById('rem_dt').value;
  if(!t||!dt){toast('Please fill all fields','w');return;}
  fetch(API,{method:'POST',body:new URLSearchParams({action:'add_reminder',csrf_token:CSRF,title:t,reminder_date:dt.replace('T',' ')})})
    .then(r=>r.json()).then(res=>{
      if(res.success){remModal.hide();cal.refetchEvents();toast('✓ Reminder saved!');location.reload();}
      else toast(res.error||'Error','e');
    });
}
function toggleRem(id){
  closePop();
  fetch(API,{method:'POST',body:new URLSearchParams({action:'toggle_reminder',csrf_token:CSRF,reminder_id:id})})
    .then(()=>{cal.refetchEvents();toast('✓ Done!');});
}
function toast(msg,t='s'){
  const bg=t==='s'?'#1a1a2e':t==='w'?'#f59e0b':'#ef4444';
  const d=document.createElement('div');
  d.className='cc-toast';d.style.background=bg;
  d.innerHTML=`<i class="fa-solid fa-check-circle"></i>${msg}`;
  document.body.appendChild(d);setTimeout(()=>d.remove(),3000);
}
function esc(s){const d=document.createElement('div');d.textContent=s||'';return d.innerHTML;}
</script>
<?php require_once '../includes/footer.php'; ?>
