<?php

declare(strict_types=1);

namespace App\Modules\Documents\Support;

final class ControlledDocumentImportFields
{
    public const IGNORE = '__ignore__';

    /**
     * @return list<array{name: string, label: string, required: bool}>
     */
    public static function all(): array
    {
        return [
            ['name' => 'document_code', 'label' => 'Document code', 'required' => true],
            ['name' => 'dcf_number', 'label' => 'DCF number', 'required' => false],
            ['name' => 'title', 'label' => 'Title', 'required' => true],
            ['name' => 'document_type', 'label' => 'Document type', 'required' => false],
            ['name' => 'department', 'label' => 'Department', 'required' => false],
            ['name' => 'revision_number', 'label' => 'Revision number', 'required' => false],
            ['name' => 'effective_date', 'label' => 'Effective date', 'required' => false],
            ['name' => 'next_review_date', 'label' => 'Next review date', 'required' => false],
            ['name' => 'change_summary', 'label' => 'Change summary', 'required' => false],
            ['name' => 'folder_path', 'label' => 'Folder path', 'required' => false],
            ['name' => 'status', 'label' => 'Status', 'required' => false],
            ['name' => 'approver_email', 'label' => 'Approver email', 'required' => false],
            ['name' => 'pending_since', 'label' => 'Pending since', 'required' => false],
        ];
    }

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return array_map(static fn (array $field): string => $field['name'], self::all());
    }

    /**
     * @param  list<string>  $headers
     * @return array<string, string>
     */
    public static function suggestMap(array $headers): array
    {
        $map = [];
        $used = [];

        foreach ($headers as $header) {
            $field = self::fieldForHeader($header);
            if ($field === null || isset($used[$field])) {
                $map[$header] = self::IGNORE;

                continue;
            }

            $used[$field] = true;
            $map[$header] = $field;
        }

        return $map;
    }

    public static function fieldForHeader(string $header): ?string
    {
        $normalized = self::normalizeHeader($header);

        foreach (self::aliases() as $field => $aliases) {
            if (in_array($normalized, $aliases, true)) {
                return $field;
            }
        }

        return null;
    }

    public static function normalizeHeader(string $header): string
    {
        $normalized = strtolower(trim($header));
        $normalized = preg_replace('/^\xEF\xBB\xBF/', '', $normalized) ?? $normalized;
        $normalized = str_replace([' ', '-', '.'], '_', $normalized);
        $normalized = preg_replace('/_+/', '_', $normalized) ?? $normalized;

        return trim($normalized, '_');
    }

    /**
     * @return array<string, list<string>>
     */
    private static function aliases(): array
    {
        return [
            'document_code' => ['document_code', 'code', 'document_no', 'doc_no'],
            'dcf_number' => ['dcf_number', 'dcf', 'dcf_no', 'dcf_num'],
            'title' => ['title', 'document_title', 'name'],
            'document_type' => ['document_type', 'type'],
            'department' => ['department', 'dept'],
            'revision_number' => ['revision_number', 'revision', 'rev', 'rev_n', 'rev_no', 'revision_no', 'revision_n'],
            'effective_date' => ['effective_date', 'effective'],
            'next_review_date' => ['next_review_date', 'review_date', 'next_review'],
            'change_summary' => ['change_summary', 'summary'],
            'folder_path' => ['folder_path', 'file_path', 'path', 'file_name', 'filename', 'attachment'],
            'status' => ['status'],
            'approver_email' => ['approver_email', 'approver'],
            'pending_since' => ['pending_since', 'submitted_at'],
        ];
    }
}
