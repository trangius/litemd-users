// Members list IIFE — loads and displays registered members in a sortable,
// searchable table with delete functionality.
(function () {
    "use strict";

    var config = window.EDITOR_CONFIG || {};

    var state = {
        members: [],
        sortField: "email",
        sortDir: "asc",
        searchQuery: "",
    };

    var elements = {
        statusMessage: document.getElementById("status-message"),
        membersCount: document.getElementById("members-count"),
        membersBody: document.getElementById("members-body"),
        membersTable: document.getElementById("members-table"),
        membersSearch: document.getElementById("members-search"),
    };

    // Shared utilities from editor-utils.js
    var escapeHtml = EditorUtils.escapeHtml;
    var apiGet = EditorUtils.apiGet;
    var apiPost = EditorUtils.apiPost;
    var handleError = EditorUtils.handleError;

    // ----------------------------------------------------------------------------
    // Filter members by the current search query against email addresses.
    // ----------------------------------------------------------------------------
    function filteredMembers() {
        var query = state.searchQuery;
        if (query === "") return state.members;
        return state.members.filter(function (m) {
            return m.email.toLowerCase().indexOf(query) !== -1;
        });
    }

    // ----------------------------------------------------------------------------
    // Sort the members array by the current sort field and direction.
    // ----------------------------------------------------------------------------
    function sortMembers(members) {
        var field = state.sortField;
        var dir = state.sortDir === "asc" ? 1 : -1;

        members.sort(function (a, b) {
            var aVal = a[field] || "";
            var bVal = b[field] || "";

            if (field === "created_at") {
                return (aVal < bVal ? -1 : aVal > bVal ? 1 : 0) * dir;
            }

            return aVal.localeCompare(bVal, undefined, { sensitivity: "base" }) * dir;
        });

        return members;
    }

    // ----------------------------------------------------------------------------
    // Render the members table body and update the count display.
    // ----------------------------------------------------------------------------
    function renderTable() {
        var visible = sortMembers(filteredMembers());

        // Show count with search context
        var total = state.members.length;
        var shown = visible.length;
        if (state.searchQuery !== "" && shown !== total) {
            elements.membersCount.textContent = shown + " of " + total + " members";
        } else {
            elements.membersCount.textContent = total + " member" + (total !== 1 ? "s" : "");
        }

        // Update sort arrow indicators in the table header
        elements.membersTable.querySelectorAll("th.sortable").forEach(function (th) {
            var arrow = th.querySelector(".sort-arrow");
            if (th.getAttribute("data-sort") === state.sortField) {
                arrow.textContent = state.sortDir === "asc" ? " \u25B2" : " \u25BC";
                th.classList.add("sorted");
            } else {
                arrow.textContent = "";
                th.classList.remove("sorted");
            }
        });

        // Build table rows
        var html = "";
        for (var i = 0; i < visible.length; i++) {
            var m = visible[i];
            var date = m.created_at ? m.created_at.replace("T", " ").replace(/\.\d+$/, "") : "";

            html += "<tr>";
            html += "<td>" + escapeHtml(m.email) + "</td>";
            html += "<td>" + escapeHtml(date) + "</td>";
            html += '<td><button type="button" class="member-delete-btn" data-member-id="' + m.id + '" data-member-email="' + escapeHtml(m.email) + '">Delete</button></td>';
            html += "</tr>";
        }

        elements.membersBody.innerHTML = html || '<tr><td colspan="3" class="empty-row">No members found.</td></tr>';
    }

    // ----------------------------------------------------------------------------
    // Load the members list from the API and render the table.
    // ----------------------------------------------------------------------------
    async function loadMembers() {
        var response = await apiGet({ action: "members-list" });
        state.members = Array.isArray(response.members) ? response.members : [];
        renderTable();
    }

    // ----------------------------------------------------------------------------
    // Bind event handlers for sorting, searching, and deleting.
    // ----------------------------------------------------------------------------
    function bindEvents() {
        // Column header sort
        elements.membersTable.querySelectorAll("th.sortable").forEach(function (th) {
            th.addEventListener("click", function () {
                var field = th.getAttribute("data-sort");
                if (state.sortField === field) {
                    state.sortDir = state.sortDir === "asc" ? "desc" : "asc";
                } else {
                    state.sortField = field;
                    state.sortDir = "asc";
                }
                renderTable();
            });
        });

        // Search input
        elements.membersSearch.addEventListener("input", function () {
            state.searchQuery = elements.membersSearch.value.trim().toLowerCase();
            renderTable();
        });

        // Delete member (delegated to tbody)
        elements.membersBody.addEventListener("click", function (e) {
            var btn = e.target.closest(".member-delete-btn");
            if (!btn) return;

            var id = parseInt(btn.dataset.memberId, 10);
            var email = btn.dataset.memberEmail || "";

            if (!window.confirm("Delete member \"" + email + "\"? This cannot be undone.")) return;

            apiPost("member-delete", { id: id, csrf: config.csrfToken || "" }).then(function () {
                state.members = state.members.filter(function (m) { return m.id !== id; });
                renderTable();
            }).catch(function (err) {
                handleError(err);
            });
        });
    }

    // ----------------------------------------------------------------------------
    // Initialise: bind events and load the members list.
    // ----------------------------------------------------------------------------
    async function init() {
        bindEvents();
        try {
            await loadMembers();
        } catch (error) {
            handleError(error);
        }
    }

    init();
})();
