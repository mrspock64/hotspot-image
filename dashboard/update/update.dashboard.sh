echo "###-START-###"

# NOTE (hotspot-image fork): this used to download a zip from a completely
# unrelated GitHub repo (FM-POLAND/hs_dashboard_pi) and blindly swap in
# /var/www/html — if that repo's directory layout ever drifted, this would
# have replaced the live dashboard with a broken/unrelated site. Rewritten
# to do a plain `git pull` against our own fork, which install-dashboard.sh
# clones into place at provisioning time.

echo "--- Dashboard - backup ---"
cp -R /var/www/html "/var/www/html.$(date +%Y%m%dT%H%M%S)"

cd /var/www/html || { echo "/var/www/html is not a git checkout — cannot update"; echo "###-FINISH-####"; exit 1; }

echo "--- Dashboard - git pull ---"
git fetch origin
git reset --hard origin/HEAD
chown -R www-data:www-data /var/www/html

echo "--- SVXlink service restart ---"
sudo service svxlink restart

echo "###-FINISH-####"
