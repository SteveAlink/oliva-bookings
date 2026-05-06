(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var container = document.getElementById('oliva-bookings');

        if (!container) {
            return;
        }

        container.classList.add('oliva-bookings-loaded');
    });
}());
