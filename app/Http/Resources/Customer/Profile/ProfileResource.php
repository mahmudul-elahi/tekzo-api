<?php

namespace App\Http\Resources\Customer\Profile;

use App\Http\Resources\Elite\EliteResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $emailVerifiedAt = $this->email_verified_at;
        $phoneVerifiedAt = $this->phone_verified_at;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'gender' => $this->gender,
            'date_of_birth' => $this->date_of_birth,
            'profile_picture' => $this->profile_picture,
            'is_email_verified' => (bool) $emailVerifiedAt,
            'is_phone_verified' => (bool) $phoneVerifiedAt,
            'email_verified_at' => $emailVerifiedAt?->toIso8601String(),
            'phone_verified_at' => $phoneVerifiedAt?->toIso8601String(),
            'total_points' => $this->total_points ?? 0,
            'elite' => $this->elite
                ? new EliteResource($this->elite, $this->total_points ?? 0)
                : null,
            'elite_history' => $this->eliteHistories->map(fn ($h) => [
                'elite' => new EliteResource($h->elite, $this->total_points ?? 0),
                'achieved_at' => $h->achieved_at,
            ])->values(),
        ];
    }
}
