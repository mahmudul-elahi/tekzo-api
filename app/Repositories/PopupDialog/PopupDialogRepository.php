<?php

namespace App\Repositories\PopupDialog;

use App\Enums\StatusEnum;
use App\Models\PopupDialog\PopupDialog;
use App\Repositories\BaseRepository;

class PopupDialogRepository extends BaseRepository
{
    public function __construct(PopupDialog $popupDialog)
    {
        $this->model = $popupDialog;
    }

    public function firstActive(): ?PopupDialog
    {
        return $this->query()
            ->where('status', StatusEnum::Active->value)
            ->select(['id', 'name', 'image', 'mobile_image', 'url'])
            ->first();
    }
}
