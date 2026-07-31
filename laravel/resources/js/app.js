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
