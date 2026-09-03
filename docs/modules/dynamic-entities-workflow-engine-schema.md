# Dynamic Entities Workflow Engine — Definition Schema (v2)

NetSuite-style **states + transitions + actors + preconditions** for all Dynamic Entity record types.

**Status:** Specification (not implemented).  
**Replaces (eventually):** Phase-0 “Workflow Buttons” (`WHEN → THEN` only, one button per `dyn_workflows` row).  
**Coexists with:** E-Approval for form-based document approvals (optional link, not duplicate engine).

---

## 1. Design goals

| Goal | Approach |
|------|----------|
| Many entities, one engine | One definition schema; per-entity workflow profiles |
| Supervisor routing | Actor types: `requestor.reports_to`, department, role |
| Block bad submits | Preconditions on transitions (e.g. require Reports to) |
| ERP actions | Submit, Approve, Return, Resubmit, Cancel, Void, Convert |
| Notifications | Per-transition notify lists (email + in-app tasks) |
| Audit | Instance + history rows (who, when, comment, from/to state) |
| Backward compatible | v1 button defs importable as single-step transitions |

---

## 2. Storage model

### 2.1 Definition (admin-configured)

Keep **`dyn_workflows`** as the definition container, evolve `definition_json` from v1 button blob to v2 document:

| Column | v2 usage |
|--------|----------|
| `entity_slug` | Record type this workflow applies to |
| `slug` | Stable workflow id (e.g. `procurement_requests_approval`) |
| `name` | Admin label |
| `trigger_mode` | `manual` \| `on_create` \| `on_update` — **auto transitions only**; user actions always via transitions |
| `is_active` | Enable/disable |
| `sort_order` | When multiple defs match, highest priority wins (or merge rules TBD) |
| `definition_json` | **Full v2 schema below** (`schema_version: 2`) |

**Rule:** One **active** workflow definition per entity slug (v2). v1 allowed many rows per entity (one button each); migration merges into one v2 def.

### 2.2 Runtime (per record)

New tenant tables (implementation):

```text
dyn_workflow_instances
  id, record_id, entity_slug, workflow_slug, current_state_id,
  status (active|completed|cancelled|voided),
  started_by, started_at, completed_at,
  context_json (snapshot: department, amounts, requestor_id)

dyn_workflow_tasks
  id, instance_id, transition_id, step_key,
  assignee_user_id, assignee_role_slug (nullable),
  status (pending|completed|skipped|cancelled),
  due_at, completed_at, completed_by

dyn_workflow_history
  id, instance_id, transition_id, from_state_id, to_state_id,
  actor_user_id, action, comment,
  metadata_json, created_at
```

Record **`status`** field stays the operational label synced from `current_state_id.label` (backward compatible with lists/filters).

---

## 3. Top-level definition document

```json
{
  "schema_version": 2,
  "kind": "state_machine",
  "label": "Procurement Request Approval",
  "description": "Submit → supervisor → CFO → CEO → approved",
  "status_field": "status",
  "initial_state": "draft",
  "terminal_states": ["approved", "cancelled", "voided", "converted"],
  "settings": {
    "require_comment_on": ["return", "reject", "void"],
    "allow_self_approval": false,
    "sync_record_status": true,
    "entra_manager_fallback": true,
    "strict_actor_resolution": true
  },
  "states": [],
  "transitions": [],
  "notifications": {},
  "actions_library": {}
}
```

| Field | Type | Description |
|-------|------|-------------|
| `schema_version` | `2` | Required for v2 parser |
| `kind` | `state_machine` | Future: `linear`, `parallel` |
| `status_field` | string | Record field synced with state (default `status`) |
| `initial_state` | string | State id when record created (or first enter workflow) |
| `terminal_states` | string[] | No outgoing user transitions (except admin override) |
| `settings.strict_actor_resolution` | bool | If true, transition fails when actor unresolved (no skip) |
| `settings.entra_manager_fallback` | bool | Use Entra manager when `manager_id` blank |

---

## 4. States

States are **stable ids**; labels are display strings (can match record status values).

```json
{
  "id": "pending_cfo",
  "label": "Pending CFO",
  "category": "pending_approval",
  "color": "warning",
  "is_terminal": false,
  "entry_actions": [],
  "metadata": {
    "list_filter": true,
    "show_in_trail": true
  }
}
```

### 4.1 State categories (UI + reporting)

