<?php

namespace App\Services\Career;

use App\Models\Career\Career;
use App\Repositories\Career\CareerRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class CareerService
{
    public function __construct(private readonly CareerRepository $careers) {}

    /**
     * @return Collection<int, Career>
     */
    public function openPositions(): Collection
    {
        return $this->careers->activeLatestFirst();
    }

    public function findOpenPosition(string $slug): ?Career
    {
        return $this->careers->findActiveBySlug($slug);
    }

    /**
     * Store a validated application for a job and attach the CV (and optional photo) through the media library.
     */
    public function apply(Career $job, Request $request): void
    {
        $application = $this->careers->createApplication([
            'career_id' => $job->id,
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'deparment' => $request->deparment,
            'gender' => $request->gender,
            'age' => $request->age,
            'skills' => $request->skills,
            'reason_of_join' => $request->reason_of_join,
            'choos_reason' => $request->choos_reason,
            'status' => 1,
        ]);

        $cvMedia = $application->addMediaFromRequest('cv')->toMediaCollection('candidate_cv');
        $application->cv = $cvMedia->getFullUrl();

        if ($request->hasFile('photo')) {
            $photoMedia = $application->addMediaFromRequest('photo')->toMediaCollection('candidate_photo');
            $application->photo = $photoMedia->getFullUrl();
        }

        $application->save();
    }
}
