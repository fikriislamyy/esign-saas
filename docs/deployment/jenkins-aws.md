# Jenkins deployment to one AWS EC2 t3.micro

This runbook implements issue #63. Commands marked **Jenkins agent**, **EC2**, or **operator computer** run on that machine. Production deployment uses an immutable GHCR image and a short maintenance window. The EC2 host never builds source code.

## 1. Decide and record infrastructure

Record the AWS account's Free Tier rules, region, instance type, EBS size, public IPv4 cost, CPU credit mode, domain, registry package, Jenkins host, backup bucket and release approver. A t3.micro has 1 GiB RAM; the initial Compose memory ceilings total 816 MiB before OS/Docker overhead. Treat these as starting limits and measure with realistic uploads and PDF signing before live use. Keep Jenkins and Docker builds on a separate machine. Set AWS budget alerts for EC2, EBS, public IPv4, S3 and data transfer. See [AWS Free Tier rules](https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/ec2-free-tier-usage.html), [T3 specifications](https://aws.amazon.com/ec2/instance-types/t3/) and [Jenkins Linux requirements](https://www.jenkins.io/doc/book/installing/linux/).

Choose whether this is a new empty database or an import. For an import, schedule a cutover, test restoring the dump on disposable infrastructure, and verify object storage paths before the first deployment. Use a private S3 bucket for documents and an independent bucket or prefix for database backups. Enable bucket versioning or an equivalent documented document recovery method. GHCR needs a publisher identity for Jenkins and a separate read-only identity for EC2.

## 2. Configure Jenkins outside EC2