| `category` | Meaning |
|------------|---------|
| `draft` | Editable by requestor |
| `pending_approval` | Waiting on assignee task |
| `returned` | Sent back to requestor |
| `approved` | Approved terminal (may still Convert) |
| `cancelled` | Cancelled terminal |
| `voided` | Voided terminal (accounting reversal semantics) |
| `converted` | Downstream doc created |
| `system` | Auto/hidden states |

### 4.2 Recommended state sets

**Approval-heavy (PR, capex):**

`draft` → `pending_*` (chain) → `approved` | `returned` | `cancelled` | `voided` → optional `converted`

**Simple operational (BIR, tickets):**

`draft` → `posted` | `cancelled`

---

## 5. Transitions

A transition is a **named edge** from one or more source states to a target state, with guards, actors, and side effects.

```json
{
  "id": "submit",
  "label": "Submit for approval",
  "kind": "submit",
  "from": ["draft"],
  "to": "pending_supervisor",
  "variant": "default",
  "confirm": null,
  "visible_to": {
    "actor_types": ["requestor", "role"],
    "role_slugs": ["staff", "project_manager"],
    "permission": "dynamic_entities:records:manage"
  },
  "preconditions": [],
  "assignment": {
    "mode": "create_tasks",
    "steps": []
  },
  "actions": {
    "field_updates": [],
    "creates": [],
    "emails": [],
    "notify": []
  },
  "comment": {
    "required": false,
    "prompt": null
  }
}
```

### 5.1 Transition kinds (standard ERP verbs)

| `kind` | Typical `from` → `to` | Actor |
|--------|------------------------|-------|
| `submit` | draft → first pending | Requestor |
| `approve` | pending_* → next pending or approved | Current task assignee |
| `return` | pending_* → returned | Current assignee |
| `resubmit` | returned → first pending | Requestor |
| `reject` | pending_* → cancelled | Current assignee (alias of return+cancel policy) |
| `cancel` | draft \| returned → cancelled | Requestor |
| `void` | approved (pre-convert) → voided | Admin / finance role |
| `convert` | approved → converted | Buyer role |
| `custom` | any | Configured |

Engine maps `kind` to default UX (button order, destructive styling, comment rules). Admins can override via `comment.required` and `variant`.

### 5.2 Multi-source `from`

`from` is a string array — one transition definition covers e.g. `["pending_supervisor", "pending_cfo"]` if ever needed, or keep one edge per step for clarity.

---

## 6. Actor types (`assignment.steps`)

When a transition enters a **pending** state, the engine creates **tasks** for resolved actors.

```json
{
  "assignment": {
    "mode": "create_tasks",
    "steps": [
      {
        "key": "supervisor",
        "actor": {
          "type": "requestor.reports_to",
          "source": "manager_id",
          "fallback": ["entra_manager", "record_field:supervisor_id"]
        },
        "parallel_mode": "any",
        "quorum": 1
      }
    ]
  }
}
```

For sequential approval chains, use **multiple states** (each with one step) rather than multiple steps in one transition — simpler task model.

### 6.1 Actor type reference

| `type` | Resolves to | Notes |
|--------|-------------|-------|
| `requestor` | User who started workflow / `created_by` | For resubmit visibility |
| `requestor.reports_to` | `users.manager_id` → active user | **Primary supervisor** |
| `requestor.reports_to.entra` | Entra Graph manager → tenant user | Fallback when `manager_id` null |
| `requestor.department` | N/A (context) | Used with `department.*` types |
| `department.head` | User with role + matching department | Requires dept head map |
| `department.role` | User(s) where `department` = X and has role Y | See below |
| `record.field` | User id/email from record field | e.g. `assigned_user_id` |
| `record.department` | Value of record department field | Context for matching |
| `role` | First active user in Spatie role | `role_slug: finance` |
| `role.all` | All users in role (parallel tasks) | CFO + delegate |
| `user` | Fixed user uuid | CEO user |
| `user_list` | Expand field (comma users) | Committee approvals |
| `previous_actor` | Who completed last task | Escalation patterns |

#### `department.role` example

Route to department head by matching **requestor’s or record’s department**:

```json
{
  "type": "department.role",
  "department_from": "requestor",
  "role_slug": "department_head",
  "match_mode": "exact"
}
```

`department_from`: `requestor` | `record` | `field:department`

#### `requestor.reports_to` sources

