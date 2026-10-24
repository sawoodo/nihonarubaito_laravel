# Machine Setup & Deploy Workflow

How to set up a machine (Mac or Windows) to work on and deploy nihonarubaito.com, and how to keep more than one machine safe.

**Golden rule: GitHub is the single source of truth.** No machine is "the real copy". Work that isn't committed and pushed doesn't exist yet.

## Production facts

| | |
|---|---|
| SSH host | `ssh.nihonarubaito.com`, port `18765` |
| SSH user | `u4602-zegubfmytubg` |
| Laravel root | `~/www/nihonarubaito.com/public_html/laravel/` |
| Deploy method | Manual `scp` of changed files. Production is **not** a git repo |
| Opcache | Disabled on this host, so matching file hashes means the change is live |
| Repo | `https://github.com/sawoodo/nihonarubaito_laravel.git` |

## 1. New machine: deploy-ready setup

You need `git`, `ssh`, and `scp`. They come with macOS. On Windows, use Git for Windows (Git Bash) or the built-in OpenSSH.

### Clone the repo

```bash
git clone https://github.com/sawoodo/nihonarubaito_laravel.git
```

Never put a token in the remote URL. Let the OS credential helper store it (`git config --global credential.helper osxkeychain` on Mac; Windows uses Git Credential Manager by default).

### Create and authorize an SSH key (one per machine)

```bash
ssh-keygen -t ed25519 -f ~/.ssh/siteground_<machine> -C "<machine-name>"
cat ~/.ssh/siteground_<machine>.pub
```

In SiteGround **Site Tools → Devs → SSH Keys Manager** (site selector: nihonarubaito.com), import that exact `.pub` line.

Add this to `~/.ssh/config` (Windows: `C:\Users\<you>\.ssh\config`):

```
Host siteground nihonarubaito
    HostName ssh.nihonarubaito.com
    User u4602-zegubfmytubg
    Port 18765
    IdentityFile ~/.ssh/siteground_<machine>
    IdentitiesOnly yes
```

Test it with `ssh nihonarubaito 'whoami'`. It should print `u4602-zegubfmytubg`. If you get `Permission denied (publickey)`, compare `ssh-keygen -lf ~/.ssh/siteground_<machine>.pub` with the fingerprint of the key you imported.

### Get `.env`

```bash
scp nihonarubaito:www/nihonarubaito.com/public_html/laravel/.env ./.env
chmod 600 .env
```

`.env` is git-ignored. Never commit it. This copy has **production** values. See section 4 before running the app locally.

### Verify the machine

```bash
git status            # "up to date with 'origin/main'", "working tree clean"
git remote -v         # points at sawoodo/nihonarubaito_laravel
ssh nihonarubaito 'whoami'
```

## 2. Every work session (any machine)

0. **Run `/session-start` in Claude Code first** (Mac or Windows). It is read-only
   until you approve: it reports branch, what's unpushed, what's uncommitted, any
   stash, and whether the remote URL is clean — then waits. On the **Windows PC in
   particular, run it and pull before any work**; that machine is the one most
   likely to be behind.
1. **`git pull` before touching anything.** This stops machines from overwriting each other.
2. Do the work.
3. **Commit and push before deploying.** Always.
4. Deploy the changed files:
   ```bash
   scp app/Http/Controllers/Foo.php nihonarubaito:www/nihonarubaito.com/public_html/laravel/app/Http/Controllers/Foo.php
   ```
5. **Verify production matches git.** A manual `scp` can miss a file:
   ```bash
   md5 -q app/Http/Controllers/Foo.php        # Mac  (Git Bash / Linux: md5sum)
   ssh nihonarubaito 'md5sum www/nihonarubaito.com/public_html/laravel/app/Http/Controllers/Foo.php'
   ```
   The hashes must match.
6. **Finish with the config cache REBUILT, and prove the app boots.** This is not
   conditional. Rebuild it whether or not you touched `config/` or `.env`, and
   *always* if the deploy ran `config:clear`, `cache:clear` or `optimize:clear`
   — a deploy that ends with the cache missing leaves the site exposed to the
   empty-`.env` fault below:
   ```bash
   ssh nihonarubaito 'cd www/nihonarubaito.com/public_html/laravel && \
     php artisan config:cache && \
     php -r "\$c = require \"bootstrap/cache/config.php\"; echo \"key_len=\", strlen(\$c[\"app\"][\"key\"] ?? \"\"), \" db=\", \$c[\"database\"][\"default\"] ?? \"?\", \" env=\", \$c[\"app\"][\"env\"] ?? \"?\", \" debug=\", var_export(\$c[\"app\"][\"debug\"], true), PHP_EOL;" && \
     php artisan --version'
   ```
   Expected: `key_len=51 db=mysql env=production debug=false`, then a
   `Laravel Framework ...` version line. The version line is the boot check —
   if the app cannot boot, that line is an exception instead.

   Route and view caches are **not** in use, so nothing else needs a rebuild.

