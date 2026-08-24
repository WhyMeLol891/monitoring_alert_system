/**
 * Website Monitoring System - Vanilla JS Helpers
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Password Visibility Toggle
    const toggleButtons = document.querySelectorAll('.password-toggle-btn');
    toggleButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('data-target');
            const input = document.getElementById(targetId);
            if (!input) return;

            if (input.type === 'password') {
                input.type = 'text';
                this.innerHTML = '👁️‍🗨️'; // Eye open / visible
                this.setAttribute('title', 'Hide password');
            } else {
                input.type = 'password';
                this.innerHTML = '👁️'; // Eye icon
                this.setAttribute('title', 'Show password');
            }
        });
    });

    // 2. Mobile Navigation Toggle
    const mobileToggle = document.querySelector('.mobile-toggle');
    const navLinks = document.querySelector('.nav-links');
    if (mobileToggle && navLinks) {
        mobileToggle.addEventListener('click', () => {
            navLinks.classList.toggle('show');
        });
    }

    // 3. Status Page Auto Refresh (Optional 60s countdown)
    const autoRefreshBadge = document.getElementById('auto-refresh-timer');
    if (autoRefreshBadge) {
        let timeLeft = 60;
        const updateTimer = () => {
            if (timeLeft <= 0) {
                window.location.reload();
            } else {
                autoRefreshBadge.textContent = `Auto-refresh in ${timeLeft}s`;
                timeLeft--;
            }
        };
        setInterval(updateTimer, 1000);
    }

    // 4. Confirm action dialogs
    document.querySelectorAll('[data-confirm]').forEach(el => {
        el.addEventListener('click', function(e) {
            const message = this.getAttribute('data-confirm') || 'Are you sure you want to proceed?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });

    // 5. Tooltip positioning on touch devices for 90-day history bars
    const ticks = document.querySelectorAll('.history-tick');
    ticks.forEach(tick => {
        tick.addEventListener('touchstart', function() {
            // Remove active from other ticks
            ticks.forEach(t => t.classList.remove('touch-active'));
            this.classList.add('touch-active');
        }, { passive: true });
    });
});
