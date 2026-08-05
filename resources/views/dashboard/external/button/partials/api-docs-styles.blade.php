<style>
    .api-docs {
        --api-bg: #0f172a;
        --api-panel: #111827;
        --api-border: #1f2937;
        --api-muted: #94a3b8;
        --api-text: #e2e8f0;
        --api-accent: #38bdf8;
        --api-post: #10b981;
        --api-get: #3b82f6;
        --api-put: #f59e0b;
        --api-delete: #ef4444;
        display: grid;
        grid-template-columns: 280px minmax(0, 1fr);
        gap: 1.5rem;
        align-items: start;
    }

    .api-docs__sidebar {
        position: sticky;
        top: 1rem;
        max-height: calc(100vh - 2rem);
        overflow: auto;
        background: var(--api-panel);
        color: var(--api-text);
        border: 1px solid var(--api-border);
        border-radius: 12px;
        padding: 1.25rem;
    }

    .api-docs__sidebar h6 {
        color: #fff;
        font-size: 0.75rem;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        margin-bottom: 0.75rem;
    }

    .api-docs__base {
        font-size: 0.8rem;
        color: var(--api-accent);
        word-break: break-all;
        margin-bottom: 1.25rem;
    }

    .api-docs__group {
        margin-bottom: 1rem;
    }

    .api-docs__group-title {
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--api-muted);
        margin-bottom: 0.4rem;
    }

    .api-docs__nav a {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: var(--api-text);
        text-decoration: none;
        font-size: 0.875rem;
        padding: 0.4rem 0.5rem;
        border-radius: 8px;
        margin-bottom: 0.15rem;
    }

    .api-docs__nav a:hover,
    .api-docs__nav a.active {
        background: rgba(56, 189, 248, 0.12);
        color: #fff;
    }

    .api-docs__content {
        min-width: 0;
    }

    .api-docs__intro {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 1.25rem 1.5rem;
        margin-bottom: 1.25rem;
    }

    .api-docs__keys code {
        display: inline-block;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        padding: 0.2rem 0.5rem;
        font-size: 0.85rem;
    }

    .api-op {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        margin-bottom: 1.25rem;
        overflow: hidden;
        scroll-margin-top: 1rem;
    }

    .api-op__header {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.75rem;
        padding: 1rem 1.25rem;
        border-bottom: 1px solid #eef2f7;
        background: #f8fafc;
    }

    .api-op__title {
        font-weight: 600;
        margin: 0;
        font-size: 1rem;
    }

    .api-op__path {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 0.85rem;
        color: #475569;
        word-break: break-all;
    }

    .api-method {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 3.5rem;
        padding: 0.2rem 0.55rem;
        border-radius: 999px;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        color: #fff;
        text-transform: uppercase;
    }

    .api-method--post { background: var(--api-post); }
    .api-method--get { background: var(--api-get); }
    .api-method--put { background: var(--api-put); }
    .api-method--delete { background: var(--api-delete); }
    .api-method--js { background: #6366f1; }

    .api-op__body {
        padding: 1.25rem;
    }

    .api-op__body h3 {
        font-size: 0.95rem;
        font-weight: 600;
        margin: 1.25rem 0 0.65rem;
    }

    .api-op__body h3:first-child {
        margin-top: 0;
    }

    .api-code {
        background: var(--api-bg);
        color: #e2e8f0;
        border-radius: 10px;
        padding: 1rem 1.1rem;
        overflow-x: auto;
        font-size: 0.82rem;
        line-height: 1.55;
        margin: 0;
    }

    .api-code code {
        color: inherit;
        white-space: pre;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    }

    .api-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.875rem;
    }

    .api-table th,
    .api-table td {
        border: 1px solid #e5e7eb;
        padding: 0.65rem 0.75rem;
        vertical-align: top;
    }

    .api-table th {
        background: #f8fafc;
        font-weight: 600;
        white-space: nowrap;
    }

    .api-table code {
        color: #0f172a;
        font-weight: 600;
    }

    @media (max-width: 991.98px) {
        .api-docs {
            grid-template-columns: 1fr;
        }

        .api-docs__sidebar {
            position: relative;
            top: 0;
            max-height: none;
        }
    }
</style>
