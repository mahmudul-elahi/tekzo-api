<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return successResponse('Welcome to Tekzo');
});
