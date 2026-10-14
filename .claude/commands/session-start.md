---
description: Start-of-session sync check (Mac or Windows) — is this repo in sync with GitHub, is anything unpushed or uncommitted? Read-only until I approve a pull.
---

# /session-start — run before any other work on this machine

This project is worked on from two machines (Windows PC and MacBook). Production is not a git checkout; GitHub `origin/main` is the source of truth. An uncommitted changeset on one machine caused the Aug 28 incident. This command makes sure we never work on a stale copy or lose work that only exists here.

**Machine-specific repo paths:**
- Mac: `/Users/yousuf/Projects/nihonarubaito/laravel`
- Windows: `C:\Users\Tech Fever\Desktop\nihonarubaito\laravel`

Rules:
- **Read-only** until Gate 3. Do not pull, merge, rebase, stash, reset, checkout, commit, push, delete or deploy anything before I approve.
- Show raw command output, not summaries.
- Never run `git reset --hard`, `git clean`, `git checkout -- .`, `git stash drop` or force-push — ever, in this command.
- Detect the OS and shell first, and use commands that work in it (PowerShell on Windows, zsh on Mac). On the Mac, plain `grep pattern file` can silently return nothing — use `git` commands or Python for counts.

---

## Gate 1 — where am I, what state is the repo in

```
git rev-parse --show-toplevel
git branch --show-current
git remote -v
git status
git stash list
git fetch origin --prune
git log --oneline -1 HEAD
git log --oneline -1 origin/main
git log --oneline HEAD..origin/main      # on GitHub, missing here
git log --oneline origin/main..HEAD      # here only, not pushed
git diff --stat
git diff --stat --cached
```

Also check for files that matter but git doesn't track:
```
git status --porcelain --ignored -- .env .claude docs storage/app 2>&1
```
(`.env` being present and ignored is normal — just confirm it's ignored, never print its contents.)

Checks:
- Remote URL must be `https://github.com/sawoodo/nihonarubaito_laravel.git` (or SSH equivalent) and must **not** contain a token or `user@`.
- Branch should be `main`. If not, say so.

## Gate 2 — classify and STOP

Report a short table, then exactly one verdict:

| Check | Result |
|---|---|
| Machine / OS | |
| Repo path | |
| Branch | |
| Behind origin (commits) | list them |
| Ahead of origin (unpushed commits) | list them |
| Uncommitted changes (files) | list them |
| Stashes | |
| Remote URL clean | yes / no |

Verdict — pick one:
- **🟢 SAFE TO PULL** — no uncommitted changes, no unpushed commits, no stashes. Behind by N commits (or already up to date).
- **🟡 LOCAL WORK EXISTS** — uncommitted changes and/or unpushed commits and/or stashes. For each changed file show `git diff --stat` and, for files under `app/`, `routes/`, `resources/`, `config/`, `database/`, the first 40 lines of `git diff <file>`. Then, read-only, compare each changed file to production by md5 to answer: **is this work already live on the server but never committed?**
  ```
  ssh -p 18765 u4602-zegubfmytubg@ssh.nihonarubaito.com "cd ~/www/nihonarubaito.com/public_html/laravel && md5sum <file1> <file2> ..."
  ```
  (local md5: `certutil -hashfile <file> MD5` on Windows, `md5 -q <file>` on Mac). Report per file: local md5 · server md5 · origin/main md5 (`git show origin/main:<file> | md5…`) · conclusion (live-but-uncommitted / local-only draft / same as origin).
- **🔴 DIVERGED** — both ahead and behind origin. Do not attempt a merge. Show both commit lists and stop.

**STOP after Gate 2.** Wait for me.

## Gate 3 — only after I say "pull"

```
git pull --ff-only origin main
git log --oneline -5
git status
```
If `--ff-only` refuses, stop and report — do not merge or rebase.

Then read, if they exist: `docs/PROJECT_STATUS.md` (section 0 "This week" + section 3 Pending), `docs/ledger.md` (last 10 entries), `SETUP.md`. Summarise in at most 8 lines: what was done last session and what is next. Do not start any of it.

---

## Notes
- If I say "commit and push local work" instead of "pull": commit only the files I approve, with a message I approve, then `git pull --ff-only` and `git push`. Never commit `.env`, `storage/logs/*`, archives or dumps.
