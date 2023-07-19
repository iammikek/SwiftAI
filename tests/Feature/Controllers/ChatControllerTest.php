<?php

namespace Tests\Feature\Controllers;

use App\Models\Chat;
use App\Models\Conversation;
use App\Models\Personality;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChatControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_can_send_message_returns_validation_errors()
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->postJson('/send-message', [])->assertUnprocessable()
            ->assertJson([
                'conversation_id' => [
                    'The conversation id field is required.',
                ],
            ]);
    }

    public function test_can_send_message()
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create(['user_id' => $user->id]);

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'some reply',
                        ],
                    ],
                ],
            ]),
        ]);

        $this
            ->actingAs($user)
            ->postJson('/send-message', [
                'conversation_id' => $conversation->id,
                'message' => 'Some Message',
            ])
            ->assertSuccessful()
            ->assertJson([
                'reply' => 'some reply',
                'messageCount' => 2,

            ]);
    }

    /** @test */
    public function test_can_create_new_conversation_fails_validation()
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->postJson('/new-conversation', [])->assertUnprocessable()
            ->assertJson([
                'title' => [
                    'The title field is required.',
                ],
                'personality_id' => [
                    'The personality id field is required.',
                ],
            ]);
    }

    /** @test */
    public function test_can_create_new_conversation()
    {
        $user = User::factory()->create();

        $payload = [
            'title' => 'Title',
            'personality_id' => Personality::factory()->create()->id,
        ];

        $this
            ->actingAs($user)
            ->postJson('/new-conversation', $payload)
            ->assertSuccessful()
            ->assertJsonFragment($payload + [
                'user_id' => $user->id,
            ]);
    }

    /** @test */
    public function test_can_get_conversations()
    {
        $user = User::factory()->create();

        $conversations = Conversation::factory()->count(4)->create(
            ['user_id' => $user->id]
        );

        $this
            ->actingAs($user)
            ->getJson('/get-conversations')
            ->assertSuccessful()
            ->assertJsonFragment($conversations->first()->toArray());
    }

    /** @test */
    public function test_can_get_message_count()
    {
        $user = User::factory()->create();

        $currentConversation = Conversation::factory()->create();

        $this
            ->actingAs($user)
            ->getJson('/get-messages/'.$currentConversation->id.'/message-count')
            ->assertSuccessful()
            ->assertJsonStructure(['count']);
    }

    /** @test */
    public function test_can_get_messages_for_conversation()
    {
        $user = User::factory()->create();

        $currentConversation = Conversation::factory()->create([
            'user_id' => $user->id,
        ]);

        $chats = Chat::factory()->count(3)->create([
            'user_id' => $user->id,
            'conversation_id' => $currentConversation->id,
        ]);

        $this
            ->actingAs($user)
            ->getJson('/get-messages/'.$currentConversation->id)
            ->assertSuccessful()
            ->dump()
            ->assertJsonStructure(['chats']);
    }

    /** @test */
    public function test_can_get_personalities()
    {
        $personalities = Personality::factory()->times(4)->create();

        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->getJson('/personalities')->assertSuccessful()
            ->assertJsonFragment($personalities->first()->toArray());
    }
}
