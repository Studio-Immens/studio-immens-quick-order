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
        var endpoint = bar.getAttribute('data-ajax-url') || window.ajaxurl || ajaxUrl;
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
                button.setAttribute('data-do', isActive ? 'close' : 'open');
                button.classList.toggle('is-open', !isActive);
                button.classList.toggle('is-close', isActive);
                button.textContent = isActive ? labelClose : labelOpen;

                if (statusLabel) {
                    statusLabel.textContent = isActive ? (json.data.label || 'Attivo') : 'Non attivo';
                }

                setTableBarStatus(bar, isActive ? 'Tavolo aperto.' : 'Tavolo chiuso.', false);
            })
            .catch(function (error) {
                setTableBarStatus(bar, error.message || 'Errore di comunicazione.', true);
            })
            .then(function () {
                button.disabled = false;
            });
    }

    root.addEventListener('click', function (event) {
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
