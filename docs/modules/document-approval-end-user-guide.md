# Document Approval — Simple user guide

A friendly walkthrough for everyday users: sign in, add your signature, submit a Document Approval, track it, check files, and approve / reject / ask for changes.

For the **general E-Approval** walkthrough (any form, not only Document Approval), see [e-approval-end-user-guide.md](./e-approval-end-user-guide.md).

---

## Who should read what?

| I am a… | Read these sections |
|---------|---------------------|
| **Requestor** (I submit documents) | Sign in → Add signature (optional) → Submit a request → View my requests → Attachments → If returned for changes |
| **Approver** (I decide on requests) | Sign in → Add signature (**required**) → Review & decide (Approve / Reject / Request revision) |
| **Both** | Read the whole guide once |

---

## Before you start

You need:

- Your **TowerOS login** (email + password, or Microsoft sign-in)
- The **website address** for your company workspace (ask your admin if unsure)
- For Document Approval: permission to use **E-Approval**

**Example login pages**

- `http://staging.myapp.localhost/login`
- `http://atc.localhost/login`

Always use your **company / tenant** login page — not the platform admin console.

---

## Part 1 — Sign in

1. Open your workspace login page in the browser.
2. Choose how you sign in:
   - **Email and password** → enter them → click **Sign in**
   - **Sign in with Microsoft** → follow the Microsoft screens
3. If you see a **security code (MFA)** screen, open your authenticator app and enter the 6-digit code.
4. You should land on the **Dashboard**.

**Tip:** On the left sidebar, look for **E-Approval**. That is where Document Approval lives.

### Sample accounts (training / demo only)

Ask your admin for real accounts. For local Alliance demos, password is often `password`:

| Email | Usually used for |
|-------|------------------|
| `admin@alliance.localhost` | Admin |
| `manager@alliance.localhost` | Manager / approver-style work |
| `ops.viewer@alliance.localhost` | View only |

Your workspace may use different emails (for example `admin@staging.myapp.localhost`).

---

## Part 2 — Add your signature

You can save a signature once, then reuse it when you approve requests.

### Why it matters

- **Approvers:** a signature is **required** before you can click **Approve**.
- **Requestors:** some forms also ask you to sign when submitting.

### Save your signature (recommended)

1. Sign in.
2. Open **E-Approval → My E-Approval profile**  
   (sidebar: **E-Approval** → **My E-Approval profile**).
3. Find the **Signature** section.
4. Choose one method:

| Method | How to do it |
|--------|----------------|
| **Draw** | Draw your signature with mouse or finger on the pad. Use **Clear** if you need to redo. |
| **Type** | Type your full name. Check the preview looks right. |
| **Upload** | Upload a clear PNG or JPEG of your signature (keep the file small, under ~350 KB). |

5. Click **Save** (or the save action on that page).
6. Wait for the success message (**Signature saved**).

**Tips**

- Use a dark signature on a light background for uploads.
- You can change your signature later on the same page.
- The next time you approve, TowerOS can load this signature automatically.

### Sign when you approve a request

1. Open the request from **E-Approval → Approvals** (or from **Notifications**).
2. Scroll to **Your decision**.
3. Under **Your signature**:
   - If your profile signature loaded, review it.
   - Or switch **Draw** / **Type** and enter a new signature for this decision.
4. Only then click **Approve**.

If you see *“Add your signature before approving.”* — finish Draw or Type first, then try again.

### Sign on a form (when submitting)

Some Document Approval forms include a **Signature** field.

1. While filling the form, find the **Signature** box.
2. **Draw** or **Type** your name (same idea as above).
3. Clear and redraw if needed.
4. Continue the form and **Submit**.

---

## Part 3 — Submit a Document Approval (requestor)

### Checklist before you start

- [ ] You are signed in  
- [ ] You can open **E-Approval**  
- [ ] The Document Approval / Document Control form is available (published)  
- [ ] You have the file(s) ready to attach  

### Steps

1. Click **E-Approval** in the left menu.
2. Open **Submissions**.
3. Click **New** (or **New submission**).
4. Choose the **Document Approval / Document Control / ISO Document Control** form.  
   *Do not pick a different form (for example payment) by mistake.*
