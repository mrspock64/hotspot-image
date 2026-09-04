echo "###-START-###"

REPO_DIR="/opt/hotspot-image"

if [ ! -d "$REPO_DIR/.git" ]; then
  echo "$REPO_DIR is not a git checkout — cannot check for updates"
  echo "###-FINISH-####"
  exit 1
fi

cd "$REPO_DIR"
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
