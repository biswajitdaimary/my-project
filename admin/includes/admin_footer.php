        </div> <!-- /.admin-content -->
    </div> <!-- /.admin-main -->
</div> <!-- /.admin-shell -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Sidebar mobile toggle
const sidebarToggle = document.getElementById('sidebarToggle');
const adminSidebar  = document.getElementById('adminSidebar');
if (sidebarToggle && adminSidebar) {
    sidebarToggle.addEventListener('click', () => adminSidebar.classList.toggle('open'));
    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', (e) => {
        if (window.innerWidth < 992 &&
            !adminSidebar.contains(e.target) &&
            e.target !== sidebarToggle) {
            adminSidebar.classList.remove('open');
        }
    });
}
</script>
<!-- Holiday Toast Notification -->
<div class="toast-container position-fixed bottom-0 start-0 p-3" style="z-index: 1055;">
  <div id="holidayToast" class="toast align-items-center text-white bg-danger border-0 shadow" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="d-flex">
      <div class="toast-body fw-bold">
        <i class="fa-solid fa-umbrella-beach me-2"></i><span id="holidayToastMsg"></span>
      </div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
  </div>
</div>

<!-- Holiday Modal Popup -->
<div class="modal fade" id="globalHolidayTodayModal" tabindex="-1" aria-labelledby="globalHolidayTodayModalLabel" aria-hidden="true" style="z-index: 1060;">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-center border-0 shadow-lg" style="border-radius:15px; overflow:hidden;">
      <div class="modal-header border-0 pb-0" style="background: linear-gradient(135deg, #ef4444, #f87171); color: white;">
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-5">
        <i class="fa-solid fa-umbrella-beach fa-4x text-danger mb-3"></i>
        <h3 class="fw-bold mb-2 text-dark">Gym Holiday Today!</h3>
        <p class="text-muted mb-4 fs-5" id="holidayModalMsg"></p>
        <button type="button" class="btn btn-danger px-4 py-2 fw-bold" style="border-radius:10px;" data-bs-dismiss="modal">Got It</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    if (!sessionStorage.getItem('holidayCheckedToday')) {
        fetch('<?= SITE_URL ?>/api/get-upcoming-holidays.php')
            .then(res => res.json())
            .then(data => {
                sessionStorage.setItem('holidayCheckedToday', '1');
                if (data.holidays && data.holidays.length > 0) {
                    let nextH = data.holidays[0];
                    if (nextH.is_today) {
                        if (!document.getElementById('holidayTodayModal')) {
                            document.getElementById('holidayModalMsg').innerText = nextH.title + ' is observed today. Regular services are paused.';
                            var myModal = new bootstrap.Modal(document.getElementById('globalHolidayTodayModal'));
                            myModal.show();
                        }
                    } else if (!sessionStorage.getItem('holidayToastShown')) {
                        let msg = 'Upcoming Holiday: ' + nextH.title + ' on ' + nextH.formatted_date;
                        document.getElementById('holidayToastMsg').innerText = msg;
                        var toastEl = document.getElementById('holidayToast');
                        var toast = new bootstrap.Toast(toastEl, { delay: 10000 });
                        toast.show();
                        sessionStorage.setItem('holidayToastShown', '1');
                    }
                }
            })
            .catch(err => console.error(err));
    }
});
</script>

</body>
</html>