Install the supported Jenkins LTS and Java combination from [Jenkins installation instructions](https://www.jenkins.io/doc/book/installing/linux/). Give the controller zero executors, a persistent backed-up Jenkins home, access control, and a separate `linux-docker-amd64` build agent with one executor. The agent needs Docker Engine and Compose, Git, Bash and OpenSSL. Install Pipeline, Git, Credentials Binding, SSH Agent and JUnit plugins. Keep Jenkins and plugins patched. The release agent has Docker access, which is powerful; restrict its use to trusted jobs.

Create two jobs/folders with separate credential scopes:

1. A PR/branch CI job with read-only checkout and no release credentials. Configure it to run the test stage only. Keep untrusted PR builds off the trusted release agent: use a separate disposable agent label or a separate Jenkins installation if necessary. The Jenkinsfile's branch condition does not secure credentials by itself.
2. A trusted multibranch release job restricted to protected `main`, with `RELEASE_JOB=true`, `DEPLOY_HOST=deploy@<verified-host>`, and the `linux-docker-amd64` agent. Require reviewed changes to `Jenkinsfile`, `scripts/deploy/`, Docker and Compose files. Only this job/folder receives these credential IDs: `esign-registry-push` (GHCR username and publish token), `esign-prod-ssh` (SSH private key), `esign-prod-known-hosts` (a verified SSH known-hosts file). Restrict production `input` approval to the Jenkins group `release-operators`.

Use SCM polling if Jenkins is private. If using a webhook, configure authenticated GitHub integration and test delivery without exposing the Jenkins dashboard openly. The release job uses the full checkout SHA, rejects a release if `main` advanced during approval, and tags the image with that SHA. Set `SCHEMA_BACKWARD_COMPATIBLE` only after reviewing every migration in that release; it permits automated restoration of the previous image after a migration has begun.

The build agent may need outbound access to Docker Hub, Packagist, npm, GHCR and EC2 SSH. Maintain a backup of Jenkins configuration and test restoring it independently of EC2.

## 3. Provision EC2

**AWS console / operator computer:** provision a supported Ubuntu LTS x86_64 t3.micro with encrypted EBS and a stable hostname. Record AMI and region. Require IMDSv2 for the instance role. Set its metadata response hop limit to 2 if the application container needs the role's S3 credentials; verify container access. AWS explains the extra Docker network hop in its [metadata guide](https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/instancedata-data-retrieval.html). Consider T3 Standard CPU credits to avoid surprise surplus CPU charges, understanding that sustained CPU can be throttled. Public security group ingress: 80/443 from the internet and 22 only from administrator/Jenkins egress addresses. No public PostgreSQL, Redis, PHP-FPM, app, Docker daemon, or Jenkins ports. Point the production DNS name to the instance before requesting certificates.

**EC2:** install Docker Engine and the Compose plugin from [Docker's Ubuntu instructions](https://docs.docker.com/engine/install/ubuntu/), AWS CLI, `curl`, `flock` (util-linux) and security updates. Enable Docker on boot. Create a dedicated `deploy` user and `/opt/esign/releases`, `/opt/esign/shared/maintenance`, `/opt/esign/backups`. Grant this user access to the Docker socket only after treating it as root-equivalent. Give the host's instance role permission to upload encrypted backups and access only the required private document bucket/prefix. Grant read-only GHCR access to the host via a credential stored outside Git; log in interactively as the deploy user before the first deployment. Test `docker pull` on a known permitted image once available.

**Operator computer:** create `/opt/esign/shared/.env.production` from [.env.production.example](../../.env.production.example). Generate `APP_KEY` once using `php -r 'echo "base64:".base64_encode(random_bytes(32)).PHP_EOL;'` and store it securely. Generate a separate long PostgreSQL password. Use the same value for `DB_PASSWORD` and `POSTGRES_PASSWORD`. Set a real HTTPS `APP_URL` and `APP_DOMAIN`, production mail settings and private S3 bucket. Keep `APP_DEBUG=false`, `QUEUE_CONNECTION=sync`, `DOCUMENTS_DISK=documents` and `OBSERVABILITY_ENABLED=false` unless external telemetry is ready. Configure Stripe/Pakasir and reCAPTCHA only through private settings; verify sandbox credentials before live payment processing. Never commit the production environment file. GHCR private packages currently require appropriately scoped classic PATs for external Docker clients; use `write:packages` for Jenkins and `read:packages` for EC2, and verify package access rights. See [GitHub's registry guide](https://docs.github.com/en/packages/working-with-a-github-packages-registry/working-with-the-container-registry).

Set owner `deploy` and mode `0600` on `.env.production`. Create `/opt/esign/shared/backup-uri` (also mode `0600`) containing only an `s3://bucket/prefix` destination. `scripts/deploy/backup.sh` needs `aws s3 cp` and `aws s3 ls` rights. Backups use S3 server-side encryption and are uploaded before each migration. Set bucket lifecycle retention and check it against legal/business needs; the script does not delete remote backups. Schedule additional regular backups if deployments are infrequent.

The Compose file injects Laravel settings from `.env.production` using `env_file`. Docker Compose also reads that file for `${APP_DOMAIN}` and PostgreSQL interpolation. The separate `release.env` contains only an immutable image digest, Git SHA, release sequence and compatibility flag. Keep `/opt/esign/shared` and the Docker named volumes through release changes. The container passes Laravel variables through PHP-FPM and creates config/view caches after it starts with production settings. The application image contains its own code and Vite assets, while the `storage/app` volume holds local application files.

## 4. Test in CI and build a release

**Jenkins agent or operator computer with Docker:** from a clean checkout, run:

```bash
bash scripts/ci/test.sh
```

The script creates an isolated Compose project, waits for PostgreSQL/Redis health, installs lockfile dependencies, checks Composer, runs Pint on changed PHP files, runs PHP and Vitest suites, then builds Vite assets. It writes JUnit reports into `ci-results/` and removes only its own disposable CI volumes. Existing repository-wide Pint debt is outside this release; run the full Pint check separately when that baseline is cleaned up. The Jenkins job archives reports even if a check fails.

On protected `main`, the Jenkinsfile builds the root production image for `linux/amd64` on the agent, confirms Vite manifest and runtime PHP extensions, publishes `<full-SHA>`, resolves the GHCR digest, then requests production approval. The `release.env` artifact has this exact four-line format:

```text
APP_IMAGE=ghcr.io/fikriislamyy/esign-saas@sha256:<64 hex characters>
RELEASE_SHA=<40 hex characters>
RELEASE_SEQUENCE=<increasing Jenkins build number>
ROLLBACK_COMPATIBLE=false
```

Do not hand-edit an approved manifest. The server scripts validate this structure and reject tags or images from a different registry path. Ensure the GHCR package is private and that the EC2 read-only identity can pull it. The `release.env` artifact is not a secret. For a local image rehearsal, build with `docker build --platform linux/amd64 --build-arg VCS_REF=<SHA> -t esign-local:<SHA> .` and run the same asset/extension checks; a local tag cannot pass the production digest validator.

## 5. First deployment and normal release

**Jenkins:** approve the trusted release only after CI passes. Jenkins checks that the approved SHA is still the tip of `main`, transfers only the Compose/Caddy/deployment files and release manifest to `/opt/esign/releases/<SHA>`, then runs `deploy.sh <SHA>` on EC2 over SSH with a preverified host key. It never runs `git pull` or builds on EC2.

**EC2:** the script holds `/opt/esign/deploy.lock`, rejects an older release sequence, pulls the digest, validates Compose, starts/waits for PostgreSQL and Redis, and uploads a PostgreSQL backup off-host. On an existing deployment it enables Caddy's maintenance response, enters Laravel maintenance mode and stops the old app, which also stops its one Supervisor scheduler. A one-off new-image container runs `php artisan migrate --force` once. The new app starts, `/ready` verifies database and Redis, the Vite manifest and running image are checked, and the script removes maintenance mode. Public HTTPS `/ready` must pass before the `current` symlink moves.

The first deployment has no previous image. If it fails, maintenance remains visible while the operator diagnoses it. Migrations should be additive and backward compatible because restoring an image does not undo database migrations. A failure after migration starts restores the old image automatically only when the approved manifest says `ROLLBACK_COMPATIBLE=true`. Otherwise leave maintenance on and inspect schema/data before manual recovery.

Check the actual host after approval:

```bash
docker compose -p esign-prod --env-file /opt/esign/shared/.env.production --env-file /opt/esign/current/release.env -f /opt/esign/current/docker-compose.prod.yml ps
curl -fsS https://<production-domain>/ready
docker stats --no-stream
df -h
```

Replace `<production-domain>` with the recorded hostname. The `/health` route is a liveness response; `/ready` verifies database and Redis. Test login/OTP, a small document upload, signing/PDF, compiled Vite assets and private file access with sandbox providers. Verify generated URLs and secure cookies behind HTTPS. Configure payment webhooks for this final hostname after sandbox tests.

## 6. Rollback and recovery

**EC2:** list `/opt/esign/releases` and read the nonsecret `release.env` of the target. Inspect migrations added since that release. If the old code can run against the current schema, run:

```bash
CONFIRM_SCHEMA_COMPATIBLE=yes bash /opt/esign/releases/<old-SHA>/scripts/deploy/rollback.sh <old-SHA>
```

The rollback script takes the same host lock, switches images under maintenance, verifies internal/public readiness and moves `current`. It does not roll back database migrations. If verification fails, it attempts to restore the release that was current when rollback began. If neither image starts, keep maintenance visible and use the backup/restore procedure. Do not delete named volumes.

**Restore rehearsal on disposable infrastructure:** download one uploaded `.dump` with `aws s3 cp s3://<bucket>/<object> ./restore.dump`; verify it with `pg_restore --list restore.dump`, start an isolated PostgreSQL 17 database, and run `pg_restore --clean --if-exists --no-owner --dbname=<disposable-database> restore.dump`. Confirm table counts and a read-only app smoke check. Never restore a deployment backup over a live database without a recovery decision and a fresh copy of current data. Include document-bucket objects in the recovery exercise.

For diagnostics, inspect `docker compose ... ps`, `docker compose ... logs --tail=100 app proxy postgres redis`, `docker stats --no-stream`, `df -h`, certificate status and AWS CPU credit metrics. Avoid commands that print the rendered Compose configuration or `.env.production`, because those reveal secrets. Check S3 backup existence and restore ability periodically.

## 7. Verification record to fill during rollout

| Check | Evidence / result |
| --- | --- |
| Jenkins CI on clean checkout | Pending external Jenkins setup |
| PHP/JS suites and reports | Record counts and report links |
| Production image digest and Git SHA | Record after registry publish |
| Restricted PR job cannot use release credentials | Pending Jenkins permission test |
| HTTPS and `/ready` | Pending EC2/domain setup |
| One scheduler after restart | Pending EC2 rehearsal |
| Memory/CPU/disk at idle and during PDF signing | Pending t3.micro rehearsal |
| Off-host backup and disposable restore | Pending AWS setup |
| Failed deployment and manual rollback | Pending disposable host rehearsal |

Current versions in repository: PHP 8.3, Node 22, PostgreSQL 17.6, Redis 7.4.5 and Caddy 2.10.0. Check supported patch versions and AWS pricing when provisioning. Do not state the pending checks passed until they have been performed on the real Jenkins/EC2 infrastructure.
