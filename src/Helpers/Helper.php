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

namespace Warlof\Seat\Connector\Drivers\Discord\Helpers;

/**
 * Class Helper.
 *
 * @package Warlof\Seat\Connector\Drivers\Discord\Helpers
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
