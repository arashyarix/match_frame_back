<?php

use Illuminate\Support\Facades\Route;

// The panel lives at /admin (registered by AdminPanelProvider). Send "/" there.
Route::get('/', fn () => redirect('/admin'));
