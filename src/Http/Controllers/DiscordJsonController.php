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

namespace Warlof\Seat\Connector\Discord\Http\Controllers;

use RestCord\DiscordClient;
use Seat\Eveapi\Models\Alliances\Alliance;
use Seat\Eveapi\Models\Corporation\CorporationInfo;
use Seat\Eveapi\Models\Corporation\CorporationTitle;
use Seat\Web\Http\Controllers\Controller;
use Seat\Web\Models\Acl\Role;
use Seat\Web\Models\Group;
use Warlof\Seat\Connector\Discord\Caches\RedisRateLimitProvider;
use Warlof\Seat\Connector\Discord\Http\Validation\AddRelation;
use Warlof\Seat\Connector\Discord\Http\Validation\DiscordUserShowModal;
use Warlof\Seat\Connector\Discord\Models\DiscordRole;
use Warlof\Seat\Connector\Discord\Models\DiscordRoleAlliance;
use Warlof\Seat\Connector\Discord\Models\DiscordRoleCorporation;
use Warlof\Seat\Connector\Discord\Models\DiscordRolePublic;
use Warlof\Seat\Connector\Discord\Models\DiscordRoleRole;
use Warlof\Seat\Connector\Discord\Models\DiscordRoleTitle;
use Warlof\Seat\Connector\Discord\Models\DiscordRoleGroup;

/**
 * Class DiscordJsonController
 * @package Warlof\Seat\Connector\Discord\Http\Controllers
 */
