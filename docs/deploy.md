# Deploying

Pushing to the `live` branch deploys automatically. GitHub Actions checks the
PHP for syntax errors, then copies `app/` to the server over SSH with rsync.
The workflow is [`.github/workflows/deploy.yml`](../.github/workflows/deploy.yml).

```
feature branch → pull request → main → merge main into live → deployed
```

What a deploy does and doesn't touch:

- **Only the app folder.** The deploy key is locked to that one folder on the
  server, so a deploy can't reach the rest of the site or any other site.
- Files removed from `app/` in git are removed from the server too. Anything
  else added to the server's app folder by hand is removed as well, except:
- The server's `config.php` is never copied or deleted, so its settings stay put.
- No `.git` folder ends up on the server.
- The database isn't touched; the app updates its own tables on the next page load.
- Before anything changes, the deploy checks that the server folder is empty or
  already holds the app (`bootstrap.php`), and stops otherwise.

The WordPress stats plugin ships inside the app, in
`app/modules/wordpress/plugins/somajewel-stats`, so deploys update it too.
WordPress sees it through a symlink you create once (step 4).

`app/lib`, `app/content`, and `app/modules` each have an `.htaccess` that blocks
web access. The app and WordPress read those files from disk, so nothing else
needs them over the web. This needs Apache with `.htaccess` enabled
(`AllowOverride All`, which WordPress sites usually have already).

## One-time setup

The examples use `/var/www/example.com/app` as the app folder and
`/var/www/example.com` as the WordPress folder. Use your own.

### 1. On the server: a deploy user that owns the app folder

```bash
sudo adduser --disabled-password --gecos "" deploy

APP=/var/www/example.com/app
sudo mkdir -p "$APP"
sudo chown -R deploy:deploy "$APP"
```

If `config.php` already exists in the app folder, `chown -R` hands it to the
deploy user too, which is fine: the web server only needs to read it.

### 2. On your computer: a deploy key, locked to the app folder

A key used only by GitHub, with no passphrase (Actions can't type one).

```bash
ssh-keygen -t ed25519 -N "" -C "github-deploy" -f ~/.ssh/somajewel_deploy
```

Install the public half for the deploy user. `rrsync` (installed with rsync on
Ubuntu) refuses anything that isn't rsync and keeps every path inside the app
folder; `restrict` turns off everything else (port forwarding, terminals, and
so on):

```bash
APP=/var/www/example.com/app
PUB=$(cat ~/.ssh/somajewel_deploy.pub)
ssh YOUR_ADMIN_LOGIN@YOUR_SERVER "sudo mkdir -p ~deploy/.ssh \
  && echo 'command=\"/usr/bin/rrsync $APP\",restrict $PUB' | sudo tee -a ~deploy/.ssh/authorized_keys >/dev/null \
  && sudo chown -R deploy:deploy ~deploy/.ssh && sudo chmod 700 ~deploy/.ssh \
  && sudo chmod 600 ~deploy/.ssh/authorized_keys"
```

The line in `~deploy/.ssh/authorized_keys` should look like:

```
command="/usr/bin/rrsync /var/www/example.com/app",restrict ssh-ed25519 AAAA... github-deploy
```

Test it; this should list the app folder's contents without asking for a password:

```bash
rsync --list-only -e "ssh -i ~/.ssh/somajewel_deploy" deploy@YOUR_SERVER:.
```

A plain `ssh` login with this key is refused ("does not run rsync"). That's expected.

### 3. On GitHub: the `live` environment and its secrets

In the repository, go to **Settings → Environments → New environment**, name it
`live`, and add these **environment secrets**:

| Secret | Value |
|---|---|
| `DEPLOY_HOST` | The server's IP address or hostname |
| `DEPLOY_SSH_KEY` | The **private** key: the full output of `cat ~/.ssh/somajewel_deploy` |
| `DEPLOY_KNOWN_HOSTS` | The output of `ssh-keyscan -t ed25519 YOUR_SERVER`, using exactly the same IP or hostname as `DEPLOY_HOST` (not an SSH shortcut from `~/.ssh/config`, which `ssh-keyscan` ignores) |

Then, on the same environment page, add one **environment variable** (not a
secret):

| Variable | Value |
|---|---|
| `DEPLOY_USER` | `deploy` |

The username isn't sensitive, and as a secret GitHub would hide the word
"deploy" everywhere in the logs. (A `DEPLOY_USER` secret still works if you
already made one.)

There's no path setting: the key itself decides where deploys go. The host is
a secret because this repository is public and secrets are masked in the
Actions logs.

If a deploy can't reach the server, the run's summary says why (for example,
a known-hosts mismatch or a rejected key) and what to check.

Optional but recommended: under the environment's **Deployment branches**,
choose **Selected branches** and add `live`, so nothing else can deploy.

Once the key is in GitHub, you can delete the private key from your computer;
if you ever need a new one, generate it again and update the secret.

### 4. On the server: link the stats plugin into WordPress

Once, as your admin user, after the first deploy has put the plugin on the server:

```bash
sudo ln -s /var/www/example.com/app/modules/wordpress/plugins/somajewel-stats \
           /var/www/example.com/wp-content/plugins/somajewel-stats
```

Then activate **SomaJewel Stats** under **Plugins** in WP admin. Later deploys
update the plugin through the link; you never need to touch `wp-content` again.
To remove it, deactivate it in WP admin and delete the link with `sudo rm` (on
the link itself, without a trailing slash).

### 5. Create the `live` branch

```bash
git checkout main && git pull
git checkout -b live && git push -u origin live
```

That first push runs the first deploy. Watch it under the **Actions** tab.

## Everyday use

```bash
git checkout live && git pull
git merge main
git push        # deploys
git checkout main
```

Or on github.com: open a pull request from `main` into `live` and merge it.

To re-run the last deploy without changes: **Actions → Deploy to live → Run workflow**.

## Moving to a new server

Repeat steps 1, 2 (just the `authorized_keys` part), and 4 on the new server,
then update `DEPLOY_HOST` and `DEPLOY_KNOWN_HOSTS` in GitHub. Create the new
server's `config.php` by hand; deploys never copy it.
