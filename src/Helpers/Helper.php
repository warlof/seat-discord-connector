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

namespace Warlof\Seat\Connector\Discord\Helpers;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Seat\Eveapi\Models\Character\CharacterInfo;
use Seat\Eveapi\Models\RefreshToken;
use Seat\Web\Models\Group;
use Warlof\Seat\Connector\Discord\Models\DiscordRolePublic;
use Warlof\Seat\Connector\Discord\Models\DiscordUser;

/**
 * Class Helper
 * @package Warlof\Seat\Connector\Discord\Helpers
 */
class Helper
{
    /**
     * The Discord Public permission
     *
     * @var int
     */
    const EVERYONE = 0x00000000;

    /**
     * The Discord Administrator permission
     *
     * @var int
     */
    const ADMINISTRATOR = 0x00000008;

    /**
     * The Discord nickname length limit.
     *
     * @var int
     */
    const NICKNAME_LENGTH_LIMIT = 32;

    /**
     * Return true if account is active
     *
     * An account is considered as active when both mail has been confirmed in case of mail activation,
     * and no administrator disabled it
     *
     * @param Group $group
     * @return bool
     */
    public static function isEnabledAccount(Group $group) : bool
    {
        return ($group->users->count() == $group->users->where('active', true)->count());
    }

    /**
     * Return true if all API Key are still enable
     *
     * @param Collection $characterIDs
     * @return bool
     */
    public static function isEnabledKey(Collection $users) : bool
    {
        // retrieve all token which are matching with the user IDs list
        $tokens = RefreshToken::whereIn('character_id', $users->pluck('id')->toArray());

        // compare both list
        // if tokens amount is matching with characters list - return true
        return ($users->count() == $tokens->count());
    }

    /**
     * Determine all channels into which an user is allowed to be
     *
     * @param DiscordUser $discord_user
     * @return array
     */
    public static function allowedRoles(DiscordUser $discord_user) : array
    {
        $channels = [];

        if (! Helper::isEnabledAccount($discord_user->group))
            return $channels;

        if (! Helper::isEnabledKey($discord_user->group->users))
            return $channels;

        $rows = Group::join('warlof_discord_connector_role_groups', 'warlof_discord_connector_role_groups.group_id', '=', 'groups.id')
                    ->select('discord_role_id')
                    ->where('groups.id', $discord_user->group_id)
                    ->union(
                        // fix model declaration calling the table directly
                        DB::table('group_role')->join('warlof_discord_connector_role_roles', 'warlof_discord_connector_role_roles.role_id', '=',
                                        'group_role.role_id')
                                 ->where('group_role.group_id', $discord_user->group_id)
                                 ->select('discord_role_id')
                    )->union(
                        CharacterInfo::join('warlof_discord_connector_role_corporations', 'warlof_discord_connector_role_corporations.corporation_id', '=',
                                            'character_infos.corporation_id')
                                     ->whereIn('character_infos.character_id', $discord_user->group->users->pluck('id')->toArray())
                                     ->select('discord_role_id')
                    )->union(
                        CharacterInfo::join('character_titles', 'character_infos.character_id', '=', 'character_titles.character_id')
                                     ->join('warlof_discord_connector_role_titles', function ($join) {
                                         $join->on('warlof_discord_connector_role_titles.corporation_id', '=',
                                             'character_infos.corporation_id');
                                         $join->on('warlof_discord_connector_role_titles.title_id', '=',
                                             'character_titles.title_id');
                                     })
                                     ->whereIn('character_infos.character_id', $discord_user->group->users->pluck('id')->toArray())
                                     ->select('discord_role_id')
                    )->union(
                        CharacterInfo::join('warlof_discord_connector_role_alliances', 'warlof_discord_connector_role_alliances.alliance_id', '=',
                                            'character_infos.alliance_id')
                                     ->whereIn('character_infos.character_id', $discord_user->group->users->pluck('id')->toArray())
                                     ->select('discord_role_id')
                    )->union(
                        DiscordRolePublic::select('discord_role_id')
                    )->get();

        $channels = $rows->unique('discord_role_id')->pluck('discord_role_id')->toArray();

        return $channels;
    }

    /**
     * @param string $role_id
     * @param DiscordUser $discord_user
     * @return bool
     */
    public static function isAllowedRole(int $role_id, DiscordUser $discord_user)
    {
        return in_array($role_id, self::allowedRoles($discord_user));
    }

    /**
     * Determine the value based on a list of masks by applying bitwise OR
     *
     * @param array $masks
     * @return int
     */
    public static function arrayBitwiseOr(array $masks): int
    {
        $value = 0;

        foreach ($masks as $mask)
            $value |= $mask;

        return $value;
    }
}
