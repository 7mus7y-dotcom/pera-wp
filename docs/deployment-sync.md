# Sync repository code to the live WordPress site

On the hosting server:

```sh
cd /home/peraukco/_work/pera-wp
git pull origin main
bash sync.sh --dry-run
bash sync.sh
```

Review the dry-run output before syncing. The script requires Bash, Git, rsync, and an existing live `wp-content` directory. Defaults are the checkout and live site paths above; `PERA_SYNC_REPO` and `PERA_SYNC_LIVE` can override them for another checkout or a test environment.

Every run checks all Git-tracked paths under `wp-content`, using checksums to copy only new or changed content. This includes new helper files such as `inc/property-faq.php`, even if multiple pulls occurred before a sync or an earlier deployment missed the file. The old `HEAD@{1}..HEAD` range described only the most recent local HEAD change, not everything missing from the live site.

There is no deletion step. Untracked live uploads, caches, plugins, and MU utilities are untouched. Files removed from Git also remain live and require separate removal when appropriate. Tracked files are deployed from the working tree, so inspect local edits with `git status --short` before deploying. This is a broader file check than the previous incremental script: any differing tracked live file is restored from the checkout.

A failed Git listing or rsync exits with an error; rerunning checks all tracked files again. The temporary NUL-separated file list is cleaned up on exit. `--dry-run` does not copy files.

After deployment, verify the helper and run the read-only audit from the live root:

```sh
ls -l /home/peraukco/public_html/wp-content/themes/hello-elementor-child/inc/property-faq.php
cd /home/peraukco/public_html
wp eval-file wp-content/themes/hello-elementor-child/tools/audit-property-seo-content.php status=publish limit=5
```

Spot-check a property page after code deployments. The sync changes files only; it does not migrate the database or edit listing content.
