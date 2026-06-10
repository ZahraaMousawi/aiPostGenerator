<?php

namespace App\Http\Controllers;

use App\Services\SocialPostAgent;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PostAgentController extends Controller
{
    public function index(): View
    {
        return view('welcome', [
            'result' => null,
            'topic' => '',
        ]);
    }

    public function generate(Request $request, SocialPostAgent $agent): View
    {
        $validated = $request->validate([
            'topic' => ['required', 'string', 'min:5', 'max:4000'],
        ], [
            'topic.required' => 'يرجى إدخال النص.',
            'topic.min' => 'يرجى إدخال نص أطول قليلا حتى يمكن إنشاء منشور مناسب.',
            'topic.max' => 'النص طويل جدا. الحد الأقصى 4000 حرف.',
        ]);

        return view('welcome', [
            'result' => $agent->generate($validated['topic']),
            'topic' => $validated['topic'],
        ]);
    }
}
