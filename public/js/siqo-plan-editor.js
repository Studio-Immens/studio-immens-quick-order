(function () {
    'use strict';

    var D = { minW: 80, minH: 60, maxSeats: 20, step: 10, maxDim: 4000 };

    var state = {
        view: 'grid',
        editing: false,
        zoom: 1,
        floor: 0,
        canvas: { w: 1200, h: 800 },
        cards: [],
        floors: [],
        zones: [],
        zoneEls: {},
        selected: null,
        selectedZone: null,
        drag: null,
        pan: null,
        suppressClick: false,
        drawZone: false
    };

    var refs = {};

    function qs(sel, root) {
        return (root || document).querySelector(sel);
    }

    function qsa(sel, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(sel));
    }

    function clamp(value, min, max) {
        return Math.min(max, Math.max(min, value));
    }

    function int(value, fallback) {
        var n = parseInt(value, 10);
        return isNaN(n) ? fallback : n;
    }

    function esc(text) {
        return String(text).replace(/[&<>"]/g, function (ch) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[ch];
        });
    }

    function setStatus(text, isError) {
        if (!refs.status) {
            return;
        }
        window.clearTimeout(refs.statusTimer);
        refs.status.textContent = text || '';
        refs.status.classList.toggle('is-error', !!isError);
        if (text) {
            refs.statusTimer = window.setTimeout(function () {
                refs.status.textContent = '';
                refs.status.classList.remove('is-error');
            }, 3200);
        }
    }

    function readConfig() {
        var el = document.getElementById('siqo-plan-data');
        if (!el) {
            return null;
        }
        try {
            return JSON.parse(el.textContent || '{}');
        } catch (e) {
            return null;
        }
    }

    function cardFloor(card) {
        return int(card.dataset.planFloor, 0);
    }

    function cardData(card) {
        return {
            id: int(card.dataset.planId, 0),
            x: int(card.dataset.planX, 0),
            y: int(card.dataset.planY, 0),
            w: int(card.dataset.planW, 180),
            h: int(card.dataset.planH, 120),
            shape: card.dataset.planShape || 'rect',
            seats: int(card.dataset.planSeats, 0),
            zone: int(card.dataset.planZone, 0)
        };
    }

    function cardSet(card, data) {
        card.dataset.planX = String(data.x);
        card.dataset.planY = String(data.y);
        card.dataset.planW = String(data.w);
        card.dataset.planH = String(data.h);
        card.dataset.planShape = data.shape;
        card.dataset.planSeats = String(data.seats);
        card.dataset.planZone = String(data.zone);
    }

    function renderSeats(el, seats) {
        var badge = el.querySelector('.siqo-plan-seats');
        if (!seats) {
            if (badge) {
                badge.parentNode.removeChild(badge);
            }
            return;
        }
        if (!badge) {
            badge = document.createElement('span');
            badge.className = 'siqo-plan-seats';
            badge.setAttribute('aria-hidden', 'true');
            el.appendChild(badge);
        }
        badge.textContent = seats + ' posti';
    }

    function writeCard(card, data) {
        var c = data || cardData(card);
        card.style.setProperty('left', c.x + 'px', 'important');
        card.style.setProperty('top', c.y + 'px', 'important');
        card.style.setProperty('width', c.w + 'px', 'important');
        card.style.setProperty('height', c.h + 'px', 'important');
        card.style.borderRadius = c.shape === 'round' ? '50%' : (c.shape === 'square' ? '10px' : '');
        card.dataset.planShape = c.shape;
        renderSeats(card, c.seats);
    }

    function clearPosition(card) {
        card.style.removeProperty('left');
        card.style.removeProperty('top');
        card.style.removeProperty('width');
        card.style.removeProperty('height');
        card.style.borderRadius = '';
    }

    function visibleCards() {
        return state.cards.filter(function (card) {
            return cardFloor(card) === state.floor;
        });
    }

    function forEachFloorCard(fn) {
        state.cards.forEach(function (card) {
            fn(card, cardFloor(card) === state.floor);
        });
    }

    function applyCanvas() {
        if (!refs.canvas || !refs.stage) {
            return;
        }
        refs.canvas.style.width = state.canvas.w + 'px';
        refs.canvas.style.height = state.canvas.h + 'px';
        refs.canvas.style.transform = 'scale(' + state.zoom + ')';
        refs.stage.style.width = Math.round(state.canvas.w * state.zoom) + 'px';
        refs.stage.style.height = Math.round(state.canvas.h * state.zoom) + 'px';
        if (refs.zoomValue) {
            refs.zoomValue.textContent = Math.round(state.zoom * 100) + '%';
        }
    }

    function renderTabs() {
        if (!refs.tabs) {
            return;
        }
        refs.tabs.innerHTML = '';
        state.floors.forEach(function (floor) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.setAttribute('role', 'tab');
            btn.setAttribute('data-floor', String(floor.id));
            btn.setAttribute('aria-selected', String(floor.id === state.floor));
            btn.setAttribute('tabindex', floor.id === state.floor ? '0' : '-1');
            btn.setAttribute('aria-controls', 'siqo-plan-canvas');
            btn.className = floor.id === state.floor ? 'is-active' : '';
            btn.textContent = floor.name;
            var badge = document.createElement('span');
            badge.className = 'siqo-plan-tab-counts';
            badge.textContent = int(floor.free, 0) + ' liberi / ' + int(floor.busy, 0) + ' occupati';
            btn.appendChild(badge);
            btn.addEventListener('click', function () {
                setFloor(floor.id);
            });
            refs.tabs.appendChild(btn);
        });
    }

    function onTabsKeyDown(event) {
        var keys = { ArrowRight: 1, ArrowLeft: -1, Home: 0, End: 0 };
        if (!(event.key in keys)) {
            return;
        }
        var index = 0;
        for (var i = 0; i < state.floors.length; i++) {
            if (state.floors[i].id === state.floor) {
                index = i;
            }
        }
        var next = index;
        if (event.key === 'Home') {
            next = 0;
        } else if (event.key === 'End') {
            next = state.floors.length - 1;
        } else {
            next = (index + keys[event.key] + state.floors.length) % state.floors.length;
        }
        if (!state.floors[next]) {
            return;
        }
        event.preventDefault();
        setFloor(state.floors[next].id);
        var focused = qs('[data-floor="' + state.floors[next].id + '"]', refs.tabs);
        if (focused) {
            focused.focus();
        }
    }

    function setFloor(floorId) {
        state.floor = floorId;
        state.floors.forEach(function (floor) {
            if (floor.id === floorId && floor.layout) {
                state.canvas = { w: int(floor.layout.w, 1200), h: int(floor.layout.h, 800) };
            }
        });
        renderTabs();
        renderZones();
        if (state.view === 'plan') {
            mountCards();
        }
        applyCanvas();
        setStatus('', false);
    }

    function mountCards() {
        forEachFloorCard(function (card, isVisible) {
            card.classList.toggle('is-offfloor', !isVisible);
            card.hidden = !isVisible;
            if (isVisible && card.parentNode !== refs.canvas) {
                refs.canvas.appendChild(card);
                writeCard(card);
            }
            card.setAttribute('tabindex', isVisible ? '0' : '-1');
            card.setAttribute('role', 'button');
            card.setAttribute('aria-label', (card.getAttribute('data-table-name') || 'Tavolo') + ' — ' + (card.getAttribute('data-table-status-label') || ''));
            if (!isVisible && card.parentNode === refs.canvas) {
                refs.grid.appendChild(card);
                clearPosition(card);
            }
        });
    }

    function unmountCards() {
        state.cards.forEach(function (card) {
            card.hidden = false;
            card.classList.remove('is-offfloor', 'is-selected');
            clearPosition(card);
            if (card.parentNode === refs.canvas) {
                refs.grid.appendChild(card);
            }
        });
    }

    function setView(view) {
        state.view = view;
        var isPlan = view === 'plan';
        refs.area.hidden = !isPlan;
        refs.grid.hidden = isPlan;
        qsa('[data-plan-view]', refs.toolbar).forEach(function (btn) {
            var active = btn.dataset.planView === view;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-pressed', String(active));
        });
        if (isPlan) {
            mountCards();
            applyCanvas();
        } else {
            selectCard(null);
            setEditing(false);
            unmountCards();
        }
    }

    function setEditing(enabled) {
        if (enabled && window.matchMedia && window.matchMedia('(max-width: 767px)').matches) {
            if (refs.hint) {
                refs.hint.hidden = false;
            }
            return;
        }
        state.editing = enabled;
        refs.area.classList.toggle('is-editing', enabled);
        refs.edit.classList.toggle('is-active', enabled);
        refs.edit.setAttribute('aria-pressed', String(enabled));
        refs.edit.textContent = enabled ? 'Fine modifica' : 'Modifica disposizione';
        if (refs.hint) {
            refs.hint.hidden = true;
        }
        if (!enabled) {
            selectCard(null);
        } else {
            setStatus('Trascina i tavoli per sistemare la sala', false);
        }
    }

    function removeHandles(el) {
        qsa('.siqo-plan-handle', el).forEach(function (h) {
            h.parentNode.removeChild(h);
        });
    }

    function addHandles(el) {
        removeHandles(el);
        var name = el.getAttribute('data-table-name') || 'tavolo';
        ['se', 'e', 's'].forEach(function (dir) {
            var handle = document.createElement('span');
            handle.className = 'siqo-plan-handle siqo-plan-handle-' + dir;
            handle.setAttribute('data-plan-handle', dir);
            handle.setAttribute('aria-label', 'Ridimensiona ' + name);
            handle.setAttribute('role', 'button');
            handle.setAttribute('tabindex', '-1');
            el.appendChild(handle);
        });
    }

    function selectCard(el) {
        if (el && state.selectedZone) {
            state.selectedZone.classList.remove('is-selected');
            state.selectedZone = null;
        }
        if (state.selected === el) {
            if (el) {
                buildTableInspector(el);
            }
            return;
        }
        if (state.selected) {
            state.selected.classList.remove('is-selected');
            removeHandles(state.selected);
        }
        state.selected = el || null;
        if (state.selected) {
            state.selected.classList.add('is-selected');
            if (state.editing) {
                addHandles(state.selected);
                buildTableInspector(state.selected);
            }
        } else {
            hideInspector();
        }
    }

    function hideInspector() {
        if (refs.inspector) {
            refs.inspector.hidden = true;
            refs.inspector.innerHTML = '';
        }
    }

    function renderZones() {
        if (!refs.zones) {
            return;
        }
        refs.zones.innerHTML = '';
        state.zoneEls = {};
        state.zones.forEach(function (zone) {
            if (Number(zone.floor) !== state.floor) {
                return;
            }
            var el = document.createElement('div');
            el.className = 'siqo-plan-zone';
            el.setAttribute('role', 'group');
            el.setAttribute('aria-label', zone.name);
            el.setAttribute('data-zone-id', String(zone.id));
            el.style.left = zone.x + 'px';
            el.style.top = zone.y + 'px';
            el.style.width = zone.w + 'px';
            el.style.height = zone.h + 'px';
            if (zone.bgUrl) {
                el.style.backgroundImage = 'url("' + zone.bgUrl.replace(/"/g, '%22') + '")';
                el.style.backgroundSize = 'cover';
                el.style.backgroundPosition = 'center';
            }
            var tint = document.createElement('span');
            tint.className = 'siqo-plan-zone-tint';
            tint.style.background = zone.color || 'transparent';
            var label = document.createElement('span');
            label.className = 'siqo-plan-zone-label';
            label.textContent = zone.name;
            el.appendChild(tint);
            el.appendChild(label);
            ['se', 'e', 's'].forEach(function (dir) {
                var handle = document.createElement('span');
                handle.className = 'siqo-plan-handle siqo-plan-handle-' + dir;
                handle.setAttribute('data-zone-handle', dir);
                handle.setAttribute('aria-hidden', 'true');
                el.appendChild(handle);
            });
            refs.zones.appendChild(el);
            state.zoneEls[zone.id] = el;
        });
    }

    function zoneById(id) {
        for (var i = 0; i < state.zones.length; i++) {
            if (Number(state.zones[i].id) === Number(id)) {
                return state.zones[i];
            }
        }
        return null;
    }

    function zoneGeometry(el) {
        return {
            id: int(el.getAttribute('data-zone-id'), 0),
            x: Math.round(parseFloat(el.style.left) || 0),
            y: Math.round(parseFloat(el.style.top) || 0),
            w: Math.round(parseFloat(el.style.width) || 240),
            h: Math.round(parseFloat(el.style.height) || 160)
        };
    }

    function selectZone(el) {
        if (state.selectedZone) {
            state.selectedZone.classList.remove('is-selected');
        }
        state.selectedZone = el || null;
        if (state.selectedZone) {
            state.selectedZone.classList.add('is-selected');
            buildZoneInspector(state.selectedZone);
        } else if (state.editing && !state.selected) {
            hideInspector();
        }
    }

    function applyZonePatch(zone, patch) {
        Object.keys(patch).forEach(function (key) {
            zone[key] = patch[key];
        });
        var data = { id: zone.id };
        Object.keys(patch).forEach(function (key) {
            data[key] = patch[key];
        });
        post('siqo_zone_update', data).then(function (result) {
            if (result && result.success) {
                state.zones = result.data.zones || state.zones;
                renderZones();
                setStatus('Zona aggiornata', false);
            } else {
                setStatus('Aggiornamento zona non riuscito', true);
            }
        });
    }

    function saveZoneGeometry(zone) {
        post('siqo_zone_update', { id: zone.id, x: zone.x, y: zone.y, w: zone.w, h: zone.h });
    }

    function post(action, data) {
        if (!refs.nonce) {
            return Promise.resolve(null);
        }
        var body = new URLSearchParams();
        body.set('action', action);
        body.set('nonce', refs.nonce.value);
        Object.keys(data || {}).forEach(function (key) {
            body.set(key, String(data[key]));
        });
        return fetch(refs.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: body
        }).then(function (response) {
            return response.json();
        }).catch(function () {
            return null;
        });
    }

    function buildZoneInspector(el) {
        if (!refs.inspector) {
            return;
        }
        var zone = zoneById(el.getAttribute('data-zone-id'));
        if (!zone) {
            return;
        }
        refs.inspector.innerHTML =
            '<div class="siqo-plan-inspector-head"><strong></strong>' +
            '<button type="button" data-plan-close aria-label="Chiudi">&times;</button></div>' +
            '<label class="siqo-plan-field">Nome<input type="text" maxlength="60" data-zone-name></label>' +
            '<label class="siqo-plan-field">Colore<input type="color" data-zone-color></label>' +
            '<div class="siqo-plan-field"><button type="button" data-zone-bg>Sfondo</button>' +
            '<button type="button" data-zone-bg-clear>Rimuovi sfondo</button></div>' +
            '<button type="button" class="siqo-plan-danger" data-zone-delete>Elimina zona</button>';
        refs.inspector.hidden = false;
        refs.inspector.setAttribute('aria-label', zone.name);
        qs('.siqo-plan-inspector-head strong', refs.inspector).textContent = zone.name;
        qs('[data-zone-name]', refs.inspector).value = zone.name;
        qs('[data-zone-color]', refs.inspector).value = zone.color || '#a6462f';

        qs('[data-plan-close]', refs.inspector).addEventListener('click', function () {
            selectZone(null);
        });
        qs('[data-zone-name]', refs.inspector).addEventListener('change', function (event) {
            applyZonePatch(zone, { name: event.target.value });
        });
        qs('[data-zone-color]', refs.inspector).addEventListener('change', function (event) {
            applyZonePatch(zone, { color: event.target.value });
        });
        qs('[data-zone-bg]', refs.inspector).addEventListener('click', function () {
            if (window.wp && window.wp.media) {
                var frame = window.wp.media({
                    title: 'Sfondo zona',
                    multiple: false,
                    library: { type: 'image' }
                });
                frame.on('select', function () {
                    var att = frame.state().get('selection').first().toJSON();
                    applyZonePatch(zone, { bg: att.id });
                });
                frame.open();
            } else if (refs.file) {
                refs.file.click();
            }
        });
        qs('[data-zone-bg-clear]', refs.inspector).addEventListener('click', function () {
            applyZonePatch(zone, { bg: 0 });
        });
        qs('[data-zone-delete]', refs.inspector).addEventListener('click', function () {
            if (!window.confirm('Eliminare la zona?')) {
                return;
            }
            post('siqo_zone_delete', { id: zone.id }).then(function (result) {
                if (result && result.success) {
                    state.zones = result.data.zones || [];
                    selectZone(null);
                    renderZones();
                    setStatus('Zona eliminata', false);
                } else {
                    setStatus('Eliminazione non riuscita', true);
                }
            });
        });
    }

    function zoneOptions(selected) {
        var html = '<option value="0">Nessuna</option>';
        state.zones.forEach(function (z) {
            if (Number(z.floor) !== state.floor) {
                return;
            }
            html += '<option value="' + z.id + '"' + (Number(selected) === Number(z.id) ? ' selected' : '') + '>' + esc(z.name) + '</option>';
        });
        return html;
    }

    function applyCardPatch(card, patch) {
        var current = cardData(card);
        Object.keys(patch).forEach(function (key) {
            current[key] = patch[key];
        });
        cardSet(card, current);
        writeCard(card, current);
        saveLayout();
        return current;
    }

    function buildTableInspector(card) {
        if (!refs.inspector) {
            return;
        }
        var c = cardData(card);
        refs.inspector.innerHTML =
            '<div class="siqo-plan-inspector-head"><strong></strong>' +
            '<button type="button" data-plan-close aria-label="Chiudi">&times;</button></div>' +
            '<label class="siqo-plan-field">Forma<select data-plan-shape><option value="rect">Rettangolo</option><option value="square">Quadrato</option><option value="round">Rotondo</option></select></label>' +
            '<label class="siqo-plan-field">Sedie<span class="siqo-plan-stepper"><button type="button" data-seats="-" aria-label="Meno sedie">&minus;</button><output data-seats-value></output><button type="button" data-seats="+" aria-label="Più sedie">+</button></span></label>' +
            '<label class="siqo-plan-field">Zona<select data-plan-zone-list>' + zoneOptions(c.zone) + '</select></label>' +
            '<p class="siqo-plan-inspector-meta"></p>';
        refs.inspector.hidden = false;
        refs.inspector.setAttribute('aria-label', card.getAttribute('data-table-name') || 'Tavolo ' + c.id);
        qs('.siqo-plan-inspector-head strong', refs.inspector).textContent = card.getAttribute('data-table-name') || 'Tavolo ' + c.id;
        qs('.siqo-plan-inspector-meta', refs.inspector).textContent = card.getAttribute('data-table-status-label') || '';
        qs('[data-plan-shape]', refs.inspector).value = c.shape;
        qs('[data-seats-value]', refs.inspector).textContent = c.seats;

        qs('[data-plan-close]', refs.inspector).addEventListener('click', function () {
            selectCard(null);
        });
        qs('[data-plan-shape]', refs.inspector).addEventListener('change', function (event) {
            applyCardPatch(card, { shape: event.target.value });
        });
        qs('[data-plan-zone-list]', refs.inspector).addEventListener('change', function (event) {
            applyCardPatch(card, { zone: int(event.target.value, 0) });
        });
        qsa('[data-seats]', refs.inspector).forEach(function (button) {
            button.addEventListener('click', function () {
                var value = clamp(cardData(card).seats + (button.getAttribute('data-seats') === '+' ? 1 : -1), 0, D.maxSeats);
                qs('[data-seats-value]', refs.inspector).textContent = value;
                applyCardPatch(card, { seats: value });
            });
        });
    }

    function setZoom(next) {
        state.zoom = clamp(Math.round(next * 10) / 10, 0.3, 2);
        applyCanvas();
    }

    function fitZoom() {
        if (!refs.area) {
            return;
        }
        var rect = refs.area.getBoundingClientRect();
        var zx = (rect.width - 32) / state.canvas.w;
        var zy = (rect.height - 32) / state.canvas.h;
        setZoom(Math.min(zx, zy, 1));
        refs.stage.scrollTop = 0;
    }

    function pointToCanvas(event) {
        var rect = refs.canvas.getBoundingClientRect();
        return {
            x: (event.clientX - rect.left) / state.zoom,
            y: (event.clientY - rect.top) / state.zoom
        };
    }

    function growFor(c) {
        if (c.x + c.w + 40 > state.canvas.w) {
            state.canvas.w = Math.min(D.maxDim, c.x + c.w + 40);
        }
        if (c.y + c.h + 40 > state.canvas.h) {
            state.canvas.h = Math.min(D.maxDim, c.y + c.h + 40);
        }
    }

    function finishDrag() {
        if (!state.drag) {
            return;
        }
        var card = state.drag.card;
        var moved = state.drag.moved;
        if (state.drag.pointerId !== null && card.hasPointerCapture && card.hasPointerCapture(state.drag.pointerId)) {
            card.releasePointerCapture(state.drag.pointerId);
        }
        state.drag = null;
        if (moved) {
            state.suppressClick = true;
            window.setTimeout(function () {
                state.suppressClick = false;
            }, 350);
            saveLayout();
        }
    }

    function beginResize(event, handle, owner) {
        selectCard(owner);
        var dir = handle.getAttribute('data-plan-handle');
        var origin = cardData(owner);
        var start = pointToCanvas(event);
        function onMove(ev) {
            var p = pointToCanvas(ev);
            var c = cardData(owner);
            if (dir === 'se' || dir === 'e') {
                c.w = origin.w + (p.x - start.x);
            }
            if (dir === 'se' || dir === 's') {
                c.h = origin.h + (p.y - start.y);
            }
            c.w = Math.max(D.minW, Math.round(c.w / D.step) * D.step);
            c.h = Math.max(D.minH, Math.round(c.h / D.step) * D.step);
            if (c.shape !== 'rect') {
                var side = Math.max(D.minW, Math.min(c.w, c.h));
                c.w = side;
                c.h = side;
            }
            cardSet(owner, c);
            writeCard(owner, c);
            growFor(c);
            applyCanvas();
        }
        function onUp() {
            owner.removeEventListener('pointermove', onMove);
            owner.removeEventListener('pointerup', onUp);
            owner.removeEventListener('pointercancel', onUp);
            saveLayout();
        }
        if (owner.setPointerCapture) {
            owner.setPointerCapture(event.pointerId);
        }
        owner.addEventListener('pointermove', onMove);
        owner.addEventListener('pointerup', onUp);
        owner.addEventListener('pointercancel', onUp);
        event.preventDefault();
        event.stopPropagation();
    }

    function onCanvasPointerDown(event) {
        var handle = event.target.closest ? event.target.closest('[data-plan-handle]') : null;
        if (handle && state.editing) {
            var owner = handle.closest('.fo_table_cell');
            if (owner) {
                beginResize(event, handle, owner);
                return;
            }
        }
        var card = event.target.closest ? event.target.closest('.fo_table_cell') : null;
        if (!card || !refs.canvas.contains(card)) {
            if (refs.stage && refs.stage.contains(event.target) && state.view === 'plan') {
                state.pan = {
                    startX: event.clientX + refs.stage.scrollLeft,
                    startY: event.clientY + refs.stage.scrollTop
                };
                refs.stage.style.cursor = 'grabbing';
            }
            return;
        }
        if (!state.editing) {
            return;
        }
        selectCard(card);
        var point = pointToCanvas(event);
        var rect = card.getBoundingClientRect();
        state.drag = {
            card: card,
            pointerId: event.pointerId,
            offsetX: (event.clientX - rect.left) / state.zoom,
            offsetY: (event.clientY - rect.top) / state.zoom,
            startX: point.x,
            startY: point.y,
            moved: false
        };
        if (card.setPointerCapture) {
            card.setPointerCapture(event.pointerId);
        }
        event.preventDefault();
    }

    function onPointerMove(event) {
        if (state.pan && refs.stage) {
            refs.stage.scrollLeft = state.pan.startX - event.clientX;
            refs.stage.scrollTop = state.pan.startY - event.clientY;
            return;
        }
        if (!state.drag || event.pointerId !== state.drag.pointerId) {
            return;
        }
        var point = pointToCanvas(event);
        if (Math.abs(point.x - state.drag.startX) > 3 || Math.abs(point.y - state.drag.startY) > 3) {
            state.drag.moved = true;
        }
        var x = Math.max(0, Math.round((point.x - state.drag.offsetX) / D.step) * D.step);
        var y = Math.max(0, Math.round((point.y - state.drag.offsetY) / D.step) * D.step);
        var card = state.drag.card;
        card.dataset.planX = String(x);
        card.dataset.planY = String(y);
        card.style.setProperty('left', x + 'px', 'important');
        card.style.setProperty('top', y + 'px', 'important');
        growFor(cardData(card));
        applyCanvas();
    }

    function onPointerUp(event) {
        if (state.pan) {
            state.pan = null;
            if (refs.stage) {
                refs.stage.style.cursor = '';
            }
            return;
        }
        if (state.drag && event.pointerId === state.drag.pointerId) {
            finishDrag();
        }
    }

    function onCanvasClick(event) {
        var card = event.target.closest ? event.target.closest('.fo_table_cell') : null;
        if (!card || !refs.canvas.contains(card)) {
            if (state.editing) {
                selectCard(null);
            }
            return;
        }
        if (state.editing || state.suppressClick) {
            event.preventDefault();
            event.stopPropagation();
            if (state.editing && !state.suppressClick) {
                selectCard(card);
            }
            return;
        }
        if (typeof window.SIQO_tab_Card_show === 'function') {
            event.preventDefault();
            event.stopPropagation();
            setView('grid');
            window.SIQO_tab_Card_show(card);
        }
    }

    function onKeyDown(event) {
        if (event.altKey && (event.key === 'ArrowLeft' || event.key === 'ArrowRight')) {
            var index = -1;
            for (var i = 0; i < state.floors.length; i++) {
                if (Number(state.floors[i].id) === state.floor) {
                    index = i;
                }
            }
            if (index >= 0 && state.floors.length > 1) {
                event.preventDefault();
                var next = (index + (event.key === 'ArrowRight' ? 1 : -1) + state.floors.length) % state.floors.length;
                setFloor(state.floors[next].id);
                var tab = qs('[data-floor="' + state.floors[next].id + '"]', refs.tabs);
                if (tab) {
                    tab.focus();
                }
            }
            return;
        }
        if (event.key === 'Escape') {
            closeManager();
            state.drawZone = false;
            selectCard(null);
            return;
        }
        if (!state.editing) {
            if (event.key === 'Enter' && state.selected) {
                state.selected.click();
            }
            return;
        }
        if (event.key === 'Tab' && state.view === 'plan') {
            var order = visibleCards();
            if (!order.length) {
                return;
            }
            var current = order.indexOf(state.selected);
            var step = event.shiftKey ? -1 : 1;
            var target = order[(current + step + order.length) % order.length];
            if (current === -1) {
                target = order[0];
            }
            event.preventDefault();
            selectCard(target);
            return;
        }
        if (!state.selected || ['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].indexOf(event.key) === -1) {
            return;
        }
        event.preventDefault();
        var delta = event.shiftKey ? 50 : D.step;
        var c = cardData(state.selected);
        if (event.key === 'ArrowLeft') {
            c.x = Math.max(0, c.x - delta);
        }
        if (event.key === 'ArrowRight') {
            c.x = c.x + delta;
        }
        if (event.key === 'ArrowUp') {
            c.y = Math.max(0, c.y - delta);
        }
        if (event.key === 'ArrowDown') {
            c.y = c.y + delta;
        }
        cardSet(state.selected, c);
        writeCard(state.selected, c);
        growFor(c);
        applyCanvas();
        saveLayout();
    }

    function onZonesPointerDown(event) {
        if (!state.editing || event.button !== 0) {
            return;
        }
        var el = event.target.closest ? event.target.closest('.siqo-plan-zone') : null;
        if (!el) {
            return;
        }
        var zone = zoneById(el.getAttribute('data-zone-id'));
        if (!zone) {
            return;
        }
        var handle = event.target.closest('[data-zone-handle]');
        selectCard(null);
        selectZone(el);
        var dir = handle ? handle.getAttribute('data-zone-handle') : '';
        var origin = zoneGeometry(el);
        var start = pointToCanvas(event);
        function onMove(ev) {
            var p = pointToCanvas(ev);
            var dx = Math.round((p.x - start.x) / D.step) * D.step;
            var dy = Math.round((p.y - start.y) / D.step) * D.step;
            if (dir) {
                var w = origin.w;
                var h = origin.h;
                if (dir === 'se' || dir === 'e') {
                    w = Math.max(D.minW, origin.w + (p.x - start.x));
                }
                if (dir === 'se' || dir === 's') {
                    h = Math.max(D.minH, origin.h + (p.y - start.y));
                }
                zone.w = Math.round(w / D.step) * D.step;
                zone.h = Math.round(h / D.step) * D.step;
            } else {
                zone.x = Math.max(0, origin.x + dx);
                zone.y = Math.max(0, origin.y + dy);
            }
            el.style.left = zone.x + 'px';
            el.style.top = zone.y + 'px';
            el.style.width = zone.w + 'px';
            el.style.height = zone.h + 'px';
            growFor({ x: zone.x, y: zone.y, w: zone.w, h: zone.h });
            applyCanvas();
        }
        function onUp() {
            el.removeEventListener('pointermove', onMove);
            el.removeEventListener('pointerup', onUp);
            el.removeEventListener('pointercancel', onUp);
            saveZoneGeometry(zone);
        }
        if (el.setPointerCapture) {
            el.setPointerCapture(event.pointerId);
        }
        el.addEventListener('pointermove', onMove);
        el.addEventListener('pointerup', onUp);
        el.addEventListener('pointercancel', onUp);
        event.preventDefault();
        event.stopPropagation();
    }

    function refreshConfig() {
        return post('siqo_floors', {}).then(function (result) {
            if (result && result.success) {
                state.floors = result.data.floors || state.floors;
                state.zones = result.data.zones || state.zones;
                renderTabs();
                renderZones();
                renderManager();
            }
        });
    }

    function renderManager() {
        if (!refs.manager) {
            return;
        }
        var floorsHtml = state.floors.map(function (floor) {
            return '<li><span>' + esc(floor.name) + ' <small>' + int(floor.free, 0) + '/' + int(floor.busy, 0) + '</small></span>' +
                '<span class="siqo-plan-manager-actions">' +
                (floor.virtual ? '' : '<button type="button" data-floor-rename="' + floor.id + '">Rinomina</button>' +
                    '<button type="button" data-floor-delete="' + floor.id + '">Elimina</button>') +
                '</span></li>';
        }).join('');
        var zonesHtml = state.zones.filter(function (zone) {
            return Number(zone.floor) === state.floor;
        }).map(function (zone) {
            return '<li><span>' + esc(zone.name) + '</span><span class="siqo-plan-manager-actions">' +
                '<button type="button" data-zone-rename="' + zone.id + '">Rinomina</button>' +
                '<button type="button" data-zone-remove="' + zone.id + '">Elimina</button></span></li>';
        }).join('');
        refs.manager.innerHTML =
            '<div class="siqo-plan-manager-head"><strong id="siqo-plan-manager-title">Gestisci disposizione</strong>' +
            '<button type="button" data-manager-close aria-label="Chiudi">&times;</button></div>' +
            '<div class="siqo-plan-manager-cols">' +
            '<section><h3>Piani</h3><ul>' + (floorsHtml || '<li>Nessun piano</li>') + '</ul>' +
            '<button type="button" data-floor-add>Nuovo piano</button></section>' +
            '<section><h3>Zone del piano</h3><ul>' + (zonesHtml || '<li>Nessuna zona</li>') + '</ul>' +
            '<button type="button" data-zone-add>Nuova zona</button> ' +
            '<button type="button" data-zone-draw>Disegna zona</button></section>' +
            '</div>';

        qs('[data-manager-close]', refs.manager).addEventListener('click', closeManager);
        qsa('[data-floor-add]', refs.manager).forEach(function (btn) {
            btn.addEventListener('click', addFloor);
        });
        qsa('[data-floor-rename]', refs.manager).forEach(function (btn) {
            btn.addEventListener('click', function () {
                renameFloor(int(btn.getAttribute('data-floor-rename'), 0));
            });
        });
        qsa('[data-floor-delete]', refs.manager).forEach(function (btn) {
            btn.addEventListener('click', function () {
                removeFloor(int(btn.getAttribute('data-floor-delete'), 0));
            });
        });
        qsa('[data-zone-add]', refs.manager).forEach(function (btn) {
            btn.addEventListener('click', addZone);
        });
        qsa('[data-zone-draw]', refs.manager).forEach(function (btn) {
            btn.addEventListener('click', function () {
                state.drawZone = true;
                if (state.view !== 'plan') {
                    setView('plan');
                }
                setEditing(true);
                closeManager();
                setStatus('Trascina sulla mappa per disegnare la zona', false);
            });
        });
        qsa('[data-zone-rename]', refs.manager).forEach(function (btn) {
            btn.addEventListener('click', function () {
                renameZone(int(btn.getAttribute('data-zone-rename'), 0));
            });
        });
        qsa('[data-zone-remove]', refs.manager).forEach(function (btn) {
            btn.addEventListener('click', function () {
                removeZone(int(btn.getAttribute('data-zone-remove'), 0));
            });
        });
    }

    function openManager() {
        renderManager();
        refs.manager.hidden = false;
        refs.manager.setAttribute('tabindex', '-1');
        refs.manage.setAttribute('aria-expanded', 'true');
        try {
            refs.manager.focus({ preventScroll: true });
        } catch (err) {
            refs.manager.focus();
        }
    }

    function closeManager() {
        var hadFocus = refs.manager && refs.manager.contains(document.activeElement);
        if (refs.manager) {
            refs.manager.hidden = true;
        }
        if (refs.manage) {
            refs.manage.setAttribute('aria-expanded', 'false');
            if (hadFocus) {
                refs.manage.focus();
            }
        }
    }

    function addFloor() {
        var name = window.prompt('Nome del nuovo piano');
        if (!name) {
            return;
        }
        var copy = window.confirm('Copiare la disposizione dal piano corrente?') ? state.floor : '';
        post('siqo_floor_create', { name: name, copy_from: copy }).then(function (result) {
            if (result && result.success) {
                state.floors = result.data.floors || state.floors;
                state.zones = result.data.zones || state.zones;
                renderTabs();
                renderManager();
                setStatus('Piano creato', false);
            } else {
                setStatus(result && result.data && result.data.message ? result.data.message : 'Creazione non riuscita', true);
            }
        });
    }

    function renameFloor(id) {
        var floor = null;
        state.floors.forEach(function (item) {
            if (Number(item.id) === id) {
                floor = item;
            }
        });
        if (!floor) {
            return;
        }
        var name = window.prompt('Nuovo nome del piano', floor.name);
        if (!name) {
            return;
        }
        post('siqo_floor_update', { id: id, name: name }).then(function (result) {
            if (result && result.success) {
                state.floors = result.data.floors || state.floors;
                renderTabs();
                renderManager();
            } else {
                setStatus('Rinomina non riuscita', true);
            }
        });
    }

    function removeFloor(id) {
        if (!window.confirm('Eliminare il piano? Le zone del piano verranno rimosse.')) {
            return;
        }
        post('siqo_floor_delete', { id: id }).then(function (result) {
            if (result && result.success) {
                state.floors = result.data.floors || state.floors;
                state.zones = result.data.zones || state.zones;
                if (state.floor === id) {
                    setFloor(0);
                }
                renderManager();
                setStatus('Piano eliminato', false);
            } else if (result && result.data && result.data.tables) {
                if (!window.confirm('Il piano contiene tavoli. Spostarli sul piano terra?')) {
                    return;
                }
                post('siqo_floor_delete', { id: id, move_to: 0 }).then(function (moved) {
                    if (moved && moved.success) {
                        state.floors = moved.data.floors || state.floors;
                        state.zones = moved.data.zones || state.zones;
                        if (state.floor === id) {
                            setFloor(0);
                        }
                        renderManager();
                        setStatus('Piano eliminato', false);
                    }
                });
            } else {
                setStatus(result && result.data && result.data.message ? result.data.message : 'Eliminazione non riuscita', true);
            }
        });
    }

    function addZone() {
        var name = window.prompt('Nome della nuova zona');
        if (!name) {
            return;
        }
        post('siqo_zone_create', { name: name, floor_id: state.floor }).then(function (result) {
            if (result && result.success) {
                state.zones = result.data.zones || state.zones;
                renderZones();
                renderManager();
                setStatus('Zona creata', false);
            } else {
                setStatus(result && result.data && result.data.message ? result.data.message : 'Creazione non riuscita', true);
            }
        });
    }

    function renameZone(id) {
        var zone = zoneById(id);
        if (!zone) {
            return;
        }
        var name = window.prompt('Nuovo nome della zona', zone.name);
        if (!name) {
            return;
        }
        applyZonePatch(zone, { name: name });
        renderManager();
    }

    function removeZone(id) {
        if (!window.confirm('Eliminare la zona?')) {
            return;
        }
        post('siqo_zone_delete', { id: id }).then(function (result) {
            if (result && result.success) {
                state.zones = result.data.zones || state.zones;
                selectZone(null);
                renderZones();
                renderManager();
            }
        });
    }

    function startDraw(event) {
        if (!state.drawZone || !state.editing) {
            return;
        }
        var target = event.target;
        if (target.closest && (target.closest('.fo_table_cell') || target.closest('.siqo-plan-zone'))) {
            return;
        }
        var start = pointToCanvas(event);
        var ghost = document.createElement('div');
        ghost.className = 'siqo-plan-zone siqo-plan-zone-ghost';
        ghost.style.left = start.x + 'px';
        ghost.style.top = start.y + 'px';
        ghost.style.width = '0px';
        ghost.style.height = '0px';
        refs.zones.appendChild(ghost);
        function onMove(ev) {
            var p = pointToCanvas(ev);
            var x = Math.max(0, Math.min(start.x, p.x));
            var y = Math.max(0, Math.min(start.y, p.y));
            ghost.style.left = x + 'px';
            ghost.style.top = y + 'px';
            ghost.style.width = Math.max(20, Math.abs(p.x - start.x)) + 'px';
            ghost.style.height = Math.max(20, Math.abs(p.y - start.y)) + 'px';
        }
        function onUp(ev) {
            document.removeEventListener('pointermove', onMove);
            document.removeEventListener('pointerup', onUp);
            var p = pointToCanvas(ev);
            var geo = {
                x: Math.max(0, Math.round(Math.min(start.x, p.x) / D.step) * D.step),
                y: Math.max(0, Math.round(Math.min(start.y, p.y) / D.step) * D.step),
                w: Math.max(D.minW, Math.round(Math.abs(p.x - start.x) / D.step) * D.step),
                h: Math.max(D.minH, Math.round(Math.abs(p.y - start.y) / D.step) * D.step)
            };
            ghost.parentNode.removeChild(ghost);
            state.drawZone = false;
            var name = window.prompt('Nome della zona');
            if (!name) {
                return;
            }
            geo.name = name;
            geo.floor_id = state.floor;
            post('siqo_zone_create', geo).then(function (result) {
                if (result && result.success) {
                    state.zones = result.data.zones || state.zones;
                    renderZones();
                    renderManager();
                    setStatus('Zona creata', false);
                } else {
                    setStatus('Creazione zona non riuscita', true);
                }
            });
        }
        document.addEventListener('pointermove', onMove);
        document.addEventListener('pointerup', onUp);
        event.preventDefault();
        event.stopPropagation();
    }

    function payload() {
        var tables = visibleCards().map(function (card) {
            var c = cardData(card);
            return {
                id: c.id,
                x: c.x,
                y: c.y,
                w: c.w,
                h: c.h,
                shape: c.shape,
                seats: c.seats,
                zone_id: c.zone
            };
        });
        var zones = state.zones.filter(function (zone) {
            return Number(zone.floor) === state.floor;
        }).map(function (zone) {
            return { id: zone.id, x: zone.x, y: zone.y, w: zone.w, h: zone.h };
        });
        return {
            action: 'siqo_save_table_layout',
            nonce: refs.nonce ? refs.nonce.value : '',
            layout: JSON.stringify({
                floor_id: state.floor,
                canvas: state.canvas,
                tables: tables,
                zones: zones
            })
        };
    }

    function saveLayout() {
        if (!refs.nonce) {
            return;
        }
        setStatus('Salvataggio...', false);
        fetch(refs.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: new URLSearchParams(payload())
        }).then(function (response) {
            return response.json();
        }).then(function (result) {
            if (result && result.success) {
                if (result.data && result.data.canvas) {
                    state.canvas = result.data.canvas;
                    applyCanvas();
                }
                setStatus('Disposizione salvata', false);
            } else {
                var message = result && result.data && result.data.message ? result.data.message : 'Salvataggio non riuscito';
                setStatus(message, true);
            }
        }).catch(function () {
            setStatus('Salvataggio non riuscito', true);
        });
    }

    function bind() {
        qsa('[data-plan-view]', refs.toolbar).forEach(function (btn) {
            btn.addEventListener('click', function () {
                setView(btn.dataset.planView);
            });
        });
        refs.edit.addEventListener('click', function () {
            if (state.view !== 'plan') {
                setView('plan');
            }
            setEditing(!state.editing);
        });
        qsa('[data-plan-zoom]', refs.toolbar).forEach(function (btn) {
            btn.addEventListener('click', function () {
                var action = btn.dataset.planZoom;
                if (action === 'in') {
                    setZoom(state.zoom + 0.1);
                } else if (action === 'out') {
                    setZoom(state.zoom - 0.1);
                } else if (action === 'fit') {
                    fitZoom();
                }
            });
        });
        var reload = qs('[data-plan-reload]', refs.toolbar);
        if (reload) {
            reload.addEventListener('click', function () {
                window.location.reload();
            });
        }
        if (refs.file) {
            refs.file.addEventListener('change', function () {
                var file = refs.file.files && refs.file.files[0];
                if (!file || !state.selectedZone) {
                    return;
                }
                var form = new FormData();
                form.append('action', 'siqo_zone_bg_upload');
                form.append('nonce', refs.nonce ? refs.nonce.value : '');
                form.append('zone_id', state.selectedZone.getAttribute('data-zone-id'));
                form.append('file', file);
                refs.file.value = '';
                setStatus('Caricamento sfondo...', false);
                fetch(refs.ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: form
                }).then(function (response) {
                    return response.json();
                }).then(function (result) {
                    if (result && result.success) {
                        state.zones = result.data.zones || state.zones;
                        renderZones();
                        setStatus('Sfondo aggiornato', false);
                    } else {
                        setStatus(result && result.data && result.data.message ? result.data.message : 'Caricamento non riuscito', true);
                    }
                }).catch(function () {
                    setStatus('Caricamento non riuscito', true);
                });
            });
        }
        refs.canvas.addEventListener('pointerdown', startDraw, true);
        refs.canvas.addEventListener('focusin', function (event) {
            var card = event.target.closest ? event.target.closest('.fo_table_cell') : null;
            if (card && state.editing && state.view === 'plan') {
                selectCard(card);
            }
        });
        refs.canvas.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return;
            }
            var card = event.target.closest ? event.target.closest('.fo_table_cell') : null;
            if (!card) {
                return;
            }
            event.preventDefault();
            selectCard(card);
        });
        refs.canvas.addEventListener('pointerdown', onCanvasPointerDown);
        refs.canvas.addEventListener('click', onCanvasClick, true);
        if (refs.manage) {
            refs.manage.addEventListener('click', function () {
                if (refs.manager.hidden) {
                    openManager();
                } else {
                    closeManager();
                }
            });
        }
        if (refs.tabs) {
            refs.tabs.addEventListener('keydown', onTabsKeyDown);
        }
        if (refs.zones) {
            refs.zones.addEventListener('pointerdown', onZonesPointerDown);
        }
        document.addEventListener('pointermove', onPointerMove);
        document.addEventListener('pointerup', onPointerUp);
        document.addEventListener('pointercancel', onPointerUp);
        document.addEventListener('keydown', onKeyDown);
    }

    function init() {
        refs.toolbar = qs('[data-plan-toolbar]');
        refs.area = qs('[data-plan-area]');
        refs.stage = qs('[data-plan-stage]');
        refs.canvas = qs('[data-plan-canvas]');
        refs.zones = qs('[data-plan-zones]');
        refs.grid = qs('[data-plan-grid]');
        refs.tabs = qs('[data-plan-tabs]');
        refs.status = qs('[data-plan-status]');
        refs.edit = qs('[data-plan-edit]');
        refs.hint = qs('[data-plan-hint]');
        refs.inspector = qs('[data-plan-inspector]');
        refs.manager = qs('[data-plan-manager]');
        refs.manage = qs('[data-plan-manage]');
        refs.file = qs('[data-plan-file]');
        refs.zoomValue = qs('[data-plan-zoom-value]');
        refs.nonce = qs('[data-plan-nonce]');
        refs.ajaxUrl = (window.SIQO_plan_editor && window.SIQO_plan_editor.ajaxUrl) || '/wp-admin/admin-ajax.php';
        var config = readConfig() || {};
        refs.floors = config.floors || [];
        if (!refs.toolbar || !refs.area || !refs.canvas || !refs.grid) {
            return;
        }
        state.cards = qsa('.fo_table_cell', refs.grid);
        state.floors = refs.floors;
        state.zones = config.zones || [];
        state.floor = int(config.floor, 0);
        if (config.canvas) {
            state.canvas = { w: int(config.canvas.w, 1200), h: int(config.canvas.h, 800) };
        }
        state.cards.forEach(function (card) {
            writeCard(card);
        });
        refs.area.hidden = true;
        renderTabs();
        renderZones();
        renderManager();
        applyCanvas();
        bind();
        window.SIQO_plan = {
            setView: setView,
            setFloor: setFloor,
            getState: function () {
                return state;
            }
        };
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
