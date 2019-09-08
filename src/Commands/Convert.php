<?php
/**
 * This file is part of SeAT Discord Connector.
 *
 * Copyright (C) 2019  Warlof Tutsimo <loic.leuilliot@gmail.com>
 *
 * SeAT Discord Connector  is free software: you can redistribute it and/or modify
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

namespace Warlof\Seat\Connector\Drivers\Discord\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Seat\Eveapi\Models\Alliances\Alliance;
use Seat\Eveapi\Models\Corporation\CorporationInfo;
use Seat\Eveapi\Models\Corporation\CorporationTitle;
use Seat\Web\Models\Acl\Role;
use Seat\Web\Models\Group;
use Warlof\Seat\Connector\Models\Set;
use Warlof\Seat\Connector\Models\User;

/**
 * Class Convert.
 *
 * @package Warlof\Seat\Connector\Drivers\Discord\Commands
 */
class Convert extends Command
{
    /**
     * @var string
     */
    protected $signature = 'seat-connector:convert:discord';
    /**
     * @var string
     */
    protected $description = 'Process data conversion from Discord 3.x generation to 4.x';

    public function handle()
    {
        $this->line('This Wizard will guide you in the process of data conversion between Discord Connector version prior to 4.0 and SeAT Connector');
        $this->line(sprintf('You can run it at any time using "php artisan %s"', $this->signature));
        $this->line('At the end of data conversion process, existing tables will not be removed. In case you want to erase them, you have to do it manually.');
        $this->line(' - warlof_discord_connector_role_alliances');
        $this->line(' - warlof_discord_connector_role_corporations');
        $this->line(' - warlof_discord_connector_role_public');
        $this->line(' - warlof_discord_connector_role_roles');
        $this->line(' - warlof_discord_connector_role_titles');
        $this->line(' - warlof_discord_connector_role_groups');
        $this->line(' - warlof_discord_connector_roles');
        $this->line(' - warlof_discord_connector_users');
        $this->line(' - warlof_discord_connector_logs');
        $this->line('');

        if ($this->requireConversion()) {
            $this->info('It appears you were running a previous version of warlof/seat-discord-connector.');

            if (! $this->confirm('Do you want to convert existing data to the new SeAT Connector layout ?', true)) {
                $this->line(
                    sprintf('We did not convert anything. You can always run this wizard using "php artisan %s"', $this->signature));
            }

            $this->convert();

            $this->info('Process has been completed. Please review upper warning or errors to be sure everything is going well.');
        }
    }

    private function requireConversion(): bool
    {
        $deprecated_tables = [
            'warlof_discord_connector_users',
            'warlof_discord_connector_roles',
            'warlof_discord_connector_role_public',
            'warlof_discord_connector_role_groups',
            'warlof_discord_connector_role_alliances',
            'warlof_discord_connector_role_corporations',
            'warlof_discord_connector_role_titles',
            'warlof_discord_connector_role_roles',
        ];

        foreach ($deprecated_tables as $table)
            if (! Schema::hasTable($table))
                return false;

        return true;
    }

    private function convert()
    {
        $progress = $this->output->createProgressBar(9);

        $this->flushConnectorData();
        $progress->advance();

        $this->convertUsers();
        $progress->advance();

        $this->convertSets();
        $progress->advance();

        $this->convertPublicSets();
        $progress->advance();

        $this->convertUserSets();
        $progress->advance();

        $this->convertRoleSets();
        $progress->advance();

        $this->convertAllianceSets();
        $progress->advance();

        $this->convertCorporationSets();
        $progress->advance();

        $this->convertTitleSets();
        $progress->advance();

        $progress->finish();
    }

    private function flushConnectorData()
    {
        User::where('connector_type', 'discord')->delete();
        Set::where('connector_type', 'discord')->delete();

        $this->info('SeAT Connector has been purged from discord data.');
    }

