<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalFormOutboundFile;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

final class EApprovalFormOutboundFileService
{
    public function __construct(
        private readonly EApprovalFileStorageService $storage,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listForForm(EApprovalForm $form): array
    {
        return EApprovalFormOutboundFile::query()
            ->where('form_id', $form->id)
            ->orderBy('created_at')
            ->get()
            ->map(static fn (EApprovalFormOutboundFile $file) => $file->toAdminRow())
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function store(EApprovalForm $form, UploadedFile $file, TenantUser $actor): array
    {
        $maxFiles = max(1, (int) config('e_approval.external_package.max_files', 25));
        $existingCount = EApprovalFormOutboundFile::query()->where('form_id', $form->id)->count();
        if ($existingCount >= $maxFiles) {
            throw ValidationException::withMessages([
                'file' => [__('This form already has the maximum number of outbound deliverable files (:max).', ['max' => $maxFiles])],
            ]);
        }

        $stored = $this->storage->storeFormOutboundFile($form, $file);

        $row = EApprovalFormOutboundFile::query()->create([
            'form_id' => $form->id,
            'file_path' => $stored['path'],
            'file_name' => $stored['name'],
            'byte_size' => $stored['byte_size'],
            'uploaded_by_user_id' => $actor->id,
        ]);

        return $row->toAdminRow();
    }

    public function destroy(EApprovalFormOutboundFile $file): void
    {
        $this->storage->deleteIfExists((string) $file->file_path);
        $file->delete();
    }
}
