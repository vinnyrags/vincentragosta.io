(function () {
    const form = document.querySelector('.wpforms-form');
    if (!form) return;

    // Float labels: add placeholder=" " for :placeholder-shown CSS selector
    form.querySelectorAll(
        '.wpforms-field input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]), .wpforms-field textarea'
    ).forEach(function (field) {
        if (!field.hasAttribute('placeholder')) {
            field.setAttribute('placeholder', ' ');
        }
    });
})();