    private function convertUsers()
    {
        $users = DB::table('warlof_discord_connector_users')->get();

        $this->info(
            sprintf('Preparing to convert schema data for Discord Users Accounts: %d', $users->count()));

        foreach ($users as $user) {
            if (! is_null(User::where('connector_type', 'discord')->where('group_id', $user->group_id)->first()))
                continue;

            $connector_user = new User();
            $connector_user->connector_type = 'discord';
            $connector_user->connector_id   = $user->discord_id;
            $connector_user->connector_name = $user->nick;
            $connector_user->unique_id      = $user->discord_id;
            $connector_user->group_id       = $user->group_id;
            $connector_user->created_at     = $user->created_at;
            $connector_user->updated_at     = $user->updated_at;
            $connector_user->save();

            $this->line(
                sprintf('Discord User Account uid:%s, group_id:%s has been successfully converted.',
                    $user->discord_id, $user->group_id));
        }

        $this->info('Discord User Accounts has been converted.');
    }

    private function convertSets()
    {
        $sets = DB::table('warlof_discord_connector_roles')->get();

        $this->info(
            sprintf('Preparing to convert schema data for Discord Sets: %d', $sets->count()));

        foreach ($sets as $set) {
            $connector_set = new Set();
            $connector_set->connector_type = 'discord';
            $connector_set->connector_id   = $set->id;
            $connector_set->name           = $set->name;
            $connector_set->save();

            $this->line(
                sprintf('Discord Set sgid:%s has been successfully converted.',
                    $set->id));
        }

        $this->info('Discord Sets has been converted.');
    }

    private function convertPublicSets()
    {
        $policies = DB::table('warlof_discord_connector_role_public')->get();

        $this->info(
            sprintf('Preparing to convert schema data for Discord Public Policies: %d.', $policies->count()));

        foreach ($policies as $policy) {
            $connector_set = Set::where('connector_type', 'discord')
                ->where('connector_id', $policy->discord_role_id)
                ->first();

            if (is_null($connector_set)) {
                $this->warn(
                    sprintf('Unable to retrieve Discord Set with ID: %s.', $policy->discord_role_id));
                continue;
            }

            $connector_set->is_public = true;
            $connector_set->save();

            $this->line(
                sprintf('Discord Public Policy for Discord Set %s has been successfully converted.',
                    $policy->discord_role_id));
        }

        $this->info('Discord Public Policies has been converted.');
    }

    private function convertUserSets()
    {
        $policies = DB::table('warlof_discord_connector_role_groups')->get();

        $this->info(
            sprintf('Preparing to convert schema data for Discord User Policies: %d.', $policies->count()));

        foreach ($policies as $policy) {
            $connector_set = Set::where('connector_type', 'discord')
                ->where('connector_id', $policy->discord_role_id)
                ->first();

            if (is_null($connector_set)) {
                $this->warn(
                    sprintf('Unable to retrieve Discord Set with ID: %s.', $policy->discord_role_id));

                continue;
            }

            DB::table('seat_connector_set_entity')->insert([
                'set_id'      => $connector_set->id,
                'entity_type' => Group::class,
                'entity_id'   => $policy->group_id,
            ]);

            $this->line(
                sprintf('Discord User Policy for Discord Set %s - User %s has been successfully converted.',
                    $connector_set->id, $policy->group_id));
        }

        $this->info('Discord User Policies has been converted.');
    }

    private function convertRoleSets()
    {
        $policies = DB::table('warlof_discord_connector_role_roles')->get();

        $this->info(
            sprintf('Preparing to convert schema data for Discord Role Policies: %d.', $policies->count()));

        foreach ($policies as $policy) {
            $connector_set = Set::where('connector_type', 'discord')
                ->where('connector_id', $policy->discord_role_id)
                ->first();

            if (is_null($connector_set)) {
                $this->warn(
                    sprintf('Unable to retrieve Discord Set with ID: %s.', $policy->discord_role_id));
                continue;
            }

            DB::table('seat_connector_set_entity')->insert([
                'set_id'      => $connector_set->id,
                'entity_type' => Role::class,
                'entity_id'   => $policy->role_id,
            ]);

            $this->line(
                sprintf('Discord User Policy for Discord Set %s - Role %s has been successfully converted.',
                    $connector_set->id, $policy->role_id));
        }

        $this->info('Discord Role Policies has been converted.');
    }

