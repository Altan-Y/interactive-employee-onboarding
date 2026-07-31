#!/bin/sh
set -eu
cd /var/www/html

echo "Waiting for WordPress files..."
while [ ! -f wp-config.php ]; do sleep 2; done

echo "Waiting for database..."
until wp db check --allow-root >/dev/null 2>&1; do sleep 2; done

if ! wp core is-installed --allow-root >/dev/null 2>&1; then
  echo "Installing WordPress..."
  wp core install \
    --url="http://localhost:8081" \
    --title="Interactive Onboarding Demo" \
    --admin_user="demo_admin" \
    --admin_password="demo_admin" \
    --admin_email="demo@example.test" \
    --skip-email \
    --allow-root
fi

wp plugin activate interactive-employee-onboarding-demo --allow-root
wp option update permalink_structure '/%postname%/' --allow-root
wp option update blog_public 0 --allow-root
wp option update timezone_string 'Europe/Berlin' --allow-root
wp rewrite flush --hard --allow-root

echo "Demo setup complete: http://localhost:8081/access/"
