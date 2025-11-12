// Data
const responses = [
    {
        id: 'R-0001',
        dateSubmitted: '2025-11-08 14:32',
        respondent: 'Anonymous',
        branch: 'Branch A',
        category: 'Service Quality',
        avgScore: 5.0,
        answers: [
            { question: 'How satisfied are you with our service?', answer: '5 - Very Satisfied' },
            { question: 'How would you rate our staff?', answer: '5 - Excellent' },
            { question: 'What can we improve?', answer: 'Nothing, everything was perfect!' }
        ]
    },
    {
        id: 'R-0002',
        dateSubmitted: '2025-11-08 11:20',
        respondent: 'Evelyn Ha',
        branch: 'Branch B',
        category: 'Staff',
        avgScore: 3.0,
        answers: [
            { question: 'How satisfied are you with our service?', answer: '4 - Satisfied' },
            { question: 'How would you rate our staff?', answer: '4 - Good' },
            { question: 'What can we improve?', answer: 'Faster response time would be great' }
        ]
    },
    {
        id: 'R-0003',
        dateSubmitted: '2025-11-07 16:15',
        respondent: 'Billie Eilish',
        branch: 'Branch B',
        category: 'Service Quality',
        avgScore: 5.0,
        answers: [
            { question: 'How satisfied are you with our service?', answer: '3 - Neutral' },
            { question: 'How would you rate our staff?', answer: '3 - Average' },
            { question: 'What can we improve?', answer: 'Better waiting area needed' }
        ]
    },
    {
        id: 'R-0004',
        dateSubmitted: '2025-11-07 09:45',
        respondent: 'Anonymous',
        branch: 'Branch A',
        category: 'Facility',
        avgScore: 4.0,
        answers: [
            { question: 'How satisfied are you with our service?', answer: '5 - Very Satisfied' },
            { question: 'How would you rate our staff?', answer: '5 - Excellent' },
            { question: 'What can we improve?', answer: 'Keep up the excellent work!' }
        ]
    },
    {
        id: 'R-0005',
        dateSubmitted: '2025-11-06 13:30',
        respondent: 'Adler',
        branch: 'Branch B',
        category: 'Staff',
        avgScore: 3.5,
        answers: [
            { question: 'How satisfied are you with our service?', answer: '4 - Satisfied' },
            { question: 'How would you rate our staff?', answer: '4 - Good' },
            { question: 'What can we improve?', answer: 'Reduce waiting time during peak hours' }
        ]
    },
    {
        id: 'R-0006',
        dateSubmitted: '2025-11-06 10:15',
        respondent: 'Mitsuki',
        branch: 'Branch A',
        category: 'Facility',
        avgScore: 4.0,
        answers: [
            { question: 'How satisfied are you with our service?', answer: '2 - Dissatisfied' },
            { question: 'How would you rate our staff?', answer: '2 - Poor' },
            { question: 'What can we improve?', answer: 'Service was slow and staff seemed disinterested' }
        ]
    }
];


// State
let filters = {
    search: '',
    dateFrom: '',
    dateTo: '',
    branch: '',
    category: ''
};

let pagination = {
    current: 1,
    perPage: 10
};

// Initialize
document.addEventListener('DOMContentLoaded', function () {
    setupFilters();
    renderTable();
});

// Setup filter event listeners
function setupFilters() {
    document.getElementById('search-input').addEventListener('input', function (e) {
        filters.search = e.target.value;
        pagination.current = 1;
        renderTable();
    });

    document.getElementById('date-from').addEventListener('change', function (e) {
        filters.dateFrom = e.target.value;
        pagination.current = 1;
        renderTable();
    });

    document.getElementById('date-to').addEventListener('change', function (e) {
        filters.dateTo = e.target.value;
        pagination.current = 1;
        renderTable();
    });

    document.getElementById('branch-filter').addEventListener('change', function (e) {
        filters.branch = e.target.value;
        pagination.current = 1;
        renderTable();
    });

    document.getElementById('category-filter').addEventListener('change', function (e) {
        filters.category = e.target.value;
        pagination.current = 1;
        renderTable();
    });

    document.getElementById('refresh-btn').addEventListener('click', resetFilters);
}

