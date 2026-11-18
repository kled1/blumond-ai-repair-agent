<?php

use Illuminate\Support\Facades\Route;

// Single page: the React app (resources/js) talks to /api/tickets.
Route::view('/', 'app');