| `source` | Resolution order |
|----------|------------------|
| `manager_id` | `TenantUser.manager_id` → active manager |
| `entra_manager` | `entra_manager_email` → lookup user; or Graph |
| `either` | manager_id, then entra (recommended default) |

---

## 7. Preconditions

Evaluated **before** transition runs. Failure → `422` with structured errors (block submit).

```json
{
  "preconditions": [
    {
      "type": "requestor_has_manager",
      "required": true,
      "source": "either",
      "message": "Cannot submit: your user profile has no Reports to. Contact your administrator."
    },
    {
      "type": "requestor_has_department",
      "required": false
    },
    {
      "type": "record_field_required",
      "field": "department",
      "message": "Department is required before submit."
    },
    {
      "type": "record_field",
      "field": "estimated_total",
      "op": "gt",
      "value": "0"
    },
    {
      "type": "state",
      "op": "in",
      "states": ["draft"]
    },
    {
      "type": "permission",
      "permission": "dynamic_entities:records:manage"
    },
    {
      "type": "no_open_tasks",
      "message": "Complete pending approvals first."
    },
    {
      "type": "downstream_absent",
      "entity_slug": "purchase_transactions",
      "link_field": "source_pr_id",
      "message": "Cannot void: a PO already exists."
    }
  ]
}
```

### 7.1 Precondition types

| Type | Purpose |
|------|---------|
| `requestor_has_manager` | Block if Reports to blank (dynamic per workflow) |
| `requestor_has_department` | Block if user.department empty |
| `record_field_required` | Field non-empty |
| `record_field` | Compare field (eq, neq, gt, gte, lt, lte, in) |
| `state` | Current state guard |
| `permission` | RBAC |
| `role` | Actor has role |
| `no_open_tasks` | No pending tasks on instance |
| `downstream_absent` | No linked converted record (void guard) |
| `expression` | Future: safe formula |

**Dynamic requirement:** `requestor_has_manager.required` is **per workflow**, not global — BIR Post does not require manager; PR Submit does.

---

## 8. Side effects (`actions`)

Reuse and extend Phase-0 action model from `DynRecordWorkflowActionService`.

### 8.1 Field updates

```json
{
  "field_updates": [
    {
      "target": "this",
      "field": "status",
      "mode": "set",
      "value": "Pending CFO"
    },
    {
      "target": "this",
      "field": "approved_at",
      "mode": "system",
      "value": "now"
    },
    {
      "target": "this",
      "field": "supervisor_id",
      "mode": "copy",
      "from": "actor.user_id"
    }
  ]
}
```

| `mode` | v2 behavior |
|--------|-------------|
| `set` | Literal value |
| `copy` | From `this.field`, `actor.*`, `context.*` |
| `system` | `now`, `today`, `actor.user_id`, `actor.email` |
| `ask` | **Prompt user** (comment modal); stored in history |
| `expr` | Future |

### 8.2 Create related record (Convert)

```json
{
  "creates": [
    {
      "entity_slug": "purchase_transactions",
      "link": {
        "field": "source_pr_id",
        "mode": "this.id"
      },
      "mappings": [
        { "field": "title", "mode": "copy", "from": "this.title" },
        { "field": "vendor_id", "mode": "copy", "from": "this.vendor_id" }
      ],
      "on_failure": "block"
    }
  ]
}
```

### 8.3 Email & notify

```json
{
  "emails": [
    {
      "template_slug": "pr_submitted_to_supervisor",
      "to": "{actor.email}",
      "cc": "{requestor.email}"
    }
  ],
  "notify": [
    { "type": "in_app", "recipients": "task.assignee" },
    { "type": "in_app", "recipients": "requestor" },
    { "type": "email", "recipients": "role:finance", "template_slug": "pr_pending_cfo" }
  ]
}
```

#### Recipient tokens

| Token | Resolves to |
|-------|-------------|
| `requestor` | Workflow starter / record created_by |
| `requestor.email` | Requestor mailbox |
| `task.assignee` | Current pending task user |
| `actor` | User who fired transition |
| `role:{slug}` | All active users in role |
| `reports_to` | Requestor's manager |
| `field:{name}` | Email or user id on record |

---

## 9. Runtime flow