7. **Last gate: no new errors.** `config:clear` and friends fail quietly — the
   site keeps serving while a fraction of requests 500:
   ```bash
   ssh nihonarubaito 'cd www/nihonarubaito.com/public_html/laravel && \
     grep -c "No application encryption key\|Database file at path" storage/logs/laravel.log'
   ```
   Note the count before the deploy and again a few minutes after. It must not
   climb. Either string means requests are booting without `.env`.

### Config cache: why it exists and the trap it creates

Since 2026-09-23 production runs with a cached config (`bootstrap/cache/config.php`). It stops roughly 40 requests a day from returning HTTP 500 when the host's filesystem hands back an empty `.env` — see `docs/ledger.md`.

**The trap: while the cache exists, `.env` and `config/` are ignored at runtime.** Edit either one and nothing changes until `php artisan config:cache` runs again.

**The second trap, and the worse one: clearing the cache re-arms the fault.** On
2026-10-24 a deploy ran `php artisan config:clear` as a routine cache-clearing
step and never rebuilt. The site stayed up and every health check passed — but
over the next 19 hours **52 requests** died with
`MissingAppKeyException`, plus one falling through to sqlite. There had been
**zero** in the preceding ten days. Nothing surfaced it: the failures are a
fraction of traffic, the pages a human checks are fine, and `LOG_LEVEL=error`
means they only ever appear in `laravel.log`. Clearing this cache is a
production change, not housekeeping — step 6 above is how it gets undone.

Rebuilding reads `.env` once. If that read hits the same filesystem hiccup, it bakes broken defaults in for *every* request, so always verify straight afterwards:

```bash
ssh nihonarubaito 'cd www/nihonarubaito.com/public_html/laravel && php -r "\$c = require \"bootstrap/cache/config.php\"; echo \"key_len=\", strlen(\$c[\"app\"][\"key\"] ?? \"\"), \" db=\", \$c[\"database\"][\"default\"] ?? \"?\", \" session=\", \$c[\"session\"][\"driver\"] ?? \"?\", PHP_EOL;"'
```

Expected: `key_len=51 db=mysql session=file`. Anything else (empty key, `db=sqlite`) means the cache is broken — run `php artisan config:clear` at once to fall back to reading `.env`, then rebuild.

Read the file with `php -r`, as above, rather than asking the app through `artisan`/`tinker`: a broken cache can still report itself as fine through the app.

## 3. Switching machines

- **Never leave uncommitted work behind.** Commit and push before you walk away. Uncommitted work on one machine is invisible to the other.
- On the machine you switch to, **`git pull` first**.
- If `git pull` reports a conflict, stop and resolve it before doing anything else. Don't deploy from a conflicted tree.

### Line endings: a drift alarm that isn't drift

A file can hash differently on the server while its **content is identical** — the
server copy is CRLF and git is LF. Seen 2026-10-22 on three analytics views
uploaded 2026-02-13.

`.gitattributes` has carried `* text=auto eol=lf` since 2026-02-09 and
`git add --renormalize .` is a no-op, so **git was never the source**. The usual
cause is the transfer: an FTP/SFTP client in ASCII/Auto mode rewrites LF to CRLF
in flight. `scp` does not. Keep any GUI client in **binary/raw** mode.

Before assuming you are about to overwrite real work, check whether it's only
line endings:

```bash
# fetch the server copy, then compare content with line endings normalised
tr -d '\r' < server_copy.php | md5 -q      # Mac (Linux/Git Bash: md5sum)
tr -d '\r' < local_copy.php  | md5 -q
```

Equal hashes means identical content — safe to deploy. A byte delta of exactly
+1 per line is the CRLF signature. Unequal means **stop**: that is real
uncommitted production code.

## 4. Drift check: git vs production

Deploys are manual, so production and git drift apart. On 2026-09-17 this check found a whole feature (FB Scheduled Posts V2) that was live but never committed. Deploying git's `routes/web.php` or `FbPost.php` would have silently removed it.

