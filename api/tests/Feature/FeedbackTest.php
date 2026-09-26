<?php

namespace Tests\Feature;

use App\Models\Feedback;
use App\Models\PoolParticipant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
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

    public function test_multiple_screenshots_can_be_attached_and_are_all_embedded_in_the_issue(): void
    {
        Storage::fake();
        $participant = PoolParticipant::factory()->create();

        Http::fake([
            'api.github.com/*' => Http::response(['html_url' => 'https://github.com/owner/repo/issues/9', 'number' => 9], 201),
        ]);

        $response = $this->actingAs($participant->user)->post('/api/feedback', [
            'type' => 'bug',
            'message' => 'Look at this',
            'screenshots' => [
                UploadedFile::fake()->image('bug1.png'),
                UploadedFile::fake()->image('bug2.png'),
                UploadedFile::fake()->image('bug3.png'),
            ],
        ]);

        $response->assertCreated();
        $screenshotUrls = $response->json('data.screenshot_urls');
        $this->assertCount(3, $screenshotUrls);

        $feedback = Feedback::query()->where('user_id', $participant->user_id)->sole();
        $this->assertSame($screenshotUrls, $feedback->screenshot_urls);

        Http::assertSent(function ($request) use ($screenshotUrls) {
            return collect($screenshotUrls)->every(fn ($url) => str_contains($request['body'], $url));
        });
    }

    public function test_more_than_five_screenshots_is_rejected(): void
    {
        $participant = PoolParticipant::factory()->create();

        $this->actingAs($participant->user)->post('/api/feedback', [
            'type' => 'bug',
            'message' => 'Look at this',
            'screenshots' => array_map(fn ($i) => UploadedFile::fake()->image("bug{$i}.png"), range(1, 6)),
        ])->assertInvalid(['screenshots']);
    }

    public function test_a_non_image_screenshot_is_rejected(): void
    {
        $participant = PoolParticipant::factory()->create();

        $this->actingAs($participant->user)->post('/api/feedback', [
            'type' => 'bug',
            'message' => 'Look at this',
            'screenshots' => [UploadedFile::fake()->create('notes.txt', 10)],
        ])->assertInvalid(['screenshots.0']);
    }
}
