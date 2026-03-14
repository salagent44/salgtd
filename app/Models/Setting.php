<?php

namespace App\Models;

use App\Traits\HasSyncVersion;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasSyncVersion;

    public $incrementing = false;
    protected $primaryKey = 'key';
    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'value',
    ];

    public static function get(string $key, ?string $default = null): ?string
    {
        return static::find($key)?->value ?? $default;
    }

    public static function set(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public static function getNoteFontCss(): string
    {
        $fontMap = [
            'system' => '-apple-system, BlinkMacSystemFont, sans-serif',
            'inter' => "'Inter', sans-serif",
            'dm-sans' => "'DM Sans', sans-serif",
            'nunito' => "'Nunito', sans-serif",
            'lora' => "'Lora', serif",
            'merriweather' => "'Merriweather', serif",
            'libre-baskerville' => "'Libre Baskerville', serif",
            'jetbrains-mono' => "'JetBrains Mono', monospace",
            'ibm-plex-mono' => "'IBM Plex Mono', monospace",
            'inconsolata' => "'Inconsolata', monospace",
        ];

        $key = static::get('note_font', 'system');

        return $fontMap[$key] ?? $fontMap['system'];
    }
}
