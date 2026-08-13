# E-Approval — End-user guide

A step-by-step guide for everyday users of TowerOS **E-Approval**: how to sign in, submit a request, track it, approve or reject it, and fix a request that was sent back.

This guide is for **normal users** (requestors and approvers). It does **not** cover form building, workflows setup, or admin settings.

---

## Who should read what?

| I am a… | Read these parts |
|---------|------------------|
| **Requestor** — I fill forms and send requests | Parts 1–6, then Parts 8–9 if needed |
| **Approver** — I decide on requests | Parts 1–2, then Parts 5–7 |
| **External / vendor** (no TowerOS login) | Part 10 |
| **Both** | Read the whole guide once |

---

## What is E-Approval?

E-Approval is where your company files digital requests (forms), sends them through an approval chain, and keeps a record of decisions.

Typical uses include:

- Leave / HR requests  
- Purchase or payment requests  
- Document / ISO control forms  
- Site or operations forms your admin published  

You always work inside your **company workspace** in TowerOS (except public/external forms — see Part 10).

---

## Before you start

You need:

1. Your **TowerOS account** (email + password, Microsoft sign-in, or **passkey** if your company enabled it)  
2. The **website address** for your company (ask your admin if unsure)  
3. Permission to use **E-Approval** (your admin assigns this)

**Login page examples**

- `https://your-company.example.com/login`  
- Local demo: `http://atc.localhost/login`

Always use your **company / tenant** login page — not the platform admin console.

---

## Part 1 — Sign in

1. Open your company login page in the browser.  
2. Sign in with one of these (what you see depends on your company):
   - **Email and password** → enter them → click **Sign in**  
   - **Sign in with Microsoft** → complete the Microsoft screens  
   - **Sign in with passkey** → use your device fingerprint, face, or security key (if offered)  
3. If you see a **security code (MFA)** screen, open your authenticator app and enter the 6-digit code.  
4. You should land on the **Dashboard**.

**Tip:** On the left sidebar, look for **E-Approval** (under **Operations**). That is the module you will use.

**Note:** Passkeys and MFA are for **signing in** only. Approving a request still uses your **electronic signature** (Part 2 and Part 7) — not a passkey.

---

## Part 2 — Find E-Approval and set your profile

### Open the module

1. In the left sidebar, click **E-Approval**.  
2. You will see pages such as:

| Menu item | What it is for |
|-----------|----------------|
| **Overview** | Summary of work waiting for you and shortcuts |
| **Submissions** | Your requests (and drafts) |
| **Approvals** | Requests waiting for your decision (approvers) |
| **My E-Approval profile** | Your signature and out-of-office settings |

Some items appear only if your role allows them (for example, **Approvals** is for approvers).

Your company may also show **form workspaces** as separate items in the sidebar (for example a Document Control or ISO workspace). See Part 3.

### Overview at a glance

On **E-Approval → Overview** you may see:

- **Needs my approval** → jump to your inbox (**View all**)  
- **Needs my attention** → returned or draft items you own (**View mine**)  
- Shortcuts to **Submissions**, **Approvals**, **Forms**, or **Reports** (permission-gated)  
- Optional finance/procurement summary cards (when those modules are enabled)

### Save your signature (strongly recommended)

Section title on the profile page: **My signature**.

You can save a signature once and reuse it when you approve.

| Role | Why it matters |
|------|----------------|
| **Approver** | A signature is **required** before you can click **Approve** |
| **Requestor** | Some forms also ask you to sign when submitting |

**How to save it**

1. Go to **E-Approval → My E-Approval profile**  
   (also under **Administration → Settings → My E-Approval profile**).  
2. Open **My signature**.  
3. Choose one method:

| Method | How |
|--------|-----|
| **Draw** | Draw with mouse or finger. Use **Clear** to redo. |
| **Type** | Type your full name and check the preview. |
| **Upload** | Upload a clear PNG or JPEG (keep it small, under ~350 KB). |

4. Check the **electronic signature consent** box (required).  
5. Click **Save signature** and wait for **Signature saved**.

