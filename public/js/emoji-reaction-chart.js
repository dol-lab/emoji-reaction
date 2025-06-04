/**
 * Emoji Reaction Chart functionality
 *
 * @since 0.4.0
 */

window.emojiReactionChart = (function () {
	'use strict';

	let chartInstances = {};

	/**
	 * Initialize a chart using parent container element
	 *
	 * @param {HTMLElement} container The parent container element
	 */
	function init(container) {
		if (!container || !container.dataset || !container.dataset.chartId || !container.dataset.postId) {
			console.error('Invalid container for emoji reaction chart initialization');
			console.debug('Container:', container);
			console.trace('Container:', container);
			return;
		}

		const chartId = container.dataset.chartId;
		const postId = parseInt(container.dataset.postId, 10);
		const type = container.dataset.type || 'bar';
		const canvas = container.querySelector('canvas');
		const loadingEl = container.querySelector('.emoji-reaction-chart-loading');

		if (!canvas) {
			console.error('Chart canvas not found in container');
			return;
		}

		// Set loading state via data attribute
		container.dataset.loading = 'true';

		// Fetch chart data
		fetchChartData(postId)
			.then(function (data) {
				container.dataset.loading = 'false';
				createChart(canvas, data, type);
			})
			.catch(function (error) {
				console.error('Error loading chart data:', error);
				container.dataset.loading = 'false';
				if (loadingEl) {
					loadingEl.innerHTML = 'Error loading chart data.';
				}
			});
	}


	/**
	 * Fetch chart data via AJAX
	 *
	 * @param {number} postId Post ID
	 * @returns {Promise} Promise resolving to chart data
	 */
	function fetchChartData(postId) {
		return new Promise(function (resolve, reject) {
			const xhr = new XMLHttpRequest();
			const url = emoji_reaction_chart.ajax_url + '?action=emoji_reaction_chart_data&post_id=' + postId;

			xhr.open('GET', url, true);
			xhr.onreadystatechange = function () {
				if (xhr.readyState === 4) {
					if (xhr.status === 200) {
						try {
							const response = JSON.parse(xhr.responseText);
							if (response.success) {
								resolve(response.data);
							} else {
								reject(new Error(response.data.message || 'Unknown error'));
							}
						} catch (e) {
							reject(new Error('Invalid JSON response'));
						}
					} else {
						reject(new Error('HTTP error: ' + xhr.status));
					}
				}
			};
			xhr.send();
		});
	}

	/**
	 * Create chart using Chart.js
	 *
	 * @param {HTMLCanvasElement} canvas Canvas element
	 * @param {Object} data Chart data
	 * @param {string} type Chart type
	 */
	function createChart(canvas, data, type) {
		if (typeof Chart === 'undefined') {
			console.error('Chart.js not loaded');
			return;
		}

		const chartId = canvas.id;

		// Check if there are no reactions
		if (data.no_reactions === true) {
			displayNoReactionsMessage(canvas, data.message || 'No reactions yet');
			return;
		}

		const ctx = canvas.getContext('2d');

		// Destroy existing chart if it exists
		if (chartInstances[chartId]) {
			chartInstances[chartId].destroy();
		}

		const config = {
			type: type === 'donut' ? 'doughnut' : 'bar',
			data: data,
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: {
					legend: {
						position: type === 'donut' ? 'right' : ''
					},
					title: {
						display: false,
						text: 'Emoji Reactions'
					}
				}
			}
		};

		// Add specific options for bar charts
		if (type === 'bar') {
			config.options.scales = {
				y: {
					beginAtZero: true,
					ticks: {
						stepSize: 1
					}
				}
			};
		}

		chartInstances[chartId] = new Chart(ctx, config);
	}

	/**
	 * Display a "no reactions" message instead of a chart
	 *
	 * @param {HTMLCanvasElement} canvas Canvas element
	 * @param {string} message Message to display
	 */
	function displayNoReactionsMessage(canvas, message) {
		const container = canvas.closest('.emoji-reaction-chart-container');
		if (!container) return;

		// Hide the canvas
		canvas.style.display = 'none';

		// Remove existing no-reactions message if any
		const existingMessage = container.querySelector('.emoji-reaction-no-reactions');
		if (existingMessage) {
			existingMessage.remove();
		}

		// Create and insert the no reactions message
		const messageDiv = document.createElement('div');
		messageDiv.className = 'emoji-reaction-no-reactions';
		messageDiv.textContent = message;

		container.appendChild(messageDiv);

	}

	/**
	 * Refresh a chart using container element
	 *
	 * @param {HTMLElement} container The parent container element
	 */
	function refresh(container) {
		if (!container || !container.dataset || !container.dataset.chartId || !container.dataset.postId) {
			console.error('Invalid container for emoji reaction chart refresh');
			return;
		}

		const chartId = container.dataset.chartId;
		const postId = parseInt(container.dataset.postId, 10);
		const type = container.dataset.type || 'bar';

		// Set loading state
		container.dataset.loading = 'true';

		// Trigger window event for chart refresh
		window.dispatchEvent(new CustomEvent('emojiChartRefreshRequested', {
			detail: {
				chartId: chartId,
				postId: postId,
				type: type,
				container: container
			}
		}));
	}

	/**
	 * Handle chart refresh internally
	 *
	 * @param {string} chartId Chart ID
	 * @param {number} postId Post ID
	 * @param {string} type Chart type
	 * @param {HTMLElement} container Optional container element
	 */
	function handleChartRefresh(chartId, postId, type, container) {
		const canvas = document.getElementById(chartId);
		if (!canvas) return;

		// Find container if not provided
		if (!container) {
			container = canvas.closest('.emoji-reaction-chart-container');
		}

		if (container) {
			container.dataset.loading = 'true';
		}

		fetchChartData(postId)
			.then(function (data) {
				if (container) {
					container.dataset.loading = 'false';
				}

				// If we're transitioning from no reactions to reactions, or vice versa
				if (data.no_reactions === true) {
					// Destroy existing chart if any
					if (chartInstances[chartId]) {
						chartInstances[chartId].destroy();
						delete chartInstances[chartId];
					}
					displayNoReactionsMessage(canvas, data.message || 'No reactions yet');
				} else {
					// Remove no reactions message if it exists
					if (container) {
						const existingMessage = container.querySelector('.emoji-reaction-no-reactions');
						if (existingMessage) {
							existingMessage.remove();
						}
					}

					// Show canvas and update/create chart
					canvas.style.display = 'block';

					if (chartInstances[chartId]) {
						chartInstances[chartId].data = data;
						chartInstances[chartId].update();
					} else {
						createChart(canvas, data, type);
					}
				}

				// Dispatch event to notify other charts with same post ID
				window.dispatchEvent(new CustomEvent('emojiChartUpdated', {
					detail: {
						chartId: chartId,
						postId: postId,
						data: data
					}
				}));
			})
			.catch(function (error) {
				console.error('Error refreshing chart:', error);
				if (container) {
					container.dataset.loading = 'false';
					const loadingEl = container.querySelector('.emoji-reaction-chart-loading');
					if (loadingEl) {
						loadingEl.innerHTML = 'Error loading chart data.';
					}
				}
			});
	}

	/**
	 * Destroy a chart instance
	 *
	 * @param {string} chartId Chart ID
	 */
	function destroy(chartId) {
		if (chartInstances[chartId]) {
			chartInstances[chartId].destroy();
			delete chartInstances[chartId];
		}
	}

	/**
	 * Listen for emoji reaction changes and update charts
	 */
	function setupEventListeners() {
		// Add click handlers for mobile interaction
		document.addEventListener('click', function (event) {
			// Handle container clicks for mobile
			if (event.target.closest('.emoji-reaction-chart-container')) {
				const container = event.target.closest('.emoji-reaction-chart-container');
				// Toggle clicked state for mobile (shows/hides refresh button)
				const currentState = container.dataset.clicked === 'true';
				container.dataset.clicked = currentState ? 'false' : 'true';
			}
		});

		// Listen for emoji reaction changes
		window.addEventListener('emojiReactionChanged', function (event) {
			// Refresh all charts for the affected post using window events
			const postId = event.detail.postId;
			Object.keys(chartInstances).forEach(function (chartId) {
				if (chartId.includes('chart-' + postId + '-')) {
					const canvas = document.getElementById(chartId);
					if (canvas) {
						const container = canvas.closest('.emoji-reaction-chart-container');
						const chartType = container ? container.dataset.type || 'bar' : 'bar';

						// Trigger refresh via window event instead of button click
						window.dispatchEvent(new CustomEvent('emojiChartRefreshRequested', {
							detail: {
								chartId: chartId,
								postId: postId,
								type: chartType
							}
						}));
					}
				}
			});
		});

		// Listen for chart refresh requests
		window.addEventListener('emojiChartRefreshRequested', function (event) {
			const { chartId, postId, type, container } = event.detail;
			handleChartRefresh(chartId, postId, type, container);
		});

		// Listen for chart updates to sync other charts with same post ID
		window.addEventListener('emojiChartUpdated', function (event) {
			const { chartId: updatedChartId, postId, data } = event.detail;

			// Update all other charts with the same post ID
			Object.keys(chartInstances).forEach(function (chartId) {
				if (chartId !== updatedChartId && chartId.includes('chart-' + postId + '-')) {
					const canvas = document.getElementById(chartId);
					if (!canvas) return;

					if (data.no_reactions === true) {
						// Destroy existing chart if any
						if (chartInstances[chartId]) {
							chartInstances[chartId].destroy();
							delete chartInstances[chartId];
						}
						displayNoReactionsMessage(canvas, data.message || 'No reactions yet');
					} else {
						// Remove no reactions message if it exists
						const container = canvas.closest('.emoji-reaction-chart-container');
						if (container) {
							const existingMessage = container.querySelector('.emoji-reaction-no-reactions');
							if (existingMessage) {
								existingMessage.remove();
							}
						}

						// Show canvas and update/create chart
						canvas.style.display = 'block';

						if (chartInstances[chartId]) {
							chartInstances[chartId].data = data;
							chartInstances[chartId].update();
						} else {
							// Determine chart type from container or default to bar
							const container = canvas.closest('.emoji-reaction-chart-container');
							const chartType = container ? container.dataset.type || 'bar' : 'bar';
							createChart(canvas, data, chartType);
						}
					}
				}
			});
		});
	}

	// Initialize event listeners when script loads
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', setupEventListeners);
	} else {
		setupEventListeners();
	}

	// Public API
	return {
		init: init,
		refresh: refresh,
		destroy: destroy
	};
})();
