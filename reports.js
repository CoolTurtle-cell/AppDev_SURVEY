// reports.js - Chart rendering and export functionality for reports page

document.addEventListener('DOMContentLoaded', function() {
    // Initialize charts
    renderCharts();

    // Setup export buttons
    document.getElementById('export-excel').addEventListener('click', exportToExcel);
    document.getElementById('export-pdf').addEventListener('click', exportToPDF);
});

function renderCharts() {
    // Check if we have valid chart data
    if (!chartData || !chartData.pieChart) {
        console.warn('Chart data not available');
        return;
    }

    // Responses Over Time Line Chart
    const responsesLineChartElement = document.getElementById('responses-line-chart');
    if (responsesLineChartElement && chartData.pieChart) {
        const labels = chartData.pieChart.labels || [];
        const data = chartData.pieChart.data || [];

        const ctx = responsesLineChartElement.getContext('2d');

        // Render a line chart showing responses over time
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Responses',
                    data: data,
                    borderColor: '#0080aa',
                    backgroundColor: 'rgba(0,128,170,0.15)',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 4,
                    pointBackgroundColor: '#0080aa'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { display: false },
                    tooltip: { mode: 'index', intersect: false }
                },
                scales: {
                    x: { display: true, title: { display: true, text: 'Date' } },
                    y: { display: true, title: { display: true, text: 'Number of Responses' }, beginAtZero: true, ticks: { precision: 0 } }
                }
            }
        });
    }
}

function exportToExcel() {
    // Prepare data for Excel export
    const totalResponses = document.querySelector('.stats-grid .stat-card:nth-child(1) .stat-value')?.textContent || 'N/A';
    const avgSatisfaction = document.querySelector('.stats-grid .stat-card:nth-child(2) .stat-value')?.textContent || 'N/A';
    const completionRate = document.querySelector('.stats-grid .stat-card:nth-child(3) .stat-value')?.textContent || 'N/A';
    const responsesTrend = document.querySelector('.stats-grid .stat-card:nth-child(4) .stat-value')?.textContent || 'N/A';

    const data = [
        ['Survey Analytics Report'],
        [],
        ['Summary Metrics'],
        ['Metric', 'Value'],
        ['Total Responses', totalResponses],
        ['Average Satisfaction', avgSatisfaction],
        ['Completion Rate', completionRate],
        ['Response Trend', responsesTrend]
    ];

    // Add responses over time data
    if (chartData.pieChart && chartData.pieChart.labels && chartData.pieChart.labels.length > 0) {
        data.push([]);
        data.push(['Responses Over Time']);
        data.push(['Date', 'Number of Responses']);
        chartData.pieChart.labels.forEach((label, index) => {
            data.push([label, chartData.pieChart.data[index] || 0]);
        });
    }

    // Add question analytics if a specific survey is selected
    if (surveyFilter && questionAnalytics) {
        data.push([]);
        data.push(['Question-by-Question Analytics']);
        data.push(['Question', 'Type', 'Responses', 'Average Rating', 'Rating Distribution (1-5)']);

        Object.values(questionAnalytics).forEach(analytics => {
            const ratingDist = analytics.rating_distribution ?
                `${analytics.rating_distribution[1] || 0},${analytics.rating_distribution[2] || 0},${analytics.rating_distribution[3] || 0},${analytics.rating_distribution[4] || 0},${analytics.rating_distribution[5] || 0}` :
                'N/A';
            const avgRating = analytics.question_type === 'rating' && analytics.responses > 0 ?
                (analytics.total_rating / analytics.responses).toFixed(1) :
                'N/A';

            data.push([
                analytics.question_text,
                analytics.question_type,
                analytics.responses,
                avgRating,
                ratingDist
            ]);
        });
    }

    // Create workbook and worksheet
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.aoa_to_sheet(data);

    // Set column widths
    ws['!cols'] = [{ wch: 40 }, { wch: 15 }, { wch: 15 }, { wch: 15 }, { wch: 20 }];

    // Add worksheet to workbook
    XLSX.utils.book_append_sheet(wb, ws, 'Reports');

    // Generate filename with current date and survey title if applicable
    const date = new Date().toISOString().split('T')[0];
    const surveySuffix = surveyTitle ? `_${surveyTitle.replace(/[^a-zA-Z0-9]/g, '_')}` : '';
    const filename = `survey_reports${surveySuffix}_${date}.xlsx`;

    // Save file
    XLSX.writeFile(wb, filename);
}

function exportToPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();

    // Add title
    doc.setFontSize(20);
    doc.text('Survey Reports', 20, 30);

    // Add generation date
    doc.setFontSize(10);
    const date = new Date().toLocaleDateString();
    doc.text(`Generated: ${date}`, 20, 38);

    // Add summary data
    doc.setFontSize(12);
    doc.setFont(undefined, 'bold');
    let yPosition = 50;
    doc.text('Summary Metrics:', 20, yPosition);
    doc.setFont(undefined, 'normal');
    yPosition += 8;

    const totalResponses = document.querySelector('.stats-grid .stat-card:nth-child(1) .stat-value')?.textContent || 'N/A';
    const avgSatisfaction = document.querySelector('.stats-grid .stat-card:nth-child(2) .stat-value')?.textContent || 'N/A';
    const completionRate = document.querySelector('.stats-grid .stat-card:nth-child(3) .stat-value')?.textContent || 'N/A';
    const responsesTrend = document.querySelector('.stats-grid .stat-card:nth-child(4) .stat-value')?.textContent || 'N/A';

    doc.text(`Total Responses: ${totalResponses}`, 25, yPosition);
    yPosition += 8;
    doc.text(`Average Satisfaction: ${avgSatisfaction}`, 25, yPosition);
    yPosition += 8;
    doc.text(`Completion Rate: ${completionRate}`, 25, yPosition);
    yPosition += 8;
    doc.text(`Response Trend: ${responsesTrend}`, 25, yPosition);

    // Add responses over time data
    if (chartData.pieChart && chartData.pieChart.labels && chartData.pieChart.labels.length > 0) {
        yPosition += 15;
        doc.setFont(undefined, 'bold');
        doc.text('Responses Over Time:', 20, yPosition);
        doc.setFont(undefined, 'normal');
        yPosition += 8;
        chartData.pieChart.labels.forEach((label, index) => {
            doc.text(`${label}: ${chartData.pieChart.data[index] || 0} responses`, 25, yPosition);
            yPosition += 6;
            // Check if we need a new page
            if (yPosition > 270) {
                doc.addPage();
                yPosition = 20;
            }
        });
    }

    // Add survey summaries if "All Surveys" is selected
    if (!surveyFilter && surveySummaries && surveySummaries.length > 0) {
        yPosition += 15;
        doc.setFont(undefined, 'bold');
        doc.text('All Surveys Summary:', 20, yPosition);
        doc.setFont(undefined, 'normal');
        yPosition += 8;

        surveySummaries.forEach(survey => {
            doc.text(`${survey.title}: ${survey.responses} responses, Avg: ${survey.avg_satisfaction}/5`, 25, yPosition);
            yPosition += 6;

            // Check if we need a new page
            if (yPosition > 270) {
                doc.addPage();
                yPosition = 20;
            }
        });
    }

    // Add question analytics if a specific survey is selected
    if (surveyFilter && questionAnalytics) {
        yPosition += 15;
        doc.setFont(undefined, 'bold');
        doc.text('Question-by-Question Analytics:', 20, yPosition);
        doc.setFont(undefined, 'normal');
        yPosition += 8;

        Object.values(questionAnalytics).forEach(analytics => {
            // Add question text
            doc.text(`Question: ${analytics.question_text}`, 25, yPosition);
            yPosition += 8;

            // Add question stats
            doc.text(`Type: ${analytics.question_type}`, 30, yPosition);
            yPosition += 6;
            doc.text(`Responses: ${analytics.responses}`, 30, yPosition);
            yPosition += 6;

            if (analytics.question_type === 'rating' && analytics.responses > 0) {
                const avgRating = (analytics.total_rating / analytics.responses).toFixed(1);
                doc.text(`Average Rating: ${avgRating}/5`, 30, yPosition);
                yPosition += 6;

                // Add rating distribution
                doc.text('Rating Distribution:', 30, yPosition);
                yPosition += 6;
                for (let i = 1; i <= 5; i++) {
                    const count = analytics.rating_distribution[i] || 0;
                    doc.text(`${i} Star${i > 1 ? 's' : ''}: ${count}`, 35, yPosition);
                    yPosition += 6;
                }
            }

            yPosition += 8; // Extra space between questions

            // Check if we need a new page
            if (yPosition > 250) {
                doc.addPage();
                yPosition = 20;
            }
        });
    }

    // Generate filename with current date and survey title if applicable
    const surveySuffix = surveyTitle ? `_${surveyTitle.replace(/[^a-zA-Z0-9]/g, '_')}` : '';
    const filename = `survey_reports${surveySuffix}_${date.replace(/\//g, '-')}.pdf`;

    // Save file
    doc.save(filename);
}