**Tips**

- Use a dark signature on a light background for uploads.  
- You can change it later on the same page.  
- When you approve, your saved signature can load automatically — you still confirm consent before approving.

### Out-of-office delegation (approvers, if enabled)

If your admin turned on delegation, you will see **Out-of-office delegation** on **My E-Approval profile**.

**How to set it**

1. Open **Out-of-office delegation**.  
2. Choose an **Acting approver**.  
3. Optional: set **Valid until** and **Notes**.  
4. Click **Add delegation**.  
5. Confirm it appears under **Active delegations**.

While a delegation is active, that person can act on approvals that were waiting on you. When you return, click **Revoke** on that row.

If you see a message that delegation is disabled for your organization, ask an admin to enable it under E-Approval settings.

---

## Part 3 — Form workspaces (if your company uses them)

Some published forms get their own **workspace** in the left sidebar (under **Operations**, next to E-Approval — not nested inside it).

**Examples:** Document Control, ISO Approval, or other named workspaces your admin configured.

### What you can do there

1. Open the workspace from the sidebar.  
2. Use saved-view chips such as **All**, **Pending**, **Needs revision**, **Mine**, **This month**.  
3. Search by **document number** or **requestor**.  
4. Click **Open** on a row (or the print icon when available).  
5. Click **New request** to start a new submission for that form.  
6. Approvers can use **My approvals** to jump to the approval inbox.  
7. **Export CSV** appears only if you have export/audit permission.

Workspaces are a faster way to live inside one form family. Everything still opens the same submission detail pages as the rest of E-Approval.

---

## Part 4 — Submit a new request (requestor)

### Checklist

- [ ] You are signed in  
- [ ] You can open **E-Approval** (or a form workspace)  
- [ ] You know which form to use (or you can find it in the list)  
- [ ] Files / photos are ready if the form needs them  

### Steps

1. Open **E-Approval → Submissions**.  
2. Click **New submission**.  
   - Or from **Overview**, click **New submission**.  
   - Or from a **form workspace**, click **New request**.  
3. You see a list of **published** forms. Search by name, category, or description if needed.  
4. Switch **Table** / **Gallery** if you prefer cards vs rows.  
5. On the correct form, click **Start request**.  
   - Optional: **Open focused view** opens a quieter full-page form (less chrome). From focused view you can return via **Standard view** or **All forms**.  
   - Optional: **Copy external link** (badge **External link**) if your admin shared that form publicly — see Part 10.  
6. Fill in all **required** fields (usually marked).  
7. If the form has special fields, complete them (see below).  
8. Optional: click **Save draft** if you need to finish later.  
9. When everything looks correct, click **Submit request**.  
10. You should see a success message (for example **Submission sent**) and return to **Submissions**.  
11. Note the **document number** on the request — use it when asking for help.

**You are done filing.** The request status becomes **Pending**, and the first approver(s) are notified.

### Special fields you may see on a form

| Field type | What to do |
|------------|------------|
| **Files / attachments** | Upload and wait until each upload finishes |
| **Camera / photo** | Click **Take photo** or **Take / choose photo**; wait for upload. Some forms show a photo counter or **GPS on** |
| **Signature (on the form)** | Draw or type in the form’s signature box (separate from your profile **My signature**) |
| **Purchase requisition** | On PO-style forms, choose an **Approved purchase requisition** under **Purchase requisition to fulfill** before submit |
| **Multi-step form** | Use **Back** / **Next**; some forms end with **Review & submit** |

### Resume a draft

1. Go to **E-Approval → Submissions**.  
2. Find the item with status **Draft** (or open it from **Overview → Needs my attention**).  
3. Click **Continue editing**.  
4. Finish the form → **Submit request**.

### If something goes wrong

| Problem | What to do |
|---------|------------|
| Form not in the list | Ask an admin to publish the form or give you access |
| Cannot submit | Fill highlighted required fields; wait for uploads to finish |
| Wrong form started | Leave / cancel the draft and start again with the correct form |
| No **New submission** button | You may only have view access — ask your admin |
| No open purchase requisitions | Ask procurement / your admin; you need an **approved** PR with remaining budget |

