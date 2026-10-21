<?php

declare(strict_types=1);

namespace SailConnect\Auth\Tests;

class UserRepositoryTest extends DatabaseTestCase
{
    public function testDisplayNamesAreDisambiguatedWithAnIncrementingInteger(): void
    {
        $a = $this->makeUser('Tim', 'Moses');
        $b = $this->makeUser('Tim', 'Morgan');
        $c = $this->makeUser('Tim', 'Mills');

        $this->assertSame('TimM', $a['display_name']);
        $this->assertSame('TimM1', $b['display_name']);
        $this->assertSame('TimM2', $c['display_name']);
    }

    public function testDisplayNameIsRecomputedWhenTheNameChanges(): void
    {
        $this->makeUser('Tim', 'Moses');
        $other = $this->makeUser('Sue', 'Smith');

        $this->users->updateProfile($other['user_id'], ['first_name' => 'Tim', 'last_name' => 'Miller', 'is_member' => false]);

        $this->assertSame('TimM1', $this->users->find($other['user_id'])['display_name']);
    }

    public function testKeepingTheSameNameKeepsTheSameDisplayName(): void
    {
        $this->makeUser('Tim', 'Moses');
        $b = $this->makeUser('Tim', 'Morgan');

        $this->users->updateProfile($b['user_id'], ['first_name' => 'Tim', 'last_name' => 'Morgan', 'is_member' => false]);

        $this->assertSame('TimM1', $this->users->find($b['user_id'])['display_name']);
    }

    public function testEmailsAreUniqueIgnoringCase(): void
    {
        $this->makeUser('A', 'One', 'Same@Example.com');

        $this->expectException(\PDOException::class);
        $this->makeUser('B', 'Two', 'same@example.com');
    }

    public function testFindByEmailIsCaseInsensitive(): void
    {
        $u = $this->makeUser('A', 'One', 'Mixed@Example.com');

        $this->assertSame($u['user_id'], $this->users->findByEmail('mixed@example.COM')['user_id']);
    }

    public function testMembershipNumberIsOnlyStoredForMembers(): void
    {
        $member = $this->makeUser('M', 'Ember', null, ['is_member' => true, 'membership_number' => '7001-1']);
        $this->assertSame('7001-1', $member['membership_number']);

        $this->users->updateProfile($member['user_id'], ['first_name' => 'M', 'last_name' => 'Ember', 'is_member' => false, 'membership_number' => '7001-1']);
        $this->assertNull($this->users->find($member['user_id'])['membership_number']);
    }

    public function testMobileNumberIsOptionalAndUpdatable(): void
    {
        $u = $this->makeUser('M', 'Obile', null, ['mobile_number' => '613-555-0100']);
        $this->assertSame('613-555-0100', $u['mobile_number']);
        $this->assertNull($this->makeUser('N', 'Omobile', null, ['mobile_number' => ''])['mobile_number']);

        $this->users->updateProfile($u['user_id'], ['first_name' => 'M', 'last_name' => 'Obile', 'is_member' => false, 'mobile_number' => '']);
        $this->assertNull($this->users->find($u['user_id'])['mobile_number']);
    }

    public function testActiveAdminCountIgnoresDeactivatedAdmins(): void
    {
        $a = $this->makeUser('A', 'Admin', null, ['is_platform_admin' => true]);
        $this->makeUser('B', 'Admin', null, ['is_platform_admin' => true]);
        $this->assertSame(2, $this->users->activeAdminCount());

        $this->users->setActive($a['user_id'], false);
        $this->assertSame(1, $this->users->activeAdminCount());
    }

    public function testCreateInsideAnOpenTransactionUsesASavepoint(): void
    {
        $this->db->beginTransaction();
        $u = $this->makeUser('Tx', 'User');
        $this->db->rollBack();

        $this->assertNull($this->users->find($u['user_id']));
    }
}
