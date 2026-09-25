<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Event;
use App\Models\AIConversation;
use App\Models\AIMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiAssistantTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Run all migrations and seed before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * Test all AI chat assistant features for customer.
     */
    public function test_customer_ai_features(): void
    {
        // 1. Login Customer
        $loginRes = $this->postJson('/api/login', [
            'email' => 'customer@kettiket.com',
            'password' => 'password123',
        ]);
        $loginRes->assertStatus(200);
        $token = $loginRes->json('access_token');
        $authHeader = ['Authorization' => 'Bearer ' . $token];

        // 2. Create AI Conversation thread
        $convRes = $this->postJson('/api/ai/conversations', ['title' => 'Tanya Konser'], $authHeader);
        $convRes->assertStatus(201)
                ->assertJsonPath('conversation.title', 'Tanya Konser');
        $convId = $convRes->json('conversation.id');

        // 3. Send chat message to AI assistant
        $chatRes = $this->postJson('/api/ai/chat', [
            'conversation_id' => $convId,
            'message' => 'Halo AI, apa saja konser Coldplay terdekat?'
        ], $authHeader);
        
        $chatRes->assertStatus(201)
                ->assertJsonStructure(['conversation_id', 'user_message', 'ai_message', 'metrics']);
        
        $aiMessageId = $chatRes->json('ai_message.id');

        // 4. Submit message feedback rating
        $feedbackRes = $this->postJson("/api/ai/messages/{$aiMessageId}/feedback", [
            'rating' => 5,
            'comment' => 'Penjelasan lokasi konser sangat jelas'
        ], $authHeader);
        
        $feedbackRes->assertStatus(200)
                    ->assertJsonPath('feedback.rating', 5);

        // 5. Request recommendations
        $recRes = $this->getJson('/api/ai/recommendations', $authHeader);
        $recRes->assertStatus(200)
               ->assertJsonStructure(['recommendations', 'metrics']);

        // 6. View conversations list
        $listRes = $this->getJson('/api/ai/conversations', $authHeader);
        $listRes->assertStatus(200)
                ->assertJsonCount(1, 'conversations');

        // 7. View conversation details
        $detailRes = $this->getJson("/api/ai/conversations/{$convId}", $authHeader);
        $detailRes->assertStatus(200)
                  ->assertJsonStructure(['conversation' => ['messages']]);

        // 8. Delete conversation thread
        $deleteRes = $this->deleteJson("/api/ai/conversations/{$convId}", [], $authHeader);
        $deleteRes->assertStatus(200);
    }

    /**
     * Test organizer AI analysis.
     */
    public function test_organizer_ai_features(): void
    {
        // 1. Login Organizer
        $loginRes = $this->postJson('/api/login', [
            'email' => 'organizer@kettiket.com',
            'password' => 'password123',
        ]);
        $loginRes->assertStatus(200);
        $token = $loginRes->json('access_token');
        $authHeader = ['Authorization' => 'Bearer ' . $token];

        // 2. Generate description
        $descRes = $this->postJson('/api/organizer/ai/generate-description', [
            'title' => 'Rock Arena 2026',
            'genre' => 'Hard Rock'
        ], $authHeader);
        $descRes->assertStatus(200)
                ->assertJsonStructure(['description', 'metrics']);

        // 3. Find event owned by this organizer
        $event = Event::first(); // Coldplay event seeded is owned by our seeded organizer
        $this->assertNotNull($event);

        // 4. Analyze sales
        $salesRes = $this->postJson('/api/organizer/ai/analyze-sales', ['event_id' => $event->id], $authHeader);
        $salesRes->assertStatus(200)->assertJsonStructure(['analysis', 'metrics']);

        // 5. Analyze buyer trends
        $trendsRes = $this->postJson('/api/organizer/ai/analyze-trends', ['event_id' => $event->id], $authHeader);
        $trendsRes->assertStatus(200)->assertJsonStructure(['trends', 'metrics']);

        // 6. Request ticket pricing suggestion
        $pricingRes = $this->postJson('/api/organizer/ai/ticket-recommendation', ['event_id' => $event->id], $authHeader);
        $pricingRes->assertStatus(200)->assertJsonStructure(['recommendations', 'metrics']);

        // 7. Generate performance report
        $reportRes = $this->postJson('/api/organizer/ai/generate-report', ['event_id' => $event->id], $authHeader);
        $reportRes->assertStatus(200)->assertJsonStructure(['report', 'metrics']);
    }

    /**
     * Test admin platform intelligence.
     */
    public function test_admin_ai_features(): void
    {
        // 1. Login Admin
        $loginRes = $this->postJson('/api/login', [
            'email' => 'admin@kettiket.com',
            'password' => 'password123',
        ]);
        $loginRes->assertStatus(200);
        $token = $loginRes->json('access_token');
        $authHeader = ['Authorization' => 'Bearer ' . $token];

        // 2. Platform performance audit
        $platRes = $this->postJson('/api/admin/ai/analyze-platform', [], $authHeader);
        $platRes->assertStatus(200)
                ->assertJsonStructure(['analysis', 'metrics']);

        // 3. Platform suspicious activity log detector
        $suspRes = $this->postJson('/api/admin/ai/detect-suspicious', [], $authHeader);
        $suspRes->assertStatus(200)
                ->assertJsonStructure(['analysis', 'metrics']);
    }

    /**
     * Test role-based protection enforcement on AI endpoints.
     */
    public function test_role_based_protection_on_ai(): void
    {
        // 1. Login Customer
        $loginRes = $this->postJson('/api/login', [
            'email' => 'customer@kettiket.com',
            'password' => 'password123',
        ]);
        $token = $loginRes->json('access_token');
        $custHeader = ['Authorization' => 'Bearer ' . $token];

        // 2. Find event
        $event = Event::first();
        $this->assertNotNull($event);

        // 3. Customer attempts to access organizer sales analysis -> Forbidden (via middleware role check or custom auth)
        // Wait, does the API route check role or does the controller?
        // Let's verify: Customer role cannot perform organizer actions. In our controller:
        // $user->role !== 'organizer' returns abort(403). So it should return 403!
        $salesRes = $this->postJson('/api/organizer/ai/analyze-sales', ['event_id' => $event->id], $custHeader);
        $salesRes->assertStatus(403);

        // 4. Customer attempts to access admin analyze-platform -> Forbidden
        // Admin endpoints are prefix admin group which checks if user has admin role.
        // Wait, does api.php check role? In our AdminController we have role check. Let's make sure it returns 403.
        $adminRes = $this->postJson('/api/admin/ai/analyze-platform', [], $custHeader);
        $adminRes->assertStatus(403);
    }
}
