console.log(WPSmsWidgetsStats)

// @todo, rename classes and make it dynamic
const ctx = document.getElementById('myChart');
const myChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August'],
        datasets: [{
            label: '# of Votes',
            data: [12, 19, 3, 5, 2, 3, 25, 6, 1, 7, 8, 2],
            label: "Total",
            borderColor: "rgb(62,149,205)",
            backgroundColor: "rgb(62,149,205,0.1)",
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        legend: {
            position: 'bottom',
        },
        animation: {
            duration: 1500,
        },
        title: {
            display: true,
            text: title
        },
        tooltips: {
            mode: 'index',
            intersect: false,
        },
        scales: {
            yAxes: [{
                ticks: {
                    beginAtZero: true
                }
            }]
        }
    }
});