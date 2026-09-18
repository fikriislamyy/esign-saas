# Two scheduled cleanup jobs: document expiry and subscription expiry

## 1. What we are building

Two Artisan commands that run on a daily schedule.

1. **`documents:expire`** — finds documents older than 3 days, marks them `expired`, and deletes
   their files from S3. Signed documents are exempt (see 3.1).
2. **`subscriptions:expire`** — finds `pro` / `enterprise` subscriptions whose `expired_at` has
   passed and puts them back on `free`.

Plus the plumbing that makes scheduled commands actually run, because **this project has no
scheduler process at all today**. If you skip Phase 6, both commands will be perfectly written
and will never execute once.

**Definition of done:**

- `php artisan documents:expire --dry-run` prints what it would do and changes nothing.
- `php artisan documents:expire` marks old draft/sent documents `expired` and their S3 files are
  gone.
- A `completed` document from last year is still downloadable. It is never touched.
- The Plan page storage bar goes **down** after documents expire.
- `php artisan subscriptions:expire` moves a lapsed Pro org to Free.
- A Pro org whose Stripe subscription is still live is **not** downgraded.
- Running either command twice in a row produces no errors and no double work.
- `docker compose up` starts a container that runs the schedule.

**Read Section 3 and Section 4 before writing code.** Section 4 contains three things that will
silently produce a broken result rather than an error message.

---

## 2. Before you write any code

### Everything runs in Docker

Every command in this document is run inside the app container:

```bash
docker compose exec app php artisan <command>
```

If you run `php artisan` on your host machine it will fail to reach Postgres.

### Where things live

| Thing | Path |
| --- | --- |
| Commands you will create | `app/Console/Commands/` |
| Schedule registration | `app/Console/Kernel.php` |
| Document model | `app/Models/Document.php` |
| Subscription model | `app/Models/Subscription.php` |
| Limit calculations | `app/Services/PlanService.php` |
| Local container setup | `docker-compose.yml` |
| Production process list | `docker/render/supervisord.conf` |

### There are no existing commands

`app/Console/Commands/` does not exist yet. `Kernel::schedule()` is empty. You are creating the
first scheduled work in this project, which is why Phase 6 exists.

### How to create a command

```bash
docker compose exec app php artisan make:command ExpireDocuments
```

This writes `app/Console/Commands/ExpireDocuments.php`. Edit the `$signature` and `$description`
properties, then put your logic in `handle()`.

---

## 3. Decisions already made (do not re-litigate)

### 3.1 Only `draft` and `sent` documents expire

`completed` documents keep their files forever. A completed document has a `signed_path` holding
the executed contract — that artifact is the entire point of this product. Deleting it would mean
a customer who signs on Monday cannot download their contract on Friday, and nothing can
regenerate it.

`cancelled` documents also keep their files for now. They are rare and nothing was executed, so
they are not worth the extra risk.

**Your query filters on `whereIn('status', ['draft', 'sent'])`. Not on "everything older than 3
days".**

### 3.2 The database row survives; only the files are deleted

An expired document keeps its row, its name, its `file_size`, and its `file_path`. Only the S3
objects go away. This preserves the audit trail — you can still answer "what was this and how big
was it" after the fact.

### 3.3 Storage frees up by excluding expired rows from the sum

This is the part that makes expiry actually reclaim quota. See Trap 2. You will change one line
in `PlanService::storageUsedBytes()`.

Do **not** zero out `file_size` to achieve this. That destroys information for no benefit.

### 3.4 An expired subscription becomes `plan=free`, `status=cancelled`

Mirror exactly what `StripeWebhookController::handleSubscriptionDeleted()` already does:

```php
['plan' => 'free', 'status' => 'cancelled', 'cancelled_at' => now()]
```

Using the same shape means the Plan page already renders it correctly — `cancelled` is already in
the status label and badge-variant maps in `resources/js/Pages/Plan/Index.vue`. Inventing a new
`expired` status would require UI changes for no gain.

### 3.5 Both commands must be safe to run twice

Schedulers get retried, restarted, and run manually by panicking humans. Running either command a
second time must be a no-op, not an error. Both queries naturally achieve this — after the first
run the rows no longer match the filter — so just do not break that property.

