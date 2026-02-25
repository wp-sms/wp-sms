jQuery(document).ready(function ($) {
    if (jQuery('.wp-sms-widgets.stats-widget').length) {
        WPSmsStatsWidget.init();
    }
});


const WPSmsStatsWidget = {
    currentTimeFrame: 'last_7_days',

    init: function () {
        this.setElements()
        this.checkIfTwoWayIsActive()
        this.showTwoWayModalIfNotActive()
        this.renderLabels()
        this.calculateCounts()
        this.initChart()
        this.addEventListener()
    },

    setElements: function () {
        this.elements = {
            context: jQuery('.wp-sms-widgets.stats-widget .stats-widget__chart canvas'),
            pills: jQuery('.wp-sms-widgets.stats-widget .stats-widget__pill'),
            smsDirection: jQuery('.wp-sms-widgets.stats-widget select.sms-direction'),
            stats: jQuery('.wp-sms-widgets.stats-widget .stats-widget__stats'),
            twoWayPromotion: jQuery('.wp-sms-widgets.stats-widget .two-way-promotion'),
            chartWrap: jQuery('.wp-sms-widgets.stats-widget .stats-widget__chart')
        }
    },

    checkIfTwoWayIsActive: function () {
        if (typeof WP_Sms_Admin_Dashboard_Object['received-messages-stats'] == 'undefined') {
            this.twoWayIsNotActive = true
            WP_Sms_Admin_Dashboard_Object['received-messages-stats'] = WP_Sms_Admin_Dashboard_Object['send-messages-stats']
        }
    },

    showTwoWayModalIfNotActive: function () {
        const direction = this.elements.smsDirection.val()
        if (direction == 'received-messages-stats' && this.twoWayIsNotActive == true) {
            this.elements.twoWayPromotion.show()
            this.elements.stats.addClass('blur')
            this.elements.chartWrap.addClass('blur')
        } else {
            this.elements.twoWayPromotion.hide()
            this.elements.stats.removeClass('blur')
            this.elements.chartWrap.removeClass('blur')
        }
    },

    renderLabels: function () {
        const localization = WP_Sms_Admin_Dashboard_Object.localization
        jQuery('[data-stat="total"] [data-label]').text(localization.total || 'Total')
        jQuery('[data-stat="successful"] [data-label]').text(localization.successful)
        jQuery('[data-stat="failure"] [data-label]').text(localization.failed)
    },

    formatNumber: function (num) {
        if (num >= 1000000) {
            return (num / 1000000).toFixed(1).replace(/\.0$/, '') + 'M'
        }
        if (num >= 1000) {
            return (num / 1000).toFixed(1).replace(/\.0$/, '') + 'K'
        }
        return num.toString()
    },

    calculateCounts: function () {
        const direction = this.elements.smsDirection.val()
        const datasets = (this.currentTimeFrame && direction) ? WP_Sms_Admin_Dashboard_Object[direction][this.currentTimeFrame] : null

        if (!datasets) return

        const sum = function (obj) {
            return Object.values(obj || {}).reduce(function (a, b) { return a + parseFloat(b || 0) }, 0)
        }

        const successful = sum(datasets['successful'])
        const failure = sum(datasets['failure'])
        const plain = sum(datasets['plain'])
        const total = successful + failure + plain
        const rate = total > 0 ? Math.round((successful / total) * 100) : 0

        const self = this
        jQuery('[data-stat="total"] [data-value]').text(self.formatNumber(total))
        jQuery('[data-stat="successful"] [data-value]').text(self.formatNumber(successful))
        jQuery('[data-stat="failure"] [data-value]').text(self.formatNumber(failure))
        jQuery('[data-stat="success_rate"] [data-value]').text(rate + '%')
    },

    createGradient: function (ctx, color) {
        const gradient = ctx.createLinearGradient(0, 0, 0, ctx.canvas.height)
        gradient.addColorStop(0, color.replace(/,\s*1\)$/, ', 0.25)'))
        gradient.addColorStop(1, color.replace(/,\s*1\)$/, ', 0.02)'))
        return gradient
    },

    getChartData: function () {
        const direction = this.elements.smsDirection.val()
        const datasets = (this.currentTimeFrame && direction) ? WP_Sms_Admin_Dashboard_Object[direction][this.currentTimeFrame] : null

        if (!datasets) return { labels: [], datasets: [] }

        const localization = WP_Sms_Admin_Dashboard_Object.localization
        const labels = Object.keys(datasets['successful'] || {})

        const ctx = this.elements.context.get(0)
        if (!ctx) return { labels: [], datasets: [] }

        const context2d = ctx.getContext('2d')
        const successColor = 'rgba(22, 163, 74, 1)'
        const failColor = 'rgba(239, 68, 68, 1)'

        const chartDatasets = [
            {
                label: localization.successful,
                backgroundColor: this.createGradient(context2d, successColor),
                borderColor: successColor,
                borderWidth: 2,
                fill: true,
                data: Object.values(datasets['successful'] || {}),
                tension: 0.35,
                pointRadius: 3,
                pointBackgroundColor: successColor,
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointHoverRadius: 5,
            },
            {
                label: localization.failed,
                backgroundColor: this.createGradient(context2d, failColor),
                borderColor: failColor,
                borderWidth: 2,
                fill: true,
                data: Object.values(datasets['failure'] || {}),
                tension: 0.35,
                pointRadius: 3,
                pointBackgroundColor: failColor,
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointHoverRadius: 5,
            }
        ]

        if (direction === 'received-messages-stats' && datasets['plain']) {
            const plainColor = 'rgba(107, 114, 128, 1)'
            chartDatasets.push({
                label: localization.plain,
                backgroundColor: this.createGradient(context2d, plainColor),
                borderColor: plainColor,
                borderWidth: 2,
                fill: true,
                data: Object.values(datasets['plain'] || {}),
                tension: 0.35,
                pointRadius: 3,
                pointBackgroundColor: plainColor,
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointHoverRadius: 5,
            })
        }

        return {
            labels: labels,
            datasets: chartDatasets
        }
    },

    addEventListener: function () {
        const self = this

        // Pill button clicks
        this.elements.pills.on('click', function () {
            self.elements.pills.removeClass('stats-widget__pill--active').attr('aria-selected', 'false').attr('tabindex', '-1')
            jQuery(this).addClass('stats-widget__pill--active').attr('aria-selected', 'true').attr('tabindex', '0')
            self.currentTimeFrame = jQuery(this).data('value')
            self.updateWidget()
        })

        // Arrow key navigation for tabs
        this.elements.pills.on('keydown', function (e) {
            var pills = self.elements.pills
            var index = pills.index(this)
            var newIndex
            if (e.key === 'ArrowRight') newIndex = (index + 1) % pills.length
            else if (e.key === 'ArrowLeft') newIndex = (index - 1 + pills.length) % pills.length
            else return
            e.preventDefault()
            pills.eq(newIndex).focus().trigger('click')
        })

        // Direction select change
        this.elements.smsDirection.on('change', function () {
            self.showTwoWayModalIfNotActive()
            self.updateWidget()
        })
    },

    updateWidget: function () {
        const direction = this.elements.smsDirection.val()
        if (this.currentTimeFrame && direction) {
            this.calculateCounts()
            if (this.chart) {
                this.chart.data = this.getChartData()
                this.chart.update()
            }
        }
    },

    initChart: function () {
        const direction = this.elements.smsDirection.val()
        if (this.currentTimeFrame && direction) {
            const ctx = this.elements.context.get(0)
            this.chart = new Chart(ctx, {
                type: 'line',
                data: this.getChartData(),
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(15, 23, 42, 0.9)',
                            titleColor: '#fff',
                            bodyColor: '#e2e8f0',
                            borderColor: 'rgba(255, 255, 255, 0.1)',
                            borderWidth: 1,
                            cornerRadius: 8,
                            padding: 10,
                            bodySpacing: 6,
                            titleFont: { size: 12, weight: '600' },
                            bodyFont: { size: 12 },
                            usePointStyle: true,
                            boxPadding: 4,
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            border: { display: false },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.04)',
                                drawTicks: false,
                            },
                            ticks: {
                                stepSize: 1,
                                maxTicksLimit: 6,
                                padding: 8,
                                font: { size: 11 },
                                color: '#94a3b8',
                            }
                        },
                        x: {
                            border: { display: false },
                            grid: { display: false },
                            ticks: {
                                maxTicksLimit: 12,
                                maxRotation: 0,
                                font: { size: 11 },
                                color: '#94a3b8',
                                padding: 4,
                            }
                        }
                    }
                }
            });
        }
    }
}
