# E-Approval — End-user guide

A step-by-step guide for everyday users of TowerOS **E-Approval**: how to sign in, submit a request, track it, approve or reject it, and fix a request that was sent back.

This guide is for **normal users** (requestors and approvers). It does **not** cover form building, workflows setup, or admin settings.

---

## Who should read what?

| I am a… | Read these parts |
|---------|------------------|
| **Requestor** — I fill forms and send requests | Parts 1–5, then Part 7 if something is returned |
| **Approver** — I decide on requests | Parts 1–2, then Part 6 |
| **Both** | Read the whole guide once |

---

## What is E-Approval?

E-Approval is where your company files digital requests (forms), sends them through an approval chain, and keeps a record of decisions.

Typical uses include:

- Leave / HR requests  
- Purchase or payment requests  
- Document / ISO control forms  
- Site or operations forms your admin published  

You always work inside your **company workspace** in TowerOS.

---

## Before you start

You need:

1. Your **TowerOS account** (email + password, or Microsoft sign-in)  
2. The **website address** for your company (ask your admin if unsure)  
3. Permission to use **E-Approval** (your admin assigns this)

**Login page examples**

- `https://your-company.example.com/login`  
- Local demo: `http://atc.localhost/login`

Always use your **company / tenant** login page — not the platform admin console.

---

## Part 1 — Sign in

1. Open your company login page in the browser.  
2. Sign in with one of these:
   - **Email and password** → enter them → click **Sign in**  
   - **Sign in with Microsoft** → complete the Microsoft screens  
3. If you see a **security code (MFA)** screen, open your authenticator app and enter the 6-digit code.  
4. You should land on the **Dashboard**.

**Tip:** On the left sidebar, look for **E-Approval** (under **Operations**). That is the module you will use.

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

### Save your signature (strongly recommended)

You can save a signature once and reuse it when you approve.

| Role | Why it matters |
|------|----------------|
| **Approver** | A signature is **required** before you can click **Approve** |
| **Requestor** | Some forms also ask you to sign when submitting |

**How to save it**

1. Go to **E-Approval → My E-Approval profile**  
   (also under **Administration → Settings → My E-Approval profile**).  
2. Open the **Signature** section.  
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

### Out-of-office (approvers, if enabled)

On **My E-Approval profile**, you may also set **delegation** so another person can approve while you are away. Ask your admin if this option is available for your company.

---

## Part 3 — Submit a new request (requestor)

### Checklist

- [ ] You are signed in  
- [ ] You can open **E-Approval**  
- [ ] You know which form to use (or you can find it in the list)  
- [ ] Files are ready if the form needs attachments  

### Steps

1. Open **E-Approval → Submissions**.  
2. Click **New submission**.  
   - Or from **Overview**, click **New submission**.  
3. You see a list of **published** forms. Search by name, category, or description if needed.  
4. On the correct form, click **Start request**.  
   - Optional: **Open focused view** opens a quieter full-page form in a new tab.  
5. Fill in all **required** fields (usually marked).  
6. If the form has file fields, **upload** your files and wait until each upload finishes.  
7. Optional: click **Save draft** if you need to finish later.  
8. When everything looks correct, click **Submit request**.  
9. You should see a success message (for example **Submission sent**) and return to **Submissions**.  
10. Note the **document number** on the request — use it when asking for help.

**You are done filing.** The request status becomes **Pending**, and the first approver(s) are notified.

### Resume a draft

1. Go to **E-Approval → Submissions**.  
2. Find the item with status **Draft**.  
3. Click **Continue editing**.  
4. Finish the form → **Submit request**.

### If something goes wrong

| Problem | What to do |
|---------|------------|
| Form not in the list | Ask an admin to publish the form or give you access |
| Cannot submit | Fill highlighted required fields; wait for uploads to finish |
| Wrong form started | Leave / cancel the draft and start again with the correct form |
| No **New submission** button | You may only have view access — ask your admin |

---

## Part 4 — Track your requests

1. Go to **E-Approval → Submissions**.  
2. Use search or status filters if available (**All**, **Needs revision**, **Pending**, **Approved**, **Rejected**, **Cancelled**).  
3. Click a request to open it.  

On the detail page you can check:

| Tab / area | What you see |
|------------|--------------|
| **Request** | Form answers, attachments, related links |
| **Approvals** | Workflow path, who is waiting, approval trail |
| **Activity** | Comments and system remarks |
| **Decide** | Actions you can take (cancel, follow-up, or approve if you are next) |

Header actions often include **Print / PDF**.

**Also useful**

- Header **Notifications** (bell) for updates  
- **Overview** cards such as **Needs my attention** for returned or draft items  

### Status meanings (plain English)

| Status you see | Meaning |
|----------------|---------|
| **Draft** | Saved, not submitted yet |
| **Pending** | Waiting for an approver |
| **Needs revision** | Approver asked you to fix something and resubmit |
| **Awaiting document control** | Waiting on a document-control step (some forms only) |
| **Approved** | Fully approved |
| **Rejected** | Stopped; usually start a **new** request if you still need approval |
| **Cancelled** | You (or an admin process) cancelled the request |

---

## Part 5 — Attachments, comments, and print

### Attachments

1. Open the request.  
2. Open the **Request** tab (or the form file fields).  
3. Find **Attachments**.  
4. Use **Open preview**, **Download**, or open with approval footer when available.  
5. Confirm it is the correct version before you approve or resubmit.

If a file will not open, ask the requestor to upload again and resubmit.

### Comments

1. Open the request → **Activity** tab.  
2. Type in **Add a comment**.  
3. Click **Post**.  

Use comments for questions and notes. For official **Approve / Reject / Request revision**, use the **Decide** tab (Part 6).

### Print / PDF

