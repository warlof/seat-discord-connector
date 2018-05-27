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

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

class CreateDiscordTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('warlof_discord_connector_logs', function (Blueprint $table) {
            $table->increments('id');

            $table->string('event');
            $table->string('message');
            $table->timestamps();
        });

        Schema::create('warlof_discord_connector_roles', function (Blueprint $table) {
            $table->string('id');
            $table->string('name');
            $table->timestamps();
            
            $table->primary('id', 'discord_roles_primary');
        });

        Schema::create('warlof_discord_connector_users', function (Blueprint $table) {
            $table->unsignedInteger('group_id');
            $table->string('discord_id');
            $table->string('nick');
            $table->timestamps();

            $table->primary('group_id', 'discord_users_primary');
            $table->unique('discord_id', 'discord_users_discord_id_unique');

            $table->foreign('group_id', 'discord_users_group_id_foreign')
                ->references('id')
                ->on('groups')
                ->onDelete('cascade');
        });

        Schema::create('warlof_discord_connector_role_public', function (Blueprint $table) {
            $table->string('discord_role_id');
            $table->boolean('enabled');
            $table->timestamps();

            $table->primary('discord_role_id', 'discord_role_public_primary');

            $table->foreign('discord_role_id', 'discord_role_public_discord_role_id_foreign')
                ->references('id')
                ->on('warlof_discord_connector_roles')
                ->onDelete('cascade');
        });

        Schema::create('warlof_discord_connector_role_alliances', function (Blueprint $table) {
            $table->integer('alliance_id');
            $table->string('discord_role_id');
            $table->boolean('enabled');
            $table->timestamps();

            $table->primary(['alliance_id', 'discord_role_id'], 'discord_role_alliances_primary');

            $table->foreign('alliance_id', 'discord_role_alliances_alliance_id_foreign')
                ->references('alliance_id')
                ->on('alliances')
                ->onDelete('cascade');

            $table->foreign('discord_role_id', 'discord_role_alliances_discord_role_id_foreign')
                ->references('id')
                ->on('warlof_discord_connector_roles')
                ->onDelete('cascade');
        });

        Schema::create('warlof_discord_connector_role_corporations', function (Blueprint $table) {
            $table->integer('corporation_id');
            $table->string('discord_role_id');
            $table->boolean('enabled');
            $table->timestamps();

            $table->primary(['corporation_id', 'discord_role_id'], 'discord_role_corporations_primary');

            $table->foreign('discord_role_id', 'discord_role_corporations_discord_role_id_foreign')
                ->references('id')
                ->on('warlof_discord_connector_roles')
                ->onDelete('cascade');
        });

        Schema::create('warlof_discord_connector_role_titles', function (Blueprint $table) {
            $table->bigInteger('corporation_id');
            $table->bigInteger('title_id');
            $table->string('discord_role_id');
            $table->boolean('enabled');
            $table->timestamps();

            $table->primary(['corporation_id', 'title_id', 'discord_role_id'], 'discord_role_titles_primary');

            $table->foreign('discord_role_id', 'discord_role_titles_discord_role_id_foreign')
                ->references('id')
                ->on('warlof_discord_connector_roles')
                ->onDelete('cascade');

        });

        Schema::create('warlof_discord_connector_role_roles', function (Blueprint $table) {
            $table->unsignedInteger('role_id');
            $table->string('discord_role_id');
            $table->boolean('enabled');
            $table->timestamps();

            $table->primary(['role_id', 'discord_role_id'], 'discord_role_roles_primary');

            $table->foreign('role_id', 'discord_role_roles_role_id_foreign')
                ->references('id')
                ->on('roles')
                ->onDelete('cascade');

            $table->foreign('discord_role_id', 'discord_role_roles_discord_role_id_foreign')
                ->references('id')
                ->on('warlof_discord_connector_roles')
                ->onDelete('cascade');
        });

        Schema::create('warlof_discord_connector_role_groups', function (Blueprint $table) {
            $table->unsignedInteger('group_id');
            $table->string('discord_role_id');
            $table->boolean('enabled');
            $table->timestamps();

            $table->primary(['group_id', 'discord_role_id'], 'discord_role_groups_primary');

            $table->foreign('group_id', 'discord_role_groups_group_id_foreign')
                ->references('id')
                ->on('groups')
                ->onDelete('cascade');

            $table->foreign('discord_role_id', 'discord_role_groups_discord_role_id_foreign')
                ->references('id')
                ->on('warlof_discord_connector_roles')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('discord_role_alliances');
        Schema::drop('discord_role_corporations');
        Schema::drop('discord_role_titles');
        Schema::drop('discord_role_roles');
        Schema::drop('discord_role_users');
        Schema::drop('discord_role_public');
        Schema::drop('discord_users');
        Schema::drop('discord_logs');
        Schema::drop('discord_roles');
    }
}