---

## Part 5 — Track your requests

1. Go to **E-Approval → Submissions** (or open the matching **form workspace**).  
2. Use search or status chips: **All**, **Needs revision**, **Pending**, **Approved**, **Rejected**, **Cancelled**.  
3. Switch **Table** / **Gallery** if you like.  
4. Click a request (**Open submission**) to open it.  

On the detail page you can check:

| Tab / area | What you see |
|------------|--------------|
| **Request** | Form answers, **Attachments**, **Related & links** (related submissions, linked documents, related tickets) |
| **Approvals** | **Workflow path** diagram, **Waiting on…**, **Approval trail** |
| **Activity** | Comments and system remarks |
| **Decide** | Actions you can take (cancel, follow-up, document control, or approve if you are next) |

**Header actions** often include:

- **Print / PDF**  
- **Raise ticket** — only if Ticketing is enabled and you can create tickets; prefills document number, form name, status, and a link to this submission. Related tickets then appear under **Request → Related & links**.

**Also useful**

- Header **Notifications** (bell) for updates  
- **Overview** cards such as **Needs my attention** for returned or draft items  
- On **Approvals** tab: hover an approver name on the workflow path to preview their signature (when available)

### Status meanings (plain English)

| Status you see | Meaning |
|----------------|---------|
| **Draft** | Saved, not submitted yet |
| **Pending** | Waiting for an approver |
| **Needs revision** | Approver asked you to fix something and resubmit |
| **Awaiting document control** | Waiting on a document-control step (some forms only) — see Part 8 |
| **Approved** | Fully approved |
| **Rejected** | Stopped; usually start a **new** request if you still need approval |
| **Cancelled** | You (or an admin process) cancelled the request |

**Tip:** Submissions filter chips do not always include **Draft** or **Awaiting document control**. Use the status badge on the card, Overview, or search by document number.

---

## Part 6 — Attachments, comments, and print

### Attachments

1. Open the request.  
2. Open the **Request** tab (or the form file fields).  
3. Find **Attachments**.  
4. Use **Open preview**, **Download**, or **Open with approval footer** when signatures are stamped on the PDF.  
5. Confirm it is the correct version before you approve or resubmit.

If a file will not open, ask the requestor to upload again and resubmit.

### Comments

1. Open the request → **Activity** tab.  
2. Type in **Add a comment**.  
3. Click **Post**.  

Use comments for questions and notes. For official **Approve / Reject / Request revision**, use the **Decide** tab (Part 7).

### Print / PDF

1. Open the request.  
2. Click **Print / PDF**.  
3. Use the browser print dialog to print or save as PDF.

---

## Part 7 — Approve, reject, or ask for revision (approver)

### Open work waiting for you

1. Sign in.  
2. Go to **E-Approval → Approvals**.  
3. Use the **Awaiting me** filter (default for the inbox). Switch to **All** to browse more broadly.  
4. Or open the item from **Notifications**, **Overview → Needs my approval**, or a workspace **My approvals** button.  
5. Switch **Table** / **Gallery** if you prefer.

Page title: **Approval inbox** — one row per document. Empty state: **Nothing awaiting you**.

### Review first

1. Click **Open submission**.  
2. Read the **Request** answers.  
3. Open **Attachments** and confirm files.  
4. Check **Approvals** for:
   - **Workflow path** — steps and parallel groups  
   - **Waiting on Step N** — who must act next (**You** appears on your row)  
   - Parallel rules such as “Any one can approve…” or “All listed approvers must approve…”  
   - **Approval trail** — history of decisions  
5. Read **Activity** for earlier comments or return remarks.

### Make your decision

Go to the **Decide** tab → **Your decision**.

