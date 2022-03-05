jQuery(document).ready(function ($) {
    statsWidget.init();
});


const statsWidget = {
    init: function () {
        this.setElements()
        this.data.init(this)
        this.initChart()
        this.data.addEventListener()
    },
    elements: [],
    setElements: function () {
        this.elements.context = jQuery('.wp-sms-widgets.stats-widget > canvas')
        this.elements.timeFrameSelect = jQuery('.wp-sms-widgets.stats-widget select.time-frame')
        this.elements.smsDirection = jQuery('.wp-sms-widgets.stats-widget select.sms-direction')
    },
    data: {
        init: function (parent) {
            this.parent = parent
        },
        getData: function () {
            const timeFrame = this.parent.elements.timeFrameSelect.val()
            const direction = this.parent.elements.smsDirection.val()
            const datasets = WPSmsStatsData[direction][timeFrame]

            switch (direction) {
                case 'send-messages-stats':
                    return {
                        datasets: [
                            {
                                label: 'Successful',
                                backgroundColor: '#74c69d',
                                borderColor: '#40916c',
                                data: datasets['successful'],
                            },
                            {
                                label: 'Failed',
                                backgroundColor: '#dd2c2f',
                                borderColor: '#bd1f21',
                                data: datasets['failure'],
                            }
                        ]
                    }
                case 'received-messages-stats':
                    return {
                        datasets: [
                            {
                                label: 'Successful',
                                backgroundColor: '#74c69d',
                                borderColor: '#40916c',
                                data: datasets['successful'],
                            },
                            {
                                label: 'Failed',
                                backgroundColor: '#dd2c2f',
                                borderColor: '#bd1f21',
                                data: datasets['failure'],
                            },
                            {
                                label: 'Plain',
                                backgroundColor: '#adb5bd',
                                borderColor: '#495057',
                                data: datasets['plain'],
                            }
                        ]

                    }
            }
        },
        addEventListener: function () {
            this.parent.elements.timeFrameSelect.change(function () {
                const chart = this.parent.chart;
                chart.data = this.getData();
                chart.update()
            }.bind(this))
            this.parent.elements.smsDirection.change(function () {
                const chart = this.parent.chart;
                chart.data = this.getData();
                chart.update()
            }.bind(this))
        }
    },

    initChart: function () {
        const ctx = this.elements.context.get(0)
        this.chart = new Chart(ctx, {
            type: 'line',
            data: this.data.getData(),
            options: {
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }


}