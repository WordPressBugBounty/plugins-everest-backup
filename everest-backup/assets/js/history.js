"use strict";
var __assign = (this && this.__assign) || function () {
    __assign = Object.assign || function(t) {
        for (var s, i = 1, n = arguments.length; i < n; i++) {
            s = arguments[i];
            for (var p in s) if (Object.prototype.hasOwnProperty.call(s, p))
                t[p] = s[p];
        }
        return t;
    };
    return __assign.apply(this, arguments);
};
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
    var locale = _everest_backup.locale, ajaxUrl = _everest_backup.ajaxUrl;
    var modal = document.getElementById('everestBackupCustomModal');
    var cmodal = document.querySelectorAll('.everest-backup-modal');
    var modal_header = document.getElementById('everestBackupHeaderText');
    var modal_footer = document.getElementById('everestBackupFooterText');
    var upload_to_cloud_btns = document.querySelectorAll('.everest-backup-upload-to-cloud-btn');
    var close_modal = document.querySelectorAll('.everest-backup-close-modal');
    var active_plugins_div = document.querySelector('#everest-backup-active-plugins');
    var loader = document.querySelector('.everest-backup-loader-overlay');
    var list_files_modal = document.getElementById('everestBackupListFilesModal');
    var list_files_modal_file_span = document.getElementById('everestBackupBackupName');
    var list_files_modal_list_container = document.getElementById('everestBackupFileList');
    var list_files_btns = document.querySelectorAll('.everest-backup-list-files-btn');
    var tree = {};
    var backup_content_file_info = {};
    var current_backup_file = '';
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
    });
    /**
     * Convert bytes to a human-readable size.
     *
     * @param {number} bytes
     * @return {string}
     */
    function bytesToSize(bytes) {
        var sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        if (bytes === 0)
            return '0 Byte';
        var i = Math.floor(Math.log(Number(bytes)) / Math.log(1024));
        return (bytes / Math.pow(1024, i)).toFixed(2) + ' ' + sizes[i];
    }
    list_files_btns.forEach(function (btn) {
        btn.addEventListener('click', listBackupFileContent);
    });
    function listBackupFileContent() {
        fetchBackupFileContent(this);
    }
    function fetchBackupFileContent(this_btn, continued, c_seek) {
        if (continued === void 0) { continued = false; }
        if (c_seek === void 0) { c_seek = 0; }
        return __awaiter(this, void 0, void 0, function () {
            var form, return_files;
            var _this = this;
            return __generator(this, function (_a) {
                switch (_a.label) {
                    case 0:
                        form = new FormData();
                        return_files = [];
                        current_backup_file = this_btn.getAttribute('data-file');
                        form.append('file', current_backup_file);
                        if (continued) {
                            form.append('resume', '1');
                            form.append('c_seek', c_seek.toString());
                        }
                        form.append('action', _everest_backup.locale.listBackupFileContent);
                        form.append('everest_backup_ajax_nonce', _everest_backup._nonce);
                        loader.style.display = 'flex';
                        return [4 /*yield*/, fetch(_everest_backup.ajaxUrl, {
                                method: 'POST',
                                body: form,
                            }).then(function (result) {
                                if (result.ok) {
                                    return result.text();
                                }
                                else {
                                    throw new Error('Connection error.');
                                }
                            }).then(function (data) { return __awaiter(_this, void 0, void 0, function () {
                                var filelist, files, morefiles, treeHTML;
                                return __generator(this, function (_a) {
                                    switch (_a.label) {
                                        case 0:
                                            filelist = JSON.parse(data);
                                            if (!(filelist.files.length > 0)) return [3 /*break*/, 5];
                                            files = (filelist.files);
                                            morefiles = [];
                                            if (!filelist.continued) return [3 /*break*/, 2];
                                            return [4 /*yield*/, fetchBackupFileContent(this_btn, true, filelist.c_seek)];
                                        case 1:
                                            morefiles = (_a.sent());
                                            files = files.concat(morefiles);
                                            _a.label = 2;
                                        case 2:
                                            if (continued) {
                                                return_files = files;
                                                return [2 /*return*/];
                                            }
                                            return [4 /*yield*/, buildTreeStructure(files)];
                                        case 3:
                                            // Build the tree structure and generate HTML
                                            tree = _a.sent();
                                            return [4 /*yield*/, treeToHTML(tree, true)];
                                        case 4:
                                            treeHTML = _a.sent();
                                            // Add the generated HTML to the DOM
                                            list_files_modal_list_container.innerHTML = treeHTML;
                                            list_files_modal_file_span.innerHTML = current_backup_file;
                                            list_files_modal_list_container.querySelectorAll('details').forEach(function (details) {
                                                details.addEventListener('click', function (event) { return handleLazyLoad(event, tree); });
                                            });
                                            _a.label = 5;
                                        case 5:
                                            list_files_modal.style.display = 'block';
                                            loader.style.display = 'none';
                                            return [2 /*return*/];
                                    }
                                });
                            }); }).catch(function (error) {
                                loader.style.display = 'none';
                                console.error('Fetch error:', error);
                            })];
                    case 1:
                        _a.sent();
                        return [2 /*return*/, return_files];
                }
            });
        });
    }
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
            cmodal.forEach(function (modal) { return modal.style.display = 'none'; });
        });
    });
    // Function to build a tree structure
    function buildTreeStructure(filePaths) {
        return __awaiter(this, void 0, void 0, function () {
            var root;
            return __generator(this, function (_a) {
                root = {};
                // Build nested structure from file paths
                filePaths.forEach(function (path) {
                    var parts = path.path.split('/');
                    var current = root;
                    parts.forEach(function (part, index) {
                        if (!current[part]) {
                            current[part] = (index === (parts.length - 1)) ? { start: path.start, end: path.end, path: path.path } : { path: path.path };
                            if (index === (parts.length - 1)) {
                                backup_content_file_info[path.path] = { start: path.start, end: path.end };
                            }
                        }
                        current = current[part];
                    });
                });
                return [2 /*return*/, root];
            });
        });
    }
    // Function to convert tree structure to HTML
    function treeToHTML(tree, root) {
        if (root === void 0) { root = false; }
        return __awaiter(this, void 0, void 0, function () {
            var html, key;
            return __generator(this, function (_a) {
                html = root ? '<ul class="tree">' : '';
                for (key in tree) {
                    if ((key === 'start') || (key === 'end') || (key === 'path')) {
                        continue;
                    }
                    if (tree[key].start) {
                        html += "<li class=\"everest-backup-file-in-backup\" key=\"".concat(key, "\" path=\"").concat(tree[key].path, "\">\n                    ").concat(key, " [size: ").concat(bytesToSize(tree[key].end - tree[key].start), "]\n                    <span class=\"everest-backup-file-in-backup-download\"></span>\n                </li>");
                    }
                    else {
                        html += "\n                    <li>\n                        <details>\n                            <summary key=\"".concat(key, "\" path=\"").concat(tree[key].path, "\">").concat(key, "</summary>\n                            <ul class=\"tree\" data-key=\"").concat(key, "\"></ul>\n                        </details>\n                    </li>\n                ");
                    }
                }
                html += root ? '</ul>' : '';
                return [2 /*return*/, html];
            });
        });
    }
    function handleLazyLoad(event, tree) {
        var _a;
        return __awaiter(this, void 0, void 0, function () {
            var summary, details, folderKey, ul, path, parts, current, _b;
            return __generator(this, function (_c) {
                switch (_c.label) {
                    case 0:
                        summary = event.target;
                        if (!summary)
                            return [2 /*return*/];
                        details = summary.closest('details');
                        if (!details)
                            return [2 /*return*/];
                        folderKey = (_a = summary.textContent) === null || _a === void 0 ? void 0 : _a.trim();
                        if (!folderKey)
                            return [2 /*return*/];
                        ul = details.querySelector('.tree');
                        if (!ul || ul.childElementCount > 0)
                            return [2 /*return*/]; // Already loaded
                        path = summary.getAttribute('path');
                        parts = path.split('/');
                        current = getCurrentFolderNode(folderKey, parts);
                        if (!(Object.keys(current).length > 0)) return [3 /*break*/, 2];
                        _b = ul;
                        return [4 /*yield*/, treeToHTML(current)];
                    case 1:
                        _b.innerHTML = _c.sent();
                        list_files_modal_list_container.querySelectorAll('details').forEach(function (details) {
                            details.addEventListener('click', function (event) { return handleLazyLoad(event, tree); });
                        });
                        _c.label = 2;
                    case 2: return [2 /*return*/];
                }
            });
        });
    }
    function getCurrentFolderNode(folderKey, parts) {
        var current = __assign({}, tree);
        for (var i = 0; i < parts.length; i++) {
            if (folderKey === parts[i]) {
                current = current[parts[i]];
                break;
            }
            if (current[parts[i]]) {
                current = current[parts[i]];
            }
        }
        return current;
    }
    document.addEventListener('click', function (event) {
        if (event.target.classList.contains('everest-backup-file-in-backup')) {
            downloadbackupFileContent(event.target);
        }
    });
    function downloadbackupFileContent(element, continued, c_seek) {
        var _this = this;
        if (continued === void 0) { continued = false; }
        if (c_seek === void 0) { c_seek = 0; }
        var key = element.getAttribute('key');
        var unhidden_filename_with_no_ext = key.replace(/^\.+/, '').replace(/\.[^/.]+$/, '');
        var path = element.getAttribute('path');
        if (backup_content_file_info[path]) {
            var file_info_key_values = backup_content_file_info[path];
            if (file_info_key_values.start
                && file_info_key_values.end) {
                var start = file_info_key_values.start;
                var end = file_info_key_values.end;
                var form = new FormData();
                form.append('start', start.toString());
                form.append('end', end.toString());
                if (continued) {
                    form.append('resume', '1');
                    form.append('c_seek', c_seek.toString());
                }
                form.append('file', unhidden_filename_with_no_ext);
                form.append('backup', current_backup_file);
                form.append('action', _everest_backup.locale.generateBackupListFile);
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
                }).then(function (data) { return __awaiter(_this, void 0, void 0, function () {
                    var response, link;
                    return __generator(this, function (_a) {
                        response = JSON.parse(data);
                        if (response.continued) {
                            downloadbackupFileContent(element, true, response.c_seek);
                        }
                        else {
                            if (response) {
                                link = document.createElement('a');
                                link.target = '_blank';
                                link.download = key;
                                link.href = locale.backupListFileTempURL + unhidden_filename_with_no_ext;
                                link.click();
                            }
                            loader.style.display = 'none';
                        }
                        return [2 /*return*/];
                    });
                }); }).catch(function (error) {
                    loader.style.display = 'none';
                    console.error('Fetch error:', error);
                });
            }
        }
    }
})();
//# sourceMappingURL=history.js.map