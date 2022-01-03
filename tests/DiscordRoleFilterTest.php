<?php

/**
 * This file is part of SeAT Discord Connector.
 *
 * Copyright (C) 2021  Troyburn <1537309279@character.id.eve.ccpgames.com>
 *
 * SeAT Discord Connector is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * SeAT Discord Connector is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace Warlof\Seat\Connector\Drivers\Discord\Tests;

use Orchestra\Testbench\TestCase;
use Warlof\Seat\Connector\Drivers\Discord\Driver\DiscordRoleFilter;

class TestItem {

    /**
     * The rolespec for the DiscordRoleFilter configuration.
     * @var string $rolespec
     */
    public $rolespec;

    /**
     * Zero or more roles to be checking.
     * @var string $rolelist
     */
    public $rolelist;

    /**
     * @var mixed $expect
     */
    public $expect;

    /**
     * @var bool $skip
     */
    public $skip;

    public function __construct(string $rolespec, string $rolelist, /*mixed*/ $expect, bool $skip = false) {
        $this->rolespec = $rolespec;
        $this->rolelist = $rolelist;
        $this->expect = $expect;
        $this->skip = $skip;
    }

    public function equalsRolespecRolelist(TestItem $other = null): bool {
        if(is_null($other))
            return false;
        if($this->rolespec === $other->rolespec && $this->rolelist == $other->rolelist)
            return true;
        return false;
    }

    public function prettyToString(): string {
        $s = 'TestItem(';
        $s .= 'skip=' . var_export($this->skip, true);
        $s .= ', expect=' . var_export($this->expect, true);
        $s .= ', rolespec=' . var_export($this->rolespec, true);
        $s .= ', rolelist=' . var_export($this->rolelist, true);
        $s .= ')';
        return $s;
    }
}

class DiscordRoleFilterTest extends TestCase
{
    const ROLE_1 = 'RoleName1';
    const ROLE_2 = 'RoleName Example2 RemoveMe';
    const ROLE_3 = 'Role Name 3';
    const ROLE_4 = 'Role Name, With Comma';
    const ROLE_5 = 'R-deadbeef';
    const ROLE_6 = 'R-My Role';
    const ROLE_7 = '';

    const ROLE_LIST_1 = self::ROLE_1;
    const ROLE_LIST_2 = self::ROLE_2;
    const ROLE_LIST_3 = self::ROLE_1 . ':' . self::ROLE_3;
    const ROLE_LIST_4 = self::ROLE_4;
    const ROLE_LIST_5 = self::ROLE_5;
    const ROLE_LIST_6 = self::ROLE_6;
    const ROLE_LIST_7 = '';

    const ROLES = [
        self::ROLE_1,
        self::ROLE_2,
        self::ROLE_3,
        self::ROLE_4,
        self::ROLE_5,
        self::ROLE_6,
        self::ROLE_7,
    ];

    const ROLE_LISTS = [
        self::ROLE_LIST_1,
        self::ROLE_LIST_2,
        self::ROLE_LIST_3,
        self::ROLE_LIST_4,
        self::ROLE_LIST_5,
        self::ROLE_LIST_6,
        self::ROLE_LIST_7,
    ];

    private $TEST_EXPECTS = array();

