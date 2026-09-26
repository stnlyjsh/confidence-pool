<?php

namespace App\Services\Github;

use App\Enums\FeedbackType;
use App\Models\Feedback;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Files a piece of in-app feedback as an issue on the app's own GitHub
 * repo, using a repo-scoped token — no GitHub account required from the
 * person submitting it. Bug/idea labels are GitHub's own repo defaults,
 * so a fresh repo doesn't need any manual label setup for this to work.
 */
class GithubIssueReporter
{
    /**
     * @return array{html_url: string, number: int}
     */
    public function report(Feedback $feedback): array
    {
        $label = $feedback->type === FeedbackType::Bug ? 'bug' : 'enhancement';

        $body = $feedback->message;

        if ($feedback->screenshot_url) {
            $body .= "\n\n![screenshot]({$feedback->screenshot_url})";
        }

        $body .= "\n\n---\nSubmitted by {$feedback->user->name} via the app.";

        $response = Http::withToken(config('services.github.token'))
            ->acceptJson()
            ->post('https://api.github.com/repos/'.config('services.github.repo').'/issues', [
                'title' => Str::limit($feedback->message, 60),
                'body' => $body,
                'labels' => [$label],
            ])
            ->throw()
            ->json();

        return [
            'html_url' => $response['html_url'],
            'number' => $response['number'],
        ];
    }
}