    private function convertAllianceSets()
    {
        $policies = DB::table('warlof_discord_connector_role_alliances')->get();

        $this->info(
            sprintf('Preparing to convert schema data for Discord Alliance Policies: %d.', $policies->count()));

        foreach ($policies as $policy) {
            $connector_set = Set::where('connector_type', 'discord')
                ->where('connector_id', $policy->discord_role_id)
                ->first();

            if (is_null($connector_set)) {
                $this->warn(
                    sprintf('Unable to retrieve Discord Set with ID: %s.', $policy->discord_role_id));
                continue;
            }

            DB::table('seat_connector_set_entity')->insert([
                'set_id'      => $connector_set->id,
                'entity_type' => Alliance::class,
                'entity_id'   => $policy->alliance_id,
            ]);

            $this->line(
                sprintf('Discord Alliance Policy for Discord Set %s - Alliance %s has been successfully converted.',
                    $connector_set->id, $policy->alliance_id));
        }

        $this->info('Discord Alliance Policies has been converted.');
    }

    private function convertCorporationSets()
    {
        $policies = DB::table('warlof_discord_connector_role_corporations')->get();

        $this->info(
            sprintf('Preparing to convert schema data for Discord Corporation Policies: %d.', $policies->count()));

        foreach ($policies as $policy) {
            $connector_set = Set::where('connector_type', 'discord')
                ->where('connector_id', $policy->discord_role_id)
                ->first();

            if (is_null($connector_set)) {
                $this->warn(
                    sprintf('Unable to retrieve Discord Set with ID: %s.', $policy->discord_role_id));
                continue;
            }

            DB::table('seat_connector_set_entity')->insert([
                'set_id'      => $connector_set->id,
                'entity_type' => CorporationInfo::class,
                'entity_id'   => $policy->corporation_id,
            ]);

            $this->line(
                sprintf('Discord Corporation Policy for Discord Set %s - Corporation %s has been successfully converted.',
                    $connector_set->id, $policy->corporation_id));
        }

        $this->info('Discord Corporation Policies has been converted.');
    }

    private function convertTitleSets()
    {
        $policies = DB::table('warlof_discord_connector_role_titles')->get();

        $this->info(
            sprintf('Preparing to convert schema data for Discord Title Policies: %d.', $policies->count()));

        foreach ($policies as $policy) {
            $connector_set = Set::where('connector_type', 'discord')
                ->where('connector_id', $policy->discord_role_id)
                ->first();

            $title = CorporationTitle::where('corporation_id', $policy->corporation_id)
                ->where('title_id', $policy->title_id)
                ->first();

            if (is_null($connector_set)) {
                $this->warn(
                    sprintf('Unable to retrieve Discord Set with ID: %s.', $policy->discord_role_id));
                continue;
            }

            if (is_null($title)) {
                $this->warn(
                    sprintf('Unable to retrieve Corporation Title with title ID: %s, Corporation ID: %s.',
                        $policy->title_id, $policy->corporation_id));
                continue;
            }

            DB::table('seat_connector_set_entity')->insert([
                'set_id'      => $connector_set->id,
                'entity_type' => CorporationTitle::class,
                'entity_id'   => $title->id,
            ]);

            $this->line(
                sprintf('Discord Title Policy for Discord Set %s - Corporation %s - Title %s has been successfully converted.',
                    $connector_set->id, $policy->corporation_id, $policy->title_id));
        }

        $this->info('Discord Title Policies has been converted.');
    }
}
