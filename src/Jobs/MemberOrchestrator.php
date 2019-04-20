<?php
/**
 * This file is part of discord-connector and provides user synchronization between both SeAT and a Discord Guild
 *
 * Copyright (C) 2016, 2017, 2018  Loïc Leuilliot <loic.leuilliot@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Warlof\Seat\Connector\Discord\Jobs;

use Illuminate\Support\Facades\Redis;
use RestCord\Model\Guild\GuildMember;
use Warlof\Seat\Connector\Discord\Exceptions\DiscordSettingException;
use Warlof\Seat\Connector\Discord\Helpers\Helper;
use Warlof\Seat\Connector\Discord\Models\DiscordUser;
use Seat\Eveapi\Models\Corporation\CorporationInfo;

/**
 * Class MemberOrchestrator
 * @package Warlof\Seat\Connector\Discord\Jobs
 */
class MemberOrchestrator extends DiscordJobBase
{
    /**
     * @var array
     */
    protected $tags = ['orchestrator'];

    /**
     * @var GuildMember
     */
    private $member;

    /**
     * @var bool
     */
    private $terminator;

    /**
     * @var int
     */
    public $tries = 100;

    /**
     * ConversationOrchestrator constructor.
     * @param string $member
     * @param bool $terminator Determine if the orchestrator must run a massive kick
     */
    public function __construct(GuildMember $member, bool $terminator = false)
    {
        logger()->debug('Initialising member orchestrator for ' . $member->nick);

        $this->terminator = $terminator;
        $this->member = $member;

        array_push($this->tags, 'member_id:' . $member->user->id);

        // if the terminator flag has been passed, append terminator into tags
        if ($this->terminator)
            array_push($this->tags, 'terminator');
    }

    /**
     * @throws DiscordSettingException
     * @throws \Seat\Services\Exceptions\SettingException
     */
    public function handle()
    {
        if (is_null(setting('warlof.discord-connector.credentials.bot_token', true)))
            throw new DiscordSettingException();

        if (is_null(setting('warlof.discord-connector.credentials.guild_id', true)))
            throw new DiscordSettingException();

        Redis::throttle('seat-discord-connector:jobs.member_orchestrator')->allow(20)->every(10)->then(function() {

            // in case terminator flag has not been specified, proceed using user defined mapping
            if (! $this->terminator) {
                $this->processMappingBase();
                return;
            }

            $this->updateMemberRoles([]);

        }, function() {

            logger()->warning('A MemberOrchestrator job is already running. Delay job by 10 seconds.');

            $this->release(10);

        });
    }

    /**
     * Set terminator flag to true.
     */
    public function setTerminatorFlag()
    {
        $this->terminator = true;

        if (! in_array('terminator', $this->tags))
            array_push($this->tags, 'terminator');
    }

    /**
     * Prepare roles mapping and update user if required.
     *
     * @throws \Seat\Services\Exceptions\SettingException
     */
    private function processMappingBase()
    {
        $nickname = null;
        $pending_drops = collect();
        $pending_adds = collect();
        $nickname_diff = false;

        $discord_user = DiscordUser::where('discord_id', $this->member->user->id)->first();

        if (! is_null($discord_user)) {
            if (! is_null($discord_user->group->main_character)) {
                $corp_id = $discord_user->group->main_character->corporation()->corporation_id;
                $corp = CorporationInfo::where('corporation_id', $corp_id)->first();
                $ticker = $corp->ticker;
                $mainname = $discord_user->group->main_character->name;
                $nickname = "[".$ticker."] ".$mainname;
                if ($this->member->nick != $nickname)
                    $nickname_diff = true;
            }
            foreach ($this->member->roles as $role_id) {
                if (! Helper::isAllowedRole($role_id, $discord_user))
                    $pending_drops->push($role_id);
            }

            $roles = Helper::allowedRoles($discord_user);

            foreach ($roles as $role_id) {
                if (! in_array($role_id, $this->member->roles))
                    $pending_adds->push($role_id);
            }

            $update_role = $pending_adds->count() > 0 || $pending_drops->count() > 0;

            if ($update_role || $nickname_diff) {
                $this->updateMemberRoles($update_role ? $roles : null, $nickname_diff ? $nickname : null);
            }
        }
    }

    /**
     * Update Discord user with new role mapping and nickname if required
     *
     * @param array|null $roles
     * @param string|null $nickname
     * @throws \Seat\Services\Exceptions\SettingException
     */
    private function updateMemberRoles(array $roles = null, string $nickname = null)
    {
        $options = [
            'guild.id' => intval(setting('warlof.discord-connector.credentials.guild_id', true)),
            'user.id'  => $this->member->user->id,
        ];

        if (! is_null($roles)) 
            array_merge($options, [
                'roles' => $roles,
            ]);

        if (! is_null($nickname))
            array_merge($options, [
                'nick' => $nickname,
            ]);

        app('discord')->guild->modifyGuildMember($options);
    }
}
