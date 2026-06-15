<?php



declare(strict_types=1);



namespace App\Modules\EApproval\Services;



use App\Modules\EApproval\Models\EApprovalForm;

use App\Modules\EApproval\Models\EApprovalSubmission;

use App\Modules\EApproval\Support\EApprovalSubmissionStatus;

use App\Modules\Identity\Models\TenantUser;

use Illuminate\Validation\ValidationException;



final class EApprovalSubmissionParentLinkService

{

    public function __construct(
        private readonly EApprovalCashAdvanceService $cashAdvances,
        private readonly EApprovalPurchaseRequisitionService $purchaseRequisitions,
        private readonly EApprovalFinanceProcurementPolicyService $procurementPolicy,
    ) {}



    /**

     * Validate and normalize a parent submission link for a new or updated child submission.

     *

     * @throws ValidationException

     */

    public function resolve(
        ?string $parentSubmissionId,
        TenantUser $requestor,
        EApprovalForm $childForm,
        ?string $excludeChildSubmissionId = null,
    ): ?string {

        $requiresParent = $this->requiresParentSubmission($childForm);

        $trimmedParentId = $parentSubmissionId !== null ? trim($parentSubmissionId) : '';



        if ($trimmedParentId === '') {

            if ($requiresParent) {

                throw ValidationException::withMessages([

                    'parent_submission_id' => [__('A linked parent submission is required for this form.')],

                ]);

            }



            return null;

        }



        $parentId = $trimmedParentId;



        /** @var EApprovalSubmission|null $parent */

        $parent = EApprovalSubmission::query()->with('form')->find($parentId);



        if ($parent === null) {

            throw ValidationException::withMessages([

                'parent_submission_id' => [__('Parent submission not found.')],

            ]);

        }



        if ((string) $parent->requestor_id !== (string) $requestor->id) {

            throw ValidationException::withMessages([

                'parent_submission_id' => [__('Parent submission must belong to the same requestor.')],

            ]);

        }



        if (in_array((string) $parent->status, [

            EApprovalSubmissionStatus::DRAFT,

            EApprovalSubmissionStatus::REJECTED,

            EApprovalSubmissionStatus::CANCELLED,

        ], true)) {

            throw ValidationException::withMessages([

                'parent_submission_id' => [__('Parent submission cannot be linked in its current status.')],

            ]);

        }



        $requiredParentFamily = $this->formMetadataString($childForm, 'parent_form_family');

        if ($requiredParentFamily !== null) {

            $parentFamily = $parent->form instanceof EApprovalForm

                ? $this->formMetadataString($parent->form, 'form_family')

                : null;



            if ($parentFamily !== $requiredParentFamily) {

                throw ValidationException::withMessages([

                    'parent_submission_id' => [

                        __('Parent submission must be a :family form.', ['family' => $requiredParentFamily]),

                    ],

                ]);

            }

        }



        if ($requiredParentFamily === 'cash_advance') {

            if ((string) $parent->status !== EApprovalSubmissionStatus::APPROVED) {

                throw ValidationException::withMessages([

                    'parent_submission_id' => [__('Cash advance must be approved before it can be liquidated.')],

                ]);

            }



            $openBalance = $this->cashAdvances->openBalanceForParent($parentId, $excludeChildSubmissionId);

            if ($openBalance === null) {

                throw ValidationException::withMessages([

                    'parent_submission_id' => [__('Cash advance does not have a valid requested amount.')],

                ]);

            }



            if ($openBalance <= 0) {

                throw ValidationException::withMessages([

                    'parent_submission_id' => [__('Cash advance has no remaining balance to liquidate.')],

                ]);

            }

        }

        if ($requiredParentFamily === 'purchase_requisition') {
            if ((string) $parent->status !== EApprovalSubmissionStatus::APPROVED) {
                throw ValidationException::withMessages([
                    'parent_submission_id' => [__('Purchase requisition must be approved before a purchase order can be issued.')],
                ]);
            }

            $openBalance = $this->purchaseRequisitions->openBalanceForParent($parentId, $excludeChildSubmissionId);
            if ($openBalance === null) {
                throw ValidationException::withMessages([
                    'parent_submission_id' => [__('Purchase requisition does not have a valid estimated total.')],
                ]);
            }

            if ($openBalance <= 0) {
                throw ValidationException::withMessages([
                    'parent_submission_id' => [__('Purchase requisition has no remaining budget for purchase orders.')],
                ]);
            }
        }

        return $parentId;

    }



    /**

     * Ensure liquidation/reimbursement amounts do not exceed the parent open balance.

     *

     * @param  array<string, mixed>  $values

     *

     * @throws ValidationException

     */

