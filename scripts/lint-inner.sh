#!/bin/sh
# Runs inside the cli container.
set -e
fail=0
count=0
for f in $(find /opt/estora/wp-content /opt/estora/tests -type f -name "*.php" -not -path "*/vendor/*" -not -path "*/node_modules/*"); do
  count=$((count+1))
  if ! php -l "$f" >/dev/null 2>&1; then
    echo "SYNTAX ERROR: $f"
    php -l "$f" || true
    fail=1
  fi
done
echo "Syntax checked: $count files"
if [ -x /opt/estora/vendor/bin/phpcs ]; then
  php /opt/estora/vendor/bin/phpcs -q --no-colors --warning-severity=0 --report=summary \
    --standard=/opt/estora/phpcs.xml.dist \
    /opt/estora/wp-content/plugins/estora-core \
    /opt/estora/wp-content/themes/estora-theme || fail=1
else
  echo "PHPCS not installed (composer install skipped) - static analysis skipped"
fi
exit $fail