```text
1. Load active workflow def for entity_slug (schema_version 2)
2. Load or create instance for record_id
3. User invokes transition T
4. Evaluate preconditions → fail fast with messages[]
5. Verify actor may fire T (visible_to + pending task assignee)
6. Require comment if configured
7. Apply field_updates, creates
8. Move instance to T.to state; complete/open tasks
9. Create new tasks for next pending state's assignment
10. Write history row; send notifications
11. Sync record.status from state.label
```

### 9.1 Approve (sequential)

States:

`draft` → `pending_supervisor` → `pending_cfo` → `pending_ceo` → `approved`

Transitions:

| id | kind | from | to | assignment |
|----|------|------|-----|------------|
| submit | submit | draft | pending_supervisor | reports_to |
| approve_supervisor | approve | pending_supervisor | pending_cfo | role:finance |
| approve_cfo | approve | pending_cfo | pending_ceo | role:administrator |
| approve_ceo | approve | pending_ceo | approved | (none) |
| return_* | return | pending_* | returned | — |
| resubmit | resubmit | returned | pending_supervisor | reports_to |
| cancel | cancel | draft, returned | cancelled | — |
| void | void | approved | voided | role:administrator |
| convert_po | convert | approved | converted | role + create PO |

---

## 10. API sketch

```text
GET  /dynamic-entities/workflows/{slug}              # v2 definition
PUT  /dynamic-entities/workflows/{slug}              # admin save

GET  /dynamic-entities/records/{id}/workflow         # instance + available transitions
POST /dynamic-entities/records/{id}/workflow/{transition_id}
     Body: { "comment": "...", "ask_values": {} }

GET  /dynamic-entities/workflow-tasks?scope=awaiting_me
POST /dynamic-entities/workflow-tasks/{id}/decide    # optional alias for transition
```

Response `available_transitions[]` includes precondition failures for disabled buttons (ERP UX: show grayed + tooltip).

---

## 11. UI contract (NetSuite-like, TowerOS chrome)

| Surface | Data |
|---------|------|
| Record header | `state.label`, document no., requestor |
| Action toolbar | `available_transitions` (kind → button style) |
| Waiting on | `tasks.where(pending).assignee` |
| Workflow trail | `history` ordered |
| My pending | `workflow-tasks?scope=awaiting_me` |

---

## 12. Example: `procurement_requests` (full minimal def)