### 3.6 Both commands need a `--dry-run` flag

These commands delete files and downgrade paying customers. You must be able to see what they
would do before letting them do it. This is not optional polish.

---

## 4. The traps

### Trap 1 — the database will reject the word `expired`

`documents.status` looks like a plain string column, but Postgres is enforcing a CHECK
constraint that Laravel's `enum()` created:

```
CHECK (status::text = ANY (ARRAY['draft','sent','completed','cancelled']::text[]))
```

`$document->update(['status' => 'expired'])` throws a `QueryException` until you change that
constraint. **Phase 1 exists solely to fix this, and it must be done first.**

`subscriptions.status` has no such constraint, so Phase 4 needs no migration.

### Trap 2 — deleting the file does not reduce storage usage

This is the most important trap in this document, because it fails *silently* and produces an
absurd result: an organization stuck over quota with an empty S3 bucket.

`PlanService::storageUsedBytes()` currently does this:

```php
return (int) Document::query()
    ->where('organization_id', $organization->id)
    ->sum('file_size');
```

It sums **every** document row regardless of status. Delete a thousand S3 objects and this number
does not move by a single byte, because `file_size` is still sitting on every row.

Fix it by excluding expired rows (Phase 2, step 3). Until you do, the storage bar on the Plan page
is a lie.

### Trap 3 — every document can have two files, not one

`file_path` holds the uploaded original. `completed` documents *also* have `signed_path` holding
the executed version. Both are real S3 objects.

Because of decision 3.1 you only expire `draft` and `sent` documents, which normally have no
`signed_path`. But a partially-signed `sent` document may already have one. Delete both paths,
and skip any that are null.

### Trap 4 — S3 deletes fail silently

`config/filesystems.php` sets `'throw' => false` on the `documents` disk. That means:

```php
$disk->delete($path);   // returns false on failure. Never throws.
```

If you ignore the return value you will mark documents `expired`, leave the files sitting on S3
costing money, and log nothing. **Check the return value and log failures.**

Note: deleting an object that does not exist returns `true`. That is correct and is what makes the
command safe to re-run.

### Trap 5 — `env()` returns null when config is cached

`DocumentController` reads the disk name with `env('DOCUMENTS_DISK', 'documents')`. In a console
command running on a production box with `php artisan config:cache` applied, `env()` returns
**null**, so `Storage::disk(null)` falls through to the default `local` disk — and your delete
loop quietly does nothing to S3.

Phase 2 step 1 creates `config/documents.php`. Use `config()`, never `env()`, outside of config files.

(The existing `DocumentController` has this same latent bug. Leave it alone — it is not this
task's job to fix, and it currently works because config is not cached here.)

### Trap 6 — do not downgrade a customer Stripe is still billing

`subscriptions.expired_at` is a cached copy of Stripe's period end, kept fresh by the
`invoice.paid` webhook. If that webhook is delayed, fails, or was never registered, `expired_at`
goes stale while the customer is being charged perfectly well.

A naive `where('expired_at', '<', now())` downgrade would then strip Pro from someone who just
paid. For `provider = 'stripe'` rows you must confirm with Stripe before downgrading. Code is in
Phase 4.

Pakasir subscriptions cannot auto-renew, so they are the normal case for this command and need no
such check.

### Trap 7 — do not load every document into memory

`Document::where(...)->get()` on a table with a million rows will exhaust the container's memory
limit. Use `chunkById(100, ...)`, which is already in the code samples below. Do not "simplify" it
away.

---

## 5. Phase 1 — Let the database accept `expired`

Create the migration:

```bash
docker compose exec app php artisan make:migration add_expired_to_documents_status_check
```

Replace the generated file's contents:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE documents DROP CONSTRAINT documents_status_check');

        DB::statement(
            "ALTER TABLE documents ADD CONSTRAINT documents_status_check
             CHECK (status::text = ANY (ARRAY['draft','sent','completed','cancelled','expired']::text[]))"
        );
    }

    public function down(): void
    {
        DB::statement("UPDATE documents SET status = 'cancelled' WHERE status = 'expired'");

        DB::statement('ALTER TABLE documents DROP CONSTRAINT documents_status_check');

        DB::statement(
            "ALTER TABLE documents ADD CONSTRAINT documents_status_check
             CHECK (status::text = ANY (ARRAY['draft','sent','completed','cancelled']::text[]))"
        );
    }
};
```

The `down()` method rewrites expired rows before restoring the narrower constraint, because
otherwise rolling back fails on any row that already says `expired`.

Run it:

```bash
docker compose exec app php artisan migrate
```

### Checkpoint

```bash
docker compose exec app php artisan tinker --execute="
\$d = App\Models\Document::first();
\$old = \$d->status;
\$d->update(['status' => 'expired']);
echo 'accepted'.PHP_EOL;
\$d->update(['status' => \$old]);
"
```

Prints `accepted`. If you get a `QueryException`, the migration did not apply.

---

## 6. Phase 2 — The document expiry command

### Step 1 — create `config/documents.php`

Both settings this command needs go in one new file:

```php
<?php

