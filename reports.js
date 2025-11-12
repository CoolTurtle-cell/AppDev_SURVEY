// reports.js

// Initialize charts when page loads
document.addEventListener('DOMContentLoaded', function() {
    loadCharts();
});

// Load all charts
function loadCharts() {
    loadLineChart();
    loadBarChart();
    loadPieChart();
    loadDonutChart();
}

// Line Chart - Satisfaction Trend
function loadLineChart() {
    const ctx = document.getElementById('line-chart');
    if (ctx) {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov'],
                datasets: [{
                    label: 'Average Satisfaction Score',
                    data: [4.1, 4.3, 4.2, 4.5, 4.4, 4.6, 4.5, 4.7, 4.6, 4.8, 4.7],
                    borderColor: '#0080AA',
                    backgroundColor: 'rgba(0, 128, 170, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 5
                    }
                }
            }
        });
    }
}

// Bar Chart - Branch Comparison
function loadBarChart() {
    const ctx = document.getElementById('bar-chart');
    if (ctx) {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Branch A', 'Branch B', 'Branch C'],
                datasets: [{
                    label: 'Average Score',
                    data: [4.5, 4.7, 3.8, 4.2],
                    backgroundColor: ['#FFD027', '#0080AA', '#FFD027']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 5
                    }
                }
            }
        });
    }
}

// Pie Chart - Service Feedback Breakdown
function loadPieChart() {
    const ctx = document.getElementById('pie-chart');
    if (ctx) {
        new Chart(ctx, {
            type: 'pie',
            data: {
                labels: ['Service Quality', 'Staff Behavior', 'Facility', 'Wait Time'],
                datasets: [{
                    data: [42, 25, 17, 16],
                    backgroundColor: ['#0080AA', '#FFD027', '#DCE6E8', '#1F2A2D']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
}

// Donut Chart - Survey Distribution
function loadDonutChart() {
    const ctx = document.getElementById('donut-chart');
    if (ctx) {
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Branch A', 'Branch B', 'Branch C'],
                datasets: [{
                    data: [35, 28, 20, 17],
                    backgroundColor: ['#0080AA', '#FFD027', '#DCE6E8']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
}