```json
{
  "schema_version": 2,
  "kind": "state_machine",
  "label": "PR Approval",
  "status_field": "status",
  "initial_state": "draft",
  "terminal_states": ["approved", "cancelled", "voided", "converted"],
  "settings": {
    "require_comment_on": ["return"],
    "strict_actor_resolution": true,
    "entra_manager_fallback": true,
    "sync_record_status": true
  },
  "states": [
    { "id": "draft", "label": "Draft", "category": "draft" },
    { "id": "pending_supervisor", "label": "Pending Supervisor", "category": "pending_approval" },
    { "id": "pending_cfo", "label": "Pending CFO", "category": "pending_approval" },
    { "id": "pending_ceo", "label": "Pending CEO", "category": "pending_approval" },
    { "id": "returned", "label": "Returned", "category": "returned" },
    { "id": "approved", "label": "Approved", "category": "approved" },
    { "id": "cancelled", "label": "Cancelled", "category": "cancelled" },
    { "id": "voided", "label": "Voided", "category": "voided" },
    { "id": "converted", "label": "Converted to PO", "category": "converted" }
  ],
  "transitions": [
    {
      "id": "submit",
      "label": "Submit",
      "kind": "submit",
      "from": ["draft"],
      "to": "pending_supervisor",
      "preconditions": [
        {
          "type": "requestor_has_manager",
          "required": true,
          "source": "either",
          "message": "Cannot submit: your profile has no Reports to. Ask an administrator to update Users."
        },
        { "type": "record_field_required", "field": "department" }
      ],
      "assignment": {
        "mode": "create_tasks",
        "steps": [{ "key": "supervisor", "actor": { "type": "requestor.reports_to", "source": "either" } }]
      },
      "actions": {
        "notify": [
          { "type": "in_app", "recipients": "task.assignee" },
          { "type": "email", "recipients": "task.assignee", "template_slug": "pr_submitted" }
        ]
      }
    },
    {
      "id": "approve_supervisor",
      "label": "Approve",
      "kind": "approve",
      "from": ["pending_supervisor"],
      "to": "pending_cfo",
      "assignment": {
        "mode": "create_tasks",
        "steps": [{ "key": "cfo", "actor": { "type": "role", "role_slug": "finance" } }]
      }
    },
    {
      "id": "approve_cfo",
      "label": "Approve",
      "kind": "approve",
      "from": ["pending_cfo"],
      "to": "pending_ceo",
      "assignment": {
        "mode": "create_tasks",
        "steps": [{ "key": "ceo", "actor": { "type": "role", "role_slug": "administrator" } }]
      }
    },
    {
      "id": "approve_ceo",
      "label": "Approve",
      "kind": "approve",
      "from": ["pending_ceo"],
      "to": "approved"
    },
    {
      "id": "return",
      "label": "Return to requestor",
      "kind": "return",
      "from": ["pending_supervisor", "pending_cfo", "pending_ceo"],
      "to": "returned",
      "comment": { "required": true, "prompt": "Reason for return" },
      "actions": {
        "notify": [{ "type": "in_app", "recipients": "requestor" }]
      }
    },
    {
      "id": "resubmit",
      "label": "Resubmit",
      "kind": "resubmit",
      "from": ["returned"],
      "to": "pending_supervisor",
      "preconditions": [{ "type": "requestor_has_manager", "required": true, "source": "either" }],
      "assignment": {
        "mode": "create_tasks",
        "steps": [{ "key": "supervisor", "actor": { "type": "requestor.reports_to", "source": "either" } }]
      }
    },
    {
      "id": "cancel",
      "label": "Cancel",
      "kind": "cancel",
      "from": ["draft", "returned"],
      "to": "cancelled",
      "variant": "destructive"
    },
    {
      "id": "void",
      "label": "Void",
      "kind": "void",
      "from": ["approved"],
      "to": "voided",
      "preconditions": [{ "type": "downstream_absent", "entity_slug": "purchase_transactions", "link_field": "source_pr_id" }],
      "visible_to": { "role_slugs": ["administrator", "finance"] }
    },
    {
      "id": "convert_po",
      "label": "Convert to PO",
      "kind": "convert",
      "from": ["approved"],
      "to": "converted",
      "actions": {
        "creates": [{
          "entity_slug": "purchase_transactions",
          "link": { "field": "source_pr_id", "mode": "this.id" },
          "mappings": [
            { "field": "title", "mode": "copy", "from": "this.title" }
          ],
          "on_failure": "block"
        }]
      }
    }
  ]
}
```

---

## 13. Migration from v1 (Workflow Buttons)

| v1 (`dyn_workflows` row) | v2 |
|--------------------------|-----|
| One row = one button | One row = full state machine |
| `trigger_mode: manual` | Transitions with `visible_to` |
| `trigger_mode: on_create` | Transition with `from: [initial]` auto-fired on create |
| `when` + `then_updates` | Transition preconditions + `field_updates` |
| `role_ids` on row | `visible_to.role_slugs` + task actors |
| `emails` / `creates` | `actions.emails` / `actions.creates` |

Importer:

1. Group rows by `entity_slug`
2. Collect unique statuses from `from_status` / `to_status`
3. Emit states + one transition per v1 row
4. Set `schema_version: 2`

---

## 14. Validation rules (admin save)

- Every `transition.to` must reference a defined `state.id`
- Every `transition.from` state must exist
- `initial_state` must exist
- Terminal states must have no outgoing `approve/submit` unless admin override flag set
- At least one path from `initial_state` to a terminal
- `requestor_has_manager` preconditions only valid on `submit` / `resubmit` kinds
- No duplicate `transition.id`

---

## 15. Related code (Phase 0 baseline)

| Area | Path |
|------|------|
| v1 definition normalize | `DynWorkflowService::normalizeDefinition` |
| v1 runtime | `DynRecordWorkflowActionService` |
| v1 action merge | `DynEntityWorkflowActions` |
| User Reports to | `users.manager_id`, `TenantUser::manager()` |
| Entra fallback | `EApprovalManagerApproverResolver` (reuse logic) |
| Org chart | `EntraOrgDirectoryService`, `frontend/lib/admin/org-chart.ts` |

---

## 16. Implementation phases

1. **Schema parser + instance/history tables + submit/approve/return with preconditions**
2. **Actor resolver** (`reports_to`, `role`, `department.role`)
3. **My pending inbox + record workflow UI**
4. **Convert, void guards, notify templates**
5. **v1 importer + admin state/transition designer**
