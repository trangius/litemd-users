<style>
/* Members admin panel (Users plugin) */
.members-panel {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.members-toolbar {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.75rem 1.25rem;
    border-bottom: 1px solid var(--border);
    background: var(--bg-surface);
    font-size: 0.9rem;
    color: var(--text-muted);
}
#members-search {
    padding: 0.35rem 0.6rem;
    font-size: 0.9rem;
    border: 1px solid #ccc;
    border-radius: 4px;
    width: 220px;
}
#members-search:focus {
    outline: none;
    border-color: var(--primary-focus);
}
.members-table-wrapper {
    flex: 1;
    overflow: auto;
    background: var(--bg-surface);
}
.members-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
}
.members-table th {
    position: sticky;
    top: 0;
    background: var(--bg-raised);
    text-align: left;
    padding: 0.7rem 1rem;
    border-bottom: 2px solid var(--border);
    font-weight: 600;
    color: var(--text-heading);
    white-space: nowrap;
    user-select: none;
}
.members-table th.sortable {
    cursor: pointer;
}
.members-table th.sortable:hover {
    background: #e4e9f2;
}
.members-table th.sorted {
    color: var(--primary-text);
}
.sort-arrow {
    font-size: 0.75rem;
}
.members-table td {
    padding: 0.6rem 1rem;
    border-bottom: 1px solid var(--border-light);
    color: var(--text-heading);
}
.members-table tr:hover td {
    background: #f8f9fc;
}
.empty-row {
    text-align: center;
    color: var(--text-placeholder);
    padding: 2rem 1rem;
}
.member-setpw-btn {
    font-size: 0.78rem;
    padding: 0.2rem 0.5rem;
    border: 1px solid var(--border);
    border-radius: 3px;
    background: var(--bg-surface);
    color: var(--text-heading);
    cursor: pointer;
}
.member-setpw-btn:hover {
    background: #f0f4ff;
}
.member-delete-btn {
    font-size: 0.78rem;
    padding: 0.2rem 0.5rem;
    border: 1px solid var(--danger-border);
    border-radius: 3px;
    background: var(--bg-surface);
    color: var(--danger-text);
    cursor: pointer;
}
.member-delete-btn:hover {
    background: #fef3f2;
}

/* Boolean column icons */
.bool-yes { color: #16a34a; font-weight: 600; }
.bool-no { color: #dc2626; font-weight: 600; }

/* Dark mode overrides */
[data-theme="dark"] .members-table th { background: var(--bg-raised); }
[data-theme="dark"] .members-table th.sortable:hover { background: var(--bg-hover); }
[data-theme="dark"] .members-table tr:hover td { background: var(--bg-hover); }
[data-theme="dark"] .member-setpw-btn { background: var(--bg-surface); }
[data-theme="dark"] .member-setpw-btn:hover { background: var(--bg-hover); }
[data-theme="dark"] .member-delete-btn { background: var(--bg-surface); }
</style>

<section class="members-panel">
    <div class="members-toolbar">
        <input id="members-search" type="search" placeholder="Search by email...">
        <span id="members-count"></span>
    </div>

    <div class="members-table-wrapper">
        <table class="members-table" id="members-table">
            <thead id="members-head">
                <tr>
                    <th class="sortable" data-sort="email">Email <span class="sort-arrow"></span></th>
                    <th class="sortable" data-sort="created_at">Registered <span class="sort-arrow"></span></th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="members-body"></tbody>
        </table>
    </div>
</section>