return [
    // Trap 5: read through config(), never env(), from a console command.
    'disk' => env('DOCUMENTS_DISK', 'documents'),

    // Decision 3.1 applies this to draft and sent documents only.
    'retention_days' => 3,
];
```

**Do not put `retention_days` in `config/plans.php`.** `SubscriptionController::index()` sends
`config('plans')` to the browser wholesale, and `PlanPickerDialog.vue` renders a plan card for
every top-level key it finds. A scalar key there produces a broken fourth card and a JavaScript
error on `plan.limits.documents`. `config/plans.php` holds plans and nothing else.

### Step 2 — write the command

```bash
docker compose exec app php artisan make:command ExpireDocuments
```

`app/Console/Commands/ExpireDocuments.php`:

```php
<?php

namespace App\Console\Commands;

use App\Models\Document;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ExpireDocuments extends Command
{
    protected $signature = 'documents:expire
                            {--days= : Override the retention window}
                            {--dry-run : Report what would happen without changing anything}';

    protected $description = 'Expire unsigned documents past the retention window and delete their files';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('documents.retention_days'));
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subDays($days);

        $disk = Storage::disk(config('documents.disk'));

        $expired = 0;
        $deleteFailures = 0;

        $this->info(($dryRun ? '[DRY RUN] ' : '')."Expiring draft/sent documents created before {$cutoff}");

        Document::query()
            // Decision 3.1: signed work is never destroyed.
            ->whereIn('status', ['draft', 'sent'])
            ->where('created_at', '<', $cutoff)
            ->chunkById(100, function ($documents) use ($disk, $dryRun, &$expired, &$deleteFailures) {
                foreach ($documents as $document) {
                    if ($dryRun) {
                        $this->line("  would expire {$document->id} ({$document->name})");
                        $expired++;

                        continue;
                    }

                    // Trap 3: a part-signed document has two objects on S3.
                    foreach ([$document->file_path, $document->signed_path] as $path) {
                        if (! $path) {
                            continue;
                        }

                        // Trap 4: this disk never throws, it returns false.
                        if (! $disk->delete($path)) {
                            $deleteFailures++;

                            Log::warning('Could not delete expired document file', [
                                'document_id' => $document->id,
                                'path' => $path,
                            ]);
                        }
                    }

                    $document->update(['status' => 'expired']);
                    $expired++;
                }
            });

        $this->info("Documents expired: {$expired}");

        if ($deleteFailures > 0) {
            $this->warn("Files that could not be deleted: {$deleteFailures} (see log)");
        }

        return self::SUCCESS;
    }
}
```

### Step 3 — make storage actually drain (Trap 2)

In `app/Services/PlanService.php`, change `storageUsedBytes()`:

```php
public function storageUsedBytes(Organization $organization): int
{
    return (int) Document::query()
        ->where('organization_id', $organization->id)
        // Expired documents have no files on S3, so they occupy no quota.
        ->where('status', '!=', 'expired')
        ->sum('file_size');
}
```

One line. Without it, Phase 2 deletes files and frees nothing.

### Checkpoint

```bash
docker compose exec app php artisan documents:expire --dry-run --days=0
```

`--days=0` makes everything look expired, so you should see a list of documents it *would* expire
and no changes. Confirm nothing actually changed, then try a real run against a throwaway
document.

---

## 7. Phase 3 — Stop serving expired documents

Once files are gone, the download routes will ask S3 for objects that no longer exist. Because the
disk has `'throw' => false`, this produces a confusing empty response rather than a clean error.

In `app/Http/Controllers/DocumentController.php`, find the download/preview methods (around lines
467, 498 and 526 — they each start by reading `$document->signed_path` or `$document->file_path`).
Each already opens with an `abort_unless(...403)` ownership check. Add this immediately after that
check, before `$filePath` is read:

```php
abort_if($document->status === 'expired', 410, 'This document has expired and its file was removed.');
```

410 Gone is the correct status: the resource existed, we know it existed, and it is permanently
gone. That is more useful to a client than a 404.

---

## 8. Phase 4 — The subscription expiry command

```bash
docker compose exec app php artisan make:command ExpireSubscriptions
```

`app/Console/Commands/ExpireSubscriptions.php`:

```php
<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Services\StripeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpireSubscriptions extends Command
{
    protected $signature = 'subscriptions:expire
                            {--dry-run : Report what would happen without changing anything}';

    protected $description = 'Return lapsed paid subscriptions to the free plan';

    public function handle(StripeService $stripe): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $downgraded = 0;
        $skipped = 0;

        Subscription::query()
            ->whereIn('plan', ['pro', 'enterprise'])
            ->whereNotNull('expired_at')
            ->where('expired_at', '<', now())
            ->chunkById(100, function ($subscriptions) use ($stripe, $dryRun, &$downgraded, &$skipped) {
                foreach ($subscriptions as $subscription) {
                    // Trap 6: Stripe is the source of truth for Stripe subscriptions.
                    // A stale expired_at means a missed webhook, not a lapsed customer.
                    if ($subscription->provider === 'stripe' && $subscription->stripe_subscription_id) {
                        if ($this->stillActiveAtStripe($stripe, $subscription)) {
                            $skipped++;

                            continue;
                        }
                    }

                    if ($dryRun) {
                        $this->line("  would downgrade org {$subscription->organization_id} from {$subscription->plan}");
                        $downgraded++;

                        continue;
                    }

                    // Decision 3.4: same shape the Stripe cancellation webhook writes.
                    $subscription->update([
                        'plan' => 'free',
                        'status' => 'cancelled',
                        'cancelled_at' => now(),
                    ]);

                    $downgraded++;
                }
            });

        $this->info("Subscriptions downgraded: {$downgraded}");

        if ($skipped > 0) {
            $this->warn("Still active at Stripe, left alone: {$skipped} (a webhook was probably missed)");
        }

        return self::SUCCESS;
    }

    protected function stillActiveAtStripe(StripeService $stripe, Subscription $subscription): bool
    {
        try {
            $remote = $stripe->client()->subscriptions->retrieve(
                $subscription->stripe_subscription_id,
                []
            );
        } catch (\Throwable $e) {
            // Cannot confirm, so do not downgrade. A paying customer keeping Pro for
            // one more day is a far cheaper mistake than wrongly stripping their plan.
            Log::warning('Could not verify subscription at Stripe; leaving it alone', [
                'subscription_id' => $subscription->id,
                'message' => $e->getMessage(),
            ]);

            return true;
        }

        if (in_array($remote->status, ['active', 'trialing'], true)) {
            Log::warning('Subscription looked expired locally but is active at Stripe', [
                'subscription_id' => $subscription->id,
                'stripe_subscription_id' => $subscription->stripe_subscription_id,
            ]);

            return true;
        }

        return false;
    }
}
```

### Why this command exists at all

`Subscription::effectivePlan()` **already** returns `free` when `expired_at` is in the past, and
`SubscriptionController::index()` already sends `effectivePlan()` to the UI. So limits are not
leaking today, and the Plan page already shows the right thing.

What this command adds is making the *stored* `plan` column agree with reality, so that reporting
queries, exports, and anything that reads `plan` directly are not wrong. Do not expect a visible
UI change from this command — if you see one, something else is broken.

### Checkpoint

```bash
docker compose exec app php artisan subscriptions:expire --dry-run
```

On a clean database this prints `Subscriptions downgraded: 0`. To test properly, hand-set a
subscription's `expired_at` into the past **and** set `provider` to something other than `stripe`
so the Stripe check is skipped.

---

## 9. Phase 5 — Register both on the schedule

`app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('documents:expire')
        ->dailyAt('02:00')
        ->withoutOverlapping()
        ->onOneServer();

    $schedule->command('subscriptions:expire')
        ->dailyAt('02:30')
        ->withoutOverlapping()
        ->onOneServer();
}
```

- `withoutOverlapping()` stops a second run starting while a slow one is still going.
- `onOneServer()` matters the moment you run more than one container. It needs a shared cache
  lock, which Redis already provides here.
- The two are half an hour apart so their logs do not interleave while you are debugging.

Confirm they registered:

```bash
docker compose exec app php artisan schedule:list
```

Both commands should appear with their next run time.

---

## 10. Phase 6 — Make the scheduler actually run

**Nothing in this project runs scheduled commands today.** `docker-compose.yml` has `app`,
`nginx`, `postgres`, `redis` and `mailpit`. `docker/render/supervisord.conf` runs `php-fpm` and
`nginx`. No cron, no `schedule:work`. Skip this phase and Phases 1–5 are decorative.

### Local

Add to `docker-compose.yml`, at the same indentation as the other services:

```yaml
    scheduler:
        build:
            context: .
            dockerfile: docker/php/Dockerfile

        container_name: esign-scheduler

        volumes:
            - .:/var/www

        working_dir: /var/www

        command: php artisan schedule:work

        depends_on:
            - postgres
            - redis
