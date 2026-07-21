<?php

namespace App\Http\Controllers;

use App\Models\BoardColumn;
use App\Models\Category;
use App\Models\Pipeline;
use Inertia\Inertia;

/** Ringkasan eksekutif read-only yang diturunkan langsung dari kartu Kanban. */
class TrackingController extends Controller
{
    public function index()
    {
        $boards = Category::where('type', 'kanban')->orderBy('name')->get();
        $columns = BoardColumn::whereIn('board_key', $boards->pluck('key'))
            ->orderBy('position')->get()->groupBy('board_key');
        $cards = Pipeline::whereIn('category', $boards->pluck('key'))
            ->whereNull('archived_at')->get()->groupBy('category');

        $tracking = $boards->map(function ($board) use ($columns, $cards) {
            $boardColumns = $columns->get($board->key, collect());
            $boardCards = $cards->get($board->key, collect());
            $doneKeys = $boardColumns->filter(fn ($column) => preg_match('/done|selesai|complete|finish/i', $column->key.' '.$column->name))
                ->pluck('key');
            $done = $boardCards->filter(fn ($card) => $card->done || $doneKeys->contains($card->progress))->count();
            $overdue = $boardCards->filter(fn ($card) => $card->deadline && $card->deadline->isPast() && ! $card->done && ! $doneKeys->contains($card->progress))->count();
            $urgent = $boardCards->filter(fn ($card) => collect($card->labels)->contains('name', 'Urgent'))->count();
            $total = $boardCards->count();
            $percent = $total ? (int) round($done / $total * 100) : 0;

            return [
                'key' => $board->key,
                'name' => $board->name,
                'total' => $total,
                'done' => $done,
                'active' => $total - $done,
                'overdue' => $overdue,
                'urgent' => $urgent,
                'percent' => $percent,
                'health' => $overdue > 0 ? 'red' : ($urgent > 0 ? 'yellow' : 'green'),
                'last_activity' => $boardCards->max('updated_at')?->diffForHumans(),
                'columns' => $boardColumns->map(fn ($column) => [
                    'name' => $column->name,
                    'color' => $column->color,
                    'count' => $boardCards->where('progress', $column->key)->count(),
                ])->values(),
                'url' => route('pipelines.kanban', ['category' => $board->key]),
            ];
        })->values();

        return Inertia::render('Tracking', [
            'tracking' => $tracking,
            'summary' => [
                'boards' => $tracking->count(),
                'cards' => $tracking->sum('total'),
                'done' => $tracking->sum('done'),
                'overdue' => $tracking->sum('overdue'),
            ],
        ]);
    }
}
