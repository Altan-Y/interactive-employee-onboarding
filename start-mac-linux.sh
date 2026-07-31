#!/usr/bin/env sh
set -eu
cd "$(dirname "$0")"
docker compose up -d --build
printf '\nDemo: http://localhost:8081/access/\nPassword: demo123\nAdmin: demo_admin / demo_admin\n'
