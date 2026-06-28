# Security

## Supported versions

| Version | Status         |
|---------|----------------|
| 0.x     | Active support |

## Reporting a vulnerability

Please **do not** open a public GitHub issue for security-sensitive findings.
Email **opensource@simtabi.com** with:

- A description of the vulnerability and its impact.
- Steps to reproduce (proof-of-concept welcome).
- The affected version(s).

We aim to acknowledge reports within 72 hours and triage within 5 business days.
Coordinated disclosure timelines are negotiated per case.

## Hardening notes for this package

- Installer routes are guarded by an install-once check and rate limiting; the
  installer is disabled the moment the app is installed.
- The `.env` is written atomically with `0600` permissions.
- Secrets (passwords, purchase codes, tokens) are masked in logs and events.
