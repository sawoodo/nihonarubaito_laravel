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

## 3. Switching machines

- **Never leave uncommitted work behind.** Commit and push before you walk away. Uncommitted work on one machine is invisible to the other.
- On the machine you switch to, **`git pull` first**.
- If `git pull` reports a conflict, stop and resolve it before doing anything else. Don't deploy from a conflicted tree.

## 4. Later: running the app locally

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
