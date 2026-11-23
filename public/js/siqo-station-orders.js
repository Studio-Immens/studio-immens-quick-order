(function () {
    'use strict';

    var root = document.getElementById('siqo-station-orders');
    if (!root || !window.fetch) {
        return;
    }

    var list = root.querySelector('#siqo-station-list');
    var ajaxUrl = root.getAttribute('data-ajax-url');
    var nonce = root.getAttribute('data-nonce');
    var activeStation = root.getAttribute('data-active-station') || '__all';
    var pollInterval = parseInt(root.getAttribute('data-poll-interval'), 10) || 30000;
    var requestController = null;
    var pollTimer = null;
    var soundRoot = root.querySelector('[data-station-sound]');
    var soundButton = soundRoot ? soundRoot.querySelector('[data-station-sound-toggle]') : null;
    var soundPanel = soundRoot ? soundRoot.querySelector('[data-station-sound-panel]') : null;
    var soundEnabled = soundRoot ? soundRoot.querySelector('[data-station-sound-enabled]') : null;
    var soundIcon = soundRoot ? soundRoot.querySelector('[data-station-sound-icon]') : null;
    var soundAudio = soundRoot ? soundRoot.querySelector('[data-station-sound-audio]') : null;
    var soundChoices = soundRoot ? Array.prototype.slice.call(soundRoot.querySelectorAll('[data-station-sound-choice]')) : [];
    var soundStorageKey = 'siqoStationSound';
    var soundEnabledStorageKey = 'siqoStationSoundEnabled';
    var lastTotalOrders = parseInt(root.getAttribute('data-total-orders'), 10) || 0;
    var soundInitialized = true;
    var soundUnlocked = false;

    function readStored(key, fallback) {
        try {
            var value = window.localStorage.getItem(key);
            return value === null ? fallback : value;
        } catch (error) {
            return fallback;
        }
    }

    function storeValue(key, value) {
        try {
            window.localStorage.setItem(key, value);
        } catch (error) {
            // Storage can be unavailable in private mode.
        }
    }

    function selectedSoundChoice() {
        for (var i = 0; i < soundChoices.length; i += 1) {
            if (soundChoices[i].checked) {
                return soundChoices[i];
            }
        }
        return soundChoices.length ? soundChoices[0] : null;
    }

    function applySoundSettings() {
        if (!soundRoot) {
            return;
        }

        var choice = selectedSoundChoice();
        var enabled = !soundEnabled || soundEnabled.checked;

        if (soundIcon) {
            soundIcon.classList.toggle('dashicons-controls-volumeon', enabled);
            soundIcon.classList.toggle('dashicons-controls-volumeoff', !enabled);
        }

        if (soundButton) {
            soundButton.classList.toggle('is-muted', !enabled);
        }

        if (soundAudio && choice) {
            var url = choice.getAttribute('data-sound-url') || '';
            if (soundAudio.getAttribute('src') !== url) {
                soundAudio.setAttribute('src', url);
            }
            soundAudio.volume = 0.65;
        }
    }

    function playStationSound() {
        if (!soundAudio || !soundEnabled || !soundEnabled.checked) {
            return;
        }

        try {
            soundAudio.currentTime = 0;
            var attempt = soundAudio.play();
            if (attempt && typeof attempt.catch === 'function') {
                attempt.catch(function () {
                    // Autoplay may be blocked until the operator interacts with the page.
                });
            }
        } catch (error) {
            // Ignore playback errors.
        }
    }

    function unlockSound() {
        if (soundUnlocked || !soundAudio || !soundEnabled || !soundEnabled.checked) {
            return;
        }

        soundUnlocked = true;
        var volume = soundAudio.volume;

        try {
            soundAudio.volume = 0;
            var attempt = soundAudio.play();
            if (attempt && typeof attempt.then === 'function') {
                attempt.then(function () {
                    soundAudio.pause();
                    soundAudio.currentTime = 0;
                    soundAudio.volume = volume;
                }).catch(function () {
                    soundAudio.volume = volume;
                });
            } else {
                soundAudio.volume = volume;
            }
        } catch (error) {
            soundAudio.volume = volume;
        }
    }

    function setSoundPanel(open) {
        if (!soundPanel || !soundButton) {
            return;
        }
        soundPanel.hidden = !open;
        soundButton.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) {
            var first = soundPanel.querySelector('input, button');
            if (first) {
                first.focus({ preventScroll: true });
            }
        }
    }

    function initSound() {
        if (!soundRoot) {
            return;
        }

        var storedSound = readStored(soundStorageKey, '');
        var storedEnabled = readStored(soundEnabledStorageKey, '1');
        var hasStoredSound = false;

        soundChoices.forEach(function (choice) {
            if (choice.value === storedSound) {
                choice.checked = true;
                hasStoredSound = true;
            }
        });

        if (!hasStoredSound) {
            var checked = selectedSoundChoice();
            if (checked) {
                storeValue(soundStorageKey, checked.value);
            }
        }

        if (soundEnabled) {
            soundEnabled.checked = storedEnabled !== '0';
        }

        applySoundSettings();
    }

    if (soundRoot) {
        soundRoot.addEventListener('change', function (event) {
            if (event.target === soundEnabled) {
                storeValue(soundEnabledStorageKey, soundEnabled.checked ? '1' : '0');
                applySoundSettings();
                return;
            }

            if (event.target.matches('[data-station-sound-choice]')) {
                storeValue(soundStorageKey, event.target.value);
                applySoundSettings();
            }
        });

        soundRoot.addEventListener('click', function (event) {
            var toggle = event.target.closest('[data-station-sound-toggle]');
            if (toggle) {
                setSoundPanel(soundPanel.hidden);
                return;
            }

            var preview = event.target.closest('[data-station-sound-preview]');
            if (preview) {
                var url = preview.getAttribute('data-station-sound-preview') || '';
                if (!url) {
                    return;
                }
                try {
                    var audio = new Audio(url);
                    audio.volume = 0.65;
                    var attempt = audio.play();
                    if (attempt && typeof attempt.catch === 'function') {
                        attempt.catch(function () {});
                    }
                } catch (error) {
                    // Ignore preview errors.
                }
            }
        });

        document.addEventListener('click', function (event) {
            if (!soundPanel.hidden && !soundRoot.contains(event.target)) {
                setSoundPanel(false);
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !soundPanel.hidden) {
                setSoundPanel(false);
                soundButton.focus({ preventScroll: true });
            }
        });

        initSound();

        document.addEventListener('pointerdown', unlockSound, { once: true });
        document.addEventListener('keydown', unlockSound, { once: true });
    }

    function setBusy(isBusy) {
        list.setAttribute('aria-busy', isBusy ? 'true' : 'false');
    }

    function setActiveStation(station) {
        activeStation = station;
        root.setAttribute('data-active-station', station);

        root.querySelectorAll('[data-station-target]').forEach(function (tab) {
            var isActive = tab.getAttribute('data-station-target') === station;
            tab.classList.toggle('is-active', isActive);
            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });
    }

    function updateCounts(counts) {
        if (!counts) {
            return;
        }

        root.querySelectorAll('[data-station-count]').forEach(function (node) {
            var key = node.getAttribute('data-station-count');
            if (Object.prototype.hasOwnProperty.call(counts, key)) {
                node.textContent = counts[key];
            }
        });
    }

    function showError(message) {
        list.innerHTML = '';
        var box = document.createElement('div');
        box.className = 'siqo-station-error';
        box.textContent = message;
        list.appendChild(box);
    }

    async function readJson(response) {
        var text = await response.text();

        try {
            return JSON.parse(text);
        } catch (error) {
            throw new Error('Sessione scaduta. Ricarica la pagina.');
        }
    }

    async function post(data) {
        var body = new URLSearchParams();
        Object.keys(data).forEach(function (key) {
            body.append(key, data[key]);
        });

        var response = await fetch(ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: body.toString()
        });

        var json = await readJson(response);

        if (!json || !json.success) {
            throw new Error(json && json.data && json.data.message ? json.data.message : 'Errore di comunicazione.');
        }

        return json.data;
    }

    async function load(station, silent) {
        if (requestController) {
            requestController.abort();
        }

        requestController = new AbortController();

        if (!silent) {
            setBusy(true);
        }

        try {
            var response = await fetch(ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                signal: requestController.signal,
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body: new URLSearchParams({
                    action: 'SIQO_station_orders',
                    nonce: nonce,
                    station: station
                }).toString()
            });

            var json = await readJson(response);

            if (!json || !json.success) {
                throw new Error(json && json.data && json.data.message ? json.data.message : 'Errore di caricamento.');
            }

            list.innerHTML = json.data.html;
            updateCounts(json.data.counts);

            var nextTotalOrders = json.data.counts && typeof json.data.counts.__all !== 'undefined'
                ? parseInt(json.data.counts.__all, 10) || 0
                : lastTotalOrders;

            if (soundInitialized && nextTotalOrders > lastTotalOrders) {
                playStationSound();
            }

            lastTotalOrders = nextTotalOrders;
            soundInitialized = true;
            setActiveStation(json.data.station);
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }
            showError(error.message);
        } finally {
            if (!silent) {
                setBusy(false);
            }
        }
    }

    async function updateStatus(button) {
        var orderId = button.getAttribute('data-order-id');
        var station = button.getAttribute('data-station');
        var status = button.getAttribute('data-station-action');

        button.disabled = true;

        try {
            await post({
                action: 'SIQO_station_update_status',
                nonce: nonce,
                order_id: orderId,
                station: station,
                status: status
            });

            await load(activeStation, true);
        } catch (error) {
            button.disabled = false;
            showError(error.message);
        }
    }

    async function handleApproval(button) {
        var box = button.closest('.siqo-order-approval');
        if (!box) {
            return;
        }

        var buttons = box.querySelectorAll('.siqo-approve, .siqo-reject');
        var status = box.querySelector('.siqo-order-approval-status');
        var isApprove = button.classList.contains('siqo-approve');
        var endpoint = box.getAttribute('data-ajax-url') || ajaxUrl;
        var body = new FormData();

        body.append('action', isApprove ? 'siqo_order_approve' : 'siqo_order_reject');
        body.append('_siqo_nonce', box.getAttribute('data-nonce') || '');
        body.append('order_id', button.getAttribute('data-order-id') || '');
        body.append('decision', isApprove ? 'approve' : 'reject');

        Array.prototype.forEach.call(buttons, function (node) {
            node.disabled = true;
        });

        if (status) {
            status.classList.remove('is-error');
            status.textContent = 'Invio…';
        }

        try {
            var response = await fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                body: body
            });
            var json = await readJson(response);

            if (!json || !json.success) {
                throw new Error(json && json.data && json.data.message ? json.data.message : 'Errore di comunicazione.');
            }

            window.location.reload();
        } catch (error) {
            var message = error.message || 'Errore di comunicazione.';

            if (status) {
                status.textContent = message;
                status.classList.add('is-error');
            } else {
                window.alert(message);
            }

            Array.prototype.forEach.call(buttons, function (node) {
                node.disabled = false;
            });
        }
    }

    function setTableBarStatus(bar, message, isError) {
        var status = bar.querySelector('.siqo-table-session-status');
        if (!status) {
            return;
        }
        status.textContent = message || '';
        status.classList.toggle('is-error', !!isError);
    }

    function toggleTable(button) {
        var bar = button.closest('.siqo-table-session-bar');
        var chip = button.closest('.siqo-table-chip');
        if (!bar || !chip) {
            return;
        }

        var doAction = button.getAttribute('data-do') === 'close' ? 'close' : 'open';
        var labelOpen = bar.getAttribute('data-label-open') || 'Apri';
        var labelClose = bar.getAttribute('data-label-close') || 'Chiudi';
        var tableId = chip.getAttribute('data-table-id') || '';
        var endpoint = bar.getAttribute('data-ajax-url') || ajaxUrl;
        var body = new FormData();

        body.append('action', doAction === 'close' ? 'siqo_table_close' : 'siqo_table_open');
        body.append('_siqo_nonce', bar.getAttribute('data-nonce') || '');
        body.append('table_id', tableId);
        body.append('do', doAction);

        button.disabled = true;
        setTableBarStatus(bar, doAction === 'close' ? 'Chiusura…' : 'Apertura…', false);

        fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            body: body
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (json) {
                if (!json || !json.success) {
                    throw new Error(json && json.data && json.data.message ? json.data.message : 'Errore di comunicazione.');
                }

                var isActive = !!json.data.active;
                var statusLabel = chip.querySelector('.siqo-table-chip-status');

                chip.classList.toggle('is-active', isActive);
                chip.setAttribute('data-expires', isActive ? (json.data.expires_at || '') : '');
                button.setAttribute('data-do', isActive ? 'close' : 'open');
                button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                button.classList.toggle('is-open', !isActive);
                button.classList.toggle('is-close', isActive);
                button.textContent = isActive ? labelClose : labelOpen;

                if (statusLabel) {
                    statusLabel.textContent = isActive ? (json.data.label || 'Attivo') : 'Non attivo';
                }

                setTableBarStatus(bar, isActive ? 'Tavolo aperto.' : 'Tavolo chiuso.', false);

                var closeAll = bar.querySelector('[data-table-close-all]');
                if (closeAll) {
                    closeAll.disabled = !bar.querySelector('.siqo-table-chip.is-active');
                }
            })
            .catch(function (error) {
                setTableBarStatus(bar, error.message || 'Errore di comunicazione.', true);
            })
            .then(function () {
                button.disabled = false;
            });
    }

    function closeAllTables(button) {
        var bar = button.closest('.siqo-table-session-bar');
        if (!bar || button.disabled) {
            return;
        }

        if (!bar.querySelector('.siqo-table-chip.is-active')) {
            setTableBarStatus(bar, 'Nessun tavolo attivo.', false);
            return;
        }

        var confirmMessage = bar.getAttribute('data-confirm-close-all') || 'Chiudere tutti i tavoli attivi?';
        if (!window.confirm(confirmMessage)) {
            return;
        }

        var endpoint = bar.getAttribute('data-ajax-url') || ajaxUrl;
        var labelOpen = bar.getAttribute('data-label-open') || 'Apri';
        var body = new FormData();

        body.append('action', 'siqo_tables_close_all');
        body.append('do', 'close_all');
        body.append('_siqo_nonce', bar.getAttribute('data-nonce') || '');

        button.disabled = true;
        setTableBarStatus(bar, 'Chiusura…', false);

        fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            body: body
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (json) {
                if (!json || !json.success) {
                    throw new Error(json && json.data && json.data.message ? json.data.message : 'Errore di comunicazione.');
                }

                Array.prototype.forEach.call(bar.querySelectorAll('.siqo-table-chip'), function (chip) {
                    chip.classList.remove('is-active');
                    chip.setAttribute('data-expires', '');

                    var toggle = chip.querySelector('.siqo-table-toggle');
                    if (toggle) {
                        toggle.setAttribute('data-do', 'open');
                        toggle.setAttribute('aria-pressed', 'false');
                        toggle.classList.remove('is-close');
                        toggle.classList.add('is-open');
                        toggle.textContent = labelOpen;
                    }

                    var statusLabel = chip.querySelector('.siqo-table-chip-status');
                    if (statusLabel) {
                        statusLabel.textContent = 'Non attivo';
                    }
                });

                setTableBarStatus(bar, json.data.message || ((json.data.closed || 0) + ' tavoli chiusi.'), false);
            })
            .catch(function (error) {
                setTableBarStatus(bar, error.message || 'Errore di comunicazione.', true);
            })
            .then(function () {
                button.disabled = !bar.querySelector('.siqo-table-chip.is-active');
            });
    }

    root.addEventListener('click', function (event) {
        var closeAllButton = event.target.closest('[data-table-close-all]');
        if (closeAllButton) {
            closeAllTables(closeAllButton);
            return;
        }

        var approvalButton = event.target.closest('.siqo-approve, .siqo-reject');
        if (approvalButton) {
            handleApproval(approvalButton);
            return;
        }

        var tableButton = event.target.closest('.siqo-table-toggle');
        if (tableButton) {
            toggleTable(tableButton);
            return;
        }

        var tab = event.target.closest('[data-station-target]');
        if (tab) {
            var station = tab.getAttribute('data-station-target');
            if (station === activeStation) {
                return;
            }
            setActiveStation(station);
            load(station, false);
            return;
        }

        var action = event.target.closest('[data-station-action]');
        if (action) {
            updateStatus(action);
            return;
        }

        if (event.target.closest('[data-station-refresh]')) {
            load(activeStation, false);
        }
    });

    function startPolling() {
        if (pollTimer) {
            clearInterval(pollTimer);
        }

        pollTimer = setInterval(function () {
            if (document.visibilityState === 'visible') {
                load(activeStation, true);
            }
        }, pollInterval);
    }

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') {
            load(activeStation, true);
        }
    });

    startPolling();
})();
