(function () {
    const form = document.querySelector('.wpforms-form');
    if (!form) return;

    // Submit button: inject arrow icon to match theme button pattern
    var submit = form.querySelector('.wpforms-submit.button');
    if (submit && !submit.querySelector('.button__icon')) {
        var icon = document.createElement('span');
        icon.className = 'button__icon arrow';
        icon.innerHTML =
            '<svg class="icon icon-arrow-up-right" focusable="false" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="9" height="9" fill="none" viewBox="8 8 8.75 8.75">' +
            '<path class="arrow" fill="none" stroke="currentColor" stroke-linecap="square" stroke-miterlimit="10" stroke-width="1.5" d="M9.273 8.727h6.55v6.55m-.454-6.095-6.642 6.642"></path>' +
            '</svg>';
        submit.appendChild(icon);
    }
})();
