<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\RecurringOperations\ApplyRecurringOperationRequest;
use App\Http\Requests\RecurringOperations\StoreRecurringOperationRequest;
use App\Http\Requests\RecurringOperations\UpdateRecurringOperationRequest;
use App\Models\Account;
use App\Models\Category;
use App\Models\RecurringOperation;
use App\Services\RecurringOperationService;
use App\Support\Money;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class RecurringOperationController extends Controller
{
    public function __construct(private readonly RecurringOperationService $recurringOperationService)
    {
    }

    public function index(Request $request): View
    {
        $operations = RecurringOperation::query()
            ->forUser($request->user()->id)
            ->with(['account', 'fromAccount', 'toAccount', 'category'])
            ->orderBy('name')
            ->paginate(25);

        return view('recurring-operations.index', [
            'operations' => $operations,
        ]);
    }

    public function create(Request $request): View
    {
        return view('recurring-operations.create', [
            'accounts' => $this->availableAccounts($request),
            'categories' => $this->availableCategories($request),
        ]);
    }

    public function store(StoreRecurringOperationRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        RecurringOperation::query()->create($this->buildPayload($validated, $request->user()->id));

        return redirect()
            ->route('recurring-operations.index')
            ->with('status', 'Recurring operation created.');
    }

    public function edit(Request $request, RecurringOperation $recurringOperation): View
    {
        $this->ensureOwnership($request, $recurringOperation);

        return view('recurring-operations.edit', [
            'operation' => $recurringOperation,
            'accounts' => $this->availableAccounts($request),
            'categories' => $this->availableCategories($request),
        ]);
    }

    public function update(UpdateRecurringOperationRequest $request, RecurringOperation $recurringOperation): RedirectResponse
    {
        $this->ensureOwnership($request, $recurringOperation);

        $validated = $request->validated();
        $recurringOperation->update($this->buildPayload($validated, $request->user()->id));

        return redirect()
            ->route('recurring-operations.index')
            ->with('status', 'Recurring operation updated.');
    }

    public function destroy(Request $request, RecurringOperation $recurringOperation): RedirectResponse
    {
        $this->ensureOwnership($request, $recurringOperation);

        $recurringOperation->delete();

        return redirect()
            ->route('recurring-operations.index')
            ->with('status', 'Recurring operation deleted.');
    }

    public function apply(ApplyRecurringOperationRequest $request, RecurringOperation $recurringOperation): RedirectResponse
    {
        $this->ensureOwnership($request, $recurringOperation);

        $validated = $request->validated();

        try {
            $this->recurringOperationService->apply(
                $request->user(),
                $recurringOperation,
                (string) $validated['occurred_at'],
            );
        } catch (DomainException $exception) {
            return redirect()
                ->route('transactions.index')
                ->withErrors(['recurring_operation' => $exception->getMessage()])
                ->withInput($request->only('occurred_at', 'selected_operation_id'));
        }

        return redirect()
            ->route('transactions.index')
            ->with('status', 'Recurring operation applied.');
    }

    /**
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    private function buildPayload(array $validated, int $userId): array
    {
        $type = (string) $validated['type'];

        $payload = [
            'user_id' => $userId,
            'name' => (string) $validated['name'],
            'type' => $type,
            'amount_minor' => Money::parseMajorToMinor((string) $validated['amount']),
            'description' => $validated['description'] ?? null,
            'schedule' => null,
            'interval' => null,
            'starts_at' => null,
            'ends_at' => null,
        ];

        if ($type === RecurringOperation::TYPE_TRANSFER) {
            $payload['account_id'] = null;
            $payload['category_id'] = null;
            $payload['from_account_id'] = (int) $validated['from_account_id'];
            $payload['to_account_id'] = (int) $validated['to_account_id'];

            return $payload;
        }

        $payload['account_id'] = (int) $validated['account_id'];
        $payload['category_id'] = isset($validated['category_id']) && $validated['category_id'] !== null
            ? (int) $validated['category_id']
            : null;
        $payload['from_account_id'] = null;
        $payload['to_account_id'] = null;

        return $payload;
    }

    /**
     * @return Collection<int, Account>
     */
    private function availableAccounts(Request $request): Collection
    {
        return Account::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('archived_at')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Category>
     */
    private function availableCategories(Request $request): Collection
    {
        $query = Category::query()->forUser($request->user()->id);

        if (Schema::hasColumn('categories', 'type')) {
            $query->orderBy('type');
        }

        return $query
            ->orderBy('name')
            ->get();
    }

    private function ensureOwnership(Request $request, RecurringOperation $recurringOperation): void
    {
        abort_unless($recurringOperation->user_id === $request->user()->id, 403);
    }
}
