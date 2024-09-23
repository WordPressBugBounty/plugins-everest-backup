"use strict";
(function () {
    var convertToSlug = function (string) {
        return string.toLowerCase()
            .replace(/ /g, '-')
            .replace(/[^\w-]+/g, '');
    };
    /**
     * SCript for settings page.
     */
    var SettingsPage = function () {
        HandleLoggerSpeedChange();
        HandleCloudDialogBox();
        function HandleLoggerSpeedChange() {
            var loggerSpeedRange = document.getElementById('logger_speed_range');
            if (!loggerSpeedRange) {
                return;
            }
            var loggerSpeedDisplay = document.getElementById('logger_speed_display');
            loggerSpeedRange.addEventListener('input', function () {
                loggerSpeedDisplay.innerText = this.value;
            });
        }
        function HandleCloudDialogBox() {
            var backupLocations = document.querySelectorAll('.ebwp-cloud-backup-location');
            if (!backupLocations.length) {
                return;
            }
            backupLocations.forEach(function (backupLocation) {
                var btn = backupLocation.querySelector('.ebwp-cloud-backup-location-btn');
                var dialog = backupLocation.querySelector('dialog');
                var dialogInput = dialog === null || dialog === void 0 ? void 0 : dialog.querySelector('input');
                var dialogBtnCancel = dialog === null || dialog === void 0 ? void 0 : dialog.querySelector('button.btn-cancel');
                btn === null || btn === void 0 ? void 0 : btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    dialog.showModal();
                });
                dialogInput === null || dialogInput === void 0 ? void 0 : dialogInput.addEventListener('input', function () {
                    var value = this.value;
                    this.value = convertToSlug(value);
                });
                dialogBtnCancel === null || dialogBtnCancel === void 0 ? void 0 : dialogBtnCancel.addEventListener('click', function (e) {
                    e.preventDefault();
                    dialog.close();
                });
            });
        }
    }; // SettingsPage.
    /**
     * After document is fully loaded.
     */
    window.addEventListener("load", function () {
        SettingsPage();
    });
})();
//# sourceMappingURL=settings.js.map