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


namespace Warlof\Seat\Connector\Drivers\Discord\Driver;

class DiscordRoleFilter
{
    /**
     *
     * @var array of string
     */
    private $filter_specs;

    /**
     *
     * @var bool
     */
    private $everyrole;

    /**
     *
     * @var string EVERYROLE_ENTRY
     */
    public const EVERYROLE_ENTRY = '@@everyrole';

    /**
     * Arbitrary string "RoleName Example2 RemoveMe" exist to self document a valid setting in database.
     *
     * @var string DEFAULT_CAN_REMOVE_ROLES
     */
    public const DEFAULT_CAN_REMOVE_ROLES = '@@everyrole:RoleName Example2 RemoveMe';

    /**
     * Arbitrary string "RoleName Example2 RemoveMe" exist to self document a valid setting in database.
     *
     * @var string DEFAULT_CAN_ADD_ROLES
     */
    public const DEFAULT_CAN_ADD_ROLES    = '@@everyrole:RoleName Example2 RemoveMe';

    /**
     * Arbitrary string "RoleName Example2 RemoveMe" exist to self document a valid setting in database.
     *
     * @var string DEFAULT_VISIBLE_ROLES
     */
    public const DEFAULT_VISIBLE_ROLES    = '@@everyrole:RoleName Example2 RemoveMe';

    /**
     * DiscordRoleFilter constructor.
     *
     * @param string $spec
     * @param string $explodeOn
     */
    public function __construct(string $spec = '', string $explodeOn = ':')
    {
        $this->filter_specs = explode($explodeOn, $spec);
        while (($key = array_search('', $this->filter_specs)) !== FALSE) {
            unset($this->filter_specs[$key]);   /* remove empty string items */
        }
        $this->everyrole = in_array(self::EVERYROLE_ENTRY, $this->filter_specs);
    }

    /**
     * Check a single role against the filter spec.
     *
     * @param string|null $s
     * @return mixed
     */
    public function checkOne(string $s = null, $defaultValue = false)
    {
        if (empty($s)) {
            return $defaultValue;
        }

        if ($this->everyrole === true) {
            return true;
        }

        /* theoretically it should be possible to modify this matching
         *  element to return true, false or $defaultValue (with an implied
         *  non-bool type) to implement ordered precedence rules and
         *  question mark prefixed inversion of a spec written like:
         * "Role.*:!RoleABC:RoleXYZ"
         */
        return in_array($s, $this->filter_specs) ? true : $defaultValue;
    }

    /**
     * Check all the roles in an array of role against the filter spec.
     *
     * @param array $ary
     * @param mixed $defaultValue  Expected to be bool or null.
     * @return mixed or type of $defaultValue
     */
    public function checkAll(array $ary = null, $defaultValue = false)
    {
        if (is_null($ary) || empty($ary)) {
            return $defaultValue;
        }

        if ($this->everyrole === true) {	// superflous?
            return true;
        }

        foreach ($ary as $item) {
            $res = $this->checkOne($item, null);
            if (! is_null($res)) {
                return $res;
            }
        }

        return $defaultValue;
    }

    /**
     * Check the arguments given as role against the filter spec.
     * Returns true|false accordingly.
     * @param $args
     * @return bool
     */
    public function check(...$args) : bool
    {
        // $args = func_get_args();
        foreach ($args as $val) {
            if(is_array($val))
                $res = $this->checkAll($val, null);
            else
                $res = $this->checkOne($val, null);
            if (! is_null($res)) {
                return $res;
            }
        }
        return false;
    }

}

?>
