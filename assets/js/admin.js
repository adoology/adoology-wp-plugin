(function () {
    'use strict';

    document.addEventListener('submit', function (event) {
        var form = event.target;
        var message = form.getAttribute('data-adoology-confirm');
        if (message && !window.confirm(message)) {
            event.preventDefault();
        }
    });
}());