    /**
     * @param  array<string, mixed>  $values
     */
    /**
     * @param  array<string, mixed>  $values
     * @return array{
     *     policy_kind: string,
     *     parent_submission_id: string,
     *     amount: float,
     *     strict_open_balance: float|null,
     *     policy_max_amount: float|null,
     *     warning: string
     * }|null
     */
    public function assertChildAmounts(
        EApprovalForm $childForm,
        ?string $parentSubmissionId,
        array $values,
        ?string $excludeChildSubmissionId = null,
    ): ?array {
        if ($parentSubmissionId === null || trim($parentSubmissionId) === '') {
            return null;
        }

        if ($childForm->fields->contains(static fn ($field): bool => (string) $field->name === 'total_reimbursement')) {
            return $this->assertCashAdvanceChildAmount($childForm, $parentSubmissionId, $values, $excludeChildSubmissionId);
        }

        if ($childForm->fields->contains(static fn ($field): bool => (string) $field->name === 'total_amount')) {
            return $this->assertPurchaseRequisitionChildAmount($parentSubmissionId, $values, $excludeChildSubmissionId);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    /**
     * @param  array<string, mixed>  $values
     */
    /**
     * @param  array<string, mixed>  $values
     * @return array{
     *     policy_kind: string,
     *     parent_submission_id: string,
     *     amount: float,
     *     strict_open_balance: float|null,
     *     policy_max_amount: float|null,
     *     warning: string
     * }|null
     */
    private function assertCashAdvanceChildAmount(
        EApprovalForm $childForm,
        string $parentSubmissionId,
        array $values,
        ?string $excludeChildSubmissionId,
    ): ?array {
        /** @var EApprovalSubmission|null $parent */
        $parent = EApprovalSubmission::query()->with('form')->find($parentSubmissionId);
        if ($parent === null || ! $parent->form instanceof EApprovalForm) {
            return null;
        }

        if ($this->formMetadataString($parent->form, 'form_family') !== 'cash_advance') {
            return null;
        }

        $amount = $this->parseAmount($values['total_reimbursement'] ?? null);
        if ($amount === null) {
            return null;
        }

        if ($this->formMetadataString($childForm, 'form_family') === 'liquidation') {
            $evaluation = $this->procurementPolicy->evaluateLiquidationAmount(
                $parentSubmissionId,
                $amount,
                $excludeChildSubmissionId,
            );

            if ($evaluation['blocked']) {
                $policyMax = $evaluation['policy_max_amount'];
                $strictOpen = $evaluation['strict_open_balance'];

                if ($policyMax !== null && $amount > $policyMax + 0.0001) {
                    throw ValidationException::withMessages([
                        'total_reimbursement' => [
                            __('Liquidation total exceeds the tenant overspend policy maximum of :max.', [
                                'max' => number_format($policyMax, 2, '.', ''),
                            ]),
                        ],
                    ]);
                }

                throw ValidationException::withMessages([
                    'total_reimbursement' => [
                        __('Amount exceeds the cash advance open balance of :balance.', [
                            'balance' => number_format($strictOpen ?? 0, 2, '.', ''),
                        ]),
                    ],
                ]);
            }

            return $this->overspendContext('liquidation', $parentSubmissionId, $amount, $evaluation);
        }

        $openBalance = $this->cashAdvances->openBalanceForParent($parentSubmissionId, $excludeChildSubmissionId);
        if ($openBalance === null) {
            throw ValidationException::withMessages([
                'parent_submission_id' => [__('Cash advance does not have a valid requested amount.')],
            ]);
        }

        if ($amount > $openBalance + 0.0001) {
            throw ValidationException::withMessages([
                'total_reimbursement' => [
                    __('Amount exceeds the cash advance open balance of :balance.', [
                        'balance' => number_format($openBalance, 2, '.', ''),
                    ]),
                ],
            ]);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    /**
     * @param  array<string, mixed>  $values
     */
    /**
     * @param  array<string, mixed>  $values
     * @return array{
     *     policy_kind: string,
     *     parent_submission_id: string,
     *     amount: float,
     *     strict_open_balance: float|null,
     *     policy_max_amount: float|null,
     *     warning: string
     * }|null
     */
    private function assertPurchaseRequisitionChildAmount(
        string $parentSubmissionId,
        array $values,
        ?string $excludeChildSubmissionId,
    ): ?array {
        /** @var EApprovalSubmission|null $parent */
        $parent = EApprovalSubmission::query()->with('form')->find($parentSubmissionId);
        if ($parent === null || ! $parent->form instanceof EApprovalForm) {
            return null;
        }

        if ($this->formMetadataString($parent->form, 'form_family') !== 'purchase_requisition') {
            return null;
        }

        $amount = $this->parseAmount($values['total_amount'] ?? null);
        if ($amount === null) {
            return null;
        }

        $evaluation = $this->procurementPolicy->evaluatePurchaseOrderAmount(
            $parentSubmissionId,
            $amount,
            $excludeChildSubmissionId,
        );

        if ($evaluation['blocked']) {
            $policyMax = $evaluation['policy_max_amount'];
            $strictOpen = $evaluation['strict_open_balance'];

            if ($policyMax !== null && $amount > $policyMax + 0.0001) {
                throw ValidationException::withMessages([
                    'total_amount' => [
                        __('PO total exceeds the tenant overspend policy maximum of :max.', [
                            'max' => number_format($policyMax, 2, '.', ''),
                        ]),
                    ],
                ]);
            }

            throw ValidationException::withMessages([
                'total_amount' => [
                    __('PO total exceeds the purchase requisition open balance of :balance.', [
                        'balance' => number_format($strictOpen ?? 0, 2, '.', ''),
                    ]),
                ],
            ]);
        }

        return $this->overspendContext('purchase_order', $parentSubmissionId, $amount, $evaluation);
    }

    /**
     * @param  array{
     *     blocked: bool,
     *     warning: string|null,
     *     strict_open_balance: float|null,
     *     policy_max_amount: float|null
     * }  $evaluation
     * @return array{
     *     policy_kind: string,
     *     parent_submission_id: string,
     *     amount: float,
     *     strict_open_balance: float|null,
     *     policy_max_amount: float|null,
     *     warning: string
     * }|null
     */
    private function overspendContext(
        string $policyKind,
        string $parentSubmissionId,
        float $amount,
        array $evaluation,
    ): ?array {
        $warning = trim((string) ($evaluation['warning'] ?? ''));
        if ($warning === '') {
            return null;
        }

        return [
            'policy_kind' => $policyKind,
            'parent_submission_id' => $parentSubmissionId,
            'amount' => $amount,
            'strict_open_balance' => $evaluation['strict_open_balance'],
            'policy_max_amount' => $evaluation['policy_max_amount'],
            'warning' => $warning,
        ];
    }

    /**

     * Prefill reference document fields on the child when a parent is linked.

     *

     * @param  array<string, mixed>  $values

     * @return array<string, mixed>

     */

    /**
     * @return array<string, string>
     */
    public function buildParentPrefillValues(EApprovalSubmission $parent, EApprovalForm $childForm): array
    {
        $parentDocumentNo = trim((string) ($parent->document_no ?? ''));
        if ($parentDocumentNo === '' || str_starts_with($parentDocumentNo, 'DRAFT-')) {
            return [];
        }

        $parent->loadMissing(['values.field', 'form']);
        $parentFamily = $parent->form instanceof EApprovalForm
            ? $this->formMetadataString($parent->form, 'form_family')
            : null;

        if ($parentFamily === null) {
            return [];
        }

        $parentValues = $this->parentValuesByFieldName($parent);

        return match ($parentFamily) {
            'cash_advance' => $this->prefillFromCashAdvance($parent, $childForm, $parentValues),
            'purchase_requisition' => $this->prefillFromPurchaseRequisition($parent, $childForm, $parentValues),
            default => [],
        };
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    public function attachPrefillToOpenParentItems(array $items, EApprovalForm $childForm): array
    {
        if ($items === []) {
            return $items;
        }

        $ids = array_values(array_filter(array_map(
            static fn (array $item): ?string => isset($item['id']) ? (string) $item['id'] : null,
            $items,
        )));

        if ($ids === []) {
            return $items;
        }

        $parents = EApprovalSubmission::query()
            ->with(['values.field', 'form'])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        return array_map(function (array $item) use ($parents, $childForm): array {
            $parent = $parents->get((string) ($item['id'] ?? ''));
            if ($parent instanceof EApprovalSubmission) {
                $item['prefill_values'] = $this->buildParentPrefillValues($parent, $childForm);
            }

            return $item;
        }, $items);
    }

    /**
     * Prefill child fields from a linked parent submission when values are empty.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function enrichValues(EApprovalSubmission $parent, EApprovalForm $childForm, array $values): array
    {
        foreach ($this->buildParentPrefillValues($parent, $childForm) as $field => $proposed) {
            if ($this->isEmptyChildValue($values[$field] ?? null)) {
                $values[$field] = $proposed;
            }
        }

        return $values;
    }

    /**
     * @param  array<string, string>  $parentValues
     * @return array<string, string>
     */
    private function prefillFromCashAdvance(EApprovalSubmission $parent, EApprovalForm $childForm, array $parentValues): array
    {
        $prefill = [];

        if ($this->childHasField($childForm, 'cash_advance_document_no')) {
            $prefill['cash_advance_document_no'] = trim((string) $parent->document_no);
        }

        if ($this->childHasField($childForm, 'liquidation_date')) {
            $prefill['liquidation_date'] = now()->toDateString();
        }

        foreach ([
            'purpose' => 'notes',
            'department' => 'department',
            'currency' => 'currency',
        ] as $parentField => $childField) {
            if (! $this->childHasField($childForm, $childField)) {
                continue;
            }

            $raw = $parentValues[$parentField] ?? null;
            if ($this->isEmptyChildValue($raw)) {
                continue;
            }

            $prefill[$childField] = trim((string) $raw);
        }

        return $prefill;
    }

    /**
     * @param  array<string, string>  $parentValues
     * @return array<string, string>
     */
    private function prefillFromPurchaseRequisition(EApprovalSubmission $parent, EApprovalForm $childForm, array $parentValues): array
    {
        $prefill = [];

        if ($this->childHasField($childForm, 'purchase_requisition_document_no')) {
            $prefill['purchase_requisition_document_no'] = trim((string) $parent->document_no);
        }

        foreach ([
            'line_items' => 'line_items',
            'estimated_total' => 'total_amount',
        ] as $parentField => $childField) {
            if (! $this->childHasField($childForm, $childField)) {
                continue;
            }

            $raw = $parentValues[$parentField] ?? null;
            if ($this->isEmptyChildValue($raw)) {
                continue;
            }

            $prefill[$childField] = trim((string) $raw);
        }

        return $prefill;
    }

    /**
     * @return array<string, string>
     */
    private function parentValuesByFieldName(EApprovalSubmission $parent): array
    {
        $out = [];

        foreach ($parent->values as $value) {
            $name = $value->field?->name;
            if (! is_string($name) || trim($name) === '') {
                continue;
            }

            $stored = $value->value;
            if ($stored === null || $stored === '') {
                continue;
            }

            $out[$name] = (string) $stored;
        }

        return $out;
    }

    private function childHasField(EApprovalForm $form, string $name): bool
    {
        return $form->fields->contains(static fn ($field): bool => (string) $field->name === $name);
    }

    private function isEmptyChildValue(mixed $raw): bool
    {
        if ($raw === null) {
            return true;
        }

        if (is_array($raw)) {
            return $raw === [];
        }

        $str = trim((string) $raw);
        if ($str === '') {
            return true;
        }

        if (! str_starts_with($str, '{') && ! str_starts_with($str, '[')) {
            return false;
        }

        try {
            $decoded = json_decode($str, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return false;
        }

        if (! is_array($decoded) || ! isset($decoded['rows']) || ! is_array($decoded['rows'])) {
            return false;
        }

        foreach ($decoded['rows'] as $row) {
            if (! is_array($row)) {
                continue;
            }

            foreach ($row as $cell) {
                if (trim((string) $cell) !== '') {
                    return false;
                }
            }
        }

        return true;
    }



    private function requiresParentSubmission(EApprovalForm $form): bool

    {

        $metadata = $form->metadata_json;

        if (! is_array($metadata)) {

            return false;

        }



        if ($this->formMetadataString($form, 'form_family') === 'liquidation') {
            return $this->procurementPolicy->liquidationRequiresParent();
        }

        if (($metadata['requires_parent_submission'] ?? false) === true) {
            return true;
        }

        return $this->formMetadataString($form, 'form_family') === 'purchase_order'
            && $this->formMetadataString($form, 'parent_form_family') === 'purchase_requisition';
    }



    private function parseAmount(mixed $raw): ?float

    {

        if ($raw === null) {

            return null;

        }



        if (is_string($raw)) {

            $trimmed = trim(str_replace(',', '', $raw));

            if ($trimmed === '') {

                return null;

            }



            return is_numeric($trimmed) ? (float) $trimmed : null;

        }



        if (is_int($raw) || is_float($raw)) {

            return (float) $raw;

        }



        return null;

    }



    private function formMetadataString(EApprovalForm $form, string $key): ?string

    {

        $metadata = $form->metadata_json;

        if (! is_array($metadata)) {

            return null;

        }



        $value = $metadata[$key] ?? null;



        return is_string($value) && trim($value) !== '' ? trim($value) : null;

    }

}

