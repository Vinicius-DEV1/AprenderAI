<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class ApiKeyVault extends Model
{
    use HasFactory;

    protected $fillable = [
        'nickname',
        'provider',
        'key',
        'is_valid',
        'last_tested_at',
    ];

    protected $casts = [
        'is_valid' => 'boolean',
        'last_tested_at' => 'datetime',
    ];

    protected $hidden = [
        'key',
    ];

    public function setKeyAttribute($value)
    {
        $this->attributes['key'] = Crypt::encryptString($value);
    }

    public function getDecryptedKeyAttribute()
    {
        return Crypt::decryptString($this->attributes['key']);
    }

    public function routes()
    {
        return $this->hasMany(ApiKey::class, 'vault_id');
    }
}
