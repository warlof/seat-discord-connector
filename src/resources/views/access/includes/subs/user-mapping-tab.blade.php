<table class="table table-condensed table-hover table-responsive">
    <thead>
    <tr>
        <th>{{ trans('web::seat.username') }}</th>
        <th>{{ trans('discord-connector::seat.discord_role') }}</th>
        <th>{{ trans('web::seat.created') }}</th>
        <th>{{ trans('web::seat.updated') }}</th>
        <th>{{ trans('web::seat.status') }}</th>
        <th></th>
    </tr>
    </thead>
    <tbody>
    @foreach($group_filters as $filter)
        <tr>
            <td>{{ optional($filter->group->main_character)->name ?: 'Unknown Character' }}</td>
            <td>{{ $filter->discord_role->name }}</td>
            <td>{{ $filter->created_at }}</td>
            <td>{{ $filter->updated_at }}</td>
            <td>
                @if ($filter->enabled)
                    <span class="fa fa-check text-success"></span>
                @else
                    <span class="fa fa-times text-danger"></span>
                @endif
            </td>
            <td>
                <div class="btn-group">
                    <a href="{{ route('discord-connector.user.remove', ['group_id' => $filter->group_id, 'discord_role_id' => $filter->discord_role_id]) }}" type="button" class="btn btn-danger btn-xs col-xs-12">
                        {{ trans('web::seat.remove') }}
                    </a>
                </div>
            </td>
        </tr>
    @endforeach
    </tbody>
</table>