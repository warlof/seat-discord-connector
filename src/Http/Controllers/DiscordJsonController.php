<?php
/**
 * This file is part of slackbot and provide user synchronization between both SeAT and a Slack Team
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
use Warlof\Seat\Connector\Discord\Http\Validation\AddRelation;
use Warlof\Seat\Connector\Discord\Http\Validation\DiscordUser;
use Warlof\Seat\Connector\Discord\Models\DiscordRole;
use Warlof\Seat\Connector\Discord\Models\DiscordRoleAlliance;
use Warlof\Seat\Connector\Discord\Models\DiscordRoleCorporation;
use Warlof\Seat\Connector\Discord\Models\DiscordRolePublic;
use Warlof\Seat\Connector\Discord\Models\DiscordRoleRole;
use Warlof\Seat\Connector\Discord\Models\DiscordRoleTitle;
use Warlof\Seat\Connector\Discord\Models\DiscordRoleGroup;

class DiscordJsonController extends Controller
{
    /**
     * @param DiscordUser $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getJsonUserRolesData(DiscordUser $request)
    {
        $discord_id = $request->input('discord_id');

        if (is_null(setting('warlof.discord-connector.credentials.bot_token', true)))
            return response()->json([]);

        $driver = new DiscordClient([
            'tokenType' => 'Bot',
            'token'     => setting('warlof.discord-connector.credentials.bot_token', true),
        ]);

        $guild_member = $driver->guild->getGuildMember([
            'guild.id' => intval(setting('warlof.discord-connector.credentials.guild_id', true)),
            'user.id' => intval($discord_id),
        ]);

        $roles = DiscordRole::whereIn('id', $guild_member->roles)->select('id', 'name')->get();

        return response()->json($roles);
    }

    public function getJsonTitle()
    {
        $corporationId = request()->input('corporation_id');

        if (!empty($corporationId)) {
            $titles = CorporationTitle::where('corporation_id', $corporationId)->select('title_id', 'name')
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

    public function getRemovePublic($discord_role_id)
    {
        $channelPublic = DiscordRolePublic::where('discord_role_id', $discord_role_id);

        if ($channelPublic != null) {
            $channelPublic->delete();
            return redirect()->back()
                ->with('success', 'The public Discord relation has been removed');
        }

        return redirect()->back()
            ->with('error', 'An error occurs while trying to remove the public Discord relation.');
    }

    public function getRemoveUser($group_id, $discord_role_id)
    {
        $channelUser = DiscordRoleGroup::where('group_id', $group_id)
            ->where('discord_role_id', $discord_role_id);

        if ($channelUser != null) {
            $channelUser->delete();
            return redirect()->back()
                ->with('success', 'The Discord relation for the user has been removed');
        }

        return redirect()->back()
            ->with('error', 'An error occurs while trying to remove the Discord relation for the user.');
    }

    public function getRemoveRole($roleId, $discord_role_id)
    {
        $channelRole = DiscordRoleRole::where('role_id', $roleId)
            ->where('discord_role_id', $discord_role_id);

        if ($channelRole != null) {
            $channelRole->delete();
            return redirect()->back()
                ->with('success', 'The Discord relation for the role has been removed');
        }

        return redirect()->back()
            ->with('error', 'An error occurs while trying to remove the Discord relation for the role.');
    }

    public function getRemoveCorporation($corporationId, $discord_role_id)
    {
        $channelCorporation = DiscordRoleCorporation::where('corporation_id', $corporationId)
            ->where('discord_role_id', $discord_role_id);

        if ($channelCorporation != null) {
            $channelCorporation->delete();
            return redirect()->back()
                ->with('success', 'The Discord relation for the corporation has been removed');
        }

        return redirect()->back()
            ->with('error', 'An error occurs while trying to remove the Discord relation for the corporation.');
    }

    public function getRemoveTitle($corporationId, $titleId, $discord_role_id)
    {
        $channelTitle = DiscordRoleTitle::where('corporation_id', $corporationId)
            ->where('title_id', $titleId)
            ->where('discord_role_id', $discord_role_id);

        if ($channelTitle != null) {
            $channelTitle->delete();
            return redirect()->back()
                ->with('success', 'The Discord relation for the title has been removed');
        }

        return redirect()->back()
            ->with('error', 'An error occurred while trying to remove the Discord relation for the title.');
    }

    public function getRemoveAlliance($allianceId, $discord_role_id)
    {
        $channelAlliance = DiscordRoleAlliance::where('alliance_id', $allianceId)
            ->where('discord_role_id', $discord_role_id);

        if ($channelAlliance != null) {
            $channelAlliance->delete();
            return redirect()->back()
                ->with('success', 'The Discord relation for the alliance has been removed');
        }

        return redirect()->back()
            ->with('error', 'An error occurs while trying to remove the Discord relation for the alliance.');
    }

    //
    // Grant access
    //

    public function postRelation(AddRelation $request)
    {
        $groupId = $request->input('discord-group-id');
        $roleId = $request->input('discord-role-id');
        $corporationId = $request->input('discord-corporation-id');
        $titleId = $request->input('discord-title-id');
        $allianceId = $request->input('discord-alliance-id');
        $discord_role_id = $request->input('discord-discord-role-id');

        // use a single post route in order to create any kind of relation
        // value are user, role, corporation or alliance
        switch ($request->input('discord-type')) {
            case 'public':
                return $this->postPublicRelation($discord_role_id);
            case 'group':
                return $this->postGroupRelation($discord_role_id, $groupId);
            case 'role':
                return $this->postRoleRelation($discord_role_id, $roleId);
            case 'corporation':
                return $this->postCorporationRelation($discord_role_id, $corporationId);
            case 'title':
                return $this->postTitleRelation($discord_role_id, $corporationId, $titleId);
            case 'alliance':
                return $this->postAllianceRelation($discord_role_id, $allianceId);
            default:
                return redirect()->back()
                    ->with('error', 'Unknown relation type');
        }
    }

    //
    // Helper methods
    //

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

    private function postGroupRelation($discord_role_id, $groupId)
    {
        $relation = DiscordRoleGroup::where('discord_role_id', '=', $discord_role_id)
            ->where('group_id', '=', $groupId)
            ->get();

        if ($relation->count() == 0) {
            DiscordRoleGroup::create([
                'group_id' => $groupId,
                'discord_role_id' => $discord_role_id,
                'enabled' => true
            ]);

            return redirect()->back()
                ->with('success', 'New Discord user relation has been created');
        }

        return redirect()->back()
            ->with('error', 'This relation already exists');
    }

    private function postRoleRelation($discord_role_id, $roleId)
    {
        $relation = DiscordRoleRole::where('role_id', '=', $roleId)
            ->where('discord_role_id', '=', $discord_role_id)
            ->get();

        if ($relation->count() == 0) {
            DiscordRoleRole::create([
                'role_id' => $roleId,
                'discord_role_id' => $discord_role_id,
                'enabled' => true
            ]);

            return redirect()->back()
                ->with('success', 'New Discord role relation has been created');
        }

        return redirect()->back()
            ->with('error', 'This relation already exists');
    }

    private function postCorporationRelation($discord_role_id, $corporationId)
    {
        $relation = DiscordRoleCorporation::where('corporation_id', '=', $corporationId)
            ->where('discord_role_id', '=', $discord_role_id)
            ->get();

        if ($relation->count() == 0) {
            DiscordRoleCorporation::create([
                'corporation_id' => $corporationId,
                'discord_role_id' => $discord_role_id,
                'enabled' => true
            ]);

            return redirect()->back()
                ->with('success', 'New Discord corporation relation has been created');
        }

        return redirect()->back()
            ->with('error', 'This relation already exists');
    }

    private function postTitleRelation($discord_role_id, $corporationId, $titleId)
    {
        $relation = DiscordRoleTitle::where('corporation_id', '=', $corporationId)
            ->where('title_id', '=', $titleId)
            ->where('discord_role_id', '=', $discord_role_id)
            ->get();

        if ($relation->count() == 0) {
            DiscordRoleTitle::create([
                'corporation_id' => $corporationId,
                'title_id' => $titleId,
                'discord_role_id' => $discord_role_id,
                'enabled' => true
            ]);

            return redirect()->back()
                ->with('success', 'New Discord title relation has been created');
        }

        return redirect()->back()
            ->with('error', 'This relation already exists');
    }

    private function postAllianceRelation($discord_role_id, $allianceId)
    {
        $relation = DiscordRoleAlliance::where('alliance_id', '=', $allianceId)
            ->where('discord_role_id', '=', $discord_role_id)
            ->get();

        if ($relation->count() == 0) {
            DiscordRoleAlliance::create([
                'alliance_id' => $allianceId,
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
