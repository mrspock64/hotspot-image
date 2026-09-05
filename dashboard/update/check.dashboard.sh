echo "###-START-###"

REPO_DIR="/opt/hotspot-image"

if [ ! -d "$REPO_DIR/.git" ]; then
  echo "$REPO_DIR is not a git checkout — cannot check for updates"
  echo "###-FINISH-####"
  exit 1
fi

cd "$REPO_DIR"

if ! git fetch origin 2>fetch_err.log; then
  echo "Could not reach the GitHub repo:"
  cat fetch_err.log
  rm -f fetch_err.log
  echo "Status: CHECK FAILED"
  echo "###-FINISH-####"
  exit 1
fi
rm -f fetch_err.log

local_rev=$(git rev-parse HEAD)
remote_rev=$(git rev-parse origin/HEAD 2>/dev/null || git rev-parse origin/main 2>/dev/null)

echo "Local commit:  $local_rev"

# rev-parse falls back to echoing an unresolvable argument verbatim to
# stdout instead of leaving it empty -- confirmed live, "origin/HEAD" or
# "origin/main" would otherwise be printed back as if it were a real
# commit and misread as an actual update below.
if [ "$remote_rev" = "origin/HEAD" ] || [ "$remote_rev" = "origin/main" ] || [ -z "$remote_rev" ]; then
  echo "Could not resolve the remote branch after fetching -- is there a"
  echo "'main' branch, and does this checkout's origin/HEAD point at it?"
  echo "Status: CHECK FAILED"
  echo "###-FINISH-####"
  exit 1
fi

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
