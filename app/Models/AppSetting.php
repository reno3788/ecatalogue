<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'logo_path',
        'currency',
        'smtp_host',
        'smtp_port',
        'smtp_username',
        'smtp_password',
        'smtp_encryption',
        'smtp_from_address',
        'smtp_from_name',
    ];

    protected $casts = [
        'smtp_password' => 'encrypted',
    ];

    /**
     * Helper to get logo URL or default placeholder
     */
    public function getLogoUrlAttribute()
    {
        return $this->logo_path 
            ? asset('storage/' . $this->logo_path) 
            : null;
    }

    protected $appends = ['logo_url'];
}