**Run it monthly, before any large deploy, and after working from a machine you haven't used in a while.** Start from a clean, up-to-date tree (`git pull`, `git status` clean). Run from the repo root in bash (macOS Terminal or Git Bash on Windows):

```bash
R=www/nihonarubaito.com/public_html/laravel
T=$(mktemp -d)
hash() { if command -v md5sum >/dev/null; then md5sum "$1" | cut -d' ' -f1; else md5 -q "$1"; fi; }

# 1. Hash every tracked file locally and on the server
git ls-files > $T/tracked.txt
while IFS= read -r f; do echo "$(hash "$f")  $f"; done < $T/tracked.txt | sort -k2 > $T/local.md5
ssh nihonarubaito "cd $R && while IFS= read -r f; do if [ -f \"\$f\" ]; then md5sum \"\$f\"; else echo \"MISSING  \$f\"; fi; done" < $T/tracked.txt | sort -k2 > $T/server.md5

# 2. In git but missing on the server
grep '^MISSING' $T/server.md5

# 3. In git but different on the server
join -1 2 -2 2 $T/local.md5 <(grep -v '^MISSING' $T/server.md5) | awk '$2!=$3{print $1}'

# 4. On the server but not in git (stray/backup files, or undeployed-to-git code)
ssh nihonarubaito "cd $R && find . -type f -not -path './vendor/*' -not -path './node_modules/*' -not -path './storage/*' -not -path './bootstrap/cache/*' | sed 's|^\./||' | sort" \
  | comm -13 <(sort $T/tracked.txt) - | grep -vE '\.(bak|backup|broken)|\.bak\.'
```

For every file in step 3, look at the actual diff before deciding which side is right:

```bash
scp nihonarubaito:$R/path/to/file $T/server-file && diff -w path/to/file $T/server-file
```

- **Server has changes git doesn't:** copy the server file into the repo unchanged (don't run Pint on it), check its hash matches, then commit and push. **Do this before any other deploy.**
- **Git has changes the server doesn't:** undeployed work. Deploy it on purpose, or list it under Parked work below.

**Known, expected differences (as of 2026-09-17). Don't treat these as drift:**
- **Line endings only (CRLF on server, LF in git):** `app/Console/Commands/FetchApplicationLogs.php`, `resources/lang/english/content.php`, `resources/views/admin/analytics/{demand-supply,employees,expiring-jobs}.blade.php`, `resources/views/partials/breadcrumb-schema.blade.php`. `diff -w --strip-trailing-cr` shows no change. These clear up when the files are next deployed.
- **Formatting only:** `app/Models/Job.php` (Pint formatting in git).
- **Dev-only dependency:** `composer.json` / `composer.lock` (`laravel/boost` in git only). Don't upload these unless you also run `composer install` on the server.
- **Local-only files:** `.claude/`, `CLAUDE.md`, `SETUP.md`, `docs/`, `.mcp.json`, `.gitignore`.
- **Unused leftovers not on the server:** `.htaccess_production`, `laravel/verify-noindex-guards.sh`, `storage/ci3_*.html`, `resources/views/listings/partials/homepage-content.blade.php`.
- **Server-only clutter:** about 30 `*.bak*` files and about 20 one-off scripts. The app doesn't load them. Left in place deliberately.

## 5. Parked work: in git, NOT deployed. Don't deploy as-is

| Work | Commit | State |
|---|---|---|
| Area slug migration (fix for substring LIKE bug, 175 unreachable areas) | `310cc5a` (2026-07-26) | `app/Console/Commands/PopulateAreaSlugs.php` and `database/migrations/2026_07_26_000000_add_slug_to_areas.php` were never uploaded. The migration has not run, and production `areas` has **no `slug` column**. Live code doesn't use it. Unfinished: review the plan before deploying. |

When you finish or abandon parked work, update this table.

## 6. Later: running the app locally

Only needed to run the site locally. Deploying doesn't need any of this.

1. Install PHP 8.2+, Composer, and MySQL. Also install Node if you'll build frontend assets.
2. `composer install` (and `npm install` if needed).
3. Create an empty local MySQL database and import data if needed.
4. Edit `.env`:
   - `APP_ENV=local`
   - `APP_DEBUG=true`
   - `APP_URL=http://localhost:8000`
   - `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` → the local database
5. `php artisan serve`

**Warning:** production database work runs over SSH on the server, never against a local database. A wrong-target near-miss has happened before (see `docs/ledger.md`, Standing environment notes). Before any data-changing command, confirm which database you're connected to.
