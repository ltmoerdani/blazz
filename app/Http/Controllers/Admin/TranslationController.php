<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller as BaseController;
use Illuminate\Http\Request;

class TranslationController extends BaseController
{
    public function index(Request $request)
    {
        // Implementation here
        return response()->json(['message' => 'Translation controller working']);
    }
}