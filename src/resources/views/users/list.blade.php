@extends('web::layouts.grids.12')

@section('title', trans('discord-connector::seat.user_mapping'))
@section('page_header', trans('discord-connector::seat.user_mapping'))

@section('full')

    <div id="user-alert" class="callout callout-danger hidden">
        <h4></h4>
        <p></p>
    </div>

    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">{{ trans_choice('web::seat.user', 2) }}</h3>
        </div>
        <div class="panel-body">
            <table class="table table-condensed table-hover table-responsive no-margin" id="users-table" data-page-length="25">
                <thead>
                    <tr>
                        <th>SeAT Group ID</th>
                        <th>SeAT User ID</th>
                        <th>SeAT Username</th>
                        <th>Discord ID</th>
                        <th>Discord Display Name</th>
                        <th></th>
                    </tr>
                </thead>
            </table>
            <form method="post" id="user-remove" action="{{ route('discord-connector.json.user.remove') }}" class="hidden">
                {{ csrf_field() }}
                {{ method_field('DELETE') }}
                <input type="hidden" name="discord_id" />
            </form>
        </div>
    </div>

    @include('discord-connector::users.includes.roles_modal')

@endsection

@push('head')
<link rel="stylesheet" type="text/css" href="{{ asset('web/css/wt-discord-hook.css') }}" />
@endpush

@push('javascript')
<script type="text/javascript">
    $(function() {
        var modal = $('#user-channels');
        var table = $('table#users-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: '{{ route('discord-connector.json.users') }}',
            columns: [
                {data: 'group_id', type: 'num'},
                {data: 'user_id', type: 'num'},
                {data: 'user_name', type: 'string'},
                {data: 'discord_id', type: 'num'},
                {data: 'nick', type: 'string'},
                @if (auth()->user()->has('discord-connector.security'))
                {
                    data: null,
                    targets: -1,
                    defaultContent: '<button class="btn btn-xs btn-info">Roles</button> <button class="btn btn-xs btn-danger">Remove</button>',
                    orderable: false
                }
                @endif
            ],
            "fnDrawCallback": function(){
                $(document).ready(function(){
                    $('img').unveil(100);
                });
            }
        });

        $('#users-table').find('tbody')
            .on('click', 'button.btn-info', function() {
                var row = table.row($(this).parents('tr')).data();
                $('#discord_username').text(row.discord_nick);
                $('#seat_username').text(row.user_name);
                $('#roles').find('tbody tr').remove();
                modal.find('.overlay').show();

                $.ajax({
                    url: '{{ route('discord-connector.json.user.roles', ['id' => null]) }}',
                    data: {'discord_id' : row.discord_id},
                    success: function(data){
                        if (data) {
                            for (var i = 0; i < data.length; i++) {
                                var role = data[i];

                                $('#roles').find('tbody').append(
                                    '<tr><td>' +
                                    role.id +
                                    '</td><td>' +
                                    role.name +
                                    '</td></tr>');
                            }
                        }

                        modal.find('.overlay').hide();
                    }
                });

                modal.modal('show');
            })
            .on('click', 'button.btn-danger', function(){
                var data = table.row($(this).parents('tr')).data();
                $('#user-remove').find('input[name="discord_id"]').val(data.discord_id).parent().submit();
            });
    });
</script>
@endpush