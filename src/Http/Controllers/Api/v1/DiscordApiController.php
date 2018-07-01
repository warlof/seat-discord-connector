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

namespace Warlof\Seat\Connector\Discord\Http\Controllers\Api\v1;


use Seat\Api\Http\Controllers\Api\v2\ApiController;
use Warlof\Seat\Connector\Discord\Models\DiscordUser;

/**
 * Class DiscordApiController
 * @package Warlof\Seat\Connector\Discord\Http\Controllers\Api\v1
 */
class DiscordApiController extends ApiController
{
    /**
     * @SWG\Get(
     *     path="/discord-connector/mapping",
     *     tags={"Discord Connector"},
     *     summary="Get a list of users, group id, and discord nickname",
     *     description="Returns list of users along with their discord mapping",
     *     security={"ApiKeyAuth"},
     *     @SWG\Response(response=200, description="Successful operation",
     *          @SWG\Schema(
     *              type="array",
     *              @SWG\Items(ref="#/definitions/DiscordUser")
     *          ),
     *          examples={"application/json":
     *              {
     *                  {"group_id":2, "discord_id":353886200135942144, "nick":"Warlof Tutsimo"}
     *              }
     *          }
     *     ),
     *     @SWG\Response(response=400, description="Bad request"),
     *     @SWG\Response(response=401, description="Unauthorized"),
     *    )
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function getDiscordMappings()
    {
        $discord_users = DiscordUser::all();
        return response()->json($discord_users->map(
            function($item){
                return [
                    'group_id' => $item->group_id,
                    'discord_id' => $item->discord_id,
                    'nick' => $item->nick
                ];
            })
        );
    }
}
