/**
 * CampusFix - Chart.js Data Visualizations
 * Initializes responsive, interactive charts for administrative facility analytics.
 */

document.addEventListener('DOMContentLoaded', function () {
    // 1. Fault Distribution by Category (Doughnut Chart)
    const categoryCtx = document.getElementById('categoryChart');
    if (categoryCtx && window.categoryChartData) {
        new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: window.categoryChartData.labels,
                datasets: [{
                    data: window.categoryChartData.values,
                    backgroundColor: window.categoryChartData.colors,
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12,
                            padding: 15,
                            font: { family: 'Inter', size: 11 }
                        }
                    }
                },
                cutout: '65%'
            }
        });
    }

    // 2. Incident Frequency by Campus Building (Bar Chart)
    const buildingCtx = document.getElementById('buildingChart');
    if (buildingCtx && window.buildingChartData) {
        new Chart(buildingCtx, {
            type: 'bar',
            data: {
                labels: window.buildingChartData.labels,
                datasets: [{
                    label: 'Reported Faults',
                    data: window.buildingChartData.values,
                    backgroundColor: '#2563eb',
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1, font: { family: 'Inter' } },
                        grid: { color: '#f1f5f9' }
                    },
                    x: {
                        ticks: { font: { family: 'Inter', size: 10 } },
                        grid: { display: false }
                    }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }

    // 3. Technician Workload & Completion (Grouped Bar Chart)
    const techCtx = document.getElementById('technicianChart');
    if (techCtx && window.techChartData) {
        new Chart(techCtx, {
            type: 'bar',
            data: {
                labels: window.techChartData.labels,
                datasets: [
                    {
                        label: 'Active Jobs',
                        data: window.techChartData.active,
                        backgroundColor: '#f59e0b',
                        borderRadius: 4
                    },
                    {
                        label: 'Completed / Fixed',
                        data: window.techChartData.completed,
                        backgroundColor: '#10b981',
                        borderRadius: 4
                    }
                ]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: { stepSize: 1, font: { family: 'Inter' } },
                        grid: { color: '#f1f5f9' }
                    },
                    y: {
                        ticks: { font: { family: 'Inter', size: 11 } },
                        grid: { display: false }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { boxWidth: 12, font: { family: 'Inter', size: 11 } }
                    }
                }
            }
        });
    }

    // 4. Incident Submission Velocity (Area Line Chart)
    const trendCtx = document.getElementById('trendChart');
    if (trendCtx && window.trendChartData) {
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: window.trendChartData.labels,
                datasets: [{
                    label: 'New Incidents',
                    data: window.trendChartData.values,
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.1)',
                    fill: true,
                    tension: 0.35,
                    borderWidth: 2,
                    pointBackgroundColor: '#2563eb'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1, font: { family: 'Inter' } },
                        grid: { color: '#f1f5f9' }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { family: 'Inter', size: 11 } }
                    }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }
});

