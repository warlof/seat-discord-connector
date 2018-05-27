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
                <input type="hidden" name="discord_id" />
            </form>
        </div>
    </div>

    <div class="modal fade" id="user-channels" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close pull-right" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <h4 class="modal-title">
                        <span id="discord_username"></span>
                        (<span id="seat_username"></span>) is member of following
                    </h4>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-condensed table-hover" id="channels">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-condensed table-hover" id="groups">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="overlay">
                        <i class="fa fa-refresh fa-spin"></i>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-xs btn-default" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('head')
<link rel="stylesheet" type="text/css" href="{{ asset('web/css/wt-discord-hook.css') }}" />
@endpush

@push('javascript')
<script type="text/javascript">
    $(function(){
        var modal = $('#user-channels');
        var table = $('table#users-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: '{{ route('discord-connector.json.users') }}',
            columns: [
                {data: 'group_id'},
                {data: 'user_id'},
                {data: 'user_nick'},
                {data: 'discord_id'},
                {data: 'discord_nick'},
                @if (auth()->user()->has('discord-connector.security'))
                {
                    data: null,
                    targets: -1,
                    defaultContent: '<button class="btn btn-xs btn-info">Channels</button> <button class="btn btn-xs btn-danger">Remove</button>',
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
            .on('click', 'button.btn-info', function(){
                var row = table.row($(this).parents('tr')).data();
                $('#discord_username').text(row.discord_nick);
                $('#seat_username').text(row.user_nick);
                $('#channels, #groups').find('tbody tr').remove();
                modal.find('.overlay').show();

                $.ajax({
                    url: '{{ route('discord-connector.json.user.channels', ['id' => null]) }}',
                    data: {'discord_id' : row.discord_id},
                    success: function(data){
                        if (data) {
                            for (var i = 0; i < data.length; i++) {
                                var conversation = data[i];

                                // conversations is a group
                                if (conversation.is_group) {
                                    $('#groups').find('tbody').append(
                                        '<tr><td>' +
                                        conversation.id +
                                        '</td><td>' +
                                        conversation.name +
                                        '</td></tr>');
                                }

                                // conversations is a channel
                                if (conversation.is_channel) {
                                    $('#channels').find('tbody').append(
                                        '<tr><td>' +
                                        conversation.id +
                                        '</td><td>' +
                                        conversation.name +
                                        '</td></tr>');
                                }
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