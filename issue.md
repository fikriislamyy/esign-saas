# Implement Jenkins CI/CD for deployment to AWS EC2 t3.micro

## Task and expected result

Implement a Jenkins pipeline that checks this project, builds a production Docker image, publishes it to a registry, and deploys an approved release to one AWS EC2 `t3.micro` instance. Write instructions that a junior programmer can follow from an empty server to a working HTTPS deployment and a tested rollback.

This issue is an implementation plan. Complete the steps in order, record actual command results, and do not mark deployment verification complete using only a successful image build.

## 1. Architecture and constraints

Use this initial architecture:

```text
GitHub -> Jenkins controller + separate build agent -> private image registry
                               |
                               +-- SSH deployment -> EC2 t3.micro
                                                      HTTPS proxy
                                                      application image
                                                      PostgreSQL + Redis
Documents and backups -> private object storage
```

- Run Jenkins and the build agent on an existing separate computer/server. A controller and agent may share that separate machine for this small project; run builds on the agent, with zero executors on the controller. Start with one build executor.
- EC2 runs production containers only. Do not install Jenkins, run Composer/npm builds, run tests, or run the local SigNoz stack there.
- A t3.micro has 2 vCPUs and 1 GiB RAM. Treat this deployment as a small, low-traffic installation whose capacity must be measured, especially during PDF signing. Jenkins recommends 4 GiB or more for a small team; its requirements reinforce the decision to separate it from production. Sources: [AWS T3 specifications](https://aws.amazon.com/ec2/instance-types/t3/), [Jenkins Linux installation](https://www.jenkins.io/doc/book/installing/linux/).
- Use a single application replica and accept a short maintenance window during releases. Do not implement blue/green deployment on this memory budget.
- Use GHCR as the initial private registry, with an image such as `ghcr.io/<owner>/esign-saas`. Use a full Git commit SHA tag and deploy the resolved immutable digest. Record owner/package permissions during setup.
- Keep PostgreSQL and Redis on EC2 initially, with persistent volumes and private networking. If measured usage exceeds available memory, document the evidence and obtain a hosting decision before moving services or resizing.
- Use a lightweight HTTPS proxy, for example Caddy, in front of the existing image's internal port 10000. Only the proxy publishes ports 80/443.
- Preserve `QUEUE_CONNECTION=sync` initially. Do not introduce a queue worker unless application queue behavior is changed in a separate task.
- Use existing private S3-compatible document storage. The repository's `documents` disk is S3-backed; setting `DOCUMENTS_DISK=local` is not a drop-in production design for all document flows.

### Free Tier and inputs to record

AWS Free Tier eligibility depends on account creation date, plan, region, and remaining allowance/credits. Accounts created before July 15, 2025 and newer accounts have different rules. Do not promise that a t3.micro deployment costs zero. Verify the account in the billing console before provisioning. See [EC2 Free Tier rules](https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/ec2-free-tier-usage.html) and [AWS Free Tier](https://aws.amazon.com/free/).

Record these values in the deployment runbook without secret values:

| Input | Required decision |
| --- | --- |
| AWS account/region | Eligibility, credit expiry, spending limit, budget alert recipient |
| Instance | Ubuntu LTS x86_64 AMI, t3.micro, EBS size, CPU credit mode |
| Jenkins host | Existing host, reachable agent, supported Jenkins LTS/Java versions |
| Domain | Production hostname and DNS control; stable IP allocation and its cost |
| Registry | Package path, push identity, read-only deployment identity |
| Data | New database or existing data import; private document bucket and backup destination |
| Dependencies | Production mail, payment/webhook, reCAPTCHA, and exchange-rate settings |
| Release policy | Protected `main`, permitted release approvers, maintenance window |

Account for EC2, EBS, snapshots, public IPv4, traffic, S3, registry storage, DNS, and any separate Jenkins hosting. Check T3 Unlimited CPU credit charges; use Standard mode initially if avoiding surplus CPU charges is the priority, documenting that CPU can be throttled. Billing alerts are notifications, not a hard spending cap. Avoid adding NAT Gateway, load balancer, or managed database infrastructure as an implicit dependency.

If the Jenkins machine or domain is not yet available, implement and test the repository artifacts locally, then report those deployment prerequisites precisely.

## 2. Repository facts the implementer must use

| Existing file | Finding and implication |
| --- | --- |
| `composer.json`, `composer.lock` | Laravel 10, PHP 8.3, PostgreSQL, Redis, S3, PDF and payment dependencies. Install from the lockfile. |
| `package.json`, `package-lock.json` | Vue/Inertia/Vite, Vitest; use `npm ci`, `npm test`, `npm run build`. Existing production build uses Node 22. |
| `Dockerfile` | Multi-stage production image: Composer dependencies, frontend assets, PHP-FPM + Nginx + Supervisor. Reuse it; inspect its runtime behavior before extending. |
| `docker/render/supervisord.conf` | Already starts `schedule:work` alongside Nginx/FPM. Adding a second scheduler would execute tasks twice. |
| `docker/render/nginx.conf` | Listens on 10000 and assumes HTTPS upstream. Verify proxy headers and Laravel trusted proxies. |
| `docker-compose.yml` | Development setup: source bind mounts, fixed container names, exposed database/Redis ports, Mailpit, separate scheduler. Do not deploy this file to production. |
| `docker/php/Dockerfile` | Development PHP image; can inform a separate CI image with development dependencies. |
| `.dockerignore` | Excludes environment secrets and dependencies, but must also exclude uploaded documents, local backups and CI reports from build context. |
| `phpunit.xml`, `tests/` | PHP tests need a disposable database and real Redis for OTP tests. No SQLite assumption. Explicitly disable telemetry/network export in CI. |
| `package.json` `test:all` | Hardcodes `docker exec esign-app`; do not use this command in isolated Jenkins builds. |
| `routes/web.php` | `/health` exists but currently returns static JSON, without testing database or Redis readiness. |
| `app/Console/Kernel.php` | Document and subscription expiry jobs use cache locks. Preserve one scheduler and working shared cache. |
| `config/filesystems.php`, `config/documents.php` | Documents use S3-compatible storage; audit runtime `env()` calls before enabling config cache. |
| `.env.example` | Missing at planning time. Supply a production example containing placeholders only; never copy the real `.env`. |
| `docs/observability.md` | Keep optional remote telemetry compatible; leave observability disabled unless an external endpoint is supplied. |

## 3. Deliverables

Create or update the following files. Keep deployment operations in scripts so they can be tested without Jenkins.

| File | Responsibility |
| --- | --- |
| `Jenkinsfile` | Trusted release pipeline: checkout, verification, build, publish, approval, deploy, reports |
| `docker-compose.ci.yml` | Isolated PHP test runner, Node runner if needed, PostgreSQL 17 and Redis, no host ports/fixed names |
| `docker/ci/Dockerfile` | PHP 8.3 test dependencies/extensions, including Redis; development Composer dependencies |
| `docker-compose.prod.yml` | Standalone runtime-only stack: proxy, app, PostgreSQL, Redis, health checks and named volumes |
| `docker/production/Caddyfile` | HTTPS, reverse proxy, persistent certificate storage |
| `docker/production/entrypoint.sh` | Runtime directory permissions and Laravel caches, then `exec` Supervisor; no migrations |
| `docker/production/php-fpm.conf` | Small worker pool tuned from actual memory measurements |
| `scripts/ci/test.sh` | Execute tests with isolated service names; export JUnit reports and clean up |
| `scripts/deploy/deploy.sh` | Validated digest deployment, server lock, backup, migration, activation and health checks |
| `scripts/deploy/rollback.sh` | Restore prior image/config release without destructive database rollback |
| `scripts/deploy/backup.sh` | Consistent PostgreSQL backup, encrypted off-host copy and retention |
| `.env.production.example` | Production Laravel/Compose setting names and safe placeholders |
| `docs/deployment/jenkins-aws.md` | Provisioning, credentials, first deploy, normal deploy, restore, recovery and costs |
| `Dockerfile`, `.dockerignore`, `README.md` | Production runtime wiring, safe build context, link to runbook |

Use explicit pinned versions for runtime images/plugins/toolchains and record why they were chosen. Resolve supported Jenkins LTS/Java combinations from official installation instructions at implementation time. Do not combine a Laravel major upgrade with this issue.

## 4. Implementation steps

### Step 1 — Capture a baseline

- [ ] Read the files above, repository instructions, and current branch changes.
- [ ] Run existing PHP and JavaScript suites in a disposable environment and record any baseline failure separately.
- [ ] Verify lockfiles are committed and identify actual PHP extensions needed by tests and PDF generation.
- [ ] Inventory required environment-variable names from configuration without printing `.env` or real credentials.
- [ ] Confirm the deployment inputs in section 1. Write down the first-deploy data-import decision.

Done when: another programmer can identify the required versions, services, environment names, and baseline checks from the runbook.

### Step 2 — Make CI independent of the developer machine

- [ ] Create CI Compose services with no `container_name`, source-host assumptions, or published PostgreSQL/Redis ports.
- [ ] Use a unique sanitized Compose project name per build, such as `esign-ci-<build-number>`, and unique volumes/networks. Keep the project name stable across stages of that build.
- [ ] Start PostgreSQL 17 and Redis; wait for actual health checks with a bounded timeout.
- [ ] Give tests an isolated database, temporary `APP_KEY`, Redis database, `APP_ENV=testing`, fake mail and `OBSERVABILITY_ENABLED=false`. Never bind production credentials into test containers.
- [ ] Install Composer development dependencies with `composer install --no-interaction --prefer-dist`, and Node dependencies with `npm ci`. Do not run dependency update commands.
- [ ] Install Composer dependencies before Vite builds: Ziggy imports from `vendor`.
- [ ] Run `composer validate --no-check-publish`, `vendor/bin/pint --test`, `php artisan test --log-junit=...`, `npm test -- --reporter=default --reporter=junit --outputFile=...`, and `npm run build`. Verify report flags against installed tools.
- [ ] Run database-backed PHP tests sequentially initially. Do not allow two suites to migrate the same test database concurrently.
- [ ] Export reports into a Jenkins-readable workspace directory, including on failure. Do not bake reports into the production image.
- [ ] In cleanup, remove only this build's CI containers/networks/volumes. Never execute broad Docker prune commands on a shared agent.

Done when: checks run from a clean checkout with no existing `esign-app` container, failures fail the build, and two isolated builds cannot affect each other's database.

### Step 3 — Prepare an immutable production image

- [ ] Reuse the root multi-stage Dockerfile; keep PHP 8.3 and the existing frontend build working. Build `linux/amd64` for t3.micro.
- [ ] Ensure the final image contains built `public/build` assets, production Composer dependencies, correct autoloading and runtime extensions. It must not contain `.env`, tests' secrets, uploaded files, backups, `node_modules`, or cached configuration from a developer machine.
- [ ] Add OCI revision labels for the Git SHA and capture the registry digest after publishing.
- [ ] Add a runtime entrypoint that prepares writable directories and builds configuration/view caches only after production settings are available. Validate route caching against existing routes before enabling it.
- [ ] Audit `env()` outside `config/` and replace deployment-relevant calls with `config()` access where necessary. Verify the chosen document disk with configuration cached.
- [ ] Keep cache directories release-local. Persist user/application files that need durability, but do not share compiled views or `bootstrap/cache` across old/new images.
- [ ] Do not generate `APP_KEY`, run migrations, or run seeders automatically at container startup.
- [ ] Verify Nginx and PHP-FPM both receive required runtime settings. Add `/health` container checks and a separate bounded readiness check for database/Redis connectivity; return generic failures without exposing credentials.
- [ ] Preserve the existing Supervisor scheduler as the only scheduler. Ensure maintenance mode stops scheduled task execution and stop the old app process before running migrations.

Done when: the image boots with injected configuration, serves compiled assets, and requires no repository bind mount or dependency installation on EC2.

### Step 4 — Define and measure the production stack

- [ ] Create `docker-compose.prod.yml` as a standalone file, not an overlay that inherits development ports, names, source mounts or Mailpit.
- [ ] App uses `image: ${APP_IMAGE}` with a digest reference and no `build:` key. Proxy reaches app:10000 over an internal network.
- [ ] Only publish 80/443. Keep PostgreSQL 5432, Redis 6379, PHP-FPM 9000 and app 10000 private.
- [ ] Persist PostgreSQL data, Redis data where required by OTP/cache/session behavior, certificate state and required application storage in stable named volumes. Volume names must survive release-directory changes; always use the same production Compose project name.
- [ ] Use explicit non-default database credentials, service health checks, `restart: unless-stopped`, and bounded Docker log rotation.
- [ ] Start with a small PHP-FPM pool (for example, two workers), conservative PostgreSQL connections and bounded Redis usage. Set and test Compose memory limits; do not assume limits reserve memory or prove the stack fits.
- [ ] Measure idle use, login/OTP, PDF upload/signing, migration and scheduler execution. Leave OS/Docker headroom within 1 GiB. Record actual peak memory, swap and CPU credit behavior. Optional swap can reduce abrupt failures but is not a capacity guarantee.
- [ ] Verify worker count and PHP memory limits against real PDFs; report capacity failure instead of silently deploying a configuration that OOM-kills signing jobs.
- [ ] Rotate logs and retain current/previous release images. Check free disk before pulls and backups.

Done when: a staging rehearsal on equivalent resources demonstrates acceptable behavior and persistence across container replacement.

### Step 5 — Provision the EC2 host and secrets

1. Verify Free Tier/billing settings; create budget alerts and record estimated recurring costs.
2. Provision the selected Ubuntu x86_64 t3.micro, encrypted EBS and a stable hostname/IP arrangement. Require IMDSv2 if using an EC2 role.
3. Configure the security group: public 80/443; SSH only from the administrator and Jenkins agent's approved addresses. Never expose Jenkins, database, Redis or Docker daemon ports on this host.
4. Install Docker Engine and Compose plugin using official Ubuntu instructions. Enable startup on reboot and configure host updates/time synchronization.
5. Create a dedicated deploy account and `/opt/esign/{releases,shared}`. Restrict secret file access. Treat Docker group membership as root-equivalent; use a narrowly controlled deploy identity and trusted release jobs.
6. Store production environment values in `/opt/esign/shared/.env.production`, readable only by intended operators/runtime. Use a separate release manifest for `APP_IMAGE`, SHA and deployment metadata. Explain Compose interpolation versus service `env_file`; validate required settings with `config --quiet` without printing rendered secrets.
7. Set `APP_ENV=production`, `APP_DEBUG=false`, correct HTTPS `APP_URL`, secure session cookies, PostgreSQL/Redis service names, real mail settings and stable document storage. Generate `APP_KEY` once through an operator setup step and back it up securely; never regenerate on release.
8. Configure private document bucket access. Prefer a scoped EC2 IAM role if compatible with the storage provider and SDK; otherwise inject scoped storage credentials. Verify actual container access and keep the bucket private.
9. Configure a registry read-only credential for pulls. Keep registry push permissions on the trusted Jenkins agent only.
10. Point DNS to EC2, bring up the proxy and verify TLS issuance/renewal. Test HTTPS redirects, secure cookies and application-generated URLs behind the proxy.
11. Configure off-host encrypted PostgreSQL backups and document-storage backup/versioning policy. Perform a restore to a disposable database before first production release.

Done when: infrastructure, DNS, TLS, private networking, secret injection, registry pulls and restore access are ready. No production data is created by CI tests.

### Step 6 — Configure Jenkins and trust boundaries

- [ ] Install supported Jenkins LTS and Java on the separate host; secure login, HTTPS or private access, controller backups and persistent Jenkins home.
- [ ] Install only needed plugins: Pipeline, Git, Credentials Binding, SSH Agent, JUnit and the chosen branch-source/registry integration. Record versions and credential IDs in the runbook.
- [ ] Configure a Docker-capable Linux build agent. Do not mount the production host's Docker socket into Jenkins. Never expose an unauthenticated Docker TCP daemon.
- [ ] Create separate jobs/trust scopes: PR/branch CI gets no production or publishing credentials; the trusted `main` release job gets scoped publishing/deployment credentials. Untrusted Jenkinsfiles must not be able to request the trusted agent or its credentials.
- [ ] Use branch protection/review for changes to Jenkinsfile and deployment scripts. Do not rely only on `when { branch 'main' }` to protect secrets from untrusted code.
- [ ] Credential IDs: `esign-git-read` if needed, `esign-registry-push`, `esign-prod-ssh`, and a managed verified SSH known-hosts file. Provision the server's read-only registry identity separately.
- [ ] Verify the SSH host fingerprint through the AWS/operator channel. Do not use `StrictHostKeyChecking=no` or blindly trust a key fetched during deployment.
- [ ] Prefer SCM polling initially when Jenkins is private. If using GitHub webhooks, document secure routing, plugin authentication and delivery tests; do not expose an unsecured controller to receive pushes.

Done when: a PR can run checks but cannot read production credentials, publish releases, or reach the deployment host.

### Step 7 — Implement the Jenkinsfile

Execute these stages in order:

1. **Checkout:** check out the exact SCM revision and record its full SHA. Clean the workspace of artifacts from previous builds.
2. **Validate and test:** run Step 2. Publish JUnit results even when tests fail. A failed check blocks all later release stages.
3. **Build:** build the production image once for that SHA; run a container smoke test with disposable dependencies and synthetic settings. Check assets and runtime extensions.
4. **Publish:** on the trusted release job only, log in using scoped credentials, push the SHA-tagged image, resolve its digest, and archive a nonsecret release manifest. Use password-stdin and avoid shell tracing/Groovy interpolation of secrets.
5. **Approve production:** initial rollout policy is an explicit Jenkins `input` approval restricted to release operators with a timeout. Display SHA/digest and migration notes. Do not hold credentials open while waiting. Automatic deployment may be enabled later by an explicit policy change.
6. **Deploy:** send the manifest/scripts for that same SHA to a release directory and invoke the server deployment script over verified SSH. Do not `git pull main` on EC2 or rebuild there.
7. **Verify/report:** archive the deployed digest, sanitized readiness/smoke output, migration result and rollback result where applicable. Report failure honestly if rollback was needed.
8. **Always cleanup:** close registry sessions, remove this build's temporary credentials and test resources, retain reports, apply build/artifact retention.

Use pipeline timeouts, timestamps and `disableConcurrentBuilds()` without aborting a running deployment. Add a host-side `flock` deployment lock covering normal deploy and rollback. Check under the lock that an older queued release cannot overwrite a newer release; allow intentional rollback only through the explicit rollback path.

Done when: test failure, rejected/timed-out approval, bad SSH credentials, registry failure and deployment failure all stop the appropriate stage without leaking secrets.

### Step 8 — Implement deployment and rollback scripts

Write Bash scripts with strict error handling, quoted arguments and validation of SHA/digest/path inputs. Do not `eval` the manifest. Use bounded waits and retain previous release metadata before changing anything.

Deployment sequence under the server lock:

1. Validate target manifest, trusted registry path, required files, disk space and database/Redis health. Pull the new digest while the old release is still serving. If pull/preflight fails, leave the old release running.
2. Record current image digest and matching Compose/proxy configuration. Take a consistent PostgreSQL backup using `pg_dump`, verify command success and transfer to the off-host destination. Never put credentials into archive names or reports.
3. Serve a deliberate maintenance response from the proxy, put Laravel into maintenance mode and stop the old application container so its scheduler also stops. Keep PostgreSQL, Redis and the proxy running.
4. Run `php artisan migrate --force` exactly once using a one-off container of the new digest, the production environment/network and an overridden command that does not start Supervisor. Do not use `migrate:fresh`, seeders, or automatic `migrate:rollback`.
5. Start the new app with the new digest and runtime entrypoint. Ensure any persisted Laravel maintenance flag is cleared deliberately before normal traffic resumes.
6. Wait for container health and application readiness, then remove proxy maintenance mode. Verify public HTTPS `/health`, database/Redis readiness, a compiled asset and the expected deployed image digest. Use bounded deadlines; HTTP 200 from the existing static health route alone is insufficient.
7. Mark the release current only after verification succeeds. Retain the previous working release. Clean old images/backups only according to documented retention, preserving current and rollback images and all persistent volumes.

Failure handling:

- Before migrations: restore availability of the old release and exit nonzero.
- During/after migrations: database changes may already be committed. Use backward-compatible additive migrations so the previous image can run against the new schema. Document migration compatibility as a release requirement.
- If activation/health fails and schema compatibility is established, start the recorded previous image/configuration, clear maintenance state appropriately, verify it, restore traffic, and report the attempted release failed.
- If compatibility is unknown or rollback fails, keep a clear maintenance response, preserve logs/backups and require operator recovery. Do not claim automatic recovery or blindly restore an old database over new transactions.
- First deployment has no previous image; failure must be reported as such with a recovery path.
- Never run `docker compose down -v` against production. Image rollback must not delete or recreate database/document storage.

Done when: both a successful upgrade and a deliberately failing upgrade have been rehearsed on disposable data, including restoration of service and exactly one scheduler.

### Step 9 — Verify and document operation

Add focused checks for deployment scripts and health behavior. Run ShellCheck for shell scripts, Compose validation and Jenkins Declarative Pipeline validation using the installed Jenkins version. Use a fake Docker/SSH command harness for failure paths, then rehearse actual containers on a disposable host.

| Scenario | Required evidence |
| --- | --- |
| Fresh checkout | All PHP/JS checks and production image build pass without local dependencies |
| Failed test | No publish or deploy attempt |
| Untrusted PR | No production credentials or release-agent access |
| Immutable release | Built, published and deployed SHA/digest match |
| Failed image pull | Old service remains available |
| Migration failure | No blind database rollback; documented recovery and failed Jenkins result |
| Failed health check | Compatible previous release restored; Jenkins still reports failed release |
| Concurrent/reordered deploy | Server lock and stale-release check prevent interference |
| Host reboot/container replacement | Database/files/certificates persist; app and one scheduler recover |
| Authentication and signing | HTTPS login/OTP, upload, signature/PDF, private file access work with sandbox providers |
| Resource capacity | Peak memory/disk/CPU observations fit the agreed t3.micro workload |
| Backup restore | Restored disposable database is usable; document recovery procedure verified |
| Secrets | Image layers, build artifacts and Jenkins output contain no production secrets |

Document exact commands with placeholders, which machine each command runs on, first-deploy ordering, credential IDs, expected outputs, common failures, manual deploy/rollback, backup restore, log access, scheduler checks and cost monitoring. Explain that provider webhooks need the final HTTPS hostname and sandbox verification before live payments. Do not send real customer mail or create real charges in CI or deployment checks.

## 5. Acceptance criteria

- [ ] Jenkins reliably runs PHP/JS validation and blocks release on failure.
- [ ] Production builds happen outside the t3.micro; EC2 pulls a tested immutable image.
- [ ] Only trusted reviewed releases can publish/deploy; production approval policy is documented.
- [ ] Production runs over HTTPS with private database/Redis and stable persistent data.
- [ ] Production secrets stay outside Git, images and test jobs; `APP_KEY` survives redeployments.
- [ ] Migrations, scheduler lifecycle, readiness and application caching work correctly during deployment.
- [ ] Rollback is demonstrated and explicitly accounts for database compatibility.
- [ ] Backup restore, reboot recovery and t3.micro resource capacity are demonstrated.
- [ ] A junior developer can reproduce installation and recovery from the runbook.
- [ ] Final report lists changes, exact verification results, deployed SHA/digest if applicable, and any blocked external setup. Do not describe unperformed AWS/Jenkins checks as passed.

## 6. Suggested implementation batches

1. CI test environment and reports.
2. Production image, Compose configuration, environment example and readiness.
3. Deployment/backup/rollback scripts and failure-path verification.
4. Jenkins pipeline, credentials and trust configuration.
5. EC2 rehearsal, resource measurements, first deployment and runbook evidence.

## Official references

- [Jenkins Pipeline syntax](https://www.jenkins.io/doc/book/pipeline/syntax/) — stages, approvals, timeouts and concurrency.
- [Jenkins credentials](https://www.jenkins.io/doc/book/using/using-credentials/) — credential types and IDs.
- [Jenkins Docker pipelines](https://www.jenkins.io/doc/book/pipeline/docker/) — Docker-capable agents and registries.
- [Docker Compose in production](https://docs.docker.com/compose/how-tos/production/) — production service configuration.
- [Compose startup order](https://docs.docker.com/compose/how-tos/startup-order/) — dependency readiness and health checks.
- [Laravel 10 deployment](https://laravel.com/docs/10.x/deployment) — production settings and configuration caching.

Sources checked on 2026-09-27. Recheck AWS eligibility, supported Jenkins/Java versions, registry permissions and pricing when implementing.
