"use strict";
/**
 * This is the global js file for Everest Backup plugin.
 */
(function () {
    var lazyLoadIframes = function () {
        var youtubeIframes = document.querySelectorAll('.everest-backup_card .youtube-iframe');
        youtubeIframes.forEach(function (youtubeIframe) {
            var youtubeID = youtubeIframe.getAttribute('data-id');
            if (!youtubeID) {
                return;
            }
            var src = "//www.youtube.com/embed/".concat(youtubeID);
            youtubeIframe.setAttribute('src', src);
        });
    };
    var toggleConsentDialog = function () {
        var dialog = document.getElementById('everest-backup-consent-dialog');
        if (!dialog) {
            return;
        }
        dialog.showModal();
    };
    /**
     * When backup is uploading to cloud.
     */
    var UploadToCloud = function () {
        if (typeof _everest_backup !== 'undefined') {
            var loader_image = new Image();
            loader_image.src = _everest_backup.locale.loadingGifURL;
            var after_uplaod_to_cloud_process_modal_1 = document.querySelector('#everestBackupCloudModal');
            var close_modal = document.querySelectorAll('.everest-backup-close-modal');
            close_modal.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    if (after_uplaod_to_cloud_process_modal_1) {
                        after_uplaod_to_cloud_process_modal_1.style.display = 'none';
                    }
                });
            });
            var locale = _everest_backup.locale;
            if (locale.UploadProcessComplete) {
                after_uplaod_to_cloud_process_modal_1.style.display = 'block';
            }
        }
    };
    window.addEventListener("load", function () {
        toggleConsentDialog();
        lazyLoadIframes();
        UploadToCloud();
    });
})();
//# sourceMappingURL=index.js.map