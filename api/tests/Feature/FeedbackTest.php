<?php

namespace Tests\Feature;

use App\Models\PoolParticipant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_requires_authentication(): void
    {
        $this->postJson('/api/feedback', ['type' => 'bug', 'message' => 'It broke'])->assertUnauthorized();
    }

    public function test_it_requires_a_valid_type_and_a_message(): void
    {
        $participant = PoolParticipant::factory()->create();

        $this->actingAs($participant->user)
            ->postJson('/api/feedback', ['type' => 'not-a-type', 'message' => ''])
            ->assertInvalid(['type', 'message']);
    }

    public function test_it_saves_feedback_and_files_a_github_issue_labeled_bug(): void
    {
        $participant = PoolParticipant::factory()->create();

        Http::fake([
            'api.github.com/*' => Http::response(['html_url' => 'https://github.com/owner/repo/issues/42', 'number' => 42], 201),
        ]);

        $response = $this->actingAs($participant->user)
            ->postJson('/api/feedback', ['type' => 'bug', 'message' => 'The scoreboard is wrong']);

        $response->assertCreated()->assertJsonPath('data.github_issue_url', 'https://github.com/owner/repo/issues/42');

        $this->assertDatabaseHas('feedback', [
            'user_id' => $participant->user_id,
            'type' => 'bug',
            'message' => 'The scoreboard is wrong',
            'github_issue_number' => 42,
        ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.github.com/repos/'.config('services.github.repo').'/issues'
                && $request['labels'] === ['bug'];
        });
    }

    public function test_an_idea_is_labeled_enhancement(): void
    {
        $participant = PoolParticipant::factory()->create();

        Http::fake([
            'api.github.com/*' => Http::response(['html_url' => 'https://github.com/owner/repo/issues/7', 'number' => 7], 201),
        ]);

        $this->actingAs($participant->user)->postJson('/api/feedback', ['type' => 'idea', 'message' => 'Add dark mode']);

        Http::assertSent(fn ($request) => $request['labels'] === ['enhancement']);
    }

    public function test_feedback_is_still_saved_even_if_github_is_down(): void
    {
        $participant = PoolParticipant::factory()->create();

        Http::fake([
            'api.github.com/*' => Http::response(['message' => 'Bad credentials'], 401),
        ]);

        $response = $this->actingAs($participant->user)
            ->postJson('/api/feedback', ['type' => 'bug', 'message' => 'Still here even if GitHub is not']);

        $response->assertCreated()->assertJsonPath('data.github_issue_url', null);

        $this->assertDatabaseHas('feedback', [
            'user_id' => $participant->user_id,
            'message' => 'Still here even if GitHub is not',
            'github_issue_url' => null,
        ]);
    }
}
