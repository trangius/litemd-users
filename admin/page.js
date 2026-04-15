// Members list IIFE — loads and displays registered members in a sortable,
// searchable table with delete functionality. Dynamically renders extra
// columns added by other plugins (e.g. newsletter from MailerLite).
(function () {
    "use strict";

    var config = window.EDITOR_CONFIG || {};

    // Core columns that we always know about — everything else is "extra"
    var CORE_FIELDS = ["id", "email", "password", "created_at", "reset_token", "reset_token_expires_at"];

    var state = {
        members: [],
        extraFields: [],
        sortField: "email",
        sortDir: "asc",
        searchQuery: "",
    };

    var elements = {
        statusMessage: document.getElementById("status-message"),
        membersCount: document.getElementById("members-count"),
        membersBody: document.getElementById("members-body"),
        membersHead: document.getElementById("members-head"),
        membersTable: document.getElementById("members-table"),
        membersSearch: document.getElementById("members-search"),
    };

    // Shared utilities from editor-utils.js
    var escapeHtml = EditorUtils.escapeHtml;
    var apiGet = EditorUtils.apiGet;
    var apiPost = EditorUtils.apiPost;
    var handleError = EditorUtils.handleError;

    // ----------------------------------------------------------------------------
    // Detect extra columns from the first member row (fields not in CORE_FIELDS).
    // ----------------------------------------------------------------------------
    function detectExtraFields(members) {
        if (members.length === 0) return [];
        var extra = [];
        var first = members[0];
        for (var key in first) {
            if (first.hasOwnProperty(key) && CORE_FIELDS.indexOf(key) === -1) {
                extra.push(key);
            }
        }
        return extra;
    }

    // ----------------------------------------------------------------------------
    // Check if a field looks like a boolean (all values are 0, 1, or null).
    // ----------------------------------------------------------------------------
    function isBoolField(members, field) {
        for (var i = 0; i < members.length; i++) {
            var val = members[i][field];
            if (val !== 0 && val !== 1 && val !== "0" && val !== "1" && val !== null) {
                return false;
            }
        }
        return true;
    }

    // ----------------------------------------------------------------------------
    // Render a cell value — booleans get check/cross icons, others show as text.
    // ----------------------------------------------------------------------------
    function renderCell(value, isBool) {
        if (isBool) {
            if (value === 1 || value === "1") {
                return '<span class="bool-yes">\u2713</span>';
            }
            return '<span class="bool-no">\u2717</span>';
        }
        return escapeHtml(String(value ?? ""));
    }

    // ----------------------------------------------------------------------------
    // Turn a field name into a display label (e.g. "newsletter" → "Newsletter").
    // ----------------------------------------------------------------------------
    function fieldLabel(field) {
        return field.charAt(0).toUpperCase() + field.slice(1).replace(/_/g, " ");
    }

    // ----------------------------------------------------------------------------
    // Build the table header row with core + extra columns.
    // ----------------------------------------------------------------------------
    function renderHeader() {
        var html = '<tr>';
        html += '<th class="sortable" data-sort="email">Email <span class="sort-arrow"></span></th>';

        // Extra columns between Email and Registered
        for (var i = 0; i < state.extraFields.length; i++) {
            var field = state.extraFields[i];
            html += '<th class="sortable" data-sort="' + escapeHtml(field) + '">' + escapeHtml(fieldLabel(field)) + ' <span class="sort-arrow"></span></th>';
        }

        html += '<th class="sortable" data-sort="created_at">Registered <span class="sort-arrow"></span></th>';
        html += '<th></th>';
        html += '</tr>';
        elements.membersHead.innerHTML = html;

        // Re-bind sort click handlers on the new header
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
    }

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
            var aVal = a[field];
            var bVal = b[field];

            // Null-safe comparison
            if (aVal == null) aVal = "";
            if (bVal == null) bVal = "";

            // Numeric comparison for numbers and booleans
            if (typeof aVal === "number" && typeof bVal === "number") {
                return (aVal - bVal) * dir;
            }

            aVal = String(aVal);
            bVal = String(bVal);

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
        var totalCols = 3 + state.extraFields.length;

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

        // Detect which extra fields are boolean
        var boolFields = {};
        for (var f = 0; f < state.extraFields.length; f++) {
            boolFields[state.extraFields[f]] = isBoolField(state.members, state.extraFields[f]);
        }

        // Build table rows
        var html = "";
        for (var i = 0; i < visible.length; i++) {
            var m = visible[i];
            var date = m.created_at ? m.created_at.replace("T", " ").replace(/\.\d+$/, "") : "";

            html += "<tr>";
            html += "<td>" + escapeHtml(m.email) + "</td>";

            // Extra columns
            for (var j = 0; j < state.extraFields.length; j++) {
                var key = state.extraFields[j];
                html += "<td>" + renderCell(m[key], boolFields[key]) + "</td>";
            }

            html += "<td>" + escapeHtml(date) + "</td>";
            html += '<td>'
                + '<button type="button" class="member-setpw-btn" data-member-id="' + m.id + '" data-member-email="' + escapeHtml(m.email) + '">Set Password</button> '
                + '<button type="button" class="member-delete-btn" data-member-id="' + m.id + '" data-member-email="' + escapeHtml(m.email) + '">Delete</button>'
                + '</td>';
            html += "</tr>";
        }

        elements.membersBody.innerHTML = html || '<tr><td colspan="' + totalCols + '" class="empty-row">No members found.</td></tr>';
    }

    // ----------------------------------------------------------------------------
    // Load the members list from the API, detect extra fields, and render.
    // ----------------------------------------------------------------------------
    async function loadMembers() {
        var response = await apiGet({ action: "members-list" });
        state.members = Array.isArray(response.members) ? response.members : [];
        state.extraFields = detectExtraFields(state.members);
        renderHeader();
        renderTable();
    }

    // ----------------------------------------------------------------------------
    // Bind event handlers for searching and deleting.
    // ----------------------------------------------------------------------------
    function bindEvents() {
        // Search input
        elements.membersSearch.addEventListener("input", function () {
            state.searchQuery = elements.membersSearch.value.trim().toLowerCase();
            renderTable();
        });

        // Set password (delegated to tbody)
        elements.membersBody.addEventListener("click", function (e) {
            var btn = e.target.closest(".member-setpw-btn");
            if (!btn) return;

            var id = parseInt(btn.dataset.memberId, 10);
            var email = btn.dataset.memberEmail || "";
            var password = window.prompt("New password for \"" + email + "\" (min 8 characters):");

            if (!password) return;
            if (password.length < 8) {
                alert("Password must be at least 8 characters.");
                return;
            }

            apiPost("member-set-password", { id: id, password: password, csrf: config.csrfToken || "" }).then(function () {
                alert("Password updated for " + email + ".");
            }).catch(function (err) {
                handleError(err);
            });
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
