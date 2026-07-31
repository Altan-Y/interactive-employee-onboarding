# Security scope

This is a local portfolio demonstration, not a production authentication system.

## Included controls

- WordPress nonce verification on the demo access form;
- HMAC-derived access token instead of storing the plaintext password in a cookie;
- `HttpOnly`, `SameSite=Lax` and secure-cookie behavior when HTTPS is active;
- server-side separation of employee and guest routes;
- escaped output and sanitized route / form values;
- no bundled secrets, certificates, API keys or production credentials.

## Limitations

The default employee and guest passwords are intentionally public (`demo123` and `guest123`) because this project is meant to run locally. Change `ONBOARDING_DEMO_PASSWORD` and `ONBOARDING_GUEST_PASSWORD` before exposing the demo beyond your own computer. Do not use this gate to protect sensitive content.

Please report security issues privately rather than posting credentials in a public issue.
