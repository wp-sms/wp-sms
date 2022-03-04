jQuery(document).ready(function ($) {
    statsWidget.init();
});


const statsWidget = {
    init: function () {
        this.setElements()
        this.initChart()
    },
    setElements: function () {
        this.elements = []
        this.elements.context = jQuery('.wp-sms-widget.stats-widget > canvas')
    },
    initChart: function () {
        const ctx = this.elements.context.get(0)
        const myChart = new Chart(ctx, {
            type: 'line',
            data: {
                datasets: [
                    {
                        label: 'Successful',
                        backgroundColor: '#74c69d',
                        borderColor: '#40916c',
                        data: WPSmsStatsData['two-way']['last_30_days']['successful'],
                    },
                    {
                        label: 'Failed',
                        backgroundColor: '#dd2c2f',
                        borderColor: '#bd1f21',
                        data: WPSmsStatsData['two-way']['last_30_days']['failure'],
                    },
                    {
                        label: 'Plain',
                        backgroundColor: '#adb5bd',
                        borderColor: '#495057',
                        data: WPSmsStatsData['two-way']['last_30_days']['plain'],
                    }
                ]
            },
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