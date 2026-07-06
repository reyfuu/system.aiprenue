<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExchangeRate
{
    private const FALLBACK = 16000.0;
    private const CACHE_KEY = 'usd_idr_rate';
    private const CACHE_TTL = 60 * 60 * 12; // 12 jam

    /**
     * Kurs USD → IDR terkini (di-cache 12 jam, fallback bila gagal).
     */
    public static function usdToIdr(): float
    {
        return (float) Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            try {
                $res = Http::timeout(8)->get('https://open.er-api.com/v6/latest/USD');
                if ($res->successful() && ($rate = $res->json('rates.IDR'))) {
                    return round((float) $rate, 2);
                }
            } catch (\Throwable $e) {
                Log::warning('Gagal ambil kurs USD/IDR: '.$e->getMessage());
            }

            return self::FALLBACK;
        });
    }

    /**
     * Info kurs lengkap untuk ditampilkan (rate + waktu update).
     */
    public static function info(): array
    {
        $rate = self::usdToIdr();

        return [
            'rate'    => $rate,
            'is_live' => $rate !== self::FALLBACK,
        ];
    }

    /** Konversi nominal USD ke IDR memakai kurs terkini. */
    public static function toIdr(float $usd): float
    {
        return $usd * self::usdToIdr();
    }
}
