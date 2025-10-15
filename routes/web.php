<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn(): \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View => view('welcome'));
