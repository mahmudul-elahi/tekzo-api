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
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'gender' => $this->gender,
            'date_of_birth' => $this->date_of_birth,
            'profile_picture' => $this->profile_picture,
            'is_email_verified' => (bool) $this->email_verified_at,
            'is_phone_verified' => (bool) $this->phone_verified_at,
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
