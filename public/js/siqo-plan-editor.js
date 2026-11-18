(function () {
    'use strict';

    var state = {
        view: 'grid',
        editing: false,
        zoom: 1,
        floor: 0,
        canvas: { w: 1200, h: 800 },
        cards: [],
        drag: null,
        pan: null,
        suppressClick: false
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

    function writeCard(card) {
        card.style.left = int(card.dataset.planX, 0) + 'px';
        card.style.top = int(card.dataset.planY, 0) + 'px';
        card.style.width = int(card.dataset.planW, 180) + 'px';
        card.style.height = int(card.dataset.planH, 120) + 'px';
        card.dataset.planShape = card.dataset.planShape || 'rect';
    }

    function clearPosition(card) {
        card.style.left = '';
        card.style.top = '';
        card.style.width = '';
        card.style.height = '';
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
        refs.zoomValue.textContent = Math.round(state.zoom * 100) + '%';
    }

    function renderTabs() {
        if (!refs.tabs) {
            return;
        }
        refs.tabs.innerHTML = '';
        var floors = (refs.config && refs.config.floors) || [];
        floors.forEach(function (floor) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.setAttribute('role', 'tab');
            btn.setAttribute('data-floor-id', String(floor.id));
            btn.setAttribute('aria-selected', String(floor.id === state.floor));
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

    function setFloor(floorId) {
        state.floor = floorId;
        if (refs.config && refs.config.floors) {
            refs.config.floors.forEach(function (floor) {
                if (floor.id === floorId && floor.layout) {
                    state.canvas = { w: int(floor.layout.w, 1200), h: int(floor.layout.h, 800) };
                }
            });
        }
        renderTabs();
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
            if (!state.editing) {
                refs.edit.classList.remove('is-active');
            }
            mountCards();
            applyCanvas();
        } else {
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
            state.cards.forEach(function (card) {
                card.classList.remove('is-selected');
            });
            closeInspector();
        } else {
            setStatus('Trascina i tavoli per sistemare la sala', false);
        }
    }

    function openInspector() {}

    function closeInspector() {
        if (refs.inspector) {
            refs.inspector.hidden = true;
        }
    }

    function setZoom(next) {
        state.zoom = clamp(Math.round(next * 10) / 10, 0.3, 2);
        applyCanvas();
    }

    function fitZoom() {
        if (!refs.stage) {
            return;
        }
        var rect = refs.area.getBoundingClientRect();
        var zx = (rect.width - 32) / state.canvas.w;
        var zy = (rect.height - 32) / state.canvas.h;
        setZoom(Math.min(zx, zy, 1));
        refs.stage.parentNode.scrollTop = 0;
    }

    function pointToCanvas(event) {
        var rect = refs.canvas.getBoundingClientRect();
        return {
            x: (event.clientX - rect.left) / state.zoom,
            y: (event.clientY - rect.top) / state.zoom
        };
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

    function onCanvasPointerDown(event) {
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
        var x = Math.max(0, Math.round((point.x - state.drag.offsetX) / 10) * 10);
        var y = Math.max(0, Math.round((point.y - state.drag.offsetY) / 10) * 10);
        var card = state.drag.card;
        card.dataset.planX = String(x);
        card.dataset.planY = String(y);
        card.style.left = x + 'px';
        card.style.top = y + 'px';
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
            return;
        }
        if (state.editing || state.suppressClick) {
            event.preventDefault();
            event.stopPropagation();
            if (state.editing && !state.suppressClick) {
                state.cards.forEach(function (item) {
                    item.classList.toggle('is-selected', item === card);
                });
                openInspector(card);
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

    function payload() {
        var tables = state.cards.filter(function (card) {
            return cardFloor(card) === state.floor;
        }).map(function (card) {
            return {
                id: int(card.dataset.planId, 0),
                x: int(card.dataset.planX, 0),
                y: int(card.dataset.planY, 0),
                w: int(card.dataset.planW, 180),
                h: int(card.dataset.planH, 120),
                shape: card.dataset.planShape || 'rect',
                seats: int(card.dataset.planSeats, 0),
                zone_id: int(card.dataset.planZone, 0)
            };
        });
        return {
            action: 'siqo_save_table_layout',
            nonce: refs.nonce ? refs.nonce.value : '',
            floor_id: String(state.floor),
            canvas: JSON.stringify(state.canvas),
            tables: JSON.stringify(tables),
            zones: JSON.stringify([])
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

    function reloadLayout() {
        window.location.reload();
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
            reload.addEventListener('click', reloadLayout);
        }
        refs.canvas.addEventListener('pointerdown', onCanvasPointerDown);
        refs.canvas.addEventListener('click', onCanvasClick, true);
        document.addEventListener('pointermove', onPointerMove);
        document.addEventListener('pointerup', onPointerUp);
        document.addEventListener('pointercancel', onPointerUp);
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
        refs.zoomValue = qs('[data-plan-zoom-value]');
        refs.nonce = qs('[data-plan-nonce]');
        refs.config = readConfig() || { floors: [], canvas: { w: 1200, h: 800 } };
        refs.ajaxUrl = (window.SIQO_plan_editor && window.SIQO_plan_editor.ajaxUrl) || '';
        if (!refs.toolbar || !refs.area || !refs.canvas || !refs.grid) {
            return;
        }
        state.cards = qsa('.fo_table_cell', refs.grid);
        state.floor = int(refs.config.floor, 0);
        if (refs.config.canvas) {
            state.canvas = { w: int(refs.config.canvas.w, 1200), h: int(refs.config.canvas.h, 800) };
        }
        state.cards.forEach(writeCard);
        refs.area.hidden = true;
        renderTabs();
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
