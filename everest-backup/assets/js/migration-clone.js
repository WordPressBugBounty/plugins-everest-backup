"use strict";
var __awaiter = (this && this.__awaiter) || function (thisArg, _arguments, P, generator) {
    function adopt(value) { return value instanceof P ? value : new P(function (resolve) { resolve(value); }); }
    return new (P || (P = Promise))(function (resolve, reject) {
        function fulfilled(value) { try { step(generator.next(value)); } catch (e) { reject(e); } }
        function rejected(value) { try { step(generator["throw"](value)); } catch (e) { reject(e); } }
        function step(result) { result.done ? resolve(result.value) : adopt(result.value).then(fulfilled, rejected); }
        step((generator = generator.apply(thisArg, _arguments || [])).next());
    });
};
var __generator = (this && this.__generator) || function (thisArg, body) {
    var _ = { label: 0, sent: function() { if (t[0] & 1) throw t[1]; return t[1]; }, trys: [], ops: [] }, f, y, t, g;
    return g = { next: verb(0), "throw": verb(1), "return": verb(2) }, typeof Symbol === "function" && (g[Symbol.iterator] = function() { return this; }), g;
    function verb(n) { return function (v) { return step([n, v]); }; }
    function step(op) {
        if (f) throw new TypeError("Generator is already executing.");
        while (_) try {
            if (f = 1, y && (t = op[0] & 2 ? y["return"] : op[0] ? y["throw"] || ((t = y["return"]) && t.call(y), 0) : y.next) && !(t = t.call(y, op[1])).done) return t;
            if (y = 0, t) op = [op[0] & 2, t.value];
            switch (op[0]) {
                case 0: case 1: t = op; break;
                case 4: _.label++; return { value: op[1], done: false };
                case 5: _.label++; y = op[1]; op = [0]; continue;
                case 7: op = _.ops.pop(); _.trys.pop(); continue;
                default:
                    if (!(t = _.trys, t = t.length > 0 && t[t.length - 1]) && (op[0] === 6 || op[0] === 2)) { _ = 0; continue; }
                    if (op[0] === 3 && (!t || (op[1] > t[0] && op[1] < t[3]))) { _.label = op[1]; break; }
                    if (op[0] === 6 && _.label < t[1]) { _.label = t[1]; t = op; break; }
                    if (t && _.label < t[2]) { _.label = t[2]; _.ops.push(op); break; }
                    if (t[2]) _.ops.pop();
                    _.trys.pop(); continue;
            }
            op = body.call(thisArg, _);
        } catch (e) { op = [6, e]; y = 0; } finally { f = t = 0; }
        if (op[0] & 5) throw op[1]; return { value: op[0] ? op[1] : void 0, done: true };
    }
};
(function () {
    var _this = this;
    var bodyClass = 'ebwp-is-active';
    var ajaxUrl = _everest_backup.ajaxUrl, actions = _everest_backup.actions, _nonce = _everest_backup._nonce, resInterval = _everest_backup.resInterval;
    var ModalContainer = document.getElementById('everest-backup-modal-wrapper');
    var LoaderWrapper = ModalContainer.querySelector('.loader-wrapper');
    var AfterRestoreDone = ModalContainer.querySelector('.after-process-complete');
    var AfterRestoreSuccess = ModalContainer.querySelector('.after-process-success');
    var AfterRestoreError = ModalContainer.querySelector('.after-process-error');
    var processDetails = document.querySelector('#process-info .process-details textarea');
    var processBar = document.querySelector('#import-on-process #process-info .progress .progress-bar');
    var processMsg = document.querySelector('#import-on-process #process-info .process-message');
    var backupErrorP = AfterRestoreError.querySelector('.everest-backup-error-during-backup-p');
    var restoreInitData = {};
    /**
     * Script for migration tab page.
     */
    var MigrationTabPage = function () {
        var backupFilesDropdown = document.querySelector('#everest-backup-modules-migration-clone-tab #backup-files-dropdown');
        var btnGenerateMigrationKey = document.querySelector('#everest-backup-modules-migration-clone-tab #generate-migration-key');
        backupFilesDropdown && backupFilesDropdown.addEventListener('change', function () {
            btnGenerateMigrationKey === null || btnGenerateMigrationKey === void 0 ? void 0 : btnGenerateMigrationKey.classList.remove('hidden');
        });
        var copyMigrationKeyToClipboard = function () {
            var copyText = document.querySelector("#everest-backup-modules-migration-clone-tab .copy-key-wrapper");
            if (null === copyText) {
                return;
            }
            var copyBtn = document.querySelector("#everest-backup-modules-migration-clone-tab .copy-button");
            var input = copyText.querySelector("#everest-backup-modules-migration-clone-tab input.text");
            copyBtn.addEventListener("click", function () {
                var _a;
                input.select();
                document.execCommand("copy");
                copyText.classList.add("active");
                (_a = window.getSelection()) === null || _a === void 0 ? void 0 : _a.removeAllRanges();
                setTimeout(function () {
                    copyText.classList.remove("active");
                }, 2500);
            });
        };
        copyMigrationKeyToClipboard();
    };
    /**
     * Script for clone tab page.
     */
    var CloneTabPage = function () {
        var _a;
        var prevTitleString = document.title;
        var cloneForm = document.getElementById('ebwp-clone-form');
        (_a = document.getElementById('migration_key_field')) === null || _a === void 0 ? void 0 : _a.addEventListener('input', function () {
            var _a;
            console.log('migration_key_field');
            (_a = document.getElementById('verify_key')) === null || _a === void 0 ? void 0 : _a.removeAttribute('disabled');
        });
        if (!cloneForm) {
            return;
        }
        var lastDetail = '';
        var lastHash = '';
        var sseURL = function () {
            var url = new URL(_everest_backup.sseURL);
            url.searchParams.append('t', "".concat(+new Date()));
            url.searchParams.append('everest_backup_ajax_nonce', _everest_backup._nonce);
            return url.toString();
        };
        var handleProcessSuccessError = function (success) {
            LoaderWrapper.classList.add('hidden');
            AfterRestoreDone.classList.remove('hidden');
            if (success) {
                AfterRestoreSuccess.classList.remove('hidden');
            }
            else {
                AfterRestoreError.classList.remove('hidden');
            }
        };
        var handleProgressInfo = function (message, progress) {
            processBar.style.width = "".concat(progress, "%");
            if ('undefined' !== typeof message) {
                processMsg.innerText = message;
            }
            if (!!message && ('undefined' !== typeof progress)) {
                document.title = "[".concat(progress, "%] ").concat(message);
            }
        };
        var handleProcessDetails = function (details) {
            if (details === lastDetail) {
                return;
            }
            if (!processDetails) {
                return;
            }
            if (('undefined' === typeof details) || !details) {
                return;
            }
            processDetails.value = "".concat(details, "\n") + processDetails.value;
            lastDetail = details;
        };
        var removeProcStatFile = function () {
            document.title = prevTitleString;
            return navigator.sendBeacon("".concat(ajaxUrl, "?action=everest_backup_process_status_unlink&everest_backup_ajax_nonce=").concat(_nonce));
        };
        /** @since 2.0.0 */
        var triggerSendBecon = function (data) {
            if (data === void 0) { data = {}; }
            var t = +new Date();
            /**
             * Send request to start backup.
             *
             * @since 1.0.7
             */
            return navigator.sendBeacon("".concat(ajaxUrl, "?action=").concat(actions.import, "&everest_backup_ajax_nonce=").concat(_nonce, "&t=").concat(t), JSON.stringify(data));
        };
        var skip_version_check = false;
        var handleProcStats = function (beaconSent) {
            var _a;
            var retry = 1;
            var timeoutNumber = 0;
            var onBeaconSent = function () { return __awaiter(_this, void 0, void 0, function () {
                var response, result;
                return __generator(this, function (_a) {
                    switch (_a.label) {
                        case 0: return [4 /*yield*/, fetch(sseURL(), {
                                method: "GET",
                                headers: {
                                    "Content-Type": "application/json"
                                }
                            })];
                        case 1:
                            response = _a.sent();
                            result = response.json();
                            result.then(function (res) {
                                retry = 1;
                                switch (res.status) {
                                    case 'done':
                                        removeProcStatFile();
                                        handleProcessSuccessError(true);
                                        break;
                                    case 'cloud':
                                        removeProcStatFile();
                                        break;
                                    case 'error':
                                        var lastError = getLastError(res.data);
                                        maybeShowLastError(lastError);
                                        removeProcStatFile();
                                        handleProcessSuccessError(false);
                                        break;
                                    default:
                                        handleProcessDetails(res.detail);
                                        handleProgressInfo(res.message, res.progress);
                                        if (!!res.version_diff_major && !skip_version_check) {
                                            if (!confirm('This backup uses PHP v' + res.zip_php_version + ', but your site is running v' + res.current_php_version + '. Restoring could cause problems. For a smooth restore, we recommend using the same PHP version for both your backup and your website. Proceed with caution! Do you wish to continue?')) {
                                                removeProcStatFile();
                                                window.location.reload();
                                                break;
                                            }
                                            else {
                                                delete res["version_diff_major"];
                                                skip_version_check = true;
                                                res.skip_php_version_check = true;
                                                triggerSendBecon(res);
                                                setTimeout(onBeaconSent, resInterval);
                                                break;
                                            }
                                        }
                                        if (!!res.next && res.next.length) {
                                            if (res.hash !== lastHash) {
                                                triggerSendBecon(res);
                                            }
                                            lastHash = res.hash;
                                        }
                                        setTimeout(onBeaconSent, resInterval);
                                        break;
                                }
                            }).catch(function (err) {
                                console.warn(err);
                                if (timeoutNumber)
                                    clearInterval(timeoutNumber);
                                if (retry > 3) {
                                    document.title = "EB: Error";
                                    handleProcessDetails("Failed to initiate connection, retry didn't work. Halting clone...");
                                    handleProcessDetails('=== Error ===');
                                    handleProcessDetails(err);
                                    handleProcessDetails('=== Error ===');
                                    handleProcessDetails('Note: Copy below error if required');
                                    return;
                                }
                                handleProcessDetails("Waiting for response. Retrying: ".concat(retry));
                                var retrySec = retry * 3000;
                                timeoutNumber = setTimeout(onBeaconSent, retrySec);
                                retry++;
                            });
                            return [2 /*return*/];
                    }
                });
            }); };
            function getLastError(data) {
                if (data.logs) {
                    if (data.logs.length > 0) {
                        var last_log = data.logs[data.logs.length - 1];
                        return (last_log.type === 'error') ? last_log.message : '';
                    }
                }
                return '';
            }
            function maybeShowLastError(lastError) {
                if (lastError && lastError !== '') {
                    if (lastError.includes('aborting restore')) {
                        backupErrorP.innerHTML = lastError;
                    }
                    if (lastError.includes('Download failed.')) {
                        backupErrorP.innerHTML = lastError;
                    }
                    if (lastError.includes('Too many retries.')) {
                        backupErrorP.innerHTML = lastError;
                    }
                    if (lastError.includes('Disk quota exceeded')) {
                        backupErrorP.innerHTML = 'Disk Quota Exceeded. Please check your server storage.';
                    }
                }
            }
            var onBeaconFailed = function () {
                removeProcStatFile();
            };
            if (beaconSent) {
                (_a = processDetails.parentElement) === null || _a === void 0 ? void 0 : _a.classList.remove('hidden');
                onBeaconSent();
            }
            else {
                onBeaconFailed();
            }
        };
        cloneForm.addEventListener('submit', function (event) {
            event.preventDefault();
            document.body.classList.add(bodyClass);
            var data = {};
            var formData = new FormData(cloneForm);
            formData.forEach(function (value, key) {
                data[key] = value;
            });
            removeProcStatFile();
            restoreInitData = data;
            var beaconSent = triggerSendBecon(data);
            handleProcStats(beaconSent);
        });
    }; // CloneTabPage.
    /**
     * After document is fully loaded.
     */
    window.addEventListener("load", function () {
        document.body.classList.remove(bodyClass);
        MigrationTabPage();
        CloneTabPage();
    });
})();
//# sourceMappingURL=migration-clone.js.map