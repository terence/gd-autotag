(function () {
    function renderLineChart(config) {
        if (typeof d3 === 'undefined' || typeof wpPluginDashboardData === 'undefined') {
            return;
        }

        var container = document.getElementById(config.containerId);
        if (!container) {
            return;
        }

        var data = wpPluginDashboardData[config.dataKey] || [];
        if (!data.length) {
            container.innerHTML = '<p style="padding: 12px;">' + (config.emptyMessage || 'No analytics data available.') + '</p>';
            return;
        }

        var parseKey = d3.timeParse('%Y-%m');
        var series = data.map(function (d) {
            var parsed = {
                date: parseKey(d.key),
                label: d.label,
                year: d.year
            };
            config.series.forEach(function (s) {
                parsed[s.key] = +d[s.key];
            });
            return parsed;
        }).filter(function (d) { return d.date instanceof Date && !isNaN(d.date); });

        if (!series.length) {
            container.innerHTML = '<p style="padding: 12px;">Unable to parse analytics data.</p>';
            return;
        }

        container.innerHTML = '';

        var margin = { top: 20, right: 20, bottom: 30, left: 45 };
        var width = container.clientWidth ? container.clientWidth - margin.left - margin.right : 640 - margin.left - margin.right;
        var height = 240 - margin.top - margin.bottom;

        var svg = d3.select(container)
            .append('svg')
            .attr('width', width + margin.left + margin.right)
            .attr('height', height + margin.top + margin.bottom)
            .append('g')
            .attr('transform', 'translate(' + margin.left + ',' + margin.top + ')');

        var x = d3.scaleTime()
            .domain(d3.extent(series, function (d) { return d.date; }))
            .range([0, width]);

        var yMax = d3.max(series, function (d) {
            return d3.max(config.series, function (s) { return d[s.key]; });
        }) || 1;

        var y = d3.scaleLinear()
            .domain([0, yMax])
            .nice()
            .range([height, 0]);

        svg.append('g')
            .attr('transform', 'translate(0,' + height + ')')
            .call(d3.axisBottom(x).ticks(6).tickFormat(d3.timeFormat('%b %y')))
            .selectAll('text')
            .style('font-size', '11px');

        svg.append('g')
            .call(d3.axisLeft(y).ticks(5))
            .selectAll('text')
            .style('font-size', '11px');

        var line = d3.line()
            .curve(d3.curveMonotoneX)
            .x(function (d) { return x(d.date); })
            .y(function (d) { return y(d.value); });

        config.series.forEach(function (serie) {
            svg.append('path')
                .datum(series.map(function (d) { return { date: d.date, value: d[serie.key] }; }))
                .attr('fill', 'none')
                .attr('stroke', serie.color)
                .attr('stroke-width', 2)
                .attr('d', line);
        });

        var tooltip = document.createElement('div');
        tooltip.className = 'wp-plugin-line-tooltip';
        container.appendChild(tooltip);

        function showTooltip(event, datum) {
            var rect = container.getBoundingClientRect();
            var inner = '<strong>' + datum.label + ' ' + datum.year + '</strong><br>';
            config.series.forEach(function (serie) {
                inner += serie.label + ': ' + datum[serie.key] + '<br>';
            });
            tooltip.innerHTML = inner;
            tooltip.style.left = (event.clientX - rect.left + 20) + 'px';
            tooltip.style.top = (event.clientY - rect.top - 10) + 'px';
            tooltip.style.opacity = 1;
        }

        function hideTooltip() {
            tooltip.style.opacity = 0;
        }

        config.series.forEach(function (serie) {
            svg.selectAll('.dot-' + serie.key)
                .data(series)
                .enter()
                .append('circle')
                .attr('class', 'dot-' + serie.key)
                .attr('cx', function (d) { return x(d.date); })
                .attr('cy', function (d) { return y(d[serie.key]); })
                .attr('r', 3.5)
                .attr('fill', serie.color)
                .attr('stroke', '#fff')
                .attr('stroke-width', 1)
                .style('cursor', 'pointer')
                .on('mouseenter', function (event, d) { showTooltip(event, d); })
                .on('mouseleave', hideTooltip);
        });

        var legend = document.createElement('div');
        legend.className = 'wp-plugin-line-legend';
        legend.innerHTML = config.series.map(function (serie) {
            return '<span><span class="dot" style="background: ' + serie.color + ';"></span>' + serie.label + '</span>';
        }).join('');
        container.appendChild(legend);
    }

    function renderTopTagsBar() {
        if (typeof d3 === 'undefined' || typeof wpPluginDashboardData === 'undefined') {
            return;
        }

        var container = document.getElementById('wp-plugin-top-tags-chart');
        if (!container) {
            return;
        }

        var data = (wpPluginDashboardData.topTags || []).slice(0, 8);

        if (!data.length) {
            container.innerHTML = '<p style="padding: 12px;">No tag data available yet.</p>';
            return;
        }

        container.innerHTML = '';

        var width = container.clientWidth || 360;
        var height = 260;
        var margin = { top: 10, right: 20, bottom: 30, left: 120 };
        var chartWidth = width - margin.left - margin.right;
        var chartHeight = height - margin.top - margin.bottom;

        var svg = d3.select(container)
            .append('svg')
            .attr('width', width)
            .attr('height', height)
            .append('g')
            .attr('transform', 'translate(' + margin.left + ',' + margin.top + ')');

        var y = d3.scaleBand()
            .domain(data.map(function (d) { return d.name; }))
            .range([0, chartHeight])
            .padding(0.2);

        var x = d3.scaleLinear()
            .domain([0, d3.max(data, function (d) { return d.count; }) || 1])
            .nice()
            .range([0, chartWidth]);

        var color = d3.scaleSequential(d3.interpolateCool)
            .domain([0, data.length]);

        svg.append('g')
            .call(d3.axisLeft(y).tickSize(0))
            .selectAll('text')
            .style('font-size', '12px');

        svg.append('g')
            .attr('transform', 'translate(0,' + chartHeight + ')')
            .call(d3.axisBottom(x).ticks(4).tickFormat(d3.format('~s')))
            .selectAll('text')
            .style('font-size', '11px');

        var tooltip = document.createElement('div');
        tooltip.className = 'wp-plugin-line-tooltip';
        container.appendChild(tooltip);

        function showTooltip(event, datum) {
            var rect = container.getBoundingClientRect();
            tooltip.innerHTML = '<strong>' + datum.name + '</strong><br>' + datum.count + ' posts';
            tooltip.style.left = (event.clientX - rect.left + 20) + 'px';
            tooltip.style.top = (event.clientY - rect.top - 10) + 'px';
            tooltip.style.opacity = 1;
        }

        function hideTooltip() {
            tooltip.style.opacity = 0;
        }

        svg.selectAll('.bar')
            .data(data)
            .enter()
            .append('rect')
            .attr('class', 'bar')
            .attr('y', function (d) { return y(d.name); })
            .attr('height', y.bandwidth())
            .attr('x', 0)
            .attr('width', function (d) { return x(d.count); })
            .attr('fill', function (d, idx) { return color(idx); })
            .style('cursor', 'pointer')
            .on('mouseenter', showTooltip)
            .on('mousemove', showTooltip)
            .on('mouseleave', hideTooltip);

        svg.selectAll('.label')
            .data(data)
            .enter()
            .append('text')
            .attr('class', 'label')
            .attr('x', function (d) { return x(d.count) + 6; })
            .attr('y', function (d) { return y(d.name) + y.bandwidth() / 2 + 4; })
            .text(function (d) { return d.count; })
            .style('font-size', '11px')
            .style('fill', '#333');
    }

    function initAutoSaveToggles() {
        if (typeof window.fetch !== 'function' || typeof window.FormData === 'undefined') {
            return;
        }

        var toggles = document.querySelectorAll('input[type="checkbox"][data-auto-save="1"]');
        if (!toggles.length) {
            return;
        }

        function setStatus(el, state, message) {
            if (!el) {
                return;
            }

            var timeoutId = el.getAttribute('data-timeout-id');
            if (timeoutId) {
                window.clearTimeout(parseInt(timeoutId, 10));
                el.removeAttribute('data-timeout-id');
            }

            el.textContent = message || '';
            el.classList.remove('is-saving', 'is-success', 'is-error');
            if (state) {
                el.classList.add('is-' + state);
            }

            if (state === 'success') {
                var newTimeoutId = window.setTimeout(function () {
                    el.textContent = '';
                    el.classList.remove('is-success');
                    el.removeAttribute('data-timeout-id');
                }, 2000);
                el.setAttribute('data-timeout-id', String(newTimeoutId));
            }
        }

        toggles.forEach(function (toggle) {
            toggle.addEventListener('change', function () {
                var form = toggle.closest('form');
                if (!form) {
                    return;
                }

                var action = form.getAttribute('action') || form.action || window.location.href;
                var statusTarget = toggle.getAttribute('data-auto-save-target');
                var statusEl = statusTarget ? document.getElementById(statusTarget) : null;
                var requestId = String(Date.now());

                setStatus(statusEl, 'saving', 'Saving...');
                toggle.dataset.autoSaveRequest = requestId;

                var formData = new FormData(form);
                var fieldName = toggle.getAttribute('name');
                if (fieldName) {
                    formData.delete(fieldName);
                    formData.append(fieldName, toggle.checked ? '1' : '0');
                }

                fetch(action, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                })
                    .then(function (response) {
                        if (!response.ok) {
                            throw new Error('Request failed');
                        }
                        return response.text();
                    })
                    .then(function () {
                        if (toggle.dataset.autoSaveRequest !== requestId) {
                            return;
                        }
                        setStatus(statusEl, 'success', 'Saved');
                    })
                    .catch(function () {
                        if (toggle.dataset.autoSaveRequest !== requestId) {
                            return;
                        }
                        setStatus(statusEl, 'error', 'Save failed');
                    });
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        renderLineChart({
            containerId: 'wp-plugin-post-timeline',
            dataKey: 'postTimeline',
            emptyMessage: 'No post analytics data available.',
            series: [
                { key: 'total', label: 'Total Posts', color: '#1d6fa5' },
                { key: 'tagged', label: 'Tagged Posts', color: '#1fb141' }
            ]
        });

        renderTopTagsBar();
        initAutoSaveToggles();
    });
})();