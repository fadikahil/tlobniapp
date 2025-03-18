<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserReview extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'reviewer_id',
        'review',
        'ratings',
        'report_status',
        'report_reason',
        'report_rejected_reason',
    ];

    /**
     * Get the user being reviewed
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the user who wrote the review
     */
    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /**
     * Scope for filtering
     */
    public function scopeFilter($query, $filterObject)
    {
        if (!empty($filterObject)) {
            foreach ($filterObject as $column => $value) {
                $query->where((string)$column, (string)$value);
            }
        }
        return $query;
    }

    /**
     * Scope for searching
     */
    public function scopeSearch($query, $search)
    {
        $search = "%" . $search . "%";
        return $query->where(function ($q) use ($search) {
            $q->orWhere('review', 'LIKE', $search)
                ->orWhere('ratings', 'LIKE', $search)
                ->orWhere('id', 'LIKE', $search)
                ->orWhere('report_status', 'LIKE', $search)
                ->orWhere('report_reason', 'LIKE', $search)
                ->orWhere('report_rejected_reason', 'LIKE', $search)
                ->orWhere('user_id', 'LIKE', $search)
                ->orWhere('reviewer_id', 'LIKE', $search)
                ->orWhereHas('user', function ($q) use ($search) {
                    $q->where('name', 'LIKE', $search);
                })
                ->orWhereHas('reviewer', function ($q) use ($search) {
                    $q->where('name', 'LIKE', $search);
                });
        });
    }

    /**
     * Scope for sorting
     */
    public function scopeSort($query, $column, $order)
    {
        if ($column == "user_name") {
            $query->leftJoin('users', 'users.id', '=', 'user_reviews.user_id')
                ->orderBy('users.name', $order);
        } else if ($column == "reviewer_name") {
            $query->leftJoin('users as reviewers', 'reviewers.id', '=', 'user_reviews.reviewer_id')
                ->orderBy('reviewers.name', $order);
        } else {
            $query->orderBy($column, $order);
        }
        return $query->select('user_reviews.*');
    }
}