| Button | When to use | What you must provide |
|--------|-------------|------------------------|
| **Approve** | Everything is OK | **Your signature** + **electronic signature consent**. Remarks optional. |
| **Reject** | This request should stop | **Remarks** required (at least 5 characters). Explain why. |
| **Request revision** | Needs fixes; they can edit and resubmit | **Remarks** required (at least 5 characters). Say what to change. |

**Approve**

1. Confirm **Your signature** (from profile, or Draw / Type / Upload now).  
2. Check the **electronic signature consent** box.  
3. Optional remarks.  
4. Click **Approve**.  

If you see *“Add your signature before approving.”* — finish Draw, Type, or Upload first, then try again.  
If you see *“Accept the electronic signature consent before approving.”* — tick the consent checkbox, then approve.

**Reject**

1. Type clear remarks (why it is rejected).  
2. Click **Reject**.

**Request revision**

1. Type clear remarks (what is missing or wrong).  
2. Optional: **Require full re-approval** — after they resubmit, the whole chain starts again (when your process allows it).  
3. Click **Request revision**.

After you decide, the status updates and the requestor is notified (in-app and email when configured).

### Optional options you may see

- Parallel wording such as “Any one can approve — first approval continues the workflow” or “At least N of M…” — follow the rule shown on screen.  
- **Admin reroute** (admins only): assign a different approver with a reason.

---

## Part 8 — Document control step (some forms)

Some controlled-document workflows pause after approvals for a **document control** step.

| You see | Meaning |
|---------|---------|
| Status **Awaiting document control** | Approval steps are paused |
| Banner **Waiting on document control** | Someone with document-control access must finish fields |

**If you are the document-control user**

1. Open the submission.  
2. Go to the **Decide** tab → **Document control**.  
3. Complete the required fields.  
4. Click **Submit document control**.  
5. Wait for **Document control submitted** — the workflow then continues or completes per your form design.

If you are not the document-control user, wait or ask your document-control team; you can still view **Request**, **Approvals**, and **Activity**.

---

## Part 9 — If your request was returned (requestor)

When status is **Needs revision**:

1. Open **E-Approval → Submissions**.  
2. Open the request (or use **Overview → Needs my attention**).  
3. Read the **revision remarks** carefully (banner and **Activity**).  
4. Note any banner such as **Awaiting resubmit · will resume at step N** or **will restart from step 1** — that tells you where the chain continues after you resubmit.  
5. Choose one:

| Action | Use when |
|--------|----------|
| **Edit and resubmit** | You need to change answers or files |
| **Resubmit without changes** | Nothing to change; you still want to send it back into the workflow |
| **Cancel request** | You no longer need this request |

6. If editing: fix fields / replace attachments → click **Resubmit request**.  
7. Track it again under **Submissions** (status returns to **Pending**).

### While a request is still Pending

On **Decide**, requestors may also:

- **Cancel request** — stop the request  
- **Send follow-up to approver** — gentle reminder (may be limited by cooldown)

### If the request was Rejected

You usually **cannot** reopen a rejected request. Start a **new** submission if you still need approval.

---

## Part 10 — Public / external forms (vendors & partners)

Some forms can be filled **without** a TowerOS login.

### Share a link (internal user)

1. Go to **E-Approval → Submissions → New submission**.  
2. Find a form with an **External link** badge.  
3. Click **Copy external link** and send it to the vendor/partner.  
4. If the form requires a password, your admin will share that separately.

### Submit as an external user

1. Open the link your company sent (example path: `/public/e-approval/…`).  
2. If asked, enter the **Access password** → **Continue**.  
3. Enter **Your contact details** (**Full name**, **Email**).  
4. Fill the form → **Submit**.  
5. You should see **Submission received**.

### Revise as an external user

If the request is returned, you receive a revise link (email or from your contact).

1. Open the revise link.  
2. Read **Needs revision** / **Revision notes**.  
3. Fix the form → **Resubmit**.  
4. You should see **Revision submitted**.

### Package download

After approval, some processes email a secure **package download** link for deliverables. Open the link and save the file; the page may start the download automatically.

---

## Part 11 — Notifications

### In-app

