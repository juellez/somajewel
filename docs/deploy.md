# Deploying

Pushing to the `live` branch deploys automatically. GitHub Actions checks the
PHP for syntax errors, then copies `app/` (and optionally the WordPress stats
plugin) to the server over SSH with rsync. The workflow is
[`.github/workflows/deploy.yml`](../.github/workflows/deploy.yml).

```
feature branch → pull request → main → merge main into live → deployed
```

What a deploy does and doesn't touch:

- It only deploys into a folder that's new or empty, or that already holds
  this app (`bootstrap.php`) or this plugin (`somajewel-stats.php`). A path
  pointing at a whole site, a WordPress folder, or anything else stops the
  deploy before anything changes, since deploys delete files missing from git.

- Files removed from `app/` in git are removed from the server too.
- Each server's `config.php` is never copied or deleted, so its settings stay put.
- No `.git` folder ends up on the server.
- The database isn't touched; the app updates its own tables on the next page load.

## One-time setup

### 1. On the server: a deploy user

A dedicated user that can only write to the app folder (and the plugin folder),
so the deploy key can't touch anything else. Replace the paths with your site's.

```bash
sudo adduser --disabled-password --gecos "" deploy

APP=/var/www/example.com/app
sudo mkdir -p "$APP"
sudo chown -R deploy:deploy "$APP"

# Optional: let deploys update the stats plugin too
PLUGIN=/var/www/example.com/wp-content/plugins/somajewel-stats
sudo mkdir -p "$PLUGIN"
sudo chown -R deploy:deploy "$PLUGIN"
```

If `config.php` already exists in the app folder, `chown -R` above hands it to
the deploy user too, which is fine: the web server only needs to read it.

### 2. On your computer: a deploy key

A key used only by GitHub, with no passphrase (Actions can't type one).

```bash
ssh-keygen -t ed25519 -N "" -C "github-actions-somajewel" -f ~/.ssh/somajewel_deploy
```

Install the public half for the deploy user, locked to rsync inside
`/var/www`. `rrsync` (installed with rsync on Ubuntu) refuses any command that
isn't rsync and keeps every path inside `/var/www`; `restrict` turns off
everything else (port forwarding, terminals, and so on):

```bash
PUB=$(cat ~/.ssh/somajewel_deploy.pub)
ssh YOUR_ADMIN_LOGIN@YOUR_SERVER "sudo mkdir -p ~deploy/.ssh \
  && echo 'command=\"/usr/bin/rrsync /var/www\",restrict $PUB' | sudo tee -a ~deploy/.ssh/authorized_keys >/dev/null \
  && sudo chown -R deploy:deploy ~deploy/.ssh && sudo chmod 700 ~deploy/.ssh \
  && sudo chmod 600 ~deploy/.ssh/authorized_keys"
```

The line in `~deploy/.ssh/authorized_keys` should look like:

```
command="/usr/bin/rrsync /var/www",restrict ssh-ed25519 AAAA... github-actions-somajewel
```

Test it; this should list the app folder's contents without asking for a
password. Note the path is relative to `/var/www`:

```bash
rsync --list-only -e "ssh -i ~/.ssh/somajewel_deploy" deploy@YOUR_SERVER:example.com/app/
```

A plain `ssh` login with this key is refused ("does not run rsync"). That's expected.

### 3. On GitHub: the `live` environment and its secrets

In the repository, go to **Settings → Environments → New environment**, name it
`live`, and add these **environment secrets**:

| Secret | Value |
|---|---|
| `DEPLOY_HOST` | The server's IP address or hostname |
| `DEPLOY_USER` | `deploy` |
| `DEPLOY_APP_PATH` | The app folder, relative to `/var/www` (e.g. `example.com/app`). A full `/var/www/...` path also works |
| `DEPLOY_PLUGIN_PATH` | Optional. The plugin folder, same format, ending in `/somajewel-stats` (e.g. `example.com/wp-content/plugins/somajewel-stats`). Leave it out to skip the plugin |
| `DEPLOY_SSH_KEY` | The **private** key: the full output of `cat ~/.ssh/somajewel_deploy` |
| `DEPLOY_KNOWN_HOSTS` | The output of `ssh-keyscan -t ed25519 YOUR_SERVER` |

Everything is a secret, even the host and paths, because this repository is
public and secrets are masked in the Actions logs.

Optional but recommended: under the environment's **Deployment branches**,
choose **Selected branches** and add `live`, so nothing else can deploy.

Once the key is in GitHub, you can delete the private key from your computer;
if you ever need a new one, generate it again and update the secret.

### 4. Create the `live` branch

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

Repeat step 1 on the new server and the `authorized_keys` part of step 2, then
update `DEPLOY_HOST`, `DEPLOY_KNOWN_HOSTS`, and the paths in GitHub. Create the
new server's `config.php` by hand; deploys never copy it.
