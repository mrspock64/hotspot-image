echo "###-START-###"

# NOTE (hotspot-image fork): this used to point at a completely unrelated
# project's GitHub repo (FM-POLAND/hs_dashboard_pi — leftover from an older
# fork lineage, never actually the dashboard RF.Guru ships). Rewritten to
# use git against our own fork instead, cloned in place by install-dashboard.sh.

cd /var/www/html || { echo "/var/www/html is not a git checkout — cannot check for updates"; echo "###-FINISH-####"; exit 1; }

git fetch origin >/dev/null 2>&1
local_rev=$(git rev-parse HEAD)
remote_rev=$(git rev-parse origin/HEAD 2>/dev/null || git rev-parse origin/main 2>/dev/null)

echo "Local commit:  $local_rev"
echo "Remote commit: $remote_rev"
if [ "$local_rev" = "$remote_rev" ]; then
  echo "Status: up to date"
else
  echo "......................................................."
  echo "Changes:"
  git log --oneline "$local_rev..$remote_rev" 2>/dev/null
  echo "......................................................."
  echo "Status: UPDATE AVAILABLE"
fi

echo "###-FINISH-####"