1. Click the **bell** icon in the top header.  
2. Open an item to jump to the related request.  
3. Mark items read as needed.

### Email

You may receive email when someone submits, approves, rejects, or returns a request — if your company has email notifications enabled.

Email footers usually include the **submission link**. Help text depends on whether Ticketing is enabled:

| Ticketing module | What the email says |
|------------------|---------------------|
| **On** | You can **create an IT support ticket** and paste the submission link |
| **Off** | **Contact your IT support team** and include the submission link (no ticket button) |

---

## Quick map

| I want to… | Go here |
|------------|---------|
| Sign in | Your company `/login` page |
| See overview | **E-Approval → Overview** |
| Save signature / OOO | **E-Approval → My E-Approval profile** |
| Work in one form family | Sidebar **form workspace** (if configured) |
| Start a request | **E-Approval → Submissions → New submission** (or workspace **New request**) |
| Quieter compose screen | Form card → **Open focused view** |
| See my requests / drafts | **E-Approval → Submissions** |
| Approve / reject / revise | **E-Approval → Approvals** (Awaiting me) |
| Document control fields | Open submission → **Decide → Document control** |
| Raise an IT ticket | Open submission → **Raise ticket** (if Ticketing is on) |
| Share vendor form | Form card → **Copy external link** |
| See alerts | Header **Notifications** (bell) |
| Print a request | Open submission → **Print / PDF** |

---

## Happy-path practice (training)

Use this with two people (or two accounts): one requestor, one approver.

1. **Requestor** signs in → (optional) saves signature → **Submissions → New submission → Start request** → fills form → **Submit request** → notes document number.  
2. **Requestor** opens **Submissions** and confirms status **Pending**.  
3. **Approver** signs in → saves signature + consent on **My E-Approval profile** → opens **Approvals** → reviews form, **Workflow path**, and files → **Approve** (or **Reject** / **Request revision**).  
4. If revision: **Requestor** opens the returned item → **Edit and resubmit** → fixes → **Resubmit request** → **Approver** decides again.  
5. If the form uses document control: complete **Decide → Document control → Submit document control**.  
6. Final status should be **Approved** (or **Rejected** / **Cancelled**).

---

## FAQ

**I do not see E-Approval in the menu.**  
Ask your admin to enable the module and give you the right role (requestor, approver, or both).

**I see Submissions but not Approvals.**  
You are set up as a requestor only. Approvers need approve permission.

**Can I edit after I submit?**  
Not while it is **Pending**, unless an approver uses **Request revision**, or you **Cancel** and start again (company policy may vary).

**Who is next in the chain?**  
Open the request → **Approvals** tab → look at **Waiting on…**, the **Workflow path**, and the approval trail.

**Someone else should approve instead of me.**  
Set **Out-of-office delegation** on your profile (if enabled), or ask an admin — admins can **reroute** on some requests.

**Why don’t I see Raise ticket?**  
Ticketing must be enabled for your company, and you need permission to create tickets.

**Why doesn’t the email offer “create an IT support ticket”?**  
Your company may have Ticketing turned off. Use the submission link in the email and contact IT another way.

**Vendors / partners without TowerOS login**  
Use **Copy external link** / Part 10. Returned external requests use a revise link your company provides.

**Do I need a passkey to approve?**  
No. Passkeys are for login only. Approving still needs **Your signature** and consent.

---

## Related guides

| Guide | Audience |
|-------|----------|
| [Document Approval — simple user guide](./document-approval-end-user-guide.md) | Same flow, focused on Document Approval forms |
| [E-Approval module overview](./e-approval.md) | Product / technical overview (admins) |
| [E-Approval go-live checklist](./e-approval-go-live-checklist.md) | Admins preparing a tenant |
| [External (public) forms](./e-approval-external-forms.md) | Admins sharing vendor links |
| [Form workspaces](./e-approval-form-workspace.md) | Workspace setup / UX notes |

---

*TowerOS · E-Approval — end-user guide*  
*File: `docs/modules/e-approval-end-user-guide.md`*
