echo "###-START-###"

REPO_DIR="/opt/hotspot-image"

if [ ! -d "$REPO_DIR/.git" ]; then
  echo "$REPO_DIR is not a git checkout — cannot update"
  echo "###-FINISH-####"
  exit 1
fi

echo "--- Dashboard - backup ---"
cp -R /var/www/html "/var/www/html.$(date +%Y%m%dT%H%M%S)"

echo "--- Pulling latest hotspot-image ---"
cd "$REPO_DIR"
git fetch origin
git reset --hard origin/HEAD

echo "--- Re-syncing dashboard/ into /var/www/html ---"
rm -rf /var/www/html
cp -r "$REPO_DIR/dashboard" /var/www/html
chown -R www-data:www-data /var/www/html

echo "--- SVXlink service restart ---"
sudo service svxlink restart

echo "###-FINISH-####"
