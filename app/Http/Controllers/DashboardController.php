<?php

namespace App\Http\Controllers;

use App\Models\Pipeline;
use App\Support\ExchangeRate;

class DashboardController extends Controller
{
    public function index()
    {
        $rate = ExchangeRate::usdToIdr();
        $all = Pipeline::all();

        $totalIdr = (float) $all->sum('amount_idr');
        $totalUsd = (float) $all->sum('amount_usd');

        $countBy = fn (string $col) => $all->groupBy($col)->map->count();

        return view('dashboard', [
            'rate'        => $rate,
            'total'       => $all->count(),
            'totalIdr'    => $totalIdr,
            'totalUsd'    => $totalUsd,
            'grandIdr'    => $totalIdr + $totalUsd * $rate,
            'lunas'       => $all->where('payment_status', 'lunas')->count(),
            'outstanding' => $all->whereIn('payment_status', ['belum', 'dp'])->count(),
            'done'        => $all->where('progress', 'done')->count(),
            'perCategory' => $countBy('category'),   // Pipeline
            'perProgress' => $countBy('progress'),    // Kanban
        ]);
    }
}