class DiscordJsonController extends Controller
{
    /**
     * @param DiscordUserShowModal $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getJsonUserRolesData(DiscordUserShowModal $request)
    {
        $discord_id = $request->input('discord_id');

        if (is_null(setting('warlof.discord-connector.credentials.bot_token', true)))
            return response()->json([]);

        if (is_null(setting('warlof.discord-connector.credentials.guild_id', true)))
            return response()->json([]);

        $driver = new DiscordClient([
            'tokenType' => 'Bot',
            'token'     => setting('warlof.discord-connector.credentials.bot_token', true),
            'rateLimitProvider' => new RedisRateLimitProvider(),
        ]);

        $guild_member = $driver->guild->getGuildMember([
            'guild.id' => intval(setting('warlof.discord-connector.credentials.guild_id', true)),
            'user.id' => intval($discord_id),
        ]);

        $roles = DiscordRole::whereIn('id', $guild_member->roles)->select('id', 'name')->get();

        return response()->json($roles);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function getJsonTitle()
    {
        $corporation_id = request()->input('corporation_id');

        if (!empty($corporation_id)) {
            $titles = CorporationTitle::where('corporation_id', $corporation_id)->select('title_id', 'name')
                ->get();

            return response()->json($titles->map(
                function($item){
                    return [
                        'title_id' => $item->title_id,
                        'name' => strip_tags($item->name)
                    ];
                })
            );
        }

        return response()->json([]);
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function getRelations()
    {
        $discord_role_filters = DiscordRolePublic::all();
        $group_filters = DiscordRoleGroup::all();
        $role_filters = DiscordRoleRole::all();
        $corporation_filters = DiscordRoleCorporation::all();
        $title_filters = DiscordRoleTitle::all();
        $alliance_filters = DiscordRoleAlliance::all();

        $groups = Group::all();
        $roles = Role::orderBy('title')->get();
        $corporations = CorporationInfo::orderBy('name')->get();
        $alliances = Alliance::orderBy('name')->get();
        $discord_roles = DiscordRole::orderBy('name')->get();

        return view('discord-connector::access.list',
            compact('discord_role_filters', 'group_filters', 'role_filters', 'corporation_filters',
                'title_filters', 'alliance_filters', 'groups', 'roles', 'corporations', 'alliances', 'discord_roles'));
    }

    //
    // Remove access
    //

    /**
     * @param $discord_role_id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function removePublic($discord_role_id)
    {
        $channel_public = DiscordRolePublic::where('discord_role_id', $discord_role_id);

        if ($channel_public != null) {
            $channel_public->delete();
            return redirect()->back()
                ->with('success', 'The public Discord relation has been removed');
        }

        return redirect()->back()
            ->with('error', 'An error occurs while trying to remove the public Discord relation.');
    }

    /**
     * @param $group_id
     * @param $discord_role_id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function removeUser($group_id, $discord_role_id)
    {
        $channel_user = DiscordRoleGroup::where('group_id', $group_id)
            ->where('discord_role_id', $discord_role_id);

        if ($channel_user != null) {
            $channel_user->delete();
            return redirect()->back()
                ->with('success', 'The Discord relation for the user has been removed');
        }

        return redirect()->back()
            ->with('error', 'An error occurs while trying to remove the Discord relation for the user.');
    }

    /**
     * @param $role_id
     * @param $discord_role_id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function removeRole($role_id, $discord_role_id)
    {
        $channel_role = DiscordRoleRole::where('role_id', $role_id)
            ->where('discord_role_id', $discord_role_id);

        if ($channel_role != null) {
            $channel_role->delete();
            return redirect()->back()
                ->with('success', 'The Discord relation for the role has been removed');
        }

        return redirect()->back()
            ->with('error', 'An error occurs while trying to remove the Discord relation for the role.');
    }

    /**
     * @param $corporation_id
     * @param $discord_role_id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function removeCorporation($corporation_id, $discord_role_id)
    {
        $channel_corporation = DiscordRoleCorporation::where('corporation_id', $corporation_id)
            ->where('discord_role_id', $discord_role_id);

        if ($channel_corporation != null) {
            $channel_corporation->delete();
            return redirect()->back()
                ->with('success', 'The Discord relation for the corporation has been removed');
        }

        return redirect()->back()
            ->with('error', 'An error occurs while trying to remove the Discord relation for the corporation.');
    }

    /**
     * @param $corporation_id
     * @param $title_id
     * @param $discord_role_id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function removeTitle($corporation_id, $title_id, $discord_role_id)
    {
        $channel_title = DiscordRoleTitle::where('corporation_id', $corporation_id)
            ->where('title_id', $title_id)
            ->where('discord_role_id', $discord_role_id);

        if ($channel_title != null) {
            $channel_title->delete();
            return redirect()->back()
                ->with('success', 'The Discord relation for the title has been removed');
        }

        return redirect()->back()
            ->with('error', 'An error occurred while trying to remove the Discord relation for the title.');
    }

    /**
     * @param $alliance_id
     * @param $discord_role_id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function removeAlliance($alliance_id, $discord_role_id)
    {
        $channel_alliance = DiscordRoleAlliance::where('alliance_id', $alliance_id)
            ->where('discord_role_id', $discord_role_id);

        if ($channel_alliance != null) {
            $channel_alliance->delete();
            return redirect()->back()
                ->with('success', 'The Discord relation for the alliance has been removed');
        }

        return redirect()->back()
            ->with('error', 'An error occurs while trying to remove the Discord relation for the alliance.');
    }

    //
    // Grant access
    //

    /**
     * @param AddRelation $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function postRelation(AddRelation $request)
    {
        $group_id = $request->input('discord-group-id');
        $role_id = $request->input('discord-role-id');
        $corporation_id = $request->input('discord-corporation-id');
        $title_id = $request->input('discord-title-id');
        $alliance_id = $request->input('discord-alliance-id');
        $discord_role_id = $request->input('discord-discord-role-id');

        // use a single post route in order to create any kind of relation
        // value are user, role, corporation or alliance
        switch ($request->input('discord-type')) {
            case 'public':
                return $this->postPublicRelation($discord_role_id);
            case 'group':
                return $this->postGroupRelation($discord_role_id, $group_id);
            case 'role':
                return $this->postRoleRelation($discord_role_id, $role_id);
            case 'corporation':
                return $this->postCorporationRelation($discord_role_id, $corporation_id);
            case 'title':
                return $this->postTitleRelation($discord_role_id, $corporation_id, $title_id);
            case 'alliance':
                return $this->postAllianceRelation($discord_role_id, $alliance_id);
            default:
                return redirect()->back()
                    ->with('error', 'Unknown relation type');
        }
    }

    //
    // Helper methods
    //

    /**
     * @param $discord_role_id
     * @return \Illuminate\Http\RedirectResponse
     */
    private function postPublicRelation($discord_role_id)
    {
        if (DiscordRolePublic::find($discord_role_id) == null) {
            DiscordRolePublic::create([
                'discord_role_id' => $discord_role_id,
                'enabled' => true
            ]);

            return redirect()->back()
                ->with('success', 'New public Discord relation has been created');
        }

        return redirect()->back()
            ->with('error', 'This relation already exists');
    }

