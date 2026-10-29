<?php

namespace Dotlib\BrunoDocs\Tests\Feature;

use Dotlib\BrunoDocs\Tests\Support\User;
use Dotlib\BrunoDocs\Tests\TestCase;

class BrunoDocsTest extends TestCase
{
    public function test_guests_are_redirected_when_access_is_private(): void
    {
        $this->get(route('bruno-docs.show'))->assertRedirect(route('login'));
        $this->get(route('bruno-docs.spec'))->assertRedirect(route('login'));
    }

    public function test_unknown_access_value_stays_private(): void
    {
        config(['bruno-docs.access' => 'friends']);

        $this->get(route('bruno-docs.show'))->assertRedirect(route('login'));
    }

    public function test_authenticated_users_receive_the_swagger_page_and_spec(): void
    {
        $user = $this->user('reader@example.com');

        $page = $this->actingAs($user)->get(route('bruno-docs.show'));
        $page->assertOk();
        $page->assertSee('SwaggerUIBundle', false);
        $page->assertSee('/vendor/bruno-docs/swagger-ui.css', false);
        $page->assertSee('/vendor/bruno-docs/swagger-ui-bundle.js', false);
        $this->assertFileExists(public_path('vendor/bruno-docs/swagger-ui.css'));
        $this->assertFileExists(public_path('vendor/bruno-docs/swagger-ui-bundle.js'));
        $page->assertSee('openapi.json', false);
        $page->assertSee('Fixture API', false);

        $spec = $this->actingAs($user)->get(route('bruno-docs.spec'));
        $spec->assertOk();
        $document = $spec->json();

        $this->assertSame('Fixture API', $document['info']['title']);
        $this->assertArrayHasKey('/api/medicaments', $document['paths']);
    }

    public function test_public_access_does_not_require_a_session(): void
    {
        config(['bruno-docs.access' => 'public']);

        $this->get(route('bruno-docs.show'))->assertOk();
        $this->get(route('bruno-docs.spec'))->assertOk();
    }

    public function test_private_access_with_an_email_list_allows_only_listed_users(): void
    {
        config([
            'bruno-docs.allowed_emails' => ' Allowed@Example.com | other@example.com | ',
        ]);

        $allowed = $this->user('allowed@example.com');
        $blocked = $this->user('blocked@example.com');

        $this->get(route('bruno-docs.show'))->assertRedirect(route('login'));

        $this->actingAs($allowed)->get(route('bruno-docs.show'))->assertOk();
        $this->actingAs($allowed)->get(route('bruno-docs.spec'))->assertOk();

        $this->actingAs($blocked)->get(route('bruno-docs.show'))
            ->assertForbidden()
            ->assertSee('Você não tem permissão para ver a documentação da API.', false);
        $this->actingAs($blocked)->get(route('bruno-docs.spec'))->assertForbidden();
    }

    public function test_email_list_is_ignored_when_access_is_public(): void
    {
        config([
            'bruno-docs.access' => 'public',
            'bruno-docs.allowed_emails' => 'only@example.com',
        ]);

        $this->get(route('bruno-docs.show'))->assertOk();
        $this->get(route('bruno-docs.spec'))->assertOk();
    }

    public function test_private_access_authenticates_with_the_configured_guard(): void
    {
        config([
            'auth.guards.docs' => [
                'driver' => 'session',
                'provider' => 'users',
            ],
            'bruno-docs.guard' => 'docs',
        ]);

        $user = $this->user('guard@example.com');

        $this->actingAs($user)->get(route('bruno-docs.show'))->assertRedirect(route('login'));
        $this->actingAs($user, 'docs')->get(route('bruno-docs.show'))->assertOk();
        $this->actingAs($user, 'docs')->get(route('bruno-docs.spec'))->assertOk();
    }

    public function test_disabled_docs_respond_with_not_found(): void
    {
        config(['bruno-docs.enabled' => false]);

        $this->get(route('bruno-docs.show'))->assertNotFound();
        $this->get(route('bruno-docs.spec'))->assertNotFound();
    }

    private function user(string $email): User
    {
        return User::query()->create([
            'name' => 'Reader',
            'email' => $email,
            'password' => 'secret',
        ]);
    }
}
