<div class="panel panel-default">
    <div class="panel-heading">
        <h3 class="panel-title">{{ trans('discord-connector::seat.quick_create') }}</h3>
    </div>
    <div class="panel-body">
        <form role="form" action="{{ route('discord-connector.add') }}" method="post">
            {{ csrf_field() }}

            <div class="box-body">

                <div class="form-group">
                    <label for="discord-type">{{ trans_choice('web::seat.type', 1) }}</label>
                    <select name="discord-type" id="discord-type" class="form-control">
                        <option value="group">{{ trans('discord-connector::seat.user_filter') }}</option>
                        <option value="role">{{ trans('discord-connector::seat.role_filter') }}</option>
                        <option value="corporation">{{ trans('discord-connector::seat.corporation_filter') }}</option>
                        <option value="title">{{ trans('discord-connector::seat.title_filter') }}</option>
                        <option value="alliance">{{ trans('discord-connector::seat.alliance_filter') }}</option>
                        <option value="public">{{ trans('discord-connector::seat.public_filter') }}</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="discord-group-id">{{ trans('web::seat.username') }}</label>
                    <select name="discord-group-id" id="discord-group-id" class="form-control">
                        @foreach($groups->sortBy('main_character.name') as $group)
                            <option value="{{ $group->id }}">{{ $group->users->implode('name', ', ') }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="discord-role-id">{{ trans_choice('web::seat.role', 1) }}</label>
                    <select name="discord-role-id" id="discord-role-id" class="form-control" disabled="disabled">
                        @foreach($roles as $role)
                            <option value="{{ $role->id }}">{{ $role->title }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="discord-corporation-id">{{ trans_choice('web::seat.corporation', 1) }}</label>
                    <select name="discord-corporation-id" id="discord-corporation-id" class="form-control" disabled="disabled">
                        @foreach($corporations as $corporation)
                            <option value="{{ $corporation->corporation_id }}">{{ $corporation->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="discord-title-id">{{ trans_choice('web::seat.title', 1) }}</label>
                    <select name="discord-title-id" id="discord-title-id" class="form-control" disabled="disabled"></select>
                </div>

                <div class="form-group">
                    <label for="discord-alliance-id">{{ trans('web::seat.alliance') }}</label>
                    <select name="discord-alliance-id" id="discord-alliance-id" class="form-control" disabled="disabled">
                        @foreach($alliances as $alliance)
                            <option value="{{ $alliance->alliance_id }}">{{ $alliance->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="discord-discord-role-id">{{ trans('discord-connector::seat.discord_role') }}</label>
                    <select name="discord-discord-role-id" id="discord-discord-role-id" class="form-control">
                        @foreach($discord_roles as $discord_role)
                            <option value="{{ $discord_role->id }}">{{ $discord_role->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="discord-enabled">{{ trans('web::seat.enabled') }}</label>
                    <input type="checkbox" name="discord-enabled" id="discord-enabled" checked="checked" value="1" />
                </div>

            </div>

            <div class="box-footer">
                <button type="submit" class="btn btn-primary pull-right">{{ trans('web::seat.add') }}</button>
            </div>

        </form>
    </div>
</div>