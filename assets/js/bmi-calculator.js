document.addEventListener('DOMContentLoaded', function() {
    const bmiForm = document.getElementById('bmiForm');
    const ageInput = document.getElementById('age');
    const genderInput = document.getElementById('gender');
    const heightInput = document.getElementById('height');
    const weightInput = document.getElementById('weight');
    const resultContainer = document.getElementById('bmiResultContainer');
    const placeholder = document.getElementById('bmiPlaceholder');
    const bmiValueEl = document.getElementById('bmiValue');
    const bmiCategoryEl = document.getElementById('bmiCategory');
    const bmiTipEl = document.getElementById('bmiTip');
    const bmiRangeEl = document.getElementById('bmiRange');
    const flashEl = document.getElementById('bmiFlash');
    const formFlashEl = document.getElementById('bmiFormFlash');
    const saveBmiBtn = document.getElementById('saveBmiBtn');
    
    const saveUrl = bmiForm ? bmiForm.dataset.saveUrl : '';
    const csrfToken = bmiForm ? bmiForm.dataset.csrfToken : '';
    const dashboardUrl = bmiForm ? bmiForm.dataset.dashboardUrl : '';
    const isLoggedIn = bmiForm ? bmiForm.dataset.isLoggedIn : '0';

    if (!bmiForm) return;

    let currentResult = null;

    function getBmiMeta(bmi) {
        if (bmi < 18.5) {
            return {
                category: 'Underweight',
                colorClass: 'text-info',
                badgeClass: 'bg-info text-dark',
                tip: 'Focus on strength training and nutrient-dense meals to build healthy weight gradually.'
            };
        }

        if (bmi < 25) {
            return {
                category: 'Normal',
                colorClass: 'text-success',
                badgeClass: 'bg-success',
                tip: 'You are in a healthy range. Keep up a balanced diet, steady sleep, and regular movement.'
            };
        }

        if (bmi < 30) {
            return {
                category: 'Overweight',
                colorClass: 'text-warning',
                badgeClass: 'bg-warning text-dark',
                tip: 'A small calorie deficit, more daily steps, and consistent workouts can help bring BMI down safely.'
            };
        }

        return {
            category: 'Obese',
            colorClass: 'text-danger',
            badgeClass: 'bg-danger',
            tip: 'Consider working with a trainer or doctor on a structured plan for sustainable weight reduction.'
        };
    }

    function setFlash(element, type, message) {
        if (!element) return;
        element.className = 'alert small text-start';
        element.classList.add(type === 'success' ? 'alert-success' : 'alert-danger');
        element.textContent = message;
    }

    function showFlash(type, message) {
        setFlash(flashEl, type, message);
    }

    function clearFlash() {
        if (flashEl) {
            flashEl.className = 'alert d-none text-start small';
            flashEl.textContent = '';
        }
        if (formFlashEl) {
            formFlashEl.className = 'alert d-none text-start small';
            formFlashEl.textContent = '';
        }
    }

    bmiForm.addEventListener('submit', function(e) {
        e.preventDefault();
        clearFlash();

        const age = parseInt(ageInput.value, 10);
        const gender = genderInput.value;
        const heightCm = parseFloat(heightInput.value);
        const weightKg = parseFloat(weightInput.value);

        if (isNaN(age) || age < 5 || age > 120) {
            setFlash(formFlashEl, 'error', 'Age must be between 5 and 120 years.');
            return;
        }

        if (!['male', 'female', 'other'].includes(gender)) {
            setFlash(formFlashEl, 'error', 'Please select a valid gender.');
            return;
        }

        if (isNaN(heightCm) || isNaN(weightKg) || heightCm < 50 || heightCm > 300 || weightKg < 10 || weightKg > 500) {
            setFlash(formFlashEl, 'error', 'Height must be 50-300 cm and weight must be 10-500 kg.');
            return;
        }

        const heightM = heightCm / 100;
        const bmi = Number((weightKg / (heightM * heightM)).toFixed(1));
        const meta = getBmiMeta(bmi);
        currentResult = {
            age,
            gender,
            heightCm: heightCm.toFixed(1),
            weightKg: weightKg.toFixed(1),
            bmi: bmi.toFixed(1),
            category: meta.category,
            badgeClass: meta.badgeClass
        };

        bmiValueEl.className = 'display-1 fw-bold mb-3 ' + meta.colorClass;
        bmiValueEl.textContent = bmi.toFixed(1);
        
        bmiCategoryEl.className = 'mb-4 text-uppercase fw-bold ' + meta.colorClass;
        bmiCategoryEl.textContent = meta.category;
        
        bmiTipEl.textContent = meta.tip;

        placeholder.style.display = 'none';
        resultContainer.style.display = 'block';

        bmiValueEl.animate([
            { transform: 'scale(0.8)', opacity: 0 },
            { transform: 'scale(1.1)', opacity: 1 },
            { transform: 'scale(1)', opacity: 1 }
        ], {
            duration: 400,
            easing: 'ease-out'
        });

        // Enable save button if user is logged in
        if (saveBmiBtn && isLoggedIn === '1') {
            saveBmiBtn.disabled = false;
            saveBmiBtn.innerHTML = '<i class="fa-solid fa-floppy-disk me-2"></i>Save to Dashboard';
            saveBmiBtn.classList.remove('btn-success');
            saveBmiBtn.classList.add('btn-primary-custom');
        }
    });

    if (saveBmiBtn) {
        saveBmiBtn.addEventListener('click', function() {
            if (!currentResult || isLoggedIn !== '1') return;

            saveBmiBtn.disabled = true;
            saveBmiBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Saving...';

            const payload = new URLSearchParams();
            payload.append('age', currentResult.age);
            payload.append('gender', currentResult.gender);
            payload.append('height', currentResult.heightCm);
            payload.append('weight', currentResult.weightKg);
            payload.append('csrf_token', csrfToken);

            fetch(saveUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'Accept': 'application/json'
                },
                body: payload.toString()
            })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    saveBmiBtn.classList.remove('btn-primary-custom');
                    saveBmiBtn.classList.add('btn-success');
                    saveBmiBtn.innerHTML = '<i class="fa-solid fa-check-circle me-2"></i>Saved! Redirecting...';
                    
                    // Automatically redirect to dashboard
                    setTimeout(() => {
                        window.location.href = data.redirect_url || dashboardUrl;
                    }, 1500);
                } else {
                    saveBmiBtn.disabled = false;
                    saveBmiBtn.innerHTML = '<i class="fa-solid fa-floppy-disk me-2"></i>Save to Dashboard';
                    showFlash('error', data.message || 'Could not save your BMI.');
                }
            })
            .catch(err => {
                console.error(err);
                saveBmiBtn.disabled = false;
                saveBmiBtn.innerHTML = '<i class="fa-solid fa-floppy-disk me-2"></i>Save to Dashboard';
                showFlash('error', 'Connection error while saving BMI.');
            });
        });
    }
});
