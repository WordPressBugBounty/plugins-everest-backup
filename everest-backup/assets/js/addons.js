"use strict";
(function () {
    var ajaxUrl = _everest_backup.ajaxUrl, _nonce = _everest_backup._nonce, locale = _everest_backup.locale;
    var AddonInstallerForms = document.querySelectorAll("#everest-backup-container .addon-installer-form");
    var ActionButtonWrapper = document.querySelector('#everest-backup-container .theme-browser .theme .theme-actions');
    var ajaxHandler = function (action, init) {
        var url = "".concat(ajaxUrl, "?action=").concat(action, "&everest_backup_ajax_nonce=").concat(_nonce);
        return fetch(url, init);
    }; // ajaxHandler.
    var toggleActionButtonWrapper = function (display) {
        if (!ActionButtonWrapper) {
            return;
        }
        if (display) {
            ActionButtonWrapper.style.opacity = '1';
        }
        else {
            ActionButtonWrapper.style.opacity = '0';
        }
    };
    /**
     * Script for addons page.
     */
    var InstallAddon = function () {
        AddonInstallerForms.forEach(function (AddonInstallerForm) {
            AddonInstallerForm.addEventListener("submit", function (event) {
                event.preventDefault();
                var installingBtn = AddonInstallerForm.querySelector('.button-addon-installing');
                var fieldset = AddonInstallerForm.querySelector('fieldset');
                fieldset === null || fieldset === void 0 ? void 0 : fieldset.classList.add('hidden');
                installingBtn === null || installingBtn === void 0 ? void 0 : installingBtn.classList.remove('hidden');
                var data = {};
                var formData = new FormData(AddonInstallerForm);
                formData.forEach(function (value, key) {
                    data[key] = value;
                });
                toggleActionButtonWrapper(true);
                ajaxHandler("everest_backup_addon", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                    },
                    body: JSON.stringify(data),
                }).then(function () {
                    window.location.href = "".concat(window.location.href, "&force_reload=true");
                });
            });
        });
    }; // InstallAddon.
    /**
     * After document is fully loaded.
     */
    window.addEventListener("load", function () {
        InstallAddon();
    });
})();
//# sourceMappingURL=addons.js.map