// Reset all filters
function resetFilters() {
    filters = {
        search: '',
        dateFrom: '',
        dateTo: '',
        branch: '',
        category: ''
    };
    pagination.current = 1;

    document.getElementById('search-input').value = '';
    document.getElementById('date-from').value = '';
    document.getElementById('date-to').value = '';
    document.getElementById('branch-filter').value = '';
    document.getElementById('category-filter').value = '';

    renderTable();
}

// Get filtered responses
function getFilteredResponses() {
    return responses.filter(r => {
        let match = true;

        if (filters.search) {
            const search = filters.search.toLowerCase();
            match = match && (
                r.id.toLowerCase().includes(search) ||
                r.respondent.toLowerCase().includes(search)
            );
        }

        if (filters.branch) {
            match = match && r.branch === filters.branch;
        }

        if (filters.category) {
            match = match && r.category === filters.category;
        }

        if (filters.dateFrom) {
            match = match && r.dateSubmitted >= filters.dateFrom;
        }

        if (filters.dateTo) {
            match = match && r.dateSubmitted <= filters.dateTo;
        }

        return match;
    });
}

// Render table
function renderTable() {
    const filtered = getFilteredResponses();
    const totalPages = Math.ceil(filtered.length / pagination.perPage);
    const start = (pagination.current - 1) * pagination.perPage;
    const end = start + pagination.perPage;
    const pageData = filtered.slice(start, end);

    const tbody = document.getElementById('responses-tbody');
    tbody.innerHTML = '';

    pageData.forEach(response => {
        const row = document.createElement('tr');

        const scoreClass = response.avgScore >= 4 ? 'score-high' :
            response.avgScore >= 3 ? 'score-medium' : 'score-low';

        row.innerHTML = `
            <td class="response-id">${response.id}</td>
            <td>${response.dateSubmitted}</td>
            <td>${response.respondent}</td>
            <td>
                <div class="branch-info">${response.branch}</div>
                <div class="category-info">${response.category}</div>
            </td>
            <td>
                <span class="score-badge ${scoreClass}">${response.avgScore.toFixed(1)}</span>
            </td>
            <td style="text-align: center;">
                <button class="btn-view" onclick="showModal('${response.id}')">
                <img src="icons/eye.svg" alt="View" class="icon" /> View
                </button>
            </td>
            `;


        tbody.appendChild(row);
    });

    // Update pagination
    document.getElementById('page-info').textContent = `Page ${pagination.current} of ${totalPages}`;
    document.getElementById('prev-btn').disabled = pagination.current === 1;
    document.getElementById('next-btn').disabled = pagination.current === totalPages;
}

// Pagination
function prevPage() {
    if (pagination.current > 1) {
        pagination.current--;
        renderTable();
    }
}

function nextPage() {
    const filtered = getFilteredResponses();
    const totalPages = Math.ceil(filtered.length / pagination.perPage);
    if (pagination.current < totalPages) {
        pagination.current++;
        renderTable();
    }
}

// Show modal
function showModal(responseId) {
    const response = responses.find(r => r.id === responseId);
    if (!response) return;

    document.getElementById('modal-id').textContent = response.id + ' • ' + response.dateSubmitted;
    document.getElementById('modal-respondent').textContent = response.respondent;
    document.getElementById('modal-branch').textContent = response.branch;
    document.getElementById('modal-category').textContent = response.category;

    const answersContainer = document.getElementById('modal-answers');
    answersContainer.innerHTML = '';

    response.answers.forEach((answer, index) => {
        const answerDiv = document.createElement('div');
        answerDiv.className = 'answer-item';
        answerDiv.innerHTML = `
            <p class="answer-question">Q${index + 1}: ${answer.question}</p>
            <p class="answer-text">${answer.answer}</p>
        `;
        answersContainer.appendChild(answerDiv);
    });

    document.getElementById('response-modal').classList.remove('hidden');
}

// Close modal
function closeModal() {
    document.getElementById('response-modal').classList.add('hidden');
}