    protected function setUp(): void
    {
        parent::setUp();

        /* special case an empty spec or empty role should result in no match */
        $this->TEST_EXPECTS[] = new TestItem('', '', false);
        /* ROLE_2 really does exist in the rolespec, and doesn't need the @@everyrole to be true */
        $this->TEST_EXPECTS[] = new TestItem( ' @@everyrole:RoleName Example2 RemoveMe', self::ROLE_2, true);
        /* Test a reversed config does not work, because @@everyrole in the rolelist is treated as a rolename */
        $this->TEST_EXPECTS[] = new TestItem('', '@@everyrole', false);

        /* expect = TRUE */
        foreach (self::ROLE_LISTS as $rolelist)
            $this->expect_add(new TestItem('@@everyrole', $rolelist, true));
        foreach (self::ROLE_LISTS as $rolelist)
            $this->expect_add(new TestItem('@@everyrole:RoleName Example2 RemoveMe', $rolelist, true));
        foreach (self::ROLE_LISTS as $rolelist)
            $this->expect_add(new TestItem(':@@everyrole', $rolelist, true));
        foreach (self::ROLE_LISTS as $rolelist)
            $this->expect_add(new TestItem('@@everyrole:', $rolelist, true));

        /* expect = FALSE */
        foreach (self::ROLE_LISTS as $rolelist)
            $this->expect_add(new TestItem('', $rolelist, false));
        foreach (self::ROLE_LISTS as $rolelist)
            $this->expect_add(new TestItem('@@everyrole ', $rolelist, false));
        foreach (self::ROLE_LISTS as $rolelist)
            $this->expect_add(new TestItem(' @@everyrole:RoleName Example2 RemoveMe', $rolelist, false));
        foreach (self::ROLE_LISTS as $rolelist)
            $this->expect_add(new TestItem(':@@everyrole ', $rolelist, false));
        foreach (self::ROLE_LISTS as $rolelist)
            $this->expect_add(new TestItem(': @@everyrole', $rolelist, false));
        foreach (self::ROLE_LISTS as $rolelist)
            $this->expect_add(new TestItem('@@everyrole :', $rolelist, false));
        foreach (self::ROLE_LISTS as $rolelist)
            $this->expect_add(new TestItem(' @@everyrole:', $rolelist, false));
        foreach (self::ROLE_LISTS as $rolelist)
            $this->expect_add(new TestItem('@everyrole', $rolelist, false));
        foreach (self::ROLE_LISTS as $rolelist)
            $this->expect_add(new TestItem('@EVERYROLE', $rolelist, false));
        foreach (self::ROLE_LISTS as $rolelist)
            $this->expect_add(new TestItem(':', $rolelist, false));
        foreach (self::ROLE_LISTS as $rolelist)
            $this->expect_add(new TestItem('::', $rolelist, false));

        /* This will dump all the TEST_EXPECTS to manually inspect the dataset and expectation */
        if(false) {
            foreach ($this->TEST_EXPECTS as $item)
                echo $item->prettyToString() . PHP_EOL;
        }
    }

    protected function expect_add(TestItem $item): bool
    {
        foreach ($this->TEST_EXPECTS as $i) {
            /* Don't add duplicate tests, this allows up to manually configure test set overrides
             *  first with expected outcome, then auto generate all the rest in setUp()
             */
            if($i->equalsRolespecRolelist($item))
                return false;
        }
        $this->TEST_EXPECTS[] = $item;
        return true;
    }

    public function testCheckOne(): void
    {
        $drf = new DiscordRoleFilter($this::ROLE_LIST_1);

        $bf = $drf->checkOne($this::ROLE_1);
        $this->assertTrue($bf);

        $bf = $drf->checkOne($this::ROLE_2);
        $this->assertFalse($bf);

        $bf = $drf->checkOne($this::ROLE_3);
        $this->assertFalse($bf);

        $bf = $drf->checkOne($this::ROLE_4);
        $this->assertFalse($bf);


        $bf = $drf->checkOne($this::ROLE_1, null);
        $this->assertTrue($bf);

        $bf = $drf->checkOne($this::ROLE_2, null);
        $this->assertNull($bf);

        $bf = $drf->checkOne($this::ROLE_3, null);
        $this->assertNull($bf);

        $bf = $drf->checkOne($this::ROLE_4, null);
        $this->assertNull($bf);


        $bf = $drf->checkOne(null, true);
        $this->assertTrue($bf);

        $bf = $drf->checkOne(null, false);
        $this->assertFalse($bf);

        $bf = $drf->checkOne(null, null);
        $this->assertNull($bf);


        /* empty spec check */
        foreach ($this::ROLES as $role) {
            $drf = new DiscordRoleFilter('');
            $bf = $drf->checkOne($role);
            $this->assertFalse($bf);
        }

        /* empty role check */
        foreach ($this::ROLE_LISTS as $rolespec) {
            $drf = new DiscordRoleFilter($rolespec);
            $bf = $drf->checkOne('');
            $this->assertFalse($bf);
        }
    }

