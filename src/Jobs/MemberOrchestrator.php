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

use Exception;
use Illuminate\Support\Str;
use RestCord\Interfaces\Guild as IGuild;
use RestCord\Model\Guild\Guild;
use RestCord\Model\Permissions\Role;
use RestCord\Model\Guild\GuildMember;
use Warlof\Seat\Connector\Discord\Exceptions\DiscordSettingException;
use Warlof\Seat\Connector\Discord\Helpers\Helper;
use Warlof\Seat\Connector\Discord\Models\DiscordLog;
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
     * @var IGuild
     */
    private $client;

    /**
     * @var Guild
     */
    private $guild;

    /**
     * @var GuildMember[]
     */
    private $members;

    /**
     * @var Role[]
     */
    private $roles;

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
    public function __construct(bool $terminator = false)
    {
        $this->terminator = $terminator;

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

        // retrieve Discord Client
        $this->client = app('discord')->guild;

        // get Discord Guild metadata
        $this->guild = $this->client->getGuild([
            'guild.id' => intval(setting('warlof.discord-connector.credentials.guild_id', true)),
        ]);

        // get Discord Guild Roles
        $this->roles = collect($this->client->getGuildRoles([
            'guild.id' => intval(setting('warlof.discord-connector.credentials.guild_id', true)),
        ]));

        // get Discord Guild Members
        $this->members = [];
        $after = null;
        $options = [
            'guild.id' => intval(setting('warlof.discord-connector.credentials.guild_id', true)),
            'limit' => 1000,
        ];
        do {
            if ($after) {
                $options['after'] = $after;
            }
            $members = $this->client->listGuildMembers($options);
            if (empty($members)) {
                break;
            }
            $this->members = array_merge($this->members, $members);
            $after = end($members)->user->id;
        } while (true);

        // loop over each Guild Member and apply policy
        foreach ($this->members as $member) {

            // ignore any bot user
            if ($member->user->bot)
                continue;

            // ignore any Guild owner
            if ($this->isOwner($member))
                continue;

            // ignore any Guild Administrator
            if ($this->isAdministrator($member))
                continue;

            // attempt to retrieve the SeAT bind user
            if (is_null($discord_user = $this->findSeATUserByDiscordGuildMember($member))) {

                // in case we're not in terminator mode - ignore the user and assume the unmap is legit
                if (! $this->terminator)
                    continue;

                // otherwise - remove all roles from the user
                try {
                    $this->updateMemberRoles($member, []);
                } catch (Exception $e) {
                    report($e);
                    logger()->error($e->getMessage(), $e->getTrace());
                    DiscordLog::create([
                        'event' => 'sync-error',
                        'message' => sprintf('Failed to sync user %s(%s). Please check worker and laravel log for more information.',
                            $member->nick, $member->user->id),
                    ]);
                }

                // ignore the remaining process as we've already revoke the user - which is unknown by SeAT
                continue;
            }

            // apply policy to current member
            try {
                $this->processMappingBase($member, $discord_user);
            } catch (Exception $e) {
                report($e);
                logger()->error($e->getMessage(), $e->getTrace());
                DiscordLog::create([
                    'event' => 'sync-error',
                    'message' => sprintf('Failed to sync user %s(%s). Please check worker and laravel log for more information.',
                        $discord_user->nick, $discord_user->discord_id),
                ]);
            }
        }
    }

    /**
     * Prepare roles mapping and update user if required.
     *
     * @param GuildMember $member
     * @param DiscordUser $discord_user
     * @throws \Seat\Services\Exceptions\SettingException
     */
    private function processMappingBase(GuildMember $member, DiscordUser $discord_user)
    {
        $roles         = null;
        $new_nickname  = null;
        $pending_drops = collect();
        $pending_adds  = collect();

        // determine if the current Discord Member nickname is valid or flag it for change
        $expected_nickname = $this->buildDiscordUserNickname($discord_user);
        if ($member->nick !== $expected_nickname && $member->user->username !== $expected_nickname)
            $new_nickname = $expected_nickname;

        // loop over roles owned by the user and prepare to drop them
        foreach ($member->roles as $role_id) {
            if (! $discord_user->isAllowedRole($role_id) || $this->terminator)
                $pending_drops->push($role_id);
        }

        // in case we are not in terminator mode, search for missing assignable roles
        if (! $this->terminator) {

            // collect all currently valid roles
            $roles = $discord_user->allowedRoles();

            // loop over granted roles and prepare to add them
            foreach ($roles as $role_id) {
                if (!in_array($role_id, $member->roles))
                    $pending_adds->push($role_id);
            }

        }

        // determine if the user is requiring a role update
        $is_roles_outdated = $pending_adds->count() > 0 || $pending_drops->count() > 0;

        // apply changes to the guild member
        if ($is_roles_outdated || ! is_null($new_nickname)) {
            $this->updateMemberRoles($member, $is_roles_outdated ? $roles : null, $new_nickname);
            $discord_user->nick = $new_nickname ? $new_nickname : $discord_user->nick;
            $discord_user->save();
            DiscordLog::create([
                'event' => 'sync',
                'message' => sprintf('Successfully sync user %s(%s).',
                    $discord_user->nick, $discord_user->discord_id),
            ]);
        }
    }

    /**
     * Update Discord user with new role mapping and nickname if required
     *
     * @param GuildMember $member
     * @param array|null $roles
     * @param string|null $nickname
     * @throws \Seat\Services\Exceptions\SettingException
     */
    private function updateMemberRoles(GuildMember $member, array $roles = null, string $nickname = null)
    {
        $options = [
            'guild.id' => intval(setting('warlof.discord-connector.credentials.guild_id', true)),
            'user.id'  => $member->user->id,
        ];

        if (! is_null($roles))
            $options['roles'] = $roles;

        if (! is_null($nickname))
            $options['nick'] = $nickname;

        $this->client->modifyGuildMember($options);

        // apply a throttler so we avoid to flood Discord Api
        sleep(5);
    }

    /**
     * Determine if the Guild Member is the Guild Owner
     *
     * @param GuildMember $member
     * @return bool
     */
    private function isOwner(GuildMember $member) : bool
    {
        return $member->user->id === $this->guild->owner_id;
    }

    /**
     * Determine if the Guild Member is an Administrator
     *
     * @param GuildMember $member
     * @return bool
     */
    private function isAdministrator(GuildMember $member) : bool
    {
        $discord_permissions = Helper::EVERYONE;

        foreach ($member->roles as $role_id)
            $discord_permissions |= $this->roles->where('id', $role_id)->first()->permissions;

        return ($discord_permissions & Helper::ADMINISTRATOR) === Helper::ADMINISTRATOR;
    }

    /**
     * Retrieve the SeAT Binding based on a Discord Guild Member
     *
     * @param GuildMember $member
     * @return DiscordUser|null
     */
    private function findSeATUserByDiscordGuildMember(GuildMember $member) : ?DiscordUser
    {
        return DiscordUser::where('discord_id', $member->user->id)->first();
    }

    /**
     * Return a string which will be used as a Discord Guild Member Nickname
     *
     * @param DiscordUser $discord_user
     * @return string
     * @throws \Seat\Services\Exceptions\SettingException
     */
    private function buildDiscordUserNickname(DiscordUser $discord_user): string
    {
        // retrieve a character related to the Discord relationship
        $character = $discord_user->group->main_character;
        if (is_null($character))
            $character = $discord_user->group->users->first()->character;

        // init the discord nickname to the character name
        $expected_nickname = $discord_user->group->main_character->name;

        // in case ticker prefix is enabled, retrieve the corporation and prepend the ticker to the nickname
        if (setting('warlof.discord-connector.ticker', true)) {
            $corporation = CorporationInfo::find($character->corporation_id);
            $nickfmt = setting('warlof.discord-connector.nickfmt', true) ?: '[%s] %s';

            if (! is_null($corporation))
                $expected_nickname = sprintf($nickfmt, $corporation->ticker, $expected_nickname);
        }

        return Str::limit($expected_nickname, Helper::NICKNAME_LENGTH_LIMIT, '');
    }
}
