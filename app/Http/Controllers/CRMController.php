<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Pipeline;
use App\Support\ExchangeRate;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;

class CRMController extends Controller
{
    public function index(Request $request)
    {
        $pipelineCategoryKeys = Category::where('type', 'pipeline')->pluck('key');
        $rate = ExchangeRate::usdToIdr();
        $jenisFilter = $request->query('jenis', '');
        $search = trim((string) $request->query('search', ''));
        $assigneeFilter = trim((string) $request->query('assignee', ''));
        $paymentFilter = trim((string) $request->query('payment', ''));
        $statusFilter = trim((string) $request->query('status', ''));
        $sortBy = (string) $request->query('sort_by', 'created_at');
        $sortDir = strtolower((string) $request->query('sort_dir', 'desc'));
        $sortDirection = $sortDir === 'asc' ? 'asc' : 'desc';

        $sortable = [
            'created_at' => fn (array $item) => (int) $item['id'],
            'code' => fn (array $item) => (string) $item['code'],
            'name' => fn (array $item) => strtolower((string) $item['name']),
            'jenis' => fn (array $item) => strtolower((string) $item['jenis']),
            'assignee' => fn (array $item) => strtolower((string) $item['assignee']),
            'stage' => fn (array $item) => strtolower((string) $item['stage']),
            'deadline' => fn (array $item) => $item['deadline'] === null ? null : strtotime($item['deadline']),
            'nilai' => fn (array $item) => (float) $item['amount_total_idr'],
            'status' => fn (array $item) => $item['is_done'] ? 1 : 0,
        ];
        if (! array_key_exists($sortBy, $sortable)) {
            $sortBy = 'created_at';
        }

        $applyCommonFilters = function ($query) use ($jenisFilter, $search, $paymentFilter, $statusFilter) {
            if ($jenisFilter !== '' && array_key_exists($jenisFilter, Pipeline::JENIS)) {
                $query->where('jenis', $jenisFilter);
            }

            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('endorse', 'like', "%$search%")
                        ->orWhere('notes', 'like', "%$search%")
                        ->orWhere('kontak_wa', 'like', "%$search%")
                        ->orWhere('kontak_gmail', 'like', "%$search%")
                        ->orWhere('kontak_ig', 'like', "%$search%")
                        ->orWhere('coaching', 'like', "%$search%")
                        ->orWhere('speaker', 'like', "%$search%");
                });
            }

            if ($paymentFilter !== '' && array_key_exists($paymentFilter, Pipeline::PAYMENT)) {
                $query->where('payment_status', $paymentFilter);
            }

            if (in_array($statusFilter, ['open', 'closed'], true)) {
                $query->where('done', $statusFilter === 'closed');
            }
        };

        $baseQuery = fn () => Pipeline::query()
            ->whereIn('category', $pipelineCategoryKeys)
            ->whereNull('archived_at');

        $query = $baseQuery()->with('assignee');
        $applyCommonFilters($query);
        if ($assigneeFilter !== '') {
            $query->where('assigned_to', (int) $assigneeFilter);
        }

        $cards = $query->orderBy('created_at', 'desc')->get();

        $cards = $cards->map(fn (Pipeline $pipeline) => [
            'id' => $pipeline->id,
            'code' => 'T-'.str_pad((string) $pipeline->id, 6, '0', STR_PAD_LEFT),
            'name' => $pipeline->endorse ?: '(tanpa nama)',
            'jenis' => Pipeline::JENIS[$pipeline->jenis] ?? $pipeline->jenis,
            'jenis_key' => $pipeline->jenis,
            'account' => Pipeline::ACCOUNTS[$pipeline->account] ?? $pipeline->account,
            'account_color' => Pipeline::ACCOUNT_COLORS[$pipeline->account] ?? 'bg-slate-500 text-white',
            'stage' => $pipeline->progress,
            'is_done' => (bool) $pipeline->done,
            'assignee_id' => $pipeline->assigned_to,
            'assignee' => $pipeline->assignee?->name,
            'payment' => Pipeline::PAYMENT[$pipeline->payment_status] ?? $pipeline->payment_status,
            'payment_key' => $pipeline->payment_status,
            'deadline' => $pipeline->deadline?->toDateString(),
            'amount_idr' => (float) $pipeline->amount_idr,
            'amount_usd' => (float) $pipeline->amount_usd,
            'amount_total_idr' => ((float) $pipeline->amount_idr) + ((float) $pipeline->amount_usd * $rate),
            'contacts' => array_values(array_filter([
                $pipeline->kontak_wa ? "WA: $pipeline->kontak_wa" : null,
                $pipeline->kontak_gmail ? "Gmail: $pipeline->kontak_gmail" : null,
                $pipeline->kontak_ig ? "IG: $pipeline->kontak_ig" : null,
            ])),
            'notes' => $pipeline->notes,
            'created_at' => $pipeline->created_at?->toDateString(),
        ]);

        $getSortValue = fn (array $item) => match ($sortBy) {
            'deadline' => $item['deadline'] === null
                ? ($sortDirection === 'asc' ? PHP_INT_MAX : PHP_INT_MIN)
                : strtotime($item['deadline']),
            default => $sortable[$sortBy]($item),
        };
        $cards = $sortDirection === 'asc' ? $cards->sortBy($getSortValue) : $cards->sortByDesc($getSortValue);
        $cards = $cards->values();

        $jenisCount = Pipeline::query()
            ->whereIn('category', $pipelineCategoryKeys)
            ->whereNull('archived_at')
            ->selectRaw('jenis, COUNT(*) as total')
            ->whereNotNull('jenis')
            ->groupBy('jenis')
            ->pluck('total', 'jenis')
            ->mapWithKeys(fn ($total, $jenis) => [$jenis => $total])
            ->toArray();

        $assigneeSummary = $cards
            ->filter(fn (array $item) => !empty($item['assignee']))
            ->groupBy('assignee')
            ->map(fn (Collection $rows, string $assignee) => [
                'assignee' => $assignee,
                'count' => $rows->count(),
                'open' => $rows->where('is_done', false)->count(),
            ])
            ->values()
            ->sortByDesc('count')
            ->values();

        $paymentQuery = $baseQuery();
        $applyCommonFilters($paymentQuery);

        $paymentSummary = $paymentQuery
            ->selectRaw('payment_status, COUNT(*) as total')
            ->groupBy('payment_status')
            ->pluck('total', 'payment_status')
            ->mapWithKeys(fn ($total, $payment) => [$payment => (int) $total])
            ->toArray();

        $totalByPayment = collect(Pipeline::PAYMENT)->mapWithKeys(fn ($label, $key) => [
            $key => $paymentSummary[$key] ?? 0,
        ])->toArray();

        $totalPipline = $cards->count();
        $openPipeline = $cards->where('is_done', false)->count();
        $donePipeline = $cards->where('is_done', true)->count();
        $dueSoon = $cards->filter(function (array $item) {
            if ($item['deadline'] === null) {
                return false;
            }
            return $item['deadline'] >= today()->toDateString() && $item['deadline'] <= today()->addDays(7)->toDateString();
        })->count();

        $pipelineValue = $cards->sum('amount_total_idr');
        $pipelineValueByUsd = $cards->sum('amount_usd');
        $pipelineValueByIdr = $cards->sum('amount_idr');
        $conversionRate = $totalPipline > 0 ? round(($donePipeline / $totalPipline) * 100, 1) : 0;
        $avgDealValue = $totalPipline > 0 ? round($pipelineValue / $totalPipline, 0) : 0;
        $openRate = $totalPipline > 0 ? round(($openPipeline / $totalPipline) * 100, 1) : 0;
        $followUpRate = $totalPipline > 0 ? round(($dueSoon / $totalPipline) * 100, 1) : 0;

        $assigneeList = $baseQuery()
            ->whereNotNull('assigned_to')
            ->with('assignee')
            ->when($jenisFilter !== '' && array_key_exists($jenisFilter, Pipeline::JENIS), fn ($q) => $q->where('jenis', $jenisFilter))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('endorse', 'like', "%$search%")
                        ->orWhere('notes', 'like', "%$search%")
                        ->orWhere('kontak_wa', 'like', "%$search%")
                        ->orWhere('kontak_gmail', 'like', "%$search%")
                        ->orWhere('kontak_ig', 'like', "%$search%")
                        ->orWhere('coaching', 'like', "%$search%")
                        ->orWhere('speaker', 'like', "%$search%");
                });
            })
            ->when($paymentFilter !== '' && array_key_exists($paymentFilter, Pipeline::PAYMENT), fn ($q) => $q->where('payment_status', $paymentFilter))
            ->when(in_array($statusFilter, ['open', 'closed'], true), fn ($q) => $q->where('done', $statusFilter === 'closed'))
            ->get()
            ->map(fn (Pipeline $pipeline) => $pipeline->assignee ? [
                'id' => $pipeline->assigned_to,
                'name' => $pipeline->assignee->name,
            ] : null)
            ->filter()
            ->unique('id')
            ->values()
            ->sortBy('name')
            ->values()
            ->map(fn (array $item) => [
                'id' => (string) $item['id'],
                'name' => $item['name'],
            ])
            ->values();

        return Inertia::render('CRM', [
            'summary' => [
                'total' => $totalPipline,
                'open' => $openPipeline,
                'deals' => $donePipeline,
                'dueSoon' => $dueSoon,
                'dueSoonRate' => $followUpRate,
                'value_idr' => $pipelineValueByIdr,
                'value_usd' => $pipelineValueByUsd,
                'value_total_idr' => $pipelineValue,
                'avg_value' => $avgDealValue,
                'conversion_rate' => $conversionRate,
                'open_rate' => $openRate,
                'payment' => $totalByPayment,
            ],
            'cards' => $cards->values(),
            'filters' => [
                'jenis' => $jenisFilter,
                'search' => $search,
                'assignee' => $assigneeFilter,
                'payment' => $paymentFilter,
                'status' => $statusFilter,
                'sort_by' => $sortBy,
                'sort_dir' => $sortDirection,
            ],
            'jenisList' => Pipeline::JENIS,
            'assigneeList' => $assigneeList,
            'paymentList' => Pipeline::PAYMENT,
            'assigneeSummary' => $assigneeSummary,
            'rate' => $rate,
        ]);
    }
}
