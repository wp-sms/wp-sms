jQuery(document).ready(function ($) {
    WPSmsStatsWidget.init();
});


const WPSmsStatsWidget = {
    init: function () {
        this.setElements()
        this.checkIfTwoWayIsActive()
        this.showTwoWayModalIfNotActive()
        this.calculateCounts()
        this.initChart()
        this.addEventListener()
    },

    setElements: function () {
        this.elements = {
            context: jQuery('.wp-sms-widgets.stats-widget .chart canvas'),
            timeFrameSelect: jQuery('.wp-sms-widgets.stats-widget select.time-frame'),
            smsDirection: jQuery('.wp-sms-widgets.stats-widget select.sms-direction'),
            totalsDiv: jQuery('.wp-sms-widgets.stats-widget table.totals tr'),
            twoWayPromotion: jQuery('.wp-sms-widgets.stats-widget .two-way-promotion')

        }
    },

    checkIfTwoWayIsActive: function () {
        if (typeof WPSmsStatsData['received-messages-stats'] == 'undefined') {
            this.twoWayIsNotActive = true
            WPSmsStatsData['received-messages-stats'] = WPSmsStatsData['send-messages-stats']
        }
    },

    showTwoWayModalIfNotActive: function () {
        const direction = this.elements.smsDirection.val()
        if (direction == 'received-messages-stats' && this.twoWayIsNotActive == true) {
            this.elements.twoWayPromotion.show()
            this.elements.totalsDiv.addClass('blur')
            this.elements.context.addClass('blur')
        } else {
            this.elements.twoWayPromotion.hide()
            this.elements.totalsDiv.removeClass('blur')
            this.elements.context.removeClass('blur')
        }
    },

    getChartData: function () {

        const timeFrame = this.elements.timeFrameSelect.val()
        const direction = this.elements.smsDirection.val()
        const datasets = WPSmsStatsData[direction][timeFrame]
        const localization = WPSmsStatsData.localization

        switch (direction) {
            case 'send-messages-stats':
                return {
                    datasets: [
                        {
                            label: localization.successful,
                            backgroundColor: 'rgba(0, 190, 86, 0.4)',
                            borderColor: 'rgba(0, 148, 67, 1)',
                            borderWidth: 1,
                            fill: true,
                            data: datasets['successful'],
                            tension: 0.4,
                        },
                        {
                            label: localization.failed,
                            backgroundColor: 'rgba(255, 99, 132, 0.5)',
                            borderColor: 'rgba(255, 99, 132, 1)',
                            borderWidth: 1,
                            fill: true,
                            data: datasets['failure'],
                            tension: 0.4,
                        }
                    ]
                }
            case 'received-messages-stats':
                return {
                    datasets: [
                        {
                            label: localization.successful,
                            backgroundColor: 'rgba(0, 190, 86, 0.4)',
                            borderColor: 'rgba(0, 148, 67, 1)',
                            borderWidth: 1,
                            fill: true,
                            data: datasets['successful'],
                            tension: 0.4,
                        },
                        {
                            label: localization.failed,
                            backgroundColor: 'rgba(255, 99, 132, 0.4)',
                            borderColor: 'rgba(255, 99, 132, 1)',
                            borderWidth: 1,
                            fill: true,
                            data: datasets['failure'],
                            tension: 0.4,
                        },
                        {
                            label: localization.plain,
                            backgroundColor: 'rgba(156, 156, 156, 0.3)',
                            borderColor: 'rgb(73, 80, 87)',
                            borderWidth: 1,
                            fill: true,
                            data: datasets['plain'],
                            tension: 0.4,
                        }
                    ]
                }
        }
    },

    calculateCounts() {
        const timeFrame = this.elements.timeFrameSelect.val()
        const direction = this.elements.smsDirection.val()
        const datasets = WPSmsStatsData[direction][timeFrame]
        const localization = WPSmsStatsData.localization

        let totals = {}
        for (const key in datasets) {
            if (Object.hasOwnProperty.call(datasets, key)) {
                const element = datasets[key];
                totals[key] = Object.keys(element).reduce((sum, key) => sum + parseFloat(element[key] || 0), 0)
            }
        }

        switch (direction) {
            case 'send-messages-stats':
                this.elements.totalsDiv.html(
                    `
                        <td class='successful'>
                            <img src="data:image/svg+xml,%3C%3Fxml version='1.0' encoding='utf-8'%3F%3E%3Csvg version='1.1' id='Layer_1' xmlns='http://www.w3.org/2000/svg' xmlns:xlink='http://www.w3.org/1999/xlink' x='0px' y='0px' width='96px' height='96px' viewBox='0 0 96 96' enable-background='new 0 0 96 96' xml:space='preserve'%3E%3Cg%3E%3Cpath fill-rule='evenodd' clip-rule='evenodd' fill='%236BBE66' d='M48,0c26.51,0,48,21.49,48,48S74.51,96,48,96S0,74.51,0,48 S21.49,0,48,0L48,0z M26.764,49.277c0.644-3.734,4.906-5.813,8.269-3.79c0.305,0.182,0.596,0.398,0.867,0.646l0.026,0.025 c1.509,1.446,3.2,2.951,4.876,4.443l1.438,1.291l17.063-17.898c1.019-1.067,1.764-1.757,3.293-2.101 c5.235-1.155,8.916,5.244,5.206,9.155L46.536,63.366c-2.003,2.137-5.583,2.332-7.736,0.291c-1.234-1.146-2.576-2.312-3.933-3.489 c-2.35-2.042-4.747-4.125-6.701-6.187C26.993,52.809,26.487,50.89,26.764,49.277L26.764,49.277z'/%3E%3C/g%3E%3C/svg%3E">
                            ${totals.successful} ${localization.successful}
                        </td>
                        <td class='failure'>
                            <img src="data:image/svg+xml,%3Csvg id='Layer_1' data-name='Layer 1' xmlns='http://www.w3.org/2000/svg' viewBox='0 0 122.88 122.88'%3E%3Cdefs%3E%3Cstyle%3E.cls-1%7Bfill:%23eb0100;%7D.cls-1,.cls-2%7Bfill-rule:evenodd;%7D.cls-2%7Bfill:%23fff;%7D%3C/style%3E%3C/defs%3E%3Ctitle%3Ecancel%3C/title%3E%3Cpath class='cls-1' d='M61.44,0A61.44,61.44,0,1,1,0,61.44,61.44,61.44,0,0,1,61.44,0Z'/%3E%3Cpath class='cls-2' d='M35.38,49.72c-2.16-2.13-3.9-3.47-1.19-6.1l8.74-8.53c2.77-2.8,4.39-2.66,7,0L61.68,46.86,73.39,35.15c2.14-2.17,3.47-3.91,6.1-1.2L88,42.69c2.8,2.77,2.66,4.4,0,7L76.27,61.44,88,73.21c2.65,2.58,2.79,4.21,0,7l-8.54,8.74c-2.63,2.71-4,1-6.1-1.19L61.68,76,49.9,87.81c-2.58,2.64-4.2,2.78-7,0l-8.74-8.53c-2.71-2.63-1-4,1.19-6.1L47.1,61.44,35.38,49.72Z'/%3E%3C/svg%3E">
                            ${totals.failure} ${localization.failed}
                        </td>
                    `
                )
                break
            case 'received-messages-stats':
                this.elements.totalsDiv.html(
                    `
                        <td class='successful'>
                            <img src="data:image/svg+xml,%3C%3Fxml version='1.0' encoding='utf-8'%3F%3E%3Csvg version='1.1' id='Layer_1' xmlns='http://www.w3.org/2000/svg' xmlns:xlink='http://www.w3.org/1999/xlink' x='0px' y='0px' width='96px' height='96px' viewBox='0 0 96 96' enable-background='new 0 0 96 96' xml:space='preserve'%3E%3Cg%3E%3Cpath fill-rule='evenodd' clip-rule='evenodd' fill='%236BBE66' d='M48,0c26.51,0,48,21.49,48,48S74.51,96,48,96S0,74.51,0,48 S21.49,0,48,0L48,0z M26.764,49.277c0.644-3.734,4.906-5.813,8.269-3.79c0.305,0.182,0.596,0.398,0.867,0.646l0.026,0.025 c1.509,1.446,3.2,2.951,4.876,4.443l1.438,1.291l17.063-17.898c1.019-1.067,1.764-1.757,3.293-2.101 c5.235-1.155,8.916,5.244,5.206,9.155L46.536,63.366c-2.003,2.137-5.583,2.332-7.736,0.291c-1.234-1.146-2.576-2.312-3.933-3.489 c-2.35-2.042-4.747-4.125-6.701-6.187C26.993,52.809,26.487,50.89,26.764,49.277L26.764,49.277z'/%3E%3C/g%3E%3C/svg%3E">
                            ${totals.successful} ${localization.successful}
                        </td>
                        <td class='failure'>
                            <img src="data:image/svg+xml,%3Csvg id='Layer_1' data-name='Layer 1' xmlns='http://www.w3.org/2000/svg' viewBox='0 0 122.88 122.88'%3E%3Cdefs%3E%3Cstyle%3E.cls-1%7Bfill:%23eb0100;%7D.cls-1,.cls-2%7Bfill-rule:evenodd;%7D.cls-2%7Bfill:%23fff;%7D%3C/style%3E%3C/defs%3E%3Ctitle%3Ecancel%3C/title%3E%3Cpath class='cls-1' d='M61.44,0A61.44,61.44,0,1,1,0,61.44,61.44,61.44,0,0,1,61.44,0Z'/%3E%3Cpath class='cls-2' d='M35.38,49.72c-2.16-2.13-3.9-3.47-1.19-6.1l8.74-8.53c2.77-2.8,4.39-2.66,7,0L61.68,46.86,73.39,35.15c2.14-2.17,3.47-3.91,6.1-1.2L88,42.69c2.8,2.77,2.66,4.4,0,7L76.27,61.44,88,73.21c2.65,2.58,2.79,4.21,0,7l-8.54,8.74c-2.63,2.71-4,1-6.1-1.19L61.68,76,49.9,87.81c-2.58,2.64-4.2,2.78-7,0l-8.74-8.53c-2.71-2.63-1-4,1.19-6.1L47.1,61.44,35.38,49.72Z'/%3E%3C/svg%3E">
                            ${totals.failure} ${localization.failed}
                        </td>
                        <td class='plain'>
                            <img src="data:image/svg+xml,%3Csvg id='Layer_1' data-name='Layer 1' xmlns='http://www.w3.org/2000/svg' viewBox='0 0 121.86 122.88'%3E%3Ctitle%3Ecomment%3C/title%3E%3Cpath d='M30.28,110.09,49.37,91.78A3.84,3.84,0,0,1,52,90.72h60a2.15,2.15,0,0,0,2.16-2.16V9.82a2.16,2.16,0,0,0-.64-1.52A2.19,2.19,0,0,0,112,7.66H9.82A2.24,2.24,0,0,0,7.65,9.82V88.55a2.19,2.19,0,0,0,2.17,2.16H26.46a3.83,3.83,0,0,1,3.82,3.83v15.55ZM28.45,63.56a3.83,3.83,0,1,1,0-7.66h53a3.83,3.83,0,0,1,0,7.66Zm0-24.86a3.83,3.83,0,1,1,0-7.65h65a3.83,3.83,0,0,1,0,7.65ZM53.54,98.36,29.27,121.64a3.82,3.82,0,0,1-6.64-2.59V98.36H9.82A9.87,9.87,0,0,1,0,88.55V9.82A9.9,9.9,0,0,1,9.82,0H112a9.87,9.87,0,0,1,9.82,9.82V88.55A9.85,9.85,0,0,1,112,98.36Z'/%3E%3C/svg%3E">
                            ${totals.plain ?? 0} ${localization.plain}
                        </td>
                    `
                )
                break
        }
    },

    addEventListener: function () {
        const action = function () {
            this.showTwoWayModalIfNotActive()
            const chart = this.chart;
            chart.data = this.getChartData();
            this.calculateCounts()
            chart.update()
        }.bind(this)

        this.elements.timeFrameSelect.change(action)
        this.elements.smsDirection.change(action)
    },

    initChart: function () {
        const ctx = this.elements.context.get(0)
        this.chart = new Chart(ctx, {
            type: 'line',
            data: this.getChartData(),
            options: {
                tooltips: {
                    mode: 'index'
                },
                interaction: {
                    intersect: false,
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    }


}