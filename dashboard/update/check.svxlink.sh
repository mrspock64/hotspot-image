echo "###-START-###"
echo "Fm branch"
tagname=$(curl -sl https://api.github.com/repos/sm0svx/svxlink/releases/latest | jq -r .tag_name)
name=$(curl -sl https://api.github.com/repos/sm0svx/svxlink/releases/latest | jq -r .name)
published=$(curl -sl https://api.github.com/repos/sm0svx/svxlink/releases/latest | jq -r .published_at)
body=$(curl -sl https://api.github.com/repos/sm0svx/svxlink/releases/latest | jq -r .body)
zipball=$(curl -sl https://api.github.com/repos/sm0svx/svxlink/releases/latest | jq -r .zipball_url)

# Ask the installed binary directly rather than trusting a version file that
# only gets written by this same script — that file doesn't exist at all on
# a node that was never updated via this dashboard, giving a false "unknown"
# result. `svxlink --version` prints something like "SvxLink v1.10.1@26.05.1
# ...", so pull out the part after the @.
installed_full=$(svxlink --version 2>/dev/null | head -n1)
installed=$(echo "$installed_full" | grep -oP '@\K[0-9.]+' | head -n1)
if [ -z "$installed" ]; then
  installed="unknown (svxlink --version did not return a parseable version)"
fi

echo "$body"
echo "......................................................."
echo "Changes:"
echo "......................................................."
echo "$zipball"
echo "......................................................."
echo "Version Name:  $name"
echo "Version Date:  $published"
echo "Latest release: $tagname"
echo "Installed version: $installed"
if [ "$tagname" = "$installed" ]; then
  echo "Status: up to date"
else
  echo "Status: UPDATE AVAILABLE ($installed -> $tagname)"
fi
echo "......................................................."
echo "###-FINISH-####"
