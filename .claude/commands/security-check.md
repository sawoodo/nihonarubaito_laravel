---
description: Read-only safety check — leaked GitHub tokens on this machine + web-exposed Laravel files on nihonarubaito.com
---

# /security-check — read-only, two parts

Rules for this whole command:
- **Read-only.** Do not edit, delete, revoke, rotate, commit or deploy anything. Report only.
- **Never print a secret.** If you find a token or password, show only the first 4 and last 4 characters (`ghp_abcd…wxyz`) and the file + line it is in.
- Show the raw command output for every check (masked where needed). No summaries in place of output.
- **Validate the instrument before trusting a negative.** Single-file `grep pattern file` has returned 0 matches on this Mac for text that is present (see docs/ledger.md, instrument lessons). Run a positive control for every search; if it fails, redo that search with `python3` and say so in the report.
- Run Part A, show results, then run Part B. STOP at the end and wait for me.

---

## Part A — GitHub token exposure (local machine)

A plaintext GitHub token was found in an old `.git/config` on 2026-08-15 and stripped from the remote URL. It was never confirmed revoked. Find every place a token could still live.

A1. Remote URLs with embedded credentials, in this repo and in any sibling repo folders:
```bash
git remote -v
for d in .. ../* ~/Desktop/* ; do
  [ -f "$d/.git/config" ] && echo "== $d" && grep -nE 'url *=.*@' "$d/.git/config"
done 2>/dev/null
```
Expect: no URL of the form `https://<something>@github.com/...`.

A2. Token-shaped strings in git config, credential files and shell profiles:
```bash
grep -rnE 'gh[pousr]_[A-Za-z0-9]{20,}|github_pat_[A-Za-z0-9_]{20,}' \
  ~/.gitconfig ~/.git-credentials ~/.config/git ~/.bashrc ~/.zshrc ~/.bash_profile ~/.profile \
  .git/config 2>/dev/null
```
Mask anything found before printing.

A3. Credential helper in use, and whether the GitHub CLI is logged in:
```bash
git config --show-origin --get-all credential.helper
gh auth status 2>&1 | sed -E 's/(gh[pousr]_[A-Za-z0-9]{4})[A-Za-z0-9]+([A-Za-z0-9]{4})/\1…\2/g'
```

A4. Tokens ever committed into this repo's history (current files + all history):
```bash
git grep -nE 'gh[pousr]_[A-Za-z0-9]{20,}|github_pat_' $(git rev-list --all) 2>/dev/null | head -20
```
Mask anything found. Expect: nothing.

**Part A report:** a table — location · token found? (masked prefix/suffix) · still active-looking? Then tell me exactly what I must do by hand:
- Go to https://github.com/settings/tokens (classic **and** fine-grained tabs) and delete every token I don't recognise or that matches a masked prefix you found.
- If A4 found a token in history: it must be revoked even if the file is gone — history keeps it.

You cannot revoke tokens yourself. Do not try.

---

## Part B — web-exposed files on the live site

Laravel lives at `public_html/laravel/`, so its private files sit under the web root. Each URL below must return **403 or 404**, and the body must **not** contain secrets.

Use a browser UA (bare curl gets 403 from the site by design, which would be a false pass). Do not use `-L`. Wait 2 seconds between requests — the SiteGround WAF answers bursts with 403/202, which would also be a false pass.

```bash
UA='Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36'
for p in \
  /.env \
  /laravel/.env \
  /laravel/.env.example \
  /laravel/.env.backup \
  /laravel/storage/logs/laravel.log \
  /laravel/storage/app/sitemap.xml \
  /laravel/composer.json \
  /laravel/composer.lock \
  /laravel/.git/config \
  /.git/config \
  /laravel/artisan \
  /laravel/config/database.php \
  /laravel/database/ \
  /laravel/bootstrap/cache/config.php \
  /laravel/routes/web.php ; do
  out=$(curl -s -A "$UA" -o /tmp/sc_body -w '%{http_code} %{size_download}' "https://nihonarubaito.com$p")
  leak=$(grep -cE 'APP_KEY|DB_PASSWORD|DB_USERNAME|MAIL_PASSWORD|\[core\]|"require"|<\?php|production\.ERROR|local\.ERROR' /tmp/sc_body)
  echo "$p  ->  $out  secret-markers=$leak"
  sleep 2
done
rm -f /tmp/sc_body
```

How to read each line:
- **403 or 404, secret-markers=0** → PASS.
- **200 and secret-markers > 0** → **FAIL — exposed.** Do not print the body. Report the path only.
- **200 and secret-markers = 0** → suspicious. It may be the site's own 404 page served with 200. Show the first 3 lines of the body (masked) so I can judge.
- **202, or 403 on everything including paths that should be 404** → the WAF challenge, not a result. Wait 60 seconds and re-run only those paths, once.

Positive control (proves the instrument works): also fetch `https://nihonarubaito.com/` with the same UA. It must return 200 and around 100KB+. If it doesn't, every result above is unreliable — say so.

Then, read-only on the server, show what protects that folder:
```bash
ssh -p 18765 u4602-zegubfmytubg@ssh.nihonarubaito.com \
  'cd ~/www/nihonarubaito.com/public_html && ls -la laravel/.htaccess .htaccess 2>&1; echo "--- laravel/.htaccess:"; cat laravel/.htaccess 2>/dev/null | head -40'
```
Do not change any `.htaccess`.

**Part B report:** a table — path · HTTP code · bytes · secret-markers · PASS / FAIL / RE-RUN / CHECK. Then one line: does any rule deny access to `laravel/` as a whole, or is protection per-file?

---

## STOP

End with:
1. **Things I must do by hand:** revoke these tokens; and, if any Part B path failed, rotate every credential in `.env` (DB password, APP_KEY, mail, ValueCommerce token) **after** blocking access — blocking alone doesn't undo a leak.
2. **Proposed fixes, not applied:** e.g. a `Require all denied` `.htaccess` in `public_html/laravel/` with an exception path for `laravel/public/`. Draft the exact file content, and list what could break (the legacy `/laravel/public/index.php/*` 301 handling must keep working).
3. A ledger line I can paste into `docs/ledger.md` once I've acted.

Wait for my go-ahead before doing anything else.