```

`schedule:work` is a foreground process that wakes every minute and runs whatever is due. It is
the modern replacement for a crontab entry and needs no cron installed in the image.

Start it:

```bash
docker compose up -d scheduler
docker compose logs -f scheduler
```

### Production

Add to `docker/render/supervisord.conf`:

```ini
[program:scheduler]
command=php /var/www/artisan schedule:work
priority=30
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
```

`/var/www` is correct — the root `Dockerfile` sets that as `WORKDIR`.

---

## 11. Verification

Work through this list before opening a pull request. Do it against a database you are willing to
damage.

**Document expiry**

1. Upload a document. In tinker, set its `created_at` to 10 days ago.
2. `php artisan documents:expire --dry-run` lists it and changes nothing. Confirm status is still
   `draft`.
3. Note the Plan page storage figure.
4. `php artisan documents:expire`. Status becomes `expired`.
5. The Plan page storage figure has **gone down**. If it has not, you skipped Phase 2 step 3.
6. The S3 object is gone. Downloading it returns 410, not a 500.
7. Run the command again. It reports 0 and does not error.
8. Create a `completed` document dated a year ago. Run the command. **It must be untouched and
   still downloadable.** This is the single most important check in this document.

**Subscription expiry**

9. Set an org to `plan=pro`, `provider=pakasir`, `expired_at` yesterday.
10. `--dry-run` lists it, changes nothing.
11. Real run sets `plan=free`, `status=cancelled`, `cancelled_at` populated.
12. Set an org to `plan=pro`, `provider=stripe` with a **live** Stripe subscription id and a stale
    `expired_at`. Run the command. It must be **skipped**, with a warning in the log. This is
    Trap 6 and it is the one that costs you a customer if it is wrong.
13. Run again. Reports 0, no errors.

**Scheduler**

14. `php artisan schedule:list` shows both commands.
15. `docker compose up -d scheduler` and the logs show it waking up.

---

## 12. Out of scope

Do not do these in this pull request. Note them and move on.

- Notifying signers that a document they were asked to sign has expired. Real feature, needs
  product input on wording and timing.
- Emailing an org before or after their plan lapses.
- Making the retention window a per-plan perk (free 3 days, pro 30). Deliberately rejected for now
  to keep this small; `config('documents.retention_days')` is the hook if it comes back.
- Fixing `DocumentController`'s use of `env()` (Trap 5). Pre-existing, harmless today.
- Adding a guard against creating a second Stripe subscription when one is already active. Real
  bug, tracked separately.
