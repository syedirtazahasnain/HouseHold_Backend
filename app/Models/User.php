<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Carbon\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\Log;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, SoftDeletes, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        'd_o_j',
        'location',
        'emp_id',
        'status',
    ];
    protected $appends = ['role'];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'integer',
        ];
    }

     // Get role name
     public function getRoleAttribute()
     {
         return config('roles.roles')[$this->is_admin] ?? 'user';
     }

     public function hasRole($role)
     {
         return $this->role === $role;
     }

     // Assign abilities based on role
     public function createAuthToken()
     {
         return $this->createToken('auth_token', [$this->role])->plainTextToken;
     }

    public function getCreatedAtAttribute($value)
    {
        return $value ? $this->asDateTime($value)->format('M d, Y') : null;
    }

    public function setDOJAttribute($value)
    {
        if (empty($value)) {
            $this->attributes['d_o_j'] = null;
            return;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $this->attributes['d_o_j'] = $value;
            return;
        }
        if (is_numeric($value)) {
            try {
                $date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value);
                $this->attributes['d_o_j'] = Carbon::instance($date)->format('Y-m-d');
                return;
            } catch (\Exception $e) {

            }
        }
        $formats = [
            'Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y', 'm-d-Y',
            'Y/m/d', 'd M Y', 'd F Y', 'M d Y', 'F d Y'
        ];
        foreach ($formats as $format) {
            try {
                $this->attributes['d_o_j'] = Carbon::createFromFormat($format, $value)->format('Y-m-d');
                return;
            } catch (\Exception $e) {
                continue;
            }
        }
        try {
            $this->attributes['d_o_j'] = Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception $e) {
            Log::error("Failed to parse date: " . $value);
            $this->attributes['d_o_j'] = null;
        }
    }
}
