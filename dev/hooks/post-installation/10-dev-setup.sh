#!/bin/sh
# Runs once after the first installation (as www-data, inside the app container).
# Output appears in the log of the app container.

occ() {
	php /var/www/html/occ "$@"
}

# The app first, so a failing step further down cannot leave it disabled.
occ app:enable vaccinations || echo "==> [dev-setup] FEHLER: App vaccinations konnte nicht aktiviert werden"

occ config:system:set debug --value=true --type=boolean
occ config:system:set loglevel --value=0 --type=integer
occ background:cron

for spec in "frank:Frank" "kind:Kind"; do
	uid="${spec%%:*}"
	name="${spec#*:}"
	if occ user:info "$uid" >/dev/null 2>&1; then
		continue
	fi
	if ! OC_PASS="$DEV_USER_PASSWORD" occ user:add --password-from-env --display-name="$name" "$uid"; then
		echo "==> [dev-setup] FEHLER: User $uid nicht angelegt. Meist lehnt die Passwort-Richtlinie"
		echo "    DEV_USER_PASSWORD ab (mind. 10 Zeichen, nicht in bekannten Passwort-Leaks)."
	fi
done

if occ user:info frank >/dev/null 2>&1 && occ user:info kind >/dev/null 2>&1; then
	occ vaccinations:guardian:add frank kind
fi

echo "==> [dev-setup] fertig"