5. Fill in all fields marked as required.
6. **Attach** your document file(s) where the form asks.
   - Wait until each upload finishes.
7. Optional: click **Save as draft** if you need to finish later.
8. When everything looks correct, click **Submit**.
9. Write down or copy the **document number** shown after submit.

**You are done filing.** Approvers will see the request in their approval inbox.

### If something goes wrong

| Message / problem | What to do |
|-------------------|------------|
| Form not in the list | Ask an admin to publish the Document Approval form or give you access |
| Cannot submit | Fill highlighted required fields; wait for uploads to finish |
| Wrong form | Discard / leave the draft and start again with the correct form |

---

## Part 4 — View your requests

1. Go to **E-Approval → Submissions**.
2. Find your request by document number, title, or status.
3. Click it to open.
4. On the detail page you can see:
   - Form answers  
   - Current status  
   - Who approved / who is next  
   - Comments and remarks  
   - Attachments  

**Also useful:** open **Notifications** for updates on requests you submitted.

### Common statuses (plain English)

| Status | Meaning |
|--------|---------|
| Draft | Saved, not submitted yet |
| Pending / In review | Waiting for an approver |
| Returned / Needs revision | Approver asked you to fix something |
| Approved | Fully approved |
| Rejected | Stopped; usually start a new request if you still need approval |

---

## Part 5 — Check attachments

1. Open the request (from **Submissions** or **Approvals**).
2. Find **Attachments** or the file fields on the form.
3. Click a file to open or download it.
4. Confirm it is the right version before you approve or resubmit.

If a file will not open, ask the requestor to upload it again and resubmit.

---

## Part 6 — Approve, reject, or ask for revision (approver)

### Open work waiting for you

1. Sign in as an approver.
2. Go to **E-Approval → Approvals**.
3. Use **Awaiting me** if you see that filter.
4. Or open the item from **Notifications** / Dashboard **Awaiting you**.

### Review first

1. Read the form answers.
2. Open the **attachments** (Part 5).
3. Read any earlier comments.

### Make your decision

Go to **Your decision**.

| Button | When to use | What you must provide |
|--------|-------------|------------------------|
| **Approve** | Everything is OK | **Signature** required. Remarks optional. |
| **Reject** | This request should stop | **Remarks** required (at least 5 characters). Explain why. |
| **Request revision** | Needs fixes, then they can resubmit | **Remarks** required (at least 5 characters). Tell them what to change. |

**Approve steps**

1. Add or confirm **Your signature** (Draw or Type) — see Part 2.  
2. Optional remarks.  
3. Click **Approve**.

**Reject steps**

1. Type clear remarks.  
2. Click **Reject**.

**Request revision steps**

1. Type clear remarks (what is missing or wrong).  
2. Click **Request revision**.

After you decide, the status updates and the requestor is notified.

---

## Part 7 — If your request was returned (requestor)

1. Open **E-Approval → Submissions**.
2. Open the request marked **Returned** / needs revision.
3. Read the **revision remarks** carefully.
4. Fix the answers and/or replace attachments.
5. Click **Resubmit**.
6. Track it again under **Submissions**.

If the request was **Rejected**, you usually cannot reopen it — start a **new** Document Approval if you still need approval.

---

## Quick map

| I want to… | Go here |
|------------|---------|
| Sign in | Your tenant `/login` page |
| Save my signature | **E-Approval → My E-Approval profile** |
| Submit Document Approval | **E-Approval → Submissions → New** |
| See my requests | **E-Approval → Submissions** |
| Approve / reject / revise | **E-Approval → Approvals** |
| See alerts | **Notifications** |

---

## Practice run (training)

1. **Requestor** signs in → (optional) saves signature → submits Document Approval → notes document number.  
2. **Requestor** opens **Submissions** and checks attachments.  
3. **Approver** signs in → saves signature on **My E-Approval profile** → opens **Approvals** → reviews files → **Approves** (or Reject / Request revision).  
4. If revision: **Requestor** fixes and **Resubmits**; **Approver** decides again.

---

*TowerOS · Document Approval — simple end-user guide*  
*File: `docs/modules/document-approval-end-user-guide.md`*
