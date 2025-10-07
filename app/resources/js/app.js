import './bootstrap';

const escapeHtml = (value) =>
    String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

const formatJson = (value) => {
    try {
        if (typeof value === 'string') {
            return JSON.stringify(JSON.parse(value), null, 2);
        }

        return JSON.stringify(value, null, 2);
    } catch (error) {
        return typeof value === 'string' ? value : JSON.stringify(value);
    }
};

document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('console-app');

    if (!container) {
        return;
    }

    const endpoints = (() => {
        try {
            return JSON.parse(container.dataset.endpoints ?? '{}');
        } catch (error) {
            return {};
        }
    })();

    const state = {
        token: container.dataset.defaultToken ?? '',
    };

    container.innerHTML = `
        <section class="panel">
            <h2>Authentication</h2>
            <p>Provide a bearer token that can access the secured API endpoints.</p>
            <label for="api-token">API token</label>
            <input id="api-token" type="text" placeholder="sk-example-token" value="${escapeHtml(state.token)}" autocomplete="off">
            <p style="margin-top: 0.75rem; font-size: 0.9rem; color: rgba(226, 232, 240, 0.75);">
                The token is only used in your browser to authorise requests made from this page.
            </p>
        </section>
        <section class="panel">
            <h2>Platform health</h2>
            <p>Ping the public health endpoint to confirm the services are reachable.</p>
            <form id="health-form">
                <button type="submit">Run health check</button>
            </form>
            <pre id="health-output">Waiting for first request…</pre>
        </section>
        <section class="panel">
            <h2>Chat completions</h2>
            <p>Send a prompt to the chat endpoint. Requires an authenticated token.</p>
            <form id="chat-form">
                <label for="chat-message">Prompt</label>
                <textarea id="chat-message" rows="4" placeholder="Summarise the current on-call status"></textarea>
                <button type="submit">Send chat request</button>
            </form>
            <pre id="chat-output">Waiting for first request…</pre>
        </section>
        <section class="panel">
            <h2>Memory search</h2>
            <p>Search previously ingested context with an authenticated request.</p>
            <form id="memory-form">
                <label for="memory-query">Search query</label>
                <input id="memory-query" type="text" placeholder="deployment runbook" autocomplete="off">
                <button type="submit">Search memory</button>
            </form>
            <pre id="memory-output">Waiting for first request…</pre>
        </section>
        <section class="panel">
            <h2>Ingest text document</h2>
            <p>Push ad-hoc context to the ingestion pipeline.</p>
            <form id="ingest-form">
                <label for="ingest-source">Source identifier</label>
                <input id="ingest-source" type="text" placeholder="ops-handbook" autocomplete="off">
                <label for="ingest-content" style="margin-top: 1rem;">Document contents</label>
                <textarea id="ingest-content" rows="4" placeholder="Add a short note or SOP snippet"></textarea>
                <button type="submit">Ingest text</button>
            </form>
            <pre id="ingest-output">Waiting for first request…</pre>
        </section>
    `;

    const tokenInput = container.querySelector('#api-token');
    tokenInput?.addEventListener('input', (event) => {
        const target = event.target;

        if (target instanceof HTMLInputElement) {
            state.token = target.value;
        }
    });

    const setOutput = (id, value) => {
        const element = container.querySelector(id);

        if (!element) {
            return;
        }

        element.textContent = value;
    };

    const callApi = async (outputSelector, endpoint, options = {}) => {
        if (!endpoint) {
            setOutput(outputSelector, 'Endpoint not configured.');
            return;
        }

        setOutput(outputSelector, '⏳ Request in flight…');

        const headers = {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...options.headers,
        };

        const token = state.token.trim();
        if (token.length > 0) {
            headers.Authorization = `Bearer ${token}`;
        }

        const requestInit = {
            method: 'GET',
            ...options,
            headers,
        };

        try {
            const response = await fetch(endpoint, requestInit);
            const text = await response.text();
            const payload = formatJson(text);
            setOutput(outputSelector, `${response.status} ${response.statusText}\n\n${payload}`);
        } catch (error) {
            setOutput(outputSelector, `Request failed: ${error.message}`);
        }
    };

    container.querySelector('#health-form')?.addEventListener('submit', (event) => {
        event.preventDefault();
        callApi('#health-output', endpoints.health, { method: 'GET' });
    });

    container.querySelector('#chat-form')?.addEventListener('submit', (event) => {
        event.preventDefault();
        const body = {
            messages: [
                {
                    role: 'user',
                    content: container.querySelector('#chat-message')?.value ?? '',
                },
            ],
        };

        callApi('#chat-output', endpoints.chat, {
            method: 'POST',
            body: JSON.stringify(body),
        });
    });

    container.querySelector('#memory-form')?.addEventListener('submit', (event) => {
        event.preventDefault();
        const query = container.querySelector('#memory-query')?.value ?? '';
        const endpoint = endpoints.memorySearch ? `${endpoints.memorySearch}?q=${encodeURIComponent(query)}` : undefined;

        callApi('#memory-output', endpoint, { method: 'GET' });
    });

    container.querySelector('#ingest-form')?.addEventListener('submit', (event) => {
        event.preventDefault();
        const body = {
            source: container.querySelector('#ingest-source')?.value ?? '',
            document: container.querySelector('#ingest-content')?.value ?? '',
        };

        callApi('#ingest-output', endpoints.ingestText, {
            method: 'POST',
            body: JSON.stringify(body),
        });
    });
});
