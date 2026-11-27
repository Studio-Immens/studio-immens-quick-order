(function () {
    'use strict';

    if (!window.fetch || !window.FormData) {
        return;
    }

    function setStatus(bar, message, isError) {
        var status = bar.querySelector('.siqo-table-session-status');
        if (!status) {
            return;
        }
        status.textContent = message || '';
        status.classList.toggle('is-error', !!isError);
    }

    function syncCloseAll(bar) {
        var closeAll = bar.querySelector('[data-table-close-all]');
        if (closeAll) {
            closeAll.disabled = !bar.querySelector('.siqo-table-chip.is-active');
        }
    }

    function request(bar, data) {
        var body = new FormData();

        Object.keys(data).forEach(function (key) {
            body.append(key, data[key]);
        });

        body.append('_siqo_nonce', bar.getAttribute('data-nonce') || '');

        return fetch(bar.getAttribute('data-ajax-url') || window.ajaxurl || '/wp-admin/admin-ajax.php', {
            method: 'POST',
            credentials: 'same-origin',
            body: body
        }).then(function (response) {
            return response.json();
        }).then(function (json) {
            if (!json || !json.success) {
                throw new Error(json && json.data && json.data.message ? json.data.message : 'Errore di comunicazione.');
            }
            return json.data;
        });
    }

    function toggleTable(button) {
        var bar = button.closest('.siqo-table-session-bar');
        var chip = button.closest('.siqo-table-chip');
        if (!bar || !chip) {
            return;
        }

        var action = button.getAttribute('data-do') === 'close' ? 'close' : 'open';
        var labelOpen = bar.getAttribute('data-label-open') || 'Apri';
        var labelClose = bar.getAttribute('data-label-close') || 'Chiudi';

        button.disabled = true;
        setStatus(bar, action === 'close' ? 'Chiusura...' : 'Apertura...', false);

        request(bar, {
            action: action === 'close' ? 'siqo_table_close' : 'siqo_table_open',
            table_id: chip.getAttribute('data-table-id') || '',
            do: action
        }).then(function (data) {
            var isActive = !!data.active;
            var statusLabel = chip.querySelector('.siqo-table-chip-status');

            chip.classList.toggle('is-active', isActive);
            chip.setAttribute('data-expires', isActive ? (data.expires_at || '') : '');
            button.setAttribute('data-do', isActive ? 'close' : 'open');
            button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            button.classList.toggle('is-open', !isActive);
            button.classList.toggle('is-close', isActive);
            button.textContent = isActive ? labelClose : labelOpen;

            if (statusLabel) {
                statusLabel.textContent = isActive ? (data.label || 'Attivo') : 'Non attivo';
            }

            setStatus(bar, isActive ? 'Tavolo aperto.' : 'Tavolo chiuso.', false);
            syncCloseAll(bar);
        }).catch(function (error) {
            setStatus(bar, error.message || 'Errore di comunicazione.', true);
        }).then(function () {
            button.disabled = false;
        });
    }

    function closeAllTables(button) {
        var bar = button.closest('.siqo-table-session-bar');
        if (!bar || button.disabled) {
            return;
        }

        if (!bar.querySelector('.siqo-table-chip.is-active')) {
            setStatus(bar, 'Nessun tavolo attivo.', false);
            return;
        }

        if (!window.confirm(bar.getAttribute('data-confirm-close-all') || 'Chiudere tutti i tavoli attivi?')) {
            return;
        }

        var labelOpen = bar.getAttribute('data-label-open') || 'Apri';

        button.disabled = true;
        setStatus(bar, 'Chiusura...', false);

        request(bar, {
            action: 'siqo_tables_close_all',
            do: 'close_all'
        }).then(function (data) {
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

            setStatus(bar, data.message || ((data.closed || 0) + ' tavoli chiusi.'), false);
        }).catch(function (error) {
            setStatus(bar, error.message || 'Errore di comunicazione.', true);
        }).then(function () {
            syncCloseAll(bar);
        });
    }

    document.addEventListener('click', function (event) {
        var closeAll = event.target.closest('[data-table-close-all]');
        if (closeAll) {
            closeAllTables(closeAll);
            return;
        }

        var toggle = event.target.closest('.siqo-table-toggle');
        if (toggle && toggle.closest('.siqo-table-session-bar')) {
            toggleTable(toggle);
        }
    });
})();
