<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserRepositoryTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_creates_or_updates_user_successfully()
    {
        $userRepository = new \App\Repository\UserRepository();
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ];

        $result = $userRepository->createOrUpdate($userData);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertEquals($userData['name'], $result['name']);
        $this->assertEquals($userData['email'], $result['email']);

        $this->assertDatabaseHas('users', $userData);

        $updatedData = [
            'id' => $result['id'], 
            'name' => 'Jane Doe',
        ];
        $updatedResult = $userRepository->createOrUpdate($updatedData);

        $this->assertNotNull($updatedResult);
        $this->assertEquals($updatedData['name'], $updatedResult['name']);

        $this->assertDatabaseHas('users', $updatedData);

        $this->assertEquals(1, \App\User::count());
    }

    /** @test */
    public function it_returns_existing_user_if_email_exists()
    {
        $userRepository = new \App\Repository\UserRepository();
        $existingUser = factory(\App\User::class)->create(['email' => 'existing@example.com']);
        $userData = [
            'name' => 'John Doe',
            'email' => 'existing@example.com', 
        ];

        $result = $userRepository->createOrUpdate($userData);

        $this->assertEquals($existingUser->id, $result['id']);
        $this->assertEquals($userData['name'], $result['name']);
    }
}