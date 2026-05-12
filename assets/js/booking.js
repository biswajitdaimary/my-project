document.addEventListener('DOMContentLoaded', function() {
    const trainerSelect = document.getElementById('trainerSelect');
    const dateSelect = document.getElementById('dateSelect');
    const slotsContainer = document.getElementById('slotsContainer');
    const slotsGrid = document.getElementById('slotsGrid');
    const confirmBtn = document.getElementById('confirmBookingBtn');
    const notesInput = document.getElementById('bookingNotes');
    
    let selectedSlotId = null;

    if (!trainerSelect || !dateSelect) return;

    function fetchSlots() {
        const trainerId = trainerSelect.value;
        const dateStr = dateSelect.value;
        
        if (!trainerId || !dateStr) return;

        slotsContainer.innerHTML = '<div class="spinner-border text-primary-custom mt-4"></div>';
        slotsGrid.classList.add('d-none');
        confirmBtn.disabled = true;
        selectedSlotId = null;

        fetch(`../api/get-slots.php?trainer_id=${trainerId}&date=${dateStr}`)
            .then(res => res.json())
            .then(data => {
                slotsContainer.classList.add('d-none');
                slotsGrid.classList.remove('d-none');
                
                if (data.status === 'success' && data.data.length > 0) {
                    slotsGrid.innerHTML = data.data.map(slot => {
                        const time = slot.start_time.substring(0, 5) + ' - ' + slot.end_time.substring(0, 5);
                        return `
                        <div class="col-6">
                            <button type="button" class="btn btn-outline-primary w-100 slot-btn" data-id="${slot.availability_id}">
                                ${time}
                            </button>
                        </div>`;
                    }).join('');

                    // Attach click logic
                    document.querySelectorAll('.slot-btn').forEach(btn => {
                        btn.addEventListener('click', function() {
                            document.querySelectorAll('.slot-btn').forEach(b => {
                                b.classList.remove('btn-primary-custom', 'text-white');
                                b.classList.add('btn-outline-primary');
                            });
                            this.classList.remove('btn-outline-primary');
                            this.classList.add('btn-primary-custom', 'text-white');
                            selectedSlotId = this.dataset.id;
                            confirmBtn.disabled = false;
                        });
                    });
                } else {
                    slotsGrid.innerHTML = `<div class="col-12 text-center text-muted small py-3">No available slots for this date.</div>`;
                }
            })
            .catch(err => {
                slotsContainer.innerHTML = '<div class="text-danger small mt-4">Error loading slots.</div>';
                slotsContainer.classList.remove('d-none');
            });
    }

    trainerSelect.addEventListener('change', fetchSlots);
    dateSelect.addEventListener('change', fetchSlots);

    // Initial fetch if pre-filled
    if (trainerSelect.value && dateSelect.value) {
        fetchSlots();
    }

    // ── Toast helper ──────────────────────────────────────────────────────
    function showToast(message, type = 'success') {
        let container = document.getElementById('bookingToastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'bookingToastContainer';
            container.style.cssText = 'position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;display:flex;flex-direction:column;gap:0.5rem;';
            document.body.appendChild(container);
        }
        const bg   = type === 'success' ? '#06d6a0' : '#e63946';
        const icon = type === 'success' ? '✓' : '✗';
        const toast = document.createElement('div');
        toast.style.cssText = `background:${bg};color:#fff;padding:0.85rem 1.25rem;border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,0.18);font-weight:600;font-size:0.9rem;display:flex;align-items:center;gap:0.6rem;max-width:320px;animation:slideIn 0.3s ease;`;
        toast.innerHTML = `<span style="font-size:1.1rem;">${icon}</span> ${message}`;
        container.appendChild(toast);
        setTimeout(() => { toast.style.opacity = '0'; toast.style.transition = 'opacity 0.4s'; setTimeout(() => toast.remove(), 400); }, 3000);
    }

    // ── Submit booking ────────────────────────────────────────────────────
    if (confirmBtn) {
        confirmBtn.addEventListener('click', function() {
            if (!selectedSlotId || !dateSelect.value) return;

            confirmBtn.disabled = true;
            confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';

            const payload = new URLSearchParams();
            payload.append('availability_id', selectedSlotId);
            payload.append('date', dateSelect.value);
            payload.append('notes', notesInput ? notesInput.value : '');

            fetch('../api/book-slot.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: payload.toString()
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    showToast('Booking confirmed! Redirecting…', 'success');
                    setTimeout(() => { window.location.href = 'bookings.php'; }, 1500);
                } else {
                    showToast(data.message || 'Booking failed. Please try again.', 'error');
                    confirmBtn.disabled = false;
                    confirmBtn.innerHTML = 'Confirm Booking';
                }
            })
            .catch(() => {
                showToast('Connection error. Please check your internet.', 'error');
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = 'Confirm Booking';
            });
        });
    }
});
