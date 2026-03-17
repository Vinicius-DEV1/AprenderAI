<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\FiltersAdmins;


class UserLog extends Model
{
    use HasFactory, FiltersAdmins;


    protected $fillable = [
        'user_id',
        'action',
        'description',
        'ip_address',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
