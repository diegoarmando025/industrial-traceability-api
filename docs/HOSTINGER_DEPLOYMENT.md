# Laravel deployment overview

This is a public, provider-neutral overview of deployment considerations for the
fictional PlantCore Traceability portfolio project. Operational infrastructure
details are intentionally maintained outside this public repository.

## Architecture

The application uses Laravel 13, PHP 8.4, MariaDB/MySQL, Sanctum and Vite.
Keep the application source and `.env` outside the web-accessible document
root. Serve only an audited public front controller and compiled browser assets.

Use an isolated database account with least-privilege access, HTTPS, correct
file permissions and separate configuration for each application.

## Release requirements

1. Review and test the exact revision intended for production.
2. Compile Vite assets in a trusted build environment.
3. Back up application files and **verify a database backup** before changes.
4. Check database migrations for backwards compatibility separately.
5. Deploy only approved code and public assets; never expose application
   secrets or internal source directories.
6. Check private logs, authentication, tenant/plant isolation and demo routes.
7. Maintain a documented recovery plan and ensure a rollback does not assume
   a database schema can always be reversed.

The fictional public demo is read-only, isolated from authenticated operations
and requires explicit enabling. See [demo documentation](PUBLIC_READONLY_DEMO.md).

## Security note

Hostnames for SSH, usernames, internal absolute paths, port numbers, SSH keys,
`authorized_keys` setup, repository secrets and production deployment scripts
are deliberately **not** provided here. This public repository is intended for
code review, not infrastructure administration.

The initial hosting setup and historical deployment instructions may remain
visible in previous commits. Removing a file from the latest commit does not
erase Git history.
