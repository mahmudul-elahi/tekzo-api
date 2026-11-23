<?php

namespace App\Http\Controllers\Api\V1\Career;

use App\Enums\StatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Career\Career;
use App\Models\Career\CareerApplie;
use Illuminate\Http\Request;

class CareerApplicationController extends Controller
{
    public function store(Request $request, string $slug)
    {
        $job = Career::query()
            ->where('status', StatusEnum::Active->value)
            ->where('slug', $slug)->first();

        if (! $job) {
            return errorResponse('Job not found', 404);
        }

        $request->validate([
            'name' => 'required',
            'email' => 'required|email',
            'phone' => 'required',
            'gender' => 'required|in:Male,Female',
            'age' => 'required',
            'deparment' => 'nullable|max:100',
            'cv' => [
                'required',
                'file',
                'mimes:pdf,doc,docx',
                'max:1024',
            ],
            'photo' => 'nullable|file|mimes:jpg,jpeg,png|max:1024',
            'skills' => 'nullable|max:500',
            'reason_of_join' => 'nullable|max:500',
            'choos_reason' => 'nullable|max:500',
        ]);

        $application = CareerApplie::query()->create([
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

        return successResponse('Applied successfully');
    }
}
