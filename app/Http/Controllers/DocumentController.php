<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DocumentController extends Controller
{
    public function index(
        Request $request
    ): Response {
        return Inertia::render(
            'Documents/Index',
            [
                'documents' =>
                    $request
                        ->user()
                        ->organization
                        ->documents()
                        ->with('uploader')
                        ->latest()
                        ->get(),
            ]
        );
    }
}
