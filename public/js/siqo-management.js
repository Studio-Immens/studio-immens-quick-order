(function () {
	'use strict';

	function root() {
		return document.documentElement;
	}

	function enterMode() {
		if (document.body.classList.contains('siqo-operator-mode') || document.body.classList.contains('siqo-customer-mode')) {
			root().classList.add('siqo-operator-mode');
		}
	}

	function fullscreen() {
		var el = document.documentElement;
		if (!document.fullscreenElement && !document.webkitFullscreenElement) {
			if (el.requestFullscreen) {
				el.requestFullscreen();
			} else if (el.webkitRequestFullscreen) {
				el.webkitRequestFullscreen();
			}
		} else if (document.exitFullscreen) {
			document.exitFullscreen();
		} else if (document.webkitExitFullscreen) {
			document.webkitExitFullscreen();
		}
	}

	function money(value) {
		if (!value || isNaN(value)) {
			return '0';
		}
		return new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' }).format(value);
	}

	function updateCard(key, value) {
		var card = document.querySelector('[data-stat="' + key + '"] strong');
		if (card) {
			card.textContent = key === 'revenue_today' ? money(value) : value;
		}
	}

	function orderRow(order) {
		return '<tr>' +
			'<td><a class="siqo-order-link" href="' + order.url + '">#' + order.number + '</a></td>' +
			'<td>' + order.table + '</td>' +
			'<td><span class="siqo-status siqo-status-' + order.slug + '">' + order.status + '</span></td>' +
			'<td>' + order.items + '</td>' +
			'<td>' + order.total + '</td>' +
			'<td>' + order.time + '</td>' +
			'</tr>';
	}

	function refresh() {
		if (!window.SIQO_MANAGEMENT || document.hidden) {
			return;
		}
		var body = new URLSearchParams();
		body.set('action', 'siqo_management_dashboard');
		body.set('nonce', window.SIQO_MANAGEMENT.nonce);

		fetch(window.SIQO_MANAGEMENT.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString()
		}).then(function (response) {
			return response.json();
		}).then(function (payload) {
			if (!payload || !payload.success || !payload.data) {
				return;
			}
			Object.keys(payload.data.stats).forEach(function (key) {
				updateCard(key, payload.data.stats[key]);
			});
			var target = document.querySelector('[data-siqo-orders]');
			if (target && payload.data.orders.length) {
				target.innerHTML = payload.data.orders.map(orderRow).join('');
			}
		}).catch(function () {});
	}

	function navGroups() {
		var groups = Array.prototype.slice.call(document.querySelectorAll('[data-siqo-nav-group]'));
		if (!groups.length) {
			return;
		}
		var canHover = window.matchMedia && window.matchMedia('(hover: hover)').matches;

		function triggerOf(group) {
			return group.querySelector('.siqo-nav-trigger');
		}

		function setExpanded(group, expanded) {
			var trigger = triggerOf(group);
			if (trigger) {
				trigger.setAttribute('aria-expanded', expanded ? 'true' : 'false');
			}
		}

		function closeAll(except) {
			groups.forEach(function (group) {
				if (group === except) {
					return;
				}
				group.classList.remove('is-open', 'is-hover');
				setExpanded(group, false);
			});
		}

		groups.forEach(function (group) {
			var trigger = triggerOf(group);
			if (!trigger) {
				return;
			}
			trigger.addEventListener('click', function (event) {
				event.preventDefault();
				event.stopPropagation();
				var open = !group.classList.contains('is-open');
				closeAll(group);
				group.classList.remove('is-hover');
				group.classList.toggle('is-open', open);
				setExpanded(group, open);
			});
			if (canHover) {
				group.addEventListener('mouseenter', function () {
					if (group.classList.contains('is-open')) {
						return;
					}
					closeAll(group);
					group.classList.add('is-hover');
					setExpanded(group, true);
				});
				group.addEventListener('mouseleave', function () {
					if (group.classList.contains('is-open')) {
						return;
					}
					group.classList.remove('is-hover');
					setExpanded(group, false);
				});
			}
		});

		document.addEventListener('click', function (event) {
			if (!event.target.closest || !event.target.closest('[data-siqo-nav-group]')) {
				closeAll(null);
			}
		});
		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape') {
				closeAll(null);
			}
		});
	}

	function bind() {
		enterMode();
		navGroups();
		document.querySelectorAll('[data-siqo-fullscreen]').forEach(function (button) {
			button.addEventListener('click', fullscreen);
		});
		var refreshButton = document.querySelector('[data-siqo-refresh]');
		if (refreshButton) {
			refreshButton.addEventListener('click', function (event) {
				event.preventDefault();
				refresh();
			});
		}
		var cart = document.querySelector('[data-siqo-cart]');
		if (cart) {
			cart.addEventListener('click', function (event) {
				event.preventDefault();
				if (typeof Show_SIQO_Front_Float === 'function') {
					Show_SIQO_Front_Float();
				}
			});
		}
		if (document.querySelector('[data-stat="orders_today"]')) {
			window.setInterval(refresh, 20000);
			document.addEventListener('visibilitychange', function () {
				if (!document.hidden) {
					refresh();
				}
			});
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', bind);
	} else {
		bind();
	}
})();