    public function testCheckAll(): void
    {
        foreach ($this->TEST_EXPECTS as $item) {
            if($item->skip)
                continue;

            $roles = explode(':', $item->rolelist);
            $drf = new DiscordRoleFilter($item->rolespec);
            $res = $drf->checkAll($roles);
            if ($res !== $item->expect)
                echo 'testCheckAll ' . var_export(array('result'=>$res,'item'=>$item),true) . PHP_EOL;
            $this->assertEquals($item->expect, $res);
        }
    }

    public function testCheckAllExtra(): void
    {
        $drf = new DiscordRoleFilter(DiscordRoleFilter::EVERYROLE_ENTRY);

        $bf = $drf->checkAll(null, false);
        $this->assertFalse($bf);

        $bf = $drf->checkAll(null, true);
        $this->assertTrue($bf);

        $bf = $drf->checkAll(null, null);
        $this->assertNull($bf);

        $ary = array();
        $bf = $drf->checkAll($ary, false);
        $this->assertFalse($bf);

        $bf = $drf->checkAll($ary, true);
        $this->assertTrue($bf);

        $bf = $drf->checkAll($ary, null);
        $this->assertNull($bf);

        $bf = $drf->checkAll();
        $this->assertFalse($bf);
    }

    public function testSaneDefaults(): void
    {
        $this->assertNotEmpty(DiscordRoleFilter::DEFAULT_CAN_ADD_ROLES);
        $this->assertNotEmpty(DiscordRoleFilter::DEFAULT_CAN_REMOVE_ROLES);
        $this->assertNotEmpty(DiscordRoleFilter::DEFAULT_VISIBLE_ROLES);
        $this->assertNotEmpty(DiscordRoleFilter::EVERYROLE_ENTRY);

        $drf = new DiscordRoleFilter(DiscordRoleFilter::DEFAULT_CAN_ADD_ROLES);
        $bf = $drf->check('anything');
        $this->assertTrue($bf);
        $bf = $drf->check('');  /* nothing */
        $this->assertFalse($bf);

        $drf = new DiscordRoleFilter(DiscordRoleFilter::DEFAULT_CAN_REMOVE_ROLES);
        $bf = $drf->check('anything');
        $this->assertTrue($bf);
        $bf = $drf->check('');  /* nothing */
        $this->assertFalse($bf);

        $drf = new DiscordRoleFilter(DiscordRoleFilter::DEFAULT_VISIBLE_ROLES);
        $bf = $drf->check('anything');
        $this->assertTrue($bf);
        $bf = $drf->check('');  /* nothing */
        $this->assertFalse($bf);

        $drf = new DiscordRoleFilter(DiscordRoleFilter::EVERYROLE_ENTRY);
        $bf = $drf->check('anything');
        $this->assertTrue($bf);
        $bf = $drf->check('');  /* nothing */
        $this->assertFalse($bf);
    }

    public function testCheck(): void
    {
        foreach ($this->TEST_EXPECTS as $item) {
            if($item->skip)
                continue;

            $roles = explode(':', $item->rolelist);
            $drf = new DiscordRoleFilter($item->rolespec);
            $res = $drf->check($roles);
            if ($res !== $item->expect)
                echo 'testCheck ' . var_export(array('result'=>$res,'item'=>$item),true) . PHP_EOL;
            $this->assertEquals($item->expect, $res);
        }

    }

    public function testCheckExtra(): void
    {
        $drf = new DiscordRoleFilter($this::ROLE_LIST_1);

        $bf = $drf->check('', 'FOO', $this::ROLE_1, 'BAR', 'FOOBAR');
        $this->assertTrue($bf);

        $bf = $drf->check('', 'FOO', 'BAR', 'FOOBAR');
        $this->assertFalse($bf);

        $bf = $drf->check($this::ROLE_1, '', 'FOO', 'BAR', 'FOOBAR');
        $this->assertTrue($bf);

        $bf = $drf->check('', 'FOO', 'BAR', 'FOOBAR');
        $this->assertFalse($bf);

        $bf = $drf->check('', 'FOO', 'BAR', 'FOOBAR', $this::ROLE_1);
        $this->assertTrue($bf);

        $bf = $drf->check(null);
        $this->assertFalse($bf);

        $bf = $drf->check();
        $this->assertFalse($bf);
    }

}

?>