    /**
     * @param $discord_role_id
     * @param $group_id
     * @return \Illuminate\Http\RedirectResponse
     */
    private function postGroupRelation($discord_role_id, $group_id)
    {
        $relation = DiscordRoleGroup::where('discord_role_id', '=', $discord_role_id)
            ->where('group_id', '=', $group_id)
            ->get();

        if ($relation->count() == 0) {
            DiscordRoleGroup::create([
                'group_id' => $group_id,
                'discord_role_id' => $discord_role_id,
                'enabled' => true
            ]);

            return redirect()->back()
                ->with('success', 'New Discord user relation has been created');
        }

        return redirect()->back()
            ->with('error', 'This relation already exists');
    }

    /**
     * @param $discord_role_id
     * @param $role_id
     * @return \Illuminate\Http\RedirectResponse
     */
    private function postRoleRelation($discord_role_id, $role_id)
    {
        $relation = DiscordRoleRole::where('role_id', '=', $role_id)
            ->where('discord_role_id', '=', $discord_role_id)
            ->get();

        if ($relation->count() == 0) {
            DiscordRoleRole::create([
                'role_id' => $role_id,
                'discord_role_id' => $discord_role_id,
                'enabled' => true
            ]);

            return redirect()->back()
                ->with('success', 'New Discord role relation has been created');
        }

        return redirect()->back()
            ->with('error', 'This relation already exists');
    }

    /**
     * @param $discord_role_id
     * @param $corporation_id
     * @return \Illuminate\Http\RedirectResponse
     */
    private function postCorporationRelation($discord_role_id, $corporation_id)
    {
        $relation = DiscordRoleCorporation::where('corporation_id', '=', $corporation_id)
            ->where('discord_role_id', '=', $discord_role_id)
            ->get();

        if ($relation->count() == 0) {
            DiscordRoleCorporation::create([
                'corporation_id' => $corporation_id,
                'discord_role_id' => $discord_role_id,
                'enabled' => true
            ]);

            return redirect()->back()
                ->with('success', 'New Discord corporation relation has been created');
        }

        return redirect()->back()
            ->with('error', 'This relation already exists');
    }

    /**
     * @param $discord_role_id
     * @param $corporation_id
     * @param $title_id
     * @return \Illuminate\Http\RedirectResponse
     */
    private function postTitleRelation($discord_role_id, $corporation_id, $title_id)
    {
        $relation = DiscordRoleTitle::where('corporation_id', '=', $corporation_id)
            ->where('title_id', '=', $title_id)
            ->where('discord_role_id', '=', $discord_role_id)
            ->get();

        if ($relation->count() == 0) {
            DiscordRoleTitle::create([
                'corporation_id' => $corporation_id,
                'title_id' => $title_id,
                'discord_role_id' => $discord_role_id,
                'enabled' => true
            ]);

            return redirect()->back()
                ->with('success', 'New Discord title relation has been created');
        }

        return redirect()->back()
            ->with('error', 'This relation already exists');
    }

    /**
     * @param $discord_role_id
     * @param $alliance_id
     * @return \Illuminate\Http\RedirectResponse
     */
    private function postAllianceRelation($discord_role_id, $alliance_id)
    {
        $relation = DiscordRoleAlliance::where('alliance_id', '=', $alliance_id)
            ->where('discord_role_id', '=', $discord_role_id)
            ->get();

        if ($relation->count() == 0) {
            DiscordRoleAlliance::create([
                'alliance_id' => $alliance_id,
                'discord_role_id' => $discord_role_id,
                'enabled' => true
            ]);

            return redirect()->back()
                ->with('success', 'New Discord alliance relation has been created');
        }

        return redirect()->back()
            ->with('error', 'This relation already exists');
    }
}
