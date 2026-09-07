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
# --verify -q, not a bare rev-parse: a bare `git rev-parse origin/HEAD`
# both prints "fatal: ambiguous argument..." to stderr AND echoes the
# literal string "origin/HEAD" to STDOUT before exiting non-zero --
# confirmed live on a checkout whose origin/HEAD symbolic ref was never
# set (this repo was never `git clone`d against the real remote, only
# fetched into after the fact). Worse than it sounds: because both sides
# of `cmd1 2>/dev/null || cmd2 2>/dev/null` still contribute to the same
# $(...) capture, a failing-but-still-printing cmd1 gets its bogus output
# concatenated with cmd2's real one -- remote_rev came out as literally
# "origin/HEAD\n<real sha>", two lines glued together, which the old
# equality check below (comparing the whole string to "origin/HEAD")
# never caught, and it read as a real (garbled) update. `--verify -q`
# is the scripting-safe form: silent, empty stdout, clean exit code, on
# failure -- no fallback text to leak into the fallback branch's capture.
remote_rev=$(git rev-parse --verify -q origin/HEAD 2>/dev/null)
if [ -z "$remote_rev" ]; then
  remote_rev=$(git rev-parse --verify -q origin/main 2>/dev/null)
fi

echo "Local commit:  $local_rev"

if [ -z "$remote_rev" ]; then
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
