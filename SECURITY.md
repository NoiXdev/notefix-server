# Security Policy

## Supported versions

Notefix Server is distributed as a Docker image. Only the latest released
version receives security fixes. Please make sure you are running the most
recent release before reporting an issue.

## Reporting a vulnerability

**Please do not report security vulnerabilities through public GitHub issues.**

Instead, use GitHub's private vulnerability reporting:

1. Go to the [**Security** tab](https://github.com/NoiXdev/notefix-server/security/advisories) of this repository.
2. Click **Report a vulnerability** and fill in the details.

If you are unable to use private reporting, you may email the maintainer at
**d.elskamp@3b.de** instead.

Please include, where possible:

- A description of the vulnerability and its impact.
- Steps to reproduce or a proof of concept.
- The affected version (image tag) and how the server is deployed.

## What to expect

- We aim to acknowledge new reports within **5 business days**.
- We will keep you informed as we investigate and work on a fix.
- Once a fix is released, we are happy to credit you in the release notes
  unless you prefer to remain anonymous.

## Scope

Notefix Server is the self-hosted sync backend for the Notefix app. Reports
concerning the following are especially welcome:

- Authentication: web login, two-factor authentication, and the OAuth 2.0 /
  PKCE flow the desktop app signs in with.
- Authorization: workspace membership and the owner / editor / viewer roles on
  the sync, image and vault endpoints.
- Invitations and the workspace vault — the server only ever stores wrapped
  keys and ciphertext for protected notes; anything that would let it (or
  another member) obtain a plaintext key or note content is in scope.
- Image upload and download (path handling, file types, size limits).
- The admin panel and the instance settings stored encrypted at rest.
- The Docker image and the release pipeline.
