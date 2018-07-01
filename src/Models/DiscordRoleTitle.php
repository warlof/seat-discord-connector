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
use Seat\Eveapi\Models\Corporation\CorporationInfo;
use Seat\Eveapi\Models\Corporation\CorporationTitle;
use Seat\Eveapi\Traits\HasCompositePrimaryKey;

/**
 * Class DiscordRoleTitle
 * @package Warlof\Seat\Connector\Discord\Models
 */
class DiscordRoleTitle extends Model
{

    use HasCompositePrimaryKey;

    /**
     * @var string
     */
    protected $table = 'warlof_discord_connector_role_titles';

    /**
     * @var array
     */
    protected $primaryKey = [
        'corporation_id', 'title_id', 'discord_role_id',
    ];

    /**
     * @var array
     */
    protected $fillable = [
        'corporation_id', 'title_id', 'discord_role_id', 'enabled',
    ];

    /**
     * @return string
     */
    public function getTitleNameAttribute()
    {
        $title = CorporationTitle::where('corporation_id', $this->corporation_id)
                                 ->where('title_id', $this->title_id)
                                 ->first();

        if (! is_null($title))
            return $title->name;

        return 'Unknown Title';
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function discord_role()
    {
        return $this->belongsTo(DiscordRole::class, 'discord_role_id', 'id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function corporation()
    {
        return $this->belongsTo(CorporationInfo::class, 'corporation_id', 'corporation_id');
    }
}
