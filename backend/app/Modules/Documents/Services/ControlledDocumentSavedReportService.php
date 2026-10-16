<?php

declare(strict_types=1);

namespace App\Modules\Documents\Services;

use App\Modules\Documents\Models\ControlledDocumentReportDefinition;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Workspace\Services\TenantActivityLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ControlledDocumentSavedReportService
{
    public function __construct(
        private readonly ControlledDocumentRegistryService $registry,
        private readonly TenantActivityLogger $activity,
    ) {}

    /**
     * @return Collection<int, ControlledDocumentReportDefinition>
     */
    public function listForUser(TenantUser $user): Collection
    {
        return ControlledDocumentReportDefinition::query()
            ->where('user_id', $user->id)
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(TenantUser $user, array $data): ControlledDocumentReportDefinition
    {
        $name = $this->name($data['name'] ?? null);
        $this->assertUniqueName($user, $name, null);

        $report = ControlledDocumentReportDefinition::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'name' => $name,
            'filters_json' => $this->filters($data['filters'] ?? null),
            'schedule_json' => $this->schedule(is_array($data['schedule'] ?? null) ? $data['schedule'] : null),
        ]);

        $this->activity->record(
            module: 'documents',
            action: 'controlled_document.report_saved',
            summary: 'Saved controlled document report · '.$report->name,
            entityType: 'controlled_document_report',
            entityId: (string) $report->id,
            entityLabel: $report->name,
            actor: $user,
        );

        return $report;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(TenantUser $user, ControlledDocumentReportDefinition $report, array $data): ControlledDocumentReportDefinition
    {
        $this->assertOwner($user, $report);

        if (array_key_exists('name', $data)) {
            $name = $this->name($data['name']);
            $this->assertUniqueName($user, $name, (string) $report->id);
            $report->name = $name;
        }
        if (array_key_exists('filters', $data)) {
            $report->filters_json = $this->filters($data['filters']);
        }
        if (array_key_exists('schedule', $data)) {
            $current = is_array($report->schedule_json) ? $report->schedule_json : [];
            $incoming = is_array($data['schedule']) ? $data['schedule'] : null;
            $report->schedule_json = $this->schedule($incoming === null ? null : array_merge($current, $incoming));
        }
        $report->save();

        return $report->refresh();
    }

    public function delete(TenantUser $user, ControlledDocumentReportDefinition $report): void
    {
        $this->assertOwner($user, $report);
        $this->deleteStoredFile($report);
        $report->delete();
    }

    public function csvFor(TenantUser $user, ControlledDocumentReportDefinition $report): string
    {
        $this->assertOwner($user, $report);

        return $this->registry->exportCsv($user, $this->filterPayload($report));
    }

    /**
     * @return list<ControlledDocumentReportDefinition>
     */
    public function dueScheduledReports(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $due = [];
        foreach (ControlledDocumentReportDefinition::query()->orderBy('name')->get() as $report) {
            if ($this->isDue($report, $now)) {
                $due[] = $report;
            }
        }

        return $due;
    }

    public function isDue(ControlledDocumentReportDefinition $report, CarbonImmutable $now): bool
    {
        $schedule = is_array($report->schedule_json) ? $report->schedule_json : [];
        if (($schedule['enabled'] ?? false) !== true) {
            return false;
        }

        $hour = (int) ($schedule['hour'] ?? 8);
        if ($now->hour !== $hour) {
            return false;
        }

        if (($schedule['frequency'] ?? 'daily') === 'weekly' && $now->dayOfWeek !== (int) ($schedule['day_of_week'] ?? 1)) {
            return false;
        }

        if ($report->last_run_at !== null && CarbonImmutable::parse($report->last_run_at)->isSameDay($now)) {
            return false;
        }

        return true;
    }

    /**
     * @return array{filename: string, recipients: list<string>}
     */
    public function runScheduled(ControlledDocumentReportDefinition $report): array
    {
        $owner = $report->owner;
        if (! $owner instanceof TenantUser) {
            throw new \RuntimeException('Scheduled report owner is missing.');
        }

        $csv = $this->registry->exportCsv($owner, $this->filterPayload($report));
        $filename = Str::slug($report->name) !== '' ? Str::slug($report->name).'.csv' : 'controlled-document-register.csv';
        $path = sprintf(
            '%s/controlled-document-reports/%s/%s',
            tenant() !== null ? (string) tenant()->getTenantKey() : 'unknown',
            $report->id,
            $filename,
        );

        $this->deleteStoredFile($report);
        Storage::disk($this->disk())->put($path, $csv);

        $report->forceFill([
            'last_run_at' => now(),
            'last_export_path' => $path,
            'last_export_filename' => $filename,
        ])->save();

        $recipients = $this->recipients(is_array($report->schedule_json) ? $report->schedule_json : []);
        if ($recipients !== []) {
            Log::info('documents.scheduled_report_ready', [
                'report_id' => $report->id,
                'report_name' => $report->name,
                'filename' => $filename,
                'recipients' => $recipients,
            ]);
        }

        $this->activity->record(
            module: 'documents',
            action: 'controlled_document.report_ran',
            summary: 'Ran scheduled controlled document report · '.$report->name,
            entityType: 'controlled_document_report',
            entityId: (string) $report->id,
            entityLabel: $report->name,
            actor: $owner,
        );

        return [
            'filename' => $filename,
            'recipients' => $recipients,
        ];
    }

    public function storedFile(TenantUser $user, ControlledDocumentReportDefinition $report): string
    {
        $this->assertOwner($user, $report);
        $path = (string) $report->last_export_path;
        if ($path === '' || ! Storage::disk($this->disk())->exists($path)) {
            throw ValidationException::withMessages([
                'file' => [__('This report has not produced a file yet.')],
            ]);
        }

        $contents = Storage::disk($this->disk())->get($path);
        if (! is_string($contents)) {
            throw ValidationException::withMessages([
                'file' => [__('This report has not produced a file yet.')],
            ]);
        }

        return $contents;
    }

    /**
     * @return array<string, mixed>
     */
    public function present(ControlledDocumentReportDefinition $report): array
    {
        return [
            'id' => (string) $report->id,
            'name' => $report->name,
            'filters' => is_array($report->filters_json) ? $report->filters_json : [],
            'schedule' => is_array($report->schedule_json) ? $report->schedule_json : null,
            'last_run_at' => $report->last_run_at?->toIso8601String(),
            'has_file' => is_string($report->last_export_path) && $report->last_export_path !== '',
            'filename' => $report->last_export_filename,
        ];
    }

    public function findForUser(TenantUser $user, string $id): ControlledDocumentReportDefinition
    {
        $report = ControlledDocumentReportDefinition::query()
            ->where('user_id', $user->id)
            ->whereKey($id)
            ->first();

        if (! $report instanceof ControlledDocumentReportDefinition) {
            throw ValidationException::withMessages([
                'report' => [__('Saved report not found.')],
            ]);
        }

        return $report;
    }

    /**
     * @return array<string, mixed>
     */
    private function filterPayload(ControlledDocumentReportDefinition $report): array
    {
        $filters = is_array($report->filters_json) ? $report->filters_json : [];

        return [
            'period' => $filters['period'] ?? 90,
            'department' => $filters['department'] ?? null,
            'document_type' => $filters['document_type'] ?? null,
            'status' => $filters['status'] ?? null,
            'review' => $filters['review'] ?? null,
            'file' => $filters['file'] ?? null,
        ];
    }

    private function name(mixed $name): string
    {
        $trimmed = trim((string) $name);
        if ($trimmed === '' || strlen($trimmed) > 160) {
            throw ValidationException::withMessages([
                'name' => [__('A report name of 160 characters or less is required.')],
            ]);
        }

        return $trimmed;
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(mixed $filters): array
    {
        $filters = is_array($filters) ? $filters : [];
        $period = (int) ($filters['period'] ?? 90);
        if (! in_array($period, [30, 90, 365], true)) {
            throw ValidationException::withMessages([
                'filters.period' => [__('Choose 30, 90, or 365 days.')],
            ]);
        }

        return [
            'period' => $period,
            'department' => $this->nullableString($filters['department'] ?? null, 120),
            'document_type' => $this->nullableString($filters['document_type'] ?? null, 120),
            'status' => $this->oneOf($filters['status'] ?? null, ['pending', 'published', 'obsolete']),
            'review' => $this->oneOf($filters['review'] ?? null, ['overdue', 'due_30', 'due_90', 'none']),
            'file' => $this->oneOf($filters['file'] ?? null, ['has', 'missing']),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $schedule
     * @return array<string, mixed>|null
     */
    private function schedule(?array $schedule): ?array
    {
        if ($schedule === null) {
            return null;
        }

        $frequency = (string) ($schedule['frequency'] ?? 'daily');
        if (! in_array($frequency, ['daily', 'weekly'], true)) {
            throw ValidationException::withMessages([
                'schedule.frequency' => [__('Choose daily or weekly.')],
            ]);
        }

        return [
            'enabled' => (bool) ($schedule['enabled'] ?? false),
            'frequency' => $frequency,
            'hour' => max(0, min(23, (int) ($schedule['hour'] ?? 8))),
            'day_of_week' => max(0, min(6, (int) ($schedule['day_of_week'] ?? 1))),
            'recipients' => $this->recipients($schedule),
        ];
    }

    /**
     * @param  array<string, mixed>  $schedule
     * @return list<string>
     */
    private function recipients(array $schedule): array
    {
        $raw = $schedule['recipients'] ?? [];
        if (! is_array($raw)) {
            return [];
        }

        $emails = [];
        foreach ($raw as $email) {
            $email = trim((string) $email);
            if ($email === '') {
                continue;
            }
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 255) {
                throw ValidationException::withMessages([
                    'schedule.recipients' => [__('Each recipient must be an email address.')],
                ]);
            }
            $emails[] = $email;
        }

        return array_values(array_unique($emails));
    }

    /**
     * @param  list<string>  $allowed
     */
    private function oneOf(mixed $value, array $allowed): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $value = (string) $value;
        if (! in_array($value, $allowed, true)) {
            throw ValidationException::withMessages([
                'filters' => [__('A filter value is not allowed.')],
            ]);
        }

        return $value;
    }

    private function nullableString(mixed $value, int $max): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (strlen($value) > $max) {
            throw ValidationException::withMessages([
                'filters' => [__('A filter value is too long.')],
            ]);
        }

        return $value;
    }

    private function assertUniqueName(TenantUser $user, string $name, ?string $ignoreId): void
    {
        $query = ControlledDocumentReportDefinition::query()
            ->where('user_id', $user->id)
            ->where('name', $name);
        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }
        if ($query->exists()) {
            throw ValidationException::withMessages([
                'name' => [__('You already have a report with this name.')],
            ]);
        }
    }

    private function assertOwner(TenantUser $user, ControlledDocumentReportDefinition $report): void
    {
        if ((string) $report->user_id !== (string) $user->id) {
            throw ValidationException::withMessages([
                'report' => [__('Saved report not found.')],
            ]);
        }
    }

    private function deleteStoredFile(ControlledDocumentReportDefinition $report): void
    {
        $path = (string) $report->last_export_path;
        if ($path !== '' && Storage::disk($this->disk())->exists($path)) {
            Storage::disk($this->disk())->delete($path);
        }
    }

    private function disk(): string
    {
        return (string) config('toweros.tenant_files.disk', 'tenant_files');
    }
}
