<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\Config;

/**
 * Auto-fills the Giornale dei Lavori weather field from Open-Meteo (free, no key).
 * Best-effort: any failure (disabled, missing coords, network error, bad payload)
 * returns null so the daily log is still created and the weather typed by hand.
 *
 * The WMO weather-code → Italian text map is public so it can be unit-tested and
 * reused when re-rendering a stored code.
 */
final class WeatherService
{
    /** WMO weather interpretation codes → Italian labels (Giornale dei Lavori). */
    public const WMO = [
        0  => 'Sereno',
        1  => 'Prevalentemente sereno',
        2  => 'Parzialmente nuvoloso',
        3  => 'Coperto',
        45 => 'Nebbia',
        48 => 'Nebbia con brina',
        51 => 'Pioviggine leggera',
        53 => 'Pioviggine moderata',
        55 => 'Pioviggine intensa',
        56 => 'Pioviggine gelata leggera',
        57 => 'Pioviggine gelata intensa',
        61 => 'Pioggia debole',
        63 => 'Pioggia moderata',
        65 => 'Pioggia forte',
        66 => 'Pioggia gelata debole',
        67 => 'Pioggia gelata forte',
        71 => 'Neve debole',
        73 => 'Neve moderata',
        75 => 'Neve forte',
        77 => 'Granelli di neve',
        80 => 'Rovesci deboli',
        81 => 'Rovesci moderati',
        82 => 'Rovesci violenti',
        85 => 'Rovesci di neve deboli',
        86 => 'Rovesci di neve forti',
        95 => 'Temporale',
        96 => 'Temporale con grandine debole',
        99 => 'Temporale con grandine forte',
    ];

    public static function describe(?int $code): string
    {
        if ($code === null) {
            return '';
        }
        return self::WMO[$code] ?? 'Condizioni variabili';
    }

    /**
     * Fetch the daily weather summary for a coordinate on a date.
     *
     * @return array{weather_code:int,weather_text:string,temp_min:float,temp_max:float}|null
     */
    public function forDate(?string $lat, ?string $lng, string $date): ?array
    {
        if (!Config::get('weather.enabled', true)) {
            return null;
        }
        if ($lat === null || $lng === null || $lat === '' || $lng === '' || !function_exists('curl_init')) {
            return null;
        }

        $url = (string) Config::get('weather.endpoint', 'https://api.open-meteo.com/v1/forecast')
            . '?' . http_build_query([
                'latitude'   => $lat,
                'longitude'  => $lng,
                'daily'      => 'weather_code,temperature_2m_max,temperature_2m_min',
                'timezone'   => 'Europe/Rome',
                'start_date' => $date,
                'end_date'   => $date,
            ]);

        $payload = $this->fetch($url);
        if ($payload === null) {
            return null;
        }

        $daily = $payload['daily'] ?? null;
        if (!is_array($daily)
            || !isset($daily['weather_code'][0], $daily['temperature_2m_max'][0], $daily['temperature_2m_min'][0])) {
            return null;
        }

        $code = (int) $daily['weather_code'][0];
        return [
            'weather_code' => $code,
            'weather_text' => self::describe($code),
            'temp_min'     => (float) $daily['temperature_2m_min'][0],
            'temp_max'     => (float) $daily['temperature_2m_max'][0],
        ];
    }

    /** @return array<string,mixed>|null Decoded JSON, or null on any transport/parse failure. */
    private function fetch(string $url): ?array
    {
        $timeout = max(1, (int) Config::get('weather.timeout', 5));
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_FAILONERROR    => true,
            CURLOPT_USERAGENT      => 'GestionaleMuratori/1.0 (+giornale-lavori)',
        ]);
        $body = curl_exec($ch);
        $ok   = $body !== false && (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE) === 200;
        curl_close($ch);

        if (!$ok) {
            return null;
        }
        $decoded = json_decode((string) $body, true);
        return is_array($decoded) ? $decoded : null;
    }
}
