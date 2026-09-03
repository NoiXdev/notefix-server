# Notefix Server

The self-hosted backend for **Notefix**, the local-first notes app.

It turns Notefix from a single-device notebook into a small shared workspace:

- **Accounts** — each person signs in to their own space.
- **Shared workspaces** — invite others onto the same notes, with owner / editor / viewer roles.
- **Self-hosted** — you run it, your notes stay on your server.
- **Guided first-run setup** — after installing, open the server in a browser and create the administrator in a short wizard.

The Notefix desktop app connects to one or more of these servers, and keeps working fully offline against local notebooks too.

## Self-hosting

See [`docs/development.md`](docs/development.md) for running and deploying the server. The scheduler that ships in the image also prunes invitations that expired more than seven days ago, once a day; their vault wraps are removed with them.

## License

[MIT](LICENSE).
