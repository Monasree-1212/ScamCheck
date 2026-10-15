document.addEventListener('DOMContentLoaded', function () {
    // Interactive Risk Calculator on Report Submission & Edit
    const flagCheckboxes = document.querySelectorAll('.flag-checkbox');
    const paymentSelect = document.getElementById('payment_demanded');
    const contactEmailInput = document.getElementById('contact_email');

    const scoreDisplay = document.getElementById('preview-score');
    const levelDisplay = document.getElementById('preview-level');
    const barFill = document.getElementById('preview-bar');

    function updateRiskPreview() {
        if (!scoreDisplay || !levelDisplay || !barFill) return;

        let score = 0;
        let flags = [];

        flagCheckboxes.forEach(cb => {
            if (cb.checked) {
                const weight = parseInt(cb.getAttribute('data-weight') || '0', 10);
                score += weight;
                flags.push(cb.value);
            }
        });

        // Check if payment demanded is yes
        if (paymentSelect && paymentSelect.value.toLowerCase() === 'yes') {
            if (!flags.includes('fee_demanded')) {
                score += 35;
            }
        }

        // Check email domain
        if (contactEmailInput && contactEmailInput.value) {
            const email = contactEmailInput.value.toLowerCase();
            const freeDomains = ['@gmail.com', '@yahoo.com', '@hotmail.com', '@outlook.com', '@rediffmail.com', '@aol.com', '@proton.me', '@protonmail.com'];
            const isFree = freeDomains.some(d => email.endsWith(d));
            if (isFree && !flags.includes('free_email')) {
                score += 15;
            }
        }

        if (score > 100) score = 100;

        let level = 'Low';
        let barColor = '#2A9D8F';
        let badgeClass = 'risk-low';

        if (score >= 85) {
            level = 'Critical';
            barColor = '#932929';
            badgeClass = 'risk-critical';
        } else if (score >= 60) {
            level = 'High';
            barColor = '#C94C4C';
            badgeClass = 'risk-high';
        } else if (score >= 30) {
            level = 'Medium';
            barColor = '#E9A23B';
            badgeClass = 'risk-medium';
        }

        scoreDisplay.textContent = score + '/100';
        levelDisplay.textContent = level;
        levelDisplay.className = 'risk-badge ' + badgeClass;

        barFill.style.width = score + '%';
        barFill.style.backgroundColor = barColor;
    }

    if (flagCheckboxes.length > 0) {
        flagCheckboxes.forEach(cb => cb.addEventListener('change', updateRiskPreview));
    }
    if (paymentSelect) {
        paymentSelect.addEventListener('change', updateRiskPreview);
    }
    if (contactEmailInput) {
        contactEmailInput.addEventListener('input', updateRiskPreview);
    }

    // Initial calculation if editing
    updateRiskPreview();

    // Confirm Delete Actions
    const deleteButtons = document.querySelectorAll('.js-confirm-delete');
    deleteButtons.forEach(btn => {
        btn.addEventListener('click', function (e) {
            const itemName = this.getAttribute('data-item') || 'this report';
            if (!confirm(`Are you sure you want to permanently delete ${itemName}? This action cannot be undone.`)) {
                e.preventDefault();
            }
        });
    });
});
