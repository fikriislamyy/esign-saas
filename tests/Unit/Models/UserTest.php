<?php

namespace Tests\Unit\Models;

use App\Models\User;
use Tests\TestCase;

class UserTest extends TestCase
{
    public function test_owner_role_is_owner(): void
    {
        $user = new User(['role' => 'owner']);

        $this->assertTrue($user->isOwner());
    }

    public function test_owner_role_is_not_admin(): void
    {
        $user = new User(['role' => 'owner']);

        $this->assertFalse($user->isAdmin());
    }

    public function test_owner_can_manage_members(): void
    {
        $user = new User(['role' => 'owner']);

        $this->assertTrue($user->canManageMembers());
    }

    public function test_admin_role_is_not_owner(): void
    {
        $user = new User(['role' => 'admin']);

        $this->assertFalse($user->isOwner());
    }

    public function test_admin_role_is_admin(): void
    {
        $user = new User(['role' => 'admin']);

        $this->assertTrue($user->isAdmin());
    }

    public function test_admin_can_manage_members(): void
    {
        $user = new User(['role' => 'admin']);

        $this->assertTrue($user->canManageMembers());
    }

    public function test_member_role_is_not_owner(): void
    {
        $user = new User(['role' => 'member']);

        $this->assertFalse($user->isOwner());
    }

    public function test_member_role_is_not_admin(): void
    {
        $user = new User(['role' => 'member']);

        $this->assertFalse($user->isAdmin());
    }

    public function test_member_cannot_manage_members(): void
    {
        $user = new User(['role' => 'member']);

        $this->assertFalse($user->canManageMembers());
    }

    public function test_null_role_is_not_owner(): void
    {
        $user = new User(['role' => null]);

        $this->assertFalse($user->isOwner());
    }

    public function test_null_role_is_not_admin(): void
    {
        $user = new User(['role' => null]);

        $this->assertFalse($user->isAdmin());
    }

    public function test_null_role_cannot_manage_members(): void
    {
        $user = new User(['role' => null]);

        $this->assertFalse($user->canManageMembers());
    }
}
