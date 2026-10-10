<?php

use Illuminate\Support\Facades\Route;

// The React SPA owns every non-API URL; React Router resolves the path client-side.
Route::view('/{any?}', 'spa')
    ->where('any', '^(?!api(/|$)|sanctum/|storage/|up$).*')
    ->name('spa');
