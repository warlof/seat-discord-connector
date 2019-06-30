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

namespace Warlof\Seat\Connector\Discord\Models;

use Illuminate\Database\Eloquent\Model;
use Seat\Eveapi\Models\Character\CharacterInfo;
use Seat\Web\Models\Group;

/**
 * Class DiscordUser
 * @package Warlof\Seat\Connector\Discord\Models
 *
 * @SWG\Definition(
 *     description="SeAT to Discord User mapping model",
 *     title="Discord User model",
 *     type="object"
 * )
 *
 * @SWG\Property(
 *     format="int",
 *     description="ID",
 *     property="group_id",
 * )
 *
 * @SWG\Property(
 *     format="int64",
 *     description="Discord Unique ID",
 *     property="discord_id",
 * )
 *
 * @SWG\Property(
 *     format="string",
 *     description="Discord user nickname",
 *     property="nick",
 * )
 */
class DiscordUser extends Model
{
    /**
     * @var string
     */
    protected $table = 'warlof_discord_connector_users';

    /**
     * @var string
     */
    protected $primaryKey = 'group_id';

    /**
     * @var bool
     */
    public $incrementing = false;

    /**
     * @var array
     */
    protected $fillable = [
        'group_id', 'discord_id', 'nick', 'scope', 'refresh_token', 'access_token', 'expires_at',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function group()
    {
        return $this->belongsTo(Group::class, 'group_id', 'id');
    }

    /**
     * Return true if account is active.
     *
     * @return bool
     */
    public function isEnabledAccount() : bool
    {
        return ($this->group->users->count() == $this->group->users->where('active', true)->count());
    }

    /**
     * Determine if all registered characters got a valid token.
     *
     * @return bool
     */
    public function areAllTokensValid(): bool
    {
        return $this->group->refresh_tokens->count() == $this->group->users->count();
    }

    /**
     * Return true if an user is allowed to have the specified role.
     *
     * @param int $role_id
     * @return bool
     */
    public function isAllowedRole(int $role_id)
    {
        return in_array($role_id, $this->allowedRoles());
    }

    /**
     * Return an array of roles ID to which the current user is granted.
     *
     * @return array
     */
    public function allowedRoles(): array
    {
        $strict_mode = setting('warlof.discord-connector.strict', true);

        $active_tokens = $this->group->refresh_tokens;

        if (empty($active_tokens))
            return [];

        if ($strict_mode && ! $this->areAllTokensValid())
            return [];

        if (! $this->isEnabledAccount())
            return [];

        $rows = $this->getDiscordRoleGroupBased(false)
            ->union($this->getDiscordRoleRoleBased(false))
            ->union($this->getDiscordRoleCorporationBased(false))
            ->union($this->getDiscordRoleCorporationTitleBased(false))
            ->union($this->getDiscordRoleAllianceBased(false))
            ->union($this->getDiscordRolePublicBased(false))
            ->get();

        return $rows->unique('discord_role_id')->pluck('discord_role_id')->toArray();
    }

    /**
     * Return all roles ID related to user mapping matching to the user.
     *
     * @param bool $get
     * @return mixed
     */
    public function getDiscordRoleGroupBased(bool $get)
    {
        $roles = DiscordRoleGroup::join('groups', 'warlof_discord_connector_role_groups.group_id', '=', 'groups.id')
            ->where('groups.id', $this->group_id)
            ->select('discord_role_id');

        return $get ? $roles->get() : $roles;
    }

    /**
     * Return all roles ID related to role mapping matching to the user.
     *
     * @param bool $get
     * @return mixed
     */
    public function getDiscordRoleRoleBased(bool $get)
    {
        $roles = DiscordRoleRole::join('group_role', 'warlof_discord_connector_role_roles.role_id', '=', 'group_role.role_id')
            ->where('group_role.group_id', $this->group_id)
            ->select('discord_role_id');

        return $get ? $roles->get() : $roles;
    }

    /**
     * Return all roles ID related to corporation mapping matching to the user.
     *
     * @param bool $get
     * @return mixed
     */
    public function getDiscordRoleCorporationBased(bool $get)
    {
        $roles = DiscordRoleCorporation::join('character_infos', 'warlof_discord_connector_role_corporations.corporation_id', 'character_infos.corporation_id')
            ->whereIn('character_infos.character_id', $this->group->users->pluck('id')->toArray())
            ->select('discord_role_id');

        return $get ? $roles->get() : $roles;
    }

    /**
     * Return all roles ID related to corporation title mapping matching to the user.
     *
     * @param bool $get
     * @return mixed
     */
    public function getDiscordRoleCorporationTitleBased(bool $get)
    {
        $roles = CharacterInfo::join('character_titles', 'character_infos.character_id', '=', 'character_titles.character_id')
            ->join('warlof_discord_connector_role_titles', function ($join) {
                $join->on('warlof_discord_connector_role_titles.title_id', '=', 'character_titles.title_id')
                     ->on('warlof_discord_connector_role_titles.corporation_id', '=', 'character_infos.corporation_id');
            })
            ->whereIn('character_infos.character_id', $this->group->users->pluck('id')->toArray())
            ->select('discord_role_id');

        return $get ? $roles->get() : $roles;
    }

    /**
     * Return all roles ID related to alliance mapping matching to the user.
     *
     * @param bool $get
     * @return mixed
     */
    public function getDiscordRoleAllianceBased(bool $get)
    {
        $roles = DiscordRoleAlliance::join('character_infos', 'warlof_discord_connector_role_alliances.alliance_id', '=', 'character_infos.alliance_id')
            ->whereIn('character_infos.character_id', $this->group->users->pluck('id')->toArray())
            ->select('discord_role_id');

        return $get ? $roles->get() : $roles;
    }

    /**
     * Return all roles ID related to public mapping.
     *
     * @param bool $get
     * @return mixed
     */
    public function getDiscordRolePublicBased(bool $get)
    {
        $roles = DiscordRolePublic::select('discord_role_id');

        return $get ? $roles->get() : $roles;
    }
}
