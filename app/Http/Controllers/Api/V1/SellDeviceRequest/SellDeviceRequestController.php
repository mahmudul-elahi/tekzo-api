<?php

namespace App\Http\Controllers\Api\V1\SellDeviceRequest;

use App\Exceptions\GeneralException;
use App\Http\Controllers\Controller;
use App\Http\Requests\SellDeviceRequest\SellDeviceImageChunkRequest;
use App\Http\Requests\SellDeviceRequest\SellDeviceRequestRequest;
use App\Http\Resources\SellDeviceRequest\SellDeviceRequestResource;
use App\Models\SellDeviceRequest\SellDeviceRequest as SellDeviceRequestModel;
use App\Services\SellDevice\SellDeviceImageUploadService;
use App\Services\SellDevice\SellDeviceRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class SellDeviceRequestController extends Controller
{
    public function __construct(
        private readonly SellDeviceRequestService $sellDeviceService,
        private readonly SellDeviceImageUploadService $imageUploadService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        // ->through() keeps this a paginator (data/current_page/last_page/...)
        // instead of Resource::collection(), which drops that structure down
        // to a flat array once it's re-serialized through successResponse().
        $requests = $this->sellDeviceService
            ->paginateForContact($this->customerId(), clampPerPage($request->input('per_page'), 10, 48))
            ->through(fn ($r) => SellDeviceRequestResource::make($r)->resolve());

        return successResponse($requests);
    }

    /**
     * @throws GeneralException
     */
    public function show(SellDeviceRequestModel $sell_device_request): JsonResponse
    {
        return successResponse(
            SellDeviceRequestResource::make($this->sellDeviceService->showForContact($sell_device_request, $this->customerId()))
        );
    }

    /**
     * Receives one chunk of a large photo; once the last chunk lands, returns the stored
     * path to use in the `images` array on store/update.
     *
     * @throws GeneralException
     */
    public function uploadImage(SellDeviceImageChunkRequest $request): JsonResponse
    {

        return successResponse($this->imageUploadService->receiveChunk(
            $this->customerId(),
            $request->input('identifier'),
            $request->input('filename'),
            (int) $request->input('chunk_index'),
            (int) $request->input('total_chunks'),
            $request->file('file'),
        ));
    }

    /**
     * @throws Throwable
     */
    public function store(SellDeviceRequestRequest $request): JsonResponse
    {
        return successResponse(
            SellDeviceRequestResource::make($this->sellDeviceService->create($request->validated(), $this->customerId())),
            201
        );
    }

    /**
     * @throws Throwable
     */
    public function update(SellDeviceRequestRequest $request, SellDeviceRequestModel $sell_device_request): JsonResponse
    {
        return successResponse(
            SellDeviceRequestResource::make($this->sellDeviceService->update($sell_device_request, $request->validated(), $this->customerId()))
        );
    }

    /**
     * @throws GeneralException
     */
    public function destroy(SellDeviceRequestModel $sell_device_request): JsonResponse
    {
        $this->sellDeviceService->delete($sell_device_request, $this->customerId());

        return responseMessage('Sell device request deleted successfully');
    }

    private function customerId(): mixed
    {
        return auth()->guard('customer')->id();
    }
}
