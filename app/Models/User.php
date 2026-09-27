<?php

namespace App\Models;

use App\Mail\EmailVerificationOtpMail;
use App\Observability\Telemetry;
use App\Services\EmailVerificationOtpService;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'organization_id',
        'role',
        'name',
        'email',
        'country_code',
        'phone_number',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    protected $key_type = 'string';

    public $incrementing = false;

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function otpVerifications()
    {
        return $this->hasMany(OtpVerification::class);
    }

    public function sendEmailVerificationNotification(): void
    {
        $otp = app(EmailVerificationOtpService::class)->generate($this);

        app(Telemetry::class)->submitMail('email_verification', fn () => Mail::to($this->email)->send(new EmailVerificationOtpMail($this, $otp)));
        app(Telemetry::class)->event('auth.otp.sent', [
            'app.outcome' => 'success',
            'app.reason' => 'email_verification',
        ]);
    }

    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function canManageMembers(): bool
    {
        return in_array(
            $this->role,
            ['owner', 'admin']
        );
    }

    public function documents()
    {
        return $this->hasMany(Document::class, 'owner_id');
    }

    public function uploadedDocuments()
    {
        return $this->hasMany(
            Document::class,
            'uploaded_by'
        );
    }
}
