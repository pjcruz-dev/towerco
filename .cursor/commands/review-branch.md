# Review current branch

Review `git diff main...HEAD`.

1. Delegate to the `code-reviewer` subagent.
2. If backend files changed, also run `security-auditor` and `tenant-isolation-auditor`.
3. If frontend files changed, also run `ui-ux-reviewer`.
4. Report one merged list ranked Blocker / Should fix / Nit, with file:line. Say explicitly when a category has no findings. Do not edit files.
