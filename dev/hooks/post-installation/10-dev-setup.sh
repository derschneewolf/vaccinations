#!/bin/sh
# Runs once after the first installation (as www-data, inside the app container).
set -eu

occ() {
	php /var/www/html/occ "$@"
}

occ config:system:set debug --value=true --type=boolean
occ config:system:set loglevel --value=0 --type=integer
occ background:cron

for spec in "frank:Frank" "kind:Kind"; do
	uid="${spec%%:*}"
	name="${spec#*:}"
	if ! occ user:info "$uid" >/dev/null 2>&1; then
		OC_PASS="$DEV_USER_PASSWORD" occ user:add --password-from-env --display-name="$name" "$uid"
	fi
done

occ app:enable vaccinations
occ vaccinations:guardian:add frank kind
