<?php

use Illuminate\Support\Facades\Route;

Route::get('/tester', function () {
    return view('tester');
});
