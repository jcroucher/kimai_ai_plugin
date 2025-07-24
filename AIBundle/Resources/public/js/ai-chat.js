class AIChat {
    constructor() {
        this.isOpen = false;
        this.currentEntries = [];
        this.init();
    }

    init() {
        this.createChatWidget();
        this.setupEventListeners();
    }

    createChatWidget() {
        const widget = document.createElement('div');
        widget.id = 'ai-chat-widget';
        widget.className = 'ai-chat-widget';
        widget.innerHTML = `
            <div class="ai-chat-toggle" id="ai-chat-toggle">
                <i class="fa fa-robot"></i>
                <span>AI Assistant</span>
            </div>
            <div class="ai-chat-panel" id="ai-chat-panel">
                <div class="ai-chat-header">
                    <h4><i class="fa fa-robot"></i> AI Assistant</h4>
                    <button class="ai-chat-close" id="ai-chat-close">
                        <i class="fa fa-times"></i>
                    </button>
                </div>
                <div class="ai-chat-tabs">
                    <button class="ai-tab-btn active" data-tab="chat">Chat</button>
                    <button class="ai-tab-btn" data-tab="timelog">Parse Time Log</button>
                </div>
                <div class="ai-chat-content">
                    <div class="ai-tab-content active" id="chat-tab">
                        <div class="ai-chat-messages" id="ai-chat-messages"></div>
                        <div class="ai-chat-input">
                            <input type="text" id="ai-chat-message" placeholder="Ask me anything..." />
                            <button id="ai-chat-send"><i class="fa fa-paper-plane"></i></button>
                        </div>
                    </div>
                    <div class="ai-tab-content" id="timelog-tab">
                        <div id="ai-timelog-input-section">
                            <textarea id="ai-timelog-input" placeholder="Paste your time log here...
Example:
Monday 1/15
9-10:30am - Meeting with team about new features
2-4pm - Development work on user dashboard
4:30-5pm - Code review"></textarea>
                            <button id="ai-parse-timelog" class="btn btn-primary">
                                <i class="fa fa-magic"></i> Parse Time Log
                            </button>
                        </div>
                        <div id="ai-timelog-results"></div>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(widget);
    }

    setupEventListeners() {
        // Toggle chat widget
        document.getElementById('ai-chat-toggle').addEventListener('click', () => {
            this.toggleChat();
        });

        document.getElementById('ai-chat-close').addEventListener('click', () => {
            this.closeChat();
        });

        // Tab switching
        document.querySelectorAll('.ai-tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                this.switchTab(btn.dataset.tab);
            });
        });

        // Chat functionality
        document.getElementById('ai-chat-send').addEventListener('click', () => {
            this.sendMessage();
        });

        document.getElementById('ai-chat-message').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                this.sendMessage();
            }
        });

        // Time log parsing
        document.getElementById('ai-parse-timelog').addEventListener('click', () => {
            this.parseTimelog();
        });
    }

    toggleChat() {
        this.isOpen = !this.isOpen;
        const panel = document.getElementById('ai-chat-panel');
        const toggle = document.getElementById('ai-chat-toggle');
        
        if (this.isOpen) {
            panel.classList.add('open');
            toggle.classList.add('active');
        } else {
            panel.classList.remove('open');
            toggle.classList.remove('active');
        }
    }

    closeChat() {
        this.isOpen = false;
        document.getElementById('ai-chat-panel').classList.remove('open');
        document.getElementById('ai-chat-toggle').classList.remove('active');
    }

    switchTab(tabName) {
        // Update tab buttons
        document.querySelectorAll('.ai-tab-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.tab === tabName);
        });

        // Update tab content
        document.querySelectorAll('.ai-tab-content').forEach(content => {
            content.classList.toggle('active', content.id === `${tabName}-tab`);
        });
    }

    async sendMessage() {
        const input = document.getElementById('ai-chat-message');
        const message = input.value.trim();
        
        if (!message) return;

        this.addMessage('user', message);
        input.value = '';

        try {
            const response = await fetch('/ai/chat', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `message=${encodeURIComponent(message)}`
            });

            const data = await response.json();
            
            if (data.error) {
                this.addMessage('error', data.error);
            } else {
                this.addMessage('ai', data.response);
            }
        } catch (error) {
            this.addMessage('error', 'Failed to send message. Please try again.');
        }
    }

    addMessage(type, content) {
        const messagesContainer = document.getElementById('ai-chat-messages');
        const messageEl = document.createElement('div');
        messageEl.className = `ai-message ai-message-${type}`;
        
        const icon = type === 'user' ? 'fa-user' : type === 'ai' ? 'fa-robot' : 'fa-exclamation-triangle';
        
        messageEl.innerHTML = `
            <div class="ai-message-icon">
                <i class="fa ${icon}"></i>
            </div>
            <div class="ai-message-content">${this.formatMessage(content)}</div>
        `;
        
        messagesContainer.appendChild(messageEl);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    formatMessage(content) {
        // Simple markdown-like formatting
        return content
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/\*(.*?)\*/g, '<em>$1</em>')
            .replace(/\n/g, '<br>');
    }

    async parseTimelog() {
        const input = document.getElementById('ai-timelog-input');
        const timelog = input.value.trim();
        
        if (!timelog) {
            alert('Please enter a time log to parse.');
            return;
        }

        const button = document.getElementById('ai-parse-timelog');
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Parsing...';
        button.disabled = true;

        try {
            const response = await fetch('/ai/parse', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `timelog=${encodeURIComponent(timelog)}`
            });

            const data = await response.json();
            
            if (data.error) {
                alert('Error: ' + data.error);
            } else {
                this.currentEntries = data.entries;
                this.displayTimelogResults(data.preview);
            }
        } catch (error) {
            alert('Failed to parse time log. Please try again.');
        } finally {
            button.innerHTML = originalText;
            button.disabled = false;
        }
    }

    displayTimelogResults(preview) {
        const resultsContainer = document.getElementById('ai-timelog-results');
        const inputSection = document.getElementById('ai-timelog-input-section');
        
        if (!preview || preview.length === 0) {
            resultsContainer.innerHTML = '<p>No entries found.</p>';
            resultsContainer.style.display = 'block';
            return;
        }

        // Hide input section and show results
        inputSection.style.display = 'none';
        resultsContainer.style.display = 'flex';
        resultsContainer.style.flexDirection = 'column';
        resultsContainer.style.flex = '1';

        let totalAmount = 0;
        let totalMinutes = 0;
        let tableHTML = `
            <div class="ai-results-header">
                <h5>Parsed Entries (${preview.length})</h5>
                <button id="ai-back-to-input" class="btn btn-outline-secondary btn-sm">
                    <i class="fa fa-arrow-left"></i> Back to Edit
                </button>
            </div>
            <div class="ai-results-table">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Description</th>
                            <th>Client</th>
                            <th>Project</th>
                            <th>Duration</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        preview.forEach((entry, index) => {
            const timeRange = entry.start_time && entry.end_time 
                ? `${entry.start_time} - ${entry.end_time}`
                : `${Math.floor(entry.duration / 60)}h ${entry.duration % 60}m`;
            
            totalAmount += entry.total;
            totalMinutes += entry.duration;
            
            tableHTML += `
                <tr class="${index % 2 === 0 ? 'even-row' : 'odd-row'}">
                    <td>${entry.date}</td>
                    <td class="time-cell">${timeRange}</td>
                    <td class="desc-cell">${entry.description}</td>
                    <td>${entry.customer}</td>
                    <td>${entry.project}</td>
                    <td>${Math.floor(entry.duration / 60)}h ${entry.duration % 60}m</td>
                    <td class="total-cell">$${entry.total.toFixed(2)}</td>
                </tr>
            `;
        });

        const totalHours = Math.floor(totalMinutes / 60);
        const remainingMinutes = totalMinutes % 60;
        const totalTimeDisplay = remainingMinutes > 0 ? `${totalHours}h ${remainingMinutes}m` : `${totalHours}h`;

        tableHTML += `
                    </tbody>
                    <tfoot>
                        <tr class="total-row">
                            <td colspan="5"><strong>Total</strong></td>
                            <td><strong>${totalTimeDisplay}</strong></td>
                            <td class="total-cell"><strong>$${totalAmount.toFixed(2)}</strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div class="ai-timelog-actions">
                <button id="ai-submit-entries" class="btn btn-success">
                    <i class="fa fa-check"></i> Create Entries
                </button>
                <button id="ai-cancel-entries" class="btn btn-secondary">
                    <i class="fa fa-times"></i> Cancel
                </button>
            </div>
        `;

        resultsContainer.innerHTML = tableHTML;

        // Setup action buttons
        document.getElementById('ai-submit-entries').addEventListener('click', () => {
            this.submitEntries();
        });

        document.getElementById('ai-cancel-entries').addEventListener('click', () => {
            this.resetTimelogView();
        });

        document.getElementById('ai-back-to-input').addEventListener('click', () => {
            this.resetTimelogView();
        });
    }

    resetTimelogView() {
        const resultsContainer = document.getElementById('ai-timelog-results');
        const inputSection = document.getElementById('ai-timelog-input-section');
        
        // Show input section and hide results
        inputSection.style.display = 'flex';
        inputSection.style.flexDirection = 'column';
        inputSection.style.gap = '16px';
        resultsContainer.style.display = 'none';
        resultsContainer.innerHTML = '';
        this.currentEntries = [];
    }

    async submitEntries() {
        if (this.currentEntries.length === 0) return;

        const button = document.getElementById('ai-submit-entries');
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Creating...';
        button.disabled = true;

        try {
            // Create FormData to send the entries
            const formData = new FormData();
            formData.append('entries', JSON.stringify(this.currentEntries));
            
            const response = await fetch('/ai/submit', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            
            if (data.error) {
                alert('Error: ' + data.error);
            } else {
                alert(`Success! Created ${data.entries_created} time entries.`);
                this.resetTimelogView();
                document.getElementById('ai-timelog-input').value = '';
                
                // Optionally refresh the page to show new entries
                if (confirm('Entries created successfully! Refresh the page to see them?')) {
                    window.location.reload();
                }
            }
        } catch (error) {
            alert('Failed to create entries. Please try again.');
        } finally {
            button.innerHTML = originalText;
            button.disabled = false;
        }
    }
}

// Initialize the AI Chat when the page loads
document.addEventListener('DOMContentLoaded', () => {
    new AIChat();
});