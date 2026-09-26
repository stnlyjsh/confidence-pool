<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFeedbackRequest;
use App\Http\Resources\FeedbackResource;
use App\Models\Feedback;
use App\Services\Github\GithubIssueReporter;
use Illuminate\Http\JsonResponse;
use Throwable;

class FeedbackController extends Controller
{
    public function store(StoreFeedbackRequest $request, GithubIssueReporter $reporter): JsonResponse
    {
        $feedback = Feedback::create([
            'user_id' => $request->user()->id,
            ...$request->validated(),
        ]);

        // The feedback is already saved regardless of what happens next —
        // a GitHub outage, a bad/expired token, or a renamed repo should
        // never mean a player's report just vanishes.
        try {
            $issue = $reporter->report($feedback);
            $feedback->update([
                'github_issue_url' => $issue['html_url'],
                'github_issue_number' => $issue['number'],
            ]);
        } catch (Throwable $e) {
            report($e);
        }

        return response()->json(['data' => new FeedbackResource($feedback)], 201);
    }
}