1. Open the request.  
2. Click **Print / PDF**.  
3. Use the browser print dialog to print or save as PDF.

---

## Part 6 — Approve, reject, or ask for revision (approver)

### Open work waiting for you

1. Sign in.  
2. Go to **E-Approval → Approvals**.  
3. Use the **Awaiting me** filter (default for the inbox).  
4. Or open the item from **Notifications** or **Overview → Needs my approval**.  

Page title: **Approval inbox** — one row per document. Open a submission to decide.

### Review first

1. Click **Open submission**.  
2. Read the **Request** answers.  
3. Open **Attachments** and confirm files.  
4. Check **Approvals** for the workflow path and **Waiting on…**.  
5. Read **Activity** for earlier comments or return remarks.

### Make your decision

Go to the **Decide** tab → **Your decision**.

| Button | When to use | What you must provide |
|--------|-------------|------------------------|
| **Approve** | Everything is OK | **Signature** required. Remarks optional. |
| **Reject** | This request should stop | **Remarks** required (at least 5 characters). Explain why. |
| **Request revision** | Needs fixes; they can edit and resubmit | **Remarks** required (at least 5 characters). Say what to change. |

**Approve**

1. Confirm **Your signature** (from profile, or Draw / Type / Upload now).  
2. Check the **electronic signature consent** box.  
3. Optional remarks.  
4. Click **Approve**.  

If you see *“Add your signature before approving.”* — finish Draw, Type, or Upload first, then try again.  
If consent is required — tick the consent checkbox, then approve.

**Reject**

1. Type clear remarks (why it is rejected).  
2. Click **Reject**.

**Request revision**

1. Type clear remarks (what is missing or wrong).  
2. Click **Request revision**.

After you decide, the status updates and the requestor is notified (in-app and email when configured).

### Optional options you may see

- **Require full re-approval** — after a revision, the whole chain starts again (when your process allows it).  
- Parallel wording such as “Any one can approve…” or “All listed approvers must approve…” — follow your company rule; the screen explains which apply.

---

## Part 7 — If your request was returned (requestor)

When status is **Needs revision**:

1. Open **E-Approval → Submissions**.  
2. Open the request (or use **Overview → Needs my attention**).  
3. Read the **revision remarks** carefully (banner and **Activity**).  
4. Choose one:

| Action | Use when |
|--------|----------|
| **Edit and resubmit** | You need to change answers or files |
| **Resubmit without changes** | Nothing to change; you still want to send it back into the workflow |
| **Cancel request** | You no longer need this request |

5. If editing: fix fields / replace attachments → click **Resubmit request**.  
6. Track it again under **Submissions** (status returns to **Pending**).

### While a request is still Pending

On **Decide**, requestors may also:

- **Cancel request** — stop the request  
- **Send follow-up to approver** — gentle reminder (may be limited by cooldown)

### If the request was Rejected

You usually **cannot** reopen a rejected request. Start a **new** submission if you still need approval.

---

## Part 8 — Notifications

1. Click the **bell** icon in the top header.  
2. Open an item to jump to the related request.  
3. Mark items read as needed.

You may also receive email when someone submits, approves, rejects, or returns a request — if your company has email notifications enabled.

---

## Quick map

| I want to… | Go here |
|------------|---------|
| Sign in | Your company `/login` page |
| See overview | **E-Approval → Overview** |
| Save signature / OOO | **E-Approval → My E-Approval profile** |
| Start a request | **E-Approval → Submissions → New submission** |
| See my requests / drafts | **E-Approval → Submissions** |
| Approve / reject / revise | **E-Approval → Approvals** (Awaiting me) |
| See alerts | Header **Notifications** (bell) |
| Print a request | Open submission → **Print / PDF** |

---

## Happy-path practice (training)

Use this with two people (or two accounts): one requestor, one approver.

1. **Requestor** signs in → (optional) saves signature → **Submissions → New submission → Start request** → fills form → **Submit request** → notes document number.  
2. **Requestor** opens **Submissions** and confirms status **Pending**.  
3. **Approver** signs in → saves signature on **My E-Approval profile** → opens **Approvals** → reviews form and files → **Approve** (or **Reject** / **Request revision**).  
4. If revision: **Requestor** opens the returned item → **Edit and resubmit** → fixes → **Resubmit request** → **Approver** decides again.  
5. Final status should be **Approved** (or **Rejected** / **Cancelled**).

---

## FAQ

**I do not see E-Approval in the menu.**  
Ask your admin to enable the module and give you the right role (requestor, approver, or both).

**I see Submissions but not Approvals.**  
You are set up as a requestor only. Approvers need approve permission.

**Can I edit after I submit?**  
Not while it is **Pending**, unless an approver uses **Request revision**, or you **Cancel** and start again (company policy may vary).

**Who is next in the chain?**  
Open the request → **Approvals** tab → look at **Waiting on…** and the approval trail.

**Someone else should approve instead of me.**  
Ask an admin. Admins can **reroute** on some requests. Approvers may also set **out-of-office delegation** on their profile when enabled.

**Vendors / partners without TowerOS login**  
Some forms have a public link. Your admin shares that link. External users fill and submit without signing in to TowerOS. Returned external requests use a revise link your company provides.

---

## Related guides

| Guide | Audience |
|-------|----------|
| [Document Approval — simple user guide](./document-approval-end-user-guide.md) | Same flow, focused on Document Approval forms |
| [E-Approval module overview](./e-approval.md) | Product / technical overview (admins) |
| [E-Approval go-live checklist](./e-approval-go-live-checklist.md) | Admins preparing a tenant |
| [External (public) forms](./e-approval-external-forms.md) | Admins sharing vendor links |

---

*TowerOS · E-Approval — end-user guide*  
*File: `docs/modules/e-approval-end-user-guide.md`*
