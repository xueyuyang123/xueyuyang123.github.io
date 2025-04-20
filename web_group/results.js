document.addEventListener('DOMContentLoaded', function() {
    const resultsContainer = document.getElementById('results-container');
    const pollFilter = document.getElementById('poll-filter');
    let currentData = [];

    function safeHTMLRender(poll) {
        return `
            <div class="poll-result">
                <div class="vote-type-label ${poll.type}">${poll.type.toUpperCase()}</div>
                <div class="poll-image">
                    <img src="${poll.image_path || 'uploads/default.png'}" alt="${poll.question}">
                </div>
                <div class="poll-header">
                    <h1>${poll.question}</h1>
                </div>
                <div class="poll-meta">
                    <div class="meta-item">
                        <i class="fas fa-user"></i>
                        <span>Created By: ${poll.creator}</span>
                    </div>
                    <div class="meta-divider"></div>
                    <div class="meta-item">
                        <i class="fas fa-chart-bar"></i>
                        <span>Total Votes: ${poll.total_votes}</span>
                    </div>
                </div>
                <div class="poll-details">
                    ${poll.options.map((option, index) => `
                        <div class="option-result">
                            <div class="option-text">${option.option_text}</div>
                            <div class="progress-container">
                                <div class="progress-bar" 
                                     style="width: ${option.percentage}%;
                                            background: ${getOptionColor(index)}">
                                </div>
                            </div>
                            <div class="vote-percentage">
                                <span>${option.vote_count} votes</span>
                                <span>${option.percentage.toFixed(1)}%</span>
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
    }

    function getOptionColor(index) {
        const hue = (index * 137.508) % 360;
        return `hsl(${hue}, 70%, 65%)`;
    }

    function updateDOM(newData) {
        const newHTML = newData.map(poll => safeHTMLRender(poll)).join('');
        morphdom(resultsContainer, newHTML, {
            childrenOnly: true,
            onBeforeElUpdated: (fromEl, toEl) => {
                if (fromEl.isEqualNode(toEl)) return false;
                return true;
            }
        });
    }

    async function fetchResults(filter) {
        try {
            resultsContainer.innerHTML = `<div class="loading-spinner"><div class="spinner"></div><p>Loading...</p></div>`;
            
            const [pollsRes, surveysRes] = await Promise.all([
                fetch(`get_latest_results.php?filter=${filter}`),
                fetch(`get_latest_survey_results.php?filter=${filter}`)
            ]);

            const pollsData = await pollsRes.json();
            const surveysData = await surveysRes.json();

            const combined = [...pollsData.polls, ...surveysData.surveys]
                .sort((a,b) => new Date(b.created_at) - new Date(a.created_at));

            if (combined.length === 0) {
                resultsContainer.innerHTML = `<div class="no-results">No results found</div>`;
                return;
            }

            currentData = combined;
            updateDOM(combined);
        } catch (error) {
            resultsContainer.innerHTML = `<div class="alert error">${error.message}</div>`;
        }
    }

    // 防抖处理
    let updateTimeout;
    function scheduleUpdate(filter) {
        clearTimeout(updateTimeout);
        updateTimeout = setTimeout(() => fetchResults(filter), 300);
    }

    // 事件监听
    pollFilter.addEventListener('change', () => scheduleUpdate(pollFilter.value));
    window.addEventListener('load', () => scheduleUpdate('all'));
    setInterval(() => scheduleUpdate(pollFilter.value), 5000);

    // 点击展开/收起
    resultsContainer.addEventListener('click', (e) => {
        const poll = e.target.closest('.poll-result');
        if (poll) {
            const details = poll.querySelector('.poll-details');
            details.classList.toggle('expanded');
        }
    });
});