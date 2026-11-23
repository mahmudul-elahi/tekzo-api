<?php

namespace App\Http\Controllers\Api\V1\Career;

use App\Http\Controllers\Controller;
use App\Services\Career\CareerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CareerApplicationController extends Controller
{
    public function __construct(private CareerService $careerService) {}

    /**
     * Validation runs after the job lookup (not in a FormRequest), so an unknown job
     * is reported as 404 even when the submitted form is invalid.
     */
    public function store(Request $request, string $slug): JsonResponse
    {
        $job = $this->careerService->findOpenPosition($slug);

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

        $this->careerService->apply($job, $request);

        return successResponse('Applied successfully');
    }
}
