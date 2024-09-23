"use strict";
(function () {
    var locale = _everest_backup.locale;
    var modal = document.getElementById('everestBackupCustomModal');
    var modal_header = document.getElementById('everestBackupHeaderText');
    var modal_footer = document.getElementById('everestBackupFooterText');
    var upload_to_cloud_btns = document.querySelectorAll('.everest-backup-upload-to-cloud-btn');
    var close_modal = document.querySelectorAll('.everest-backup-close-modal');
    var active_plugins_div = document.querySelector('#everest-backup-active-plugins');
    var loader = document.querySelector('.everest-backup-loader-overlay');
    upload_to_cloud_btns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var this_btn = this;
            var form = new FormData();
            form.append('cloud_info', encodeURIComponent(JSON.stringify(Object.keys(JSON.parse(_everest_backup.locale.cloudLogos)))));
            form.append('action', _everest_backup.locale.ajaxGetCloudStorage);
            form.append('everest_backup_ajax_nonce', _everest_backup._nonce);
            loader.style.display = 'flex';
            fetch(_everest_backup.ajaxUrl, {
                method: 'POST',
                body: form,
            }).then(function (result) {
                if (result.ok) {
                    return result.text();
                }
                else {
                    throw new Error('Connection error.');
                }
            }).then(function (data) {
                var _a;
                var result = JSON.parse(data);
                var cloud_space_available;
                if (result.success) {
                    cloud_space_available = result.data;
                }
                modal.style.display = 'block';
                var upload_file = this_btn.getAttribute('data-file');
                var upload_size = parseInt(this_btn.getAttribute('data-file_size'));
                var active_plugins = ((_a = this_btn.getAttribute('data-active-plugins')) === null || _a === void 0 ? void 0 : _a.split(',')) || [];
                if (active_plugins.length > 0) {
                    var html = '';
                    var cloudLogos = JSON.parse(locale.cloudLogos);
                    for (var i = 0; i < active_plugins.length; i++) {
                        var plugin = active_plugins[i];
                        var upload_btn_class = '';
                        var upload_warning = '';
                        var disabled = '';
                        var available_space = parseInt(cloud_space_available[plugin]);
                        var available_space_html = (available_space !== -1) ? ('Available: ' + bytesToSize(available_space) + '<br>') : '';
                        if (available_space === -1 || available_space > upload_size) {
                            upload_btn_class = 'everest-backup-start-upload-to-cloud';
                        }
                        else {
                            upload_warning = '<small style="color:red">Warning: insufficient space.</small>';
                            disabled = 'disabled';
                        }
                        var cloudLogo = cloudLogos[plugin];
                        html += '<div class="everest-backup-start-upload-to-cloud-wrapper" style="width:50%"><button ' +
                            'data-href="' + locale.uploadToCloudURL + '&cloud=' + active_plugins[i] + '&file=' + upload_file + '" ' +
                            'class="button ' + upload_btn_class + '" ' +
                            'type="button" ' +
                            disabled +
                            '>' + cloudLogo + '</button>' +
                            '</div>' +
                            '<div class="everest-backup-cloud-available-storage" style="width:50%; text-align:left;">' +
                            available_space_html +
                            'Upload Size: ' + bytesToSize(upload_size) + '<br>' +
                            upload_warning +
                            '</div>';
                    }
                    active_plugins_div.innerHTML = html;
                    loader.style.display = 'none';
                }
            }).catch(function (error) {
                loader.style.display = 'none';
                console.error('Fetch error:', error);
            });
        });
        function bytesToSize(bytes) {
            var sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
            if (bytes === 0)
                return '0 Byte';
            var i = Math.floor(Math.log(Number(bytes)) / Math.log(1024));
            return (bytes / Math.pow(1024, i)).toFixed(2) + ' ' + sizes[i];
        }
    });
    /**
     * When upload to cloud button is clicked.
     * Button is dynamically created, so search for button on document for button click.
     */
    document.addEventListener('click', function (event) {
        var targetElement = event.target;
        var hasParentWithClass = false;
        // Traverse up the DOM tree
        while (targetElement) {
            if (targetElement.classList.contains('everest-backup-start-upload-to-cloud')) {
                hasParentWithClass = true;
                break;
            }
            // Check if parentElement is not null
            if (targetElement.parentElement) {
                targetElement = targetElement.parentElement;
            }
            else {
                break; // Exit the loop if parentElement is null
            }
        }
        if (hasParentWithClass) {
            var button_wrapper = document.querySelector('.everest-backup-start-upload-to-cloud-wrapper');
            button_wrapper === null || button_wrapper === void 0 ? void 0 : button_wrapper.setAttribute('style', 'margin: 0 auto;');
            var URL_1 = targetElement.getAttribute('data-href');
            var cloud_storage_info_div = document.querySelector('.everest-backup-cloud-available-storage');
            active_plugins_div.outerHTML = '<div class="loader-box"><img src="' + locale.loadingGifURL + '"></div>';
            if (cloud_storage_info_div) {
                cloud_storage_info_div.outerHTML = '';
            }
            modal_header.innerHTML = '';
            modal_footer.innerHTML = 'Please wait while we prepare the file for uploading to your cloud.';
            close_modal.forEach(function (btn) {
                btn.style.display = 'none';
            });
            targetElement.setAttribute('data-href', '');
            if (URL_1 !== '' || URL_1 !== undefined) {
                window.location.href = URL_1;
            }
        }
    });
    close_modal.forEach(function (btn) {
        btn.addEventListener('click', function () {
            modal.style.display = 'none';
        });
    });
})();
//# sourceMappingURL=upload-to-cloud.js.map