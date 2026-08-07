// resources/js/app.js

import './bootstrap';
import './preferences';
import './live-prices';

import Alpine from 'alpinejs';
import ApexCharts from 'apexcharts';

window.Alpine = Alpine;
window.ApexCharts = ApexCharts;

import './charts';

Alpine.start();

// Small, dependency-free assistant modal used by Smart Selection. This
// listener intentionally does not rely on Alpine so it still works if a
// page-level Alpine component has malformed state during a test.
document.addEventListener('click', (event) => {
    const openButton = event.target.closest?.('[data-aki-chat-open]');
    const modal = document.querySelector('[data-aki-chat-modal]');
    if (!modal) return;
    if (openButton) {
        modal.style.display = 'grid';
        return;
    }
    if (event.target === modal || event.target.closest?.('[data-aki-chat-close]')) {
        modal.style.display = 'none';
    }
});
