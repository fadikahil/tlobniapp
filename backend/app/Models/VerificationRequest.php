<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class   VerificationRequest extends Model
{
    use HasFactory;
    protected $fillable = [
        'verification_field_value_id',
        'user_id',
        'status',
        'rejection_reason'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function verification_field_values()
    {
        return $this->hasMany(VerificationFieldValue::class, 'verification_request_id', 'id');
    }

    public function scopeOwner($query){
        return $query->where('user_id', auth()->id());
    }